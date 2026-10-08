<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Privacy {
    public static function routes(): void {
        Core::route('/privacy/export', 'POST', [self::class, 'request_export'], [Core::class, 'member']);
        Core::route('/privacy/delete-account', 'POST', [self::class, 'request_delete'], [Core::class, 'member']);
        Core::route('/privacy/requests', 'GET', [self::class, 'listing'], [Core::class, 'member']);
        Core::route('/privacy/exports/(?P<id>[a-f0-9-]+)/content', 'GET', [self::class, 'download'], [Core::class, 'member']);
    }
    public static function boot(): void {
        add_filter('wp_privacy_personal_data_exporters', static function ($items) {
            $items['iaai-portal'] = ['exporter_friendly_name' => 'IAAI private portal', 'callback' => static function ($email, $page = 1) {
                $user = get_user_by('email', $email);
                return ['data' => $user ? [['group_id' => 'iaai-portal', 'group_label' => 'Private portal', 'item_id' => 'account-' . $user->ID, 'data' => [['name' => 'Secure records export', 'value' => 'Sign in to Account Settings to request the authenticated private archive. Medical records are not included in a public WordPress export.']]]] : [], 'done' => true];
            }]; return $items;
        });
        add_filter('wp_privacy_personal_data_erasers', static function ($items) {
            $items['iaai-portal'] = ['eraser_friendly_name' => 'IAAI private portal', 'callback' => static function ($email, $page = 1) {
                $user = get_user_by('email', $email);
                return self::core_erasure($email);
            }]; return $items;
        });
    }
    public static function core_erasure(string $email): array {
        $user = get_user_by('email', $email);
        if (!$user) { return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true]; }
        $request = wp_get_user_request(absint($_POST['id'] ?? 0));
        if (!$request || $request->action_name !== 'remove_personal_data' || $request->status !== 'request-confirmed' || strtolower($request->email) !== strtolower($email) || !current_user_can('erase_others_personal_data') || is_wp_error(Core::staff(null)) || !get_option('iaai_retention_approved')) {
            return ['items_removed' => false, 'items_retained' => true, 'messages' => ['Use the verified account deletion flow or a confirmed WordPress privacy request processed by an MFA-protected administrator.'], 'done' => true];
        }
        self::queue_delete($user->ID);
        return ['items_removed' => false, 'items_retained' => true, 'messages' => ['Access is blocked and private-file deletion is queued. The portal privacy request records completion after the worker finishes; this callback does not claim files are already erased.'], 'done' => true];
    }
    public static function queue_delete(int $id): string {
        global $wpdb;
        $public = wp_generate_uuid4();
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->insert(Core::table('privacy_requests'), ['public_id' => $public, 'owner_id' => $id, 'type' => 'delete', 'state' => 'queued', 'created_at' => Core::now()])) { throw new \RuntimeException('Deletion request unavailable'); }
            update_user_meta($id, 'iaai_restricted', 1);
            $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('grants') . ' SET revoked_at=%s WHERE owner_id=%d', Core::now(), $id));
            $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('support_access') . ' SET revoked_at=%s WHERE owner_id=%d', Core::now(), $id));
            Worker::enqueue('delete_account', (string) $id); Core::audit('account_deletion_requested', 'privacy', $public);
            $wpdb->query('COMMIT'); \WP_Session_Tokens::get_instance($id)->destroy_all();
            return $public;
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
    }
    public static function request_export($r) {
        global $wpdb;
        $fresh = Auth::fresh_session(true); if (is_wp_error($fresh)) { return $fresh; }
        $rate = Core::rate('export', 3, 3600, (string) get_current_user_id()); if (is_wp_error($rate)) { return $rate; }
        Records::root(); Core::key();
        $public = wp_generate_uuid4();
        if (!$wpdb->insert(Core::table('privacy_requests'), ['public_id' => $public, 'owner_id' => get_current_user_id(), 'type' => 'export', 'state' => 'queued', 'created_at' => Core::now()])) { return Core::error('unavailable', 503); }
        Worker::enqueue('export', $public); Core::audit('export_requested', 'privacy', $public);
        return ['id' => $public, 'status' => 'queued'];
    }
    public static function request_delete($r) {
        global $wpdb;
        $fresh = Auth::fresh_session(true); if (is_wp_error($fresh)) { return $fresh; }
        if ((Core::json($r)['confirm'] ?? '') !== 'DELETE') { return Core::error('invalid'); }
        if (!get_option('iaai_retention_approved')) { return Core::error('unavailable', 503); }
        self::queue_delete(get_current_user_id()); wp_logout();
        return ['ok' => true, 'logout' => true];
    }
    public static function listing($r) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT public_id AS id,type,state,expires_at FROM ' . Core::table('privacy_requests') . ' WHERE owner_id=%d ORDER BY id DESC LIMIT 50', get_current_user_id()), ARRAY_A);
    }
    public static function build_export(string $public): void {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('privacy_requests') . " WHERE public_id=%s AND type='export' AND state='queued'", $public), ARRAY_A);
        if (!$row || get_user_meta((int) $row['owner_id'], 'iaai_restricted', true)) { return; }
        $owner = (int) $row['owner_id']; $user = get_userdata($owner);
        $profile = $wpdb->get_var($wpdb->prepare('SELECT payload FROM ' . Core::table('profiles') . ' WHERE user_id=%d', $owner));
        $reportRows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('reports') . " WHERE owner_id=%d AND state='available' AND deleted_at IS NULL", $owner), ARRAY_A);
        $treatmentRows = $wpdb->get_results($wpdb->prepare('SELECT public_id,event_date,type,payload FROM ' . Core::table('treatments') . ' WHERE owner_id=%d', $owner), ARRAY_A);
        $enquiries = $wpdb->get_results($wpdb->prepare('SELECT public_ref,service,status,created_at FROM ' . Core::table('enquiries') . ' WHERE user_id=%d', $owner), ARRAY_A);
        $data = ['name' => $user->display_name, 'email' => $user->user_email, 'profile' => Core::open($profile), 'reports' => array_map(static fn($r) => Records::safe($r, true), $reportRows), 'treatments' => array_map(static fn($r) => ['id' => $r['public_id'], 'date' => $r['event_date'], 'type' => $r['type'], 'details' => Core::open($r['payload'])], $treatmentRows), 'enquiries' => $enquiries];
        $path = Records::root() . '/export-' . bin2hex(random_bytes(12)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) { throw new \RuntimeException('Archive unavailable'); }
        try {
            $zip->addFromString('my-records.json', wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            foreach ($reportRows as $report) { $meta = Core::open($report['metadata']); $zip->addFromString('reports/' . $report['public_id'] . '-' . sanitize_file_name($meta['filename']), Records::bytes($report['storage_key'])); }
            $zip->close(); chmod($path, 0600);
            if (get_user_meta($owner, 'iaai_restricted', true)) { return; }
            $key = Records::store(file_get_contents($path));
            $wpdb->update(Core::table('privacy_requests'), ['state' => 'ready', 'storage_key' => $key, 'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS)], ['id' => $row['id']]);
        } finally { @unlink($path); }
    }
    public static function allowed_export(string $public) {
        global $wpdb;
        $fresh = Auth::fresh_session(true); if (is_wp_error($fresh)) { return false; }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('privacy_requests') . " WHERE public_id=%s AND owner_id=%d AND type='export' AND state='ready' AND expires_at>%s", $public, get_current_user_id(), Core::now()), ARRAY_A);
    }
    public static function download($r) {
        $row = self::allowed_export($r['id']); if (!$row) { return Core::error('not_found', 404); }
        Core::audit('export_download', 'privacy', $row['public_id']);
        return new \WP_REST_Response(['iaai_binary' => ['key' => $row['storage_key'], 'mime' => 'application/zip', 'filename' => 'my-insurance-records.zip', 'export' => $row['public_id']]], 200);
    }
    public static function purge_export(string $public): void {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('privacy_requests') . " WHERE public_id=%s AND type='export'", $public), ARRAY_A);
        if (!$row || $row['state'] !== 'ready' || strtotime($row['expires_at']) > time()) { return; }
        Records::purge($row['storage_key']); $wpdb->update(Core::table('privacy_requests'), ['state' => 'expired', 'storage_key' => null], ['id' => $row['id']]);
    }
    public static function erase_account(int $id): void {
        global $wpdb;
        if (!get_user_meta($id, 'iaai_restricted', true)) { return; }
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('reports') . ' WHERE owner_id=%d AND deleted_at IS NULL', $id), ARRAY_A) as $report) { Records::mark_deleted($report); }
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('reports') . ' WHERE owner_id=%d', $id), ARRAY_A) as $report) { Worker::purge_report($report['public_id']); }
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('privacy_requests') . " WHERE owner_id=%d AND type='export' AND state='ready'", $id), ARRAY_A) as $export) { Records::purge($export['storage_key']); }
        $wpdb->delete(Core::table('profiles'), ['user_id' => $id]);
        $wpdb->delete(Core::table('treatment_reports'), ['owner_id' => $id]);
        $wpdb->delete(Core::table('treatments'), ['owner_id' => $id]);
        // Enquiry retention is a separately configured business policy; remove direct account linkage.
        $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('enquiries') . ' SET user_id=NULL WHERE user_id=%d', $id));
        $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('privacy_requests') . " SET state='completed',storage_key=NULL WHERE owner_id=%d AND type='delete'", $id));
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($id);
    }
}
