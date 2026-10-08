<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Enquiries {
    const SERVICES = ['health', 'term', 'policy_review', 'claim_help'];
    const STATUSES = ['new', 'assigned', 'contact_attempted', 'connected', 'guidance_in_progress', 'closed'];
    const REASONS = ['guidance_completed', 'unable_to_reach', 'duplicate', 'withdrawn', 'out_of_scope'];
    public static function routes(): void {
        Core::route('/enquiries', 'POST', [self::class, 'create'], [Core::class, 'public_permission']);
        Core::route('/enquiries/mine', 'GET', [self::class, 'mine'], [Core::class, 'member']);
        Core::route('/staff/enquiries', 'GET', [self::class, 'queue'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/export', 'POST', [self::class, 'export'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/(?P<id>[a-zA-Z0-9-]+)', 'GET', [self::class, 'detail'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/(?P<id>[a-zA-Z0-9-]+)', 'PATCH', [self::class, 'update'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/(?P<id>[a-zA-Z0-9-]+)/assign', 'POST', [self::class, 'assign'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/(?P<id>[a-zA-Z0-9-]+)/notes', 'POST', [self::class, 'note'], [Core::class, 'staff']);
        Core::route('/staff/enquiries/(?P<id>[a-zA-Z0-9-]+)/shared-records', 'GET', [Sharing::class, 'staff_records'], [Core::class, 'staff']);
    }
    public static function create($r) {
        if (!Core::notices_ready()) { return Core::error('unavailable', 503); }
        global $wpdb;
        $p = Core::json($r);
        $rate = Core::rate('enquiry', 10); if (is_wp_error($rate)) { return $rate; }
        $service = sanitize_key($p['service'] ?? '');
        $phone = preg_replace('/[\s()-]/', '', Core::text($p['phone'] ?? '', 20));
        if (preg_match('/^[6-9][0-9]{9}$/', $phone)) { $phone = '+91' . $phone; }
        if (!in_array($service, self::SERVICES, true) || !Core::text($p['name'] ?? '', 100) || !preg_match('/^\+91[6-9][0-9]{9}$/', $phone) || empty($p['contact_consent']) || (!empty($p['email']) && !is_email($p['email']))) { return Core::error('invalid'); }
        if (!empty($p['website'])) { return Core::error('invalid'); }
        $allowed = [
            'health' => ['household_size', 'age_band', 'budget_band'],
            'term' => ['age_band', 'dependants'],
            'policy_review' => ['product_type', 'insurer'],
            'claim_help' => ['claim_type', 'insurer', 'hospitalized'],
        ];
        $data = ['name' => Core::text($p['name'], 100), 'phone' => $phone, 'email' => sanitize_email($p['email'] ?? ''), 'city' => Core::text($p['city'] ?? '', 100), 'message' => Core::text($p['message'] ?? '', 1000), 'callback' => Core::text($p['callback'] ?? '', 100)];
        foreach ($allowed[$service] as $field) { $data[$field] = Core::text($p[$field] ?? '', 100); }
        $key = (string) $r->get_header('Idempotency-Key');
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/', $key)) { return Core::error('invalid', 400, 'A valid submission reference is required.'); }
        $actor = hash_hmac('sha256', (is_user_logged_in() ? 'user:' . get_current_user_id() : 'guest:' . ($_COOKIE['iaai_pre_auth'] ?? '')), wp_salt('auth'));
        $digest = hash_hmac('sha256', wp_json_encode([$service, $data, !empty($p['marketing'])]), Core::key());
        $kh = hash('sha256', $key); $idem = Core::table('idempotency');
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $idem (scope,actor_hash,key_hash,payload_hmac,expires_at) VALUES ('enquiry',%s,%s,%s,%s)", $actor, $kh, $digest, gmdate('Y-m-d H:i:s', time() + 86400)));
            $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM $idem WHERE scope='enquiry' AND actor_hash=%s AND key_hash=%s FOR UPDATE", $actor, $kh), ARRAY_A);
            if (!$saved || !hash_equals($saved['payload_hmac'], $digest)) { $wpdb->query('ROLLBACK'); return Core::error('conflict', 409); }
            if ($saved['result_ref']) { $wpdb->query('COMMIT'); return ['reference' => $saved['result_ref'], 'saved' => true]; }
            $ref = 'IAAI-' . strtoupper(bin2hex(random_bytes(6)));
            $uid = is_user_logged_in() ? get_current_user_id() : 0;
            if (!$wpdb->insert(Core::table('enquiries'), ['public_ref' => $ref, 'user_id' => $uid ?: null, 'service' => $service, 'payload' => Core::seal($data), 'locale' => Core::locale(), 'urgency' => in_array($p['hospitalized'] ?? '', ['yes', 'no', 'prefer_not_to_say'], true) ? $p['hospitalized'] : '', 'source_code' => in_array($p['campaign'] ?? '', (array) get_option('iaai_campaign_codes', []), true) ? $p['campaign'] : '', 'created_at' => Core::now()])) { throw new \RuntimeException('Enquiry save failed'); }
            $id = (int) $wpdb->insert_id;
            Core::consent('enquiry_contact', 'enquiry-v1', $uid, $id);
            if (!empty($p['marketing'])) { Core::consent('marketing', 'marketing-v1', $uid, $id); }
            Worker::enqueue('lead_notice', $ref, ['service' => $service]);
            $wpdb->query($wpdb->prepare("UPDATE $idem SET result_ref=%s WHERE scope='enquiry' AND actor_hash=%s AND key_hash=%s", $ref, $actor, $kh));
            $wpdb->query('COMMIT');
            return new \WP_REST_Response(['reference' => $ref, 'saved' => true], 201);
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); return Core::error('unavailable', 503); }
    }
    public static function mine($r) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,public_ref,service,status,assignee_id,created_at FROM ' . Core::table('enquiries') . ' WHERE user_id=%d ORDER BY id DESC LIMIT 100', get_current_user_id()), ARRAY_A);
        return array_map(static function ($row) {
            $staff = $row['assignee_id'] ? get_userdata($row['assignee_id']) : false;
            return ['reference' => $row['public_ref'], 'service' => $row['service'], 'status' => $row['status'], 'created_at' => $row['created_at'], 'staff' => $staff ? ['id' => (int) $staff->ID, 'name' => $staff->display_name] : null];
        }, $rows);
    }
    public static function find(string $ref) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('enquiries') . ' WHERE public_ref=%s', $ref), ARRAY_A);
    }
    public static function authorized(array $row): bool {
        return current_user_can('iaai_assign_enquiries') || (int) $row['assignee_id'] === get_current_user_id();
    }
    public static function queue($r) {
        global $wpdb;
        $manager = current_user_can('iaai_assign_enquiries');
        $where = $manager ? '1=1' : $wpdb->prepare('assignee_id=%d', get_current_user_id());
        $status = sanitize_key($r['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) { $where .= $wpdb->prepare(' AND status=%s', $status); }
        if (in_array($r['service']??'',self::SERVICES,true)) { $where .= $wpdb->prepare(' AND service=%s',$r['service']); }
        if (in_array($r['language']??'',['en','hi'],true)) { $where .= $wpdb->prepare(' AND locale=%s',$r['language']); }
        if ($manager && ($r['assignment']??'')==='unassigned') { $where .= ' AND assignee_id IS NULL'; }
        if (($r['followup']??'')==='overdue') { $where .= $wpdb->prepare(" AND followup_at < %s AND status <> 'closed'",Core::now()); }
        $page=max(1,min(10000,(int)($r['page']??1)));$offset=($page-1)*100;
        $rows = $wpdb->get_results('SELECT * FROM ' . Core::table('enquiries') . " WHERE $where ORDER BY created_at DESC,id DESC LIMIT 100 OFFSET $offset", ARRAY_A);
        return array_map(static function ($row) {
            $data = Core::open($row['payload']);
            return ['reference' => $row['public_ref'], 'service' => $row['service'], 'status' => $row['status'], 'language' => $row['locale'], 'contact' => '••••' . substr($data['phone'], -4), 'assignee' => (int) $row['assignee_id'], 'followup' => $row['followup_at'], 'urgency' => $row['urgency'], 'created_at' => $row['created_at'], 'version' => (int) $row['version']];
        }, $rows);
    }
    public static function export($r) {
        if (!current_user_can('manage_options') || !current_user_can('iaai_export_leads')) { return Core::error('not_found',404); }
        $fresh=Auth::fresh_session(true);if(is_wp_error($fresh))return $fresh;
        $rate=Core::rate('lead-export',5,HOUR_IN_SECONDS,(string)get_current_user_id());if(is_wp_error($rate))return $rate;
        $filter=Core::json($r);$request=new \WP_REST_Request('GET');foreach($filter as$key=>$value)$request->set_param($key,$value);
        $rows=self::queue($request);
        // No names, phone numbers, email, enquiry free text, notes or medical fields in bulk exports.
        $rows=array_map(static function($row){unset($row['contact'],$row['urgency'],$row['version']);return $row;},$rows);
        Core::audit('lead_export','enquiry-page',(string)max(1,(int)($filter['page']??1)));
        return ['items'=>$rows,'page'=>max(1,(int)($filter['page']??1)),'has_more'=>count($rows)===100];
    }
    public static function detail($r) {
        global $wpdb;
        $row = self::find($r['id']); if (!$row || !self::authorized($row)) { return Core::error('not_found', 404); }
        $data = Core::open($row['payload']); unset($row['payload']);
        $row['details'] = $data;
        $events = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('enquiry_events') . ' WHERE enquiry_id=%d ORDER BY id', $row['id']), ARRAY_A);
        $row['events'] = array_map(static function ($event) { $event['note'] = Core::open($event['payload']); unset($event['payload']); return $event; }, $events);
        Core::audit('lead_read', 'enquiry', $row['public_ref']); return $row;
    }
    public static function update($r) {
        global $wpdb;
        $row = self::find($r['id']); if (!$row || !self::authorized($row)) { return Core::error('not_found', 404); }
        $p = Core::json($r); $status = sanitize_key($p['status'] ?? '');
        if (!in_array($status, self::STATUSES, true) || ($status === 'closed' && !in_array($p['close_reason'] ?? '', self::REASONS, true))) { return Core::error('invalid'); }
        $followup = !empty($p['followup']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $p['followup']) ? $p['followup'] : null;
        $changed = $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('enquiries') . ' SET status=%s,close_reason=%s,followup_at=' . ($followup ? '%s' : 'NULL') . ',version=version+1 WHERE id=%d AND version=%d', ...array_merge([$status, $status === 'closed' ? $p['close_reason'] : ''], $followup ? [$followup] : [], [$row['id'], (int) ($p['version'] ?? 0)])));
        if (!$changed) { return Core::error('conflict', 409); }
        if ($status === 'closed') { self::revoke((int) $row['id']); }
        self::event((int) $row['id'], 'status:' . $status);
        return ['ok' => true];
    }
    public static function assign($r) {
        global $wpdb;
        if (!current_user_can('iaai_assign_enquiries')) { return Core::error('not_found', 404); }
        $row = self::find($r['id']); if (!$row) { return Core::error('not_found', 404); }
        $id = (int) (Core::json($r)['staff_id'] ?? 0); $staff = get_userdata($id);
        if (!$staff || !$staff->has_cap('iaai_view_assigned_enquiries')) { return Core::error('invalid'); }
        $wpdb->query('START TRANSACTION');
        $ok = $wpdb->update(Core::table('enquiries'), ['assignee_id' => $id, 'status' => 'assigned', 'version' => (int) $row['version'] + 1], ['id' => $row['id'], 'version' => $row['version']]);
        if (!$ok) { $wpdb->query('ROLLBACK'); return Core::error('conflict', 409); }
        self::revoke((int) $row['id']); self::event((int) $row['id'], 'assigned'); $wpdb->query('COMMIT');
        return ['ok' => true];
    }
    public static function revoke(int $id): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('grants') . ' SET revoked_at=%s WHERE enquiry_id=%d AND revoked_at IS NULL', Core::now(), $id));
    }
    public static function event(int $id, string $type, string $note = ''): void {
        global $wpdb;
        $wpdb->insert(Core::table('enquiry_events'), ['enquiry_id' => $id, 'actor_id' => get_current_user_id(), 'event_type' => $type, 'payload' => $note ? Core::seal(['note' => $note]) : null, 'created_at' => Core::now()]);
        Core::audit('lead_changed', 'enquiry', (string) $id);
    }
    public static function note($r) {
        $row = self::find($r['id']); if (!$row || !self::authorized($row)) { return Core::error('not_found', 404); }
        $note = Core::text(Core::json($r)['note'] ?? '', 2000); if (!$note) { return Core::error('invalid'); }
        self::event((int) $row['id'], 'note', $note); return ['ok' => true];
    }
}
