<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Support {
    public static function routes(): void {
        Core::route('/staff/reauth', 'POST', [Auth::class, 'reauth'], [Core::class, 'staff']);
        Core::route('/staff/support-access', 'POST', [self::class, 'create'], [Core::class, 'staff']);
        Core::route('/staff/support-access/(?P<id>[a-f0-9-]+)', 'DELETE', [self::class, 'revoke'], [Core::class, 'staff']);
        Core::route('/sharing/support-access/(?P<id>[a-f0-9-]+)', 'DELETE', [self::class, 'revoke'], [Core::class, 'member']);
        Core::route('/sharing/support-history', 'GET', [self::class, 'history'], [Core::class, 'member']);
    }
    public static function create($r) {
        global $wpdb;
        if (!current_user_can('iaai_request_support_access') || !get_option('iaai_support_procedure_approved')) { return Core::error('not_found', 404); }
        $fresh = Auth::fresh_session(true); if (is_wp_error($fresh)) { return $fresh; }
        $p = Core::json($r); $owner = (int) ($p['owner_id'] ?? 0);
        if (!$owner || !get_user_meta($owner, 'iaai_verified_at', true) || get_user_meta($owner, 'iaai_restricted', true) || !get_user_meta($owner, 'iaai_storage_consent', true)) { return Core::error('not_found', 404); }
        $issue = Core::text($p['issue_ref'] ?? '', 80); $reason = sanitize_key($p['reason_code'] ?? '');
        if (!preg_match('/^[A-Za-z0-9-]{5,80}$/', $issue) || !in_array($reason, ['preview_failure', 'download_failure', 'storage_recovery'], true)) { return Core::error('invalid'); }
        $resources = array_values(array_unique((array) ($p['reports'] ?? [])));
        if (!$resources || count($resources) > 5) { return Core::error('invalid'); }
        $rows = [];
        foreach ($resources as $id) {
            $row = Records::row((string) $id);
            if (!$row || (int) $row['owner_id'] !== $owner || $row['state'] !== 'available') { return Core::error('not_found', 404); }
            $rows[] = (int) $row['id'];
        }
        $rate = Core::rate('support-access', 5, HOUR_IN_SECONDS, (string) get_current_user_id()); if (is_wp_error($rate)) { return $rate; }
        $public = wp_generate_uuid4(); $expires = gmdate('Y-m-d H:i:s', time() + 900);
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->insert(Core::table('support_access'), ['public_id' => $public, 'actor_id' => get_current_user_id(), 'owner_id' => $owner, 'issue_ref' => $issue, 'reason_code' => $reason, 'expires_at' => $expires, 'created_at' => Core::now()])) { throw new \RuntimeException('Support scope unavailable'); }
            $sid = $wpdb->insert_id;
            foreach ($rows as $rid) { if (!$wpdb->insert(Core::table('support_resources'), ['access_id' => $sid, 'report_id' => $rid])) { throw new \RuntimeException('Support resource unavailable'); } }
            Core::audit('support_access_created', 'support', $public);
            Worker::enqueue('mail', '', ['to' => get_userdata($owner)->user_email, 'subject' => 'A limited support session was opened', 'body' => 'Support issue: ' . $issue . '. A named administrator has limited access to selected records for 15 minutes. Sign in to My Enquiries to inspect or revoke access. No records are attached to this message.', 'expires' => time() + DAY_IN_SECONDS]);
            $wpdb->query('COMMIT'); return ['id' => $public, 'expires' => $expires];
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); return Core::error('unavailable', 503); }
    }
    public static function can_read(int $report): bool {
        global $wpdb;
        if (!current_user_can('iaai_request_support_access') || !get_option('iaai_support_procedure_approved') || !Mfa::verified(false, get_current_user_id(), wp_get_session_token()) || is_wp_error(Auth::fresh_session(true))) { return false; }
        $owner = $wpdb->get_var($wpdb->prepare('SELECT s.owner_id FROM ' . Core::table('support_access') . ' s JOIN ' . Core::table('support_resources') . ' x ON x.access_id=s.id JOIN ' . Core::table('reports') . " r ON r.id=x.report_id WHERE x.report_id=%d AND s.actor_id=%d AND r.owner_id=s.owner_id AND r.deleted_at IS NULL AND r.state='available' AND s.revoked_at IS NULL AND s.expires_at>%s", $report, get_current_user_id(), Core::now()));
        return $owner && !get_user_meta((int) $owner, 'iaai_restricted', true) && get_user_meta((int) $owner, 'iaai_storage_consent', true);
    }
    public static function history($r) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT public_id AS id,actor_id,issue_ref,reason_code,expires_at,revoked_at,created_at FROM ' . Core::table('support_access') . ' WHERE owner_id=%d ORDER BY id DESC LIMIT 100', get_current_user_id()), ARRAY_A);
    }
    public static function revoke($r) {
        global $wpdb;
        $where = current_user_can('iaai_request_support_access') ? ['actor_id' => get_current_user_id()] : ['owner_id' => get_current_user_id()];
        $where['public_id'] = $r['id'];
        if (!$wpdb->update(Core::table('support_access'), ['revoked_at' => Core::now()], $where)) { return Core::error('not_found', 404); }
        Core::audit('support_access_revoked', 'support', $r['id']); return ['ok' => true];
    }
}
