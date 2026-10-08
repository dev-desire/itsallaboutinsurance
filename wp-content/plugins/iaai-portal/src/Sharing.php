<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Sharing {
    public static function routes(): void {
        Core::route('/sharing', 'GET', [self::class, 'listing'], [Core::class, 'member']);
        Core::route('/sharing', 'POST', [self::class, 'create'], [Core::class, 'member']);
        Core::route('/sharing/(?P<id>[a-f0-9-]+)', 'DELETE', [self::class, 'revoke'], [Core::class, 'member']);
        Core::route('/sharing/history', 'GET', [self::class, 'history'], [Core::class, 'member']);
    }
    public static function can_read(int $report): bool {
        global $wpdb;
        if (!current_user_can('iaai_view_assigned_enquiries') || is_wp_error(Auth::fresh_session(false)) || !apply_filters('iaai_mfa_verified', false, get_current_user_id(), wp_get_session_token())) { return false; }
        $g = Core::table('grants'); $x = Core::table('grant_reports'); $e = Core::table('enquiries'); $r = Core::table('reports');
        $owner = $wpdb->get_var($wpdb->prepare("SELECT g.owner_id FROM $g g JOIN $x x ON x.grant_id=g.id JOIN $e e ON e.id=g.enquiry_id JOIN $r r ON r.id=x.report_id WHERE x.report_id=%d AND g.recipient_id=%d AND e.assignee_id=g.recipient_id AND e.user_id=g.owner_id AND r.owner_id=g.owner_id AND e.status<>'closed' AND g.revoked_at IS NULL AND g.expires_at>%s AND r.deleted_at IS NULL AND r.state='available' LIMIT 1", $report, get_current_user_id(), Core::now()));
        return $owner && !get_user_meta((int) $owner, 'iaai_restricted', true) && get_user_meta((int) $owner, 'iaai_storage_consent', true);
    }
    public static function create($request) {
        global $wpdb;
        $p = Core::json($request); $owner = get_current_user_id();
        if (!get_user_meta($owner, 'iaai_storage_consent', true)) { return Core::error('consent', 403); }
        $enquiry = Enquiries::find(Core::text($p['enquiry'] ?? '', 40));
        if (!$enquiry || (int) $enquiry['user_id'] !== $owner || !$enquiry['assignee_id'] || $enquiry['status'] === 'closed' || (int) ($p['recipient_id'] ?? 0) !== (int) $enquiry['assignee_id']) { return Core::error('not_found', 404); }
        $staff = get_userdata($enquiry['assignee_id']);
        if (!$staff || !$staff->has_cap('iaai_view_assigned_enquiries')) { return Core::error('invalid'); }
        $days = (int) ($p['days'] ?? 7);
        if ($days < 1 || $days > 30 || empty($p['consent'])) { return Core::error('consent', 403); }
        $reports = [];
        foreach (array_slice((array) ($p['reports'] ?? []), 0, 100) as $public) {
            $row = Records::row((string) $public);
            if (!$row || (int) $row['owner_id'] !== $owner || $row['state'] !== 'available') { return Core::error('not_found', 404); }
            $reports[] = $row['id'];
        }
        $sections = array_values(array_intersect((array) ($p['sections'] ?? []), ['background', 'medical']));
        if (!$reports && !$sections) { return Core::error('invalid'); }
        $profile = Records::profile($request);
        $public = wp_generate_uuid4();
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->insert(Core::table('grants'), ['public_id' => $public, 'owner_id' => $owner, 'enquiry_id' => $enquiry['id'], 'recipient_id' => $staff->ID, 'purpose' => 'enquiry_guidance', 'expires_at' => gmdate('Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS), 'created_at' => Core::now()])) { throw new \RuntimeException('Grant save failed'); }
            $gid = $wpdb->insert_id;
            foreach ($reports as $rid) { if (!$wpdb->insert(Core::table('grant_reports'), ['grant_id' => $gid, 'report_id' => $rid])) { throw new \RuntimeException('Grant resource save failed'); } }
            foreach ($sections as $section) {
                $fields = $section === 'background' ? ['phone', 'city', 'year_of_birth', 'occupation', 'purpose'] : ['conditions', 'allergies', 'medications', 'surgeries', 'history'];
                $snapshot = ['source_version' => $profile['version'], 'fields' => array_intersect_key((array) $profile['data'], array_flip($fields))];
                if (!$wpdb->insert(Core::table('grant_sections'), ['grant_id' => $gid, 'section_key' => $section, 'snapshot' => Core::seal($snapshot)])) { throw new \RuntimeException('Grant snapshot save failed'); }
            }
            Core::consent('sharing', 'sharing-v1', $owner, (int) $enquiry['id']);
            Core::audit('shared', 'grant', $public); $wpdb->query('COMMIT');
            return ['id' => $public];
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); return Core::error('unavailable', 503); }
    }
    public static function listing($r) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT g.*,e.public_ref FROM ' . Core::table('grants') . ' g JOIN ' . Core::table('enquiries') . ' e ON e.id=g.enquiry_id WHERE g.owner_id=%d ORDER BY g.id DESC LIMIT 100', get_current_user_id()), ARRAY_A);
        return array_map(static function ($row) use ($wpdb) {
            $staff = get_userdata($row['recipient_id']);
            $refs = $wpdb->get_col($wpdb->prepare('SELECT r.public_id FROM ' . Core::table('grant_reports') . ' x JOIN ' . Core::table('reports') . ' r ON r.id=x.report_id WHERE x.grant_id=%d', $row['id']));
            return ['id' => $row['public_id'], 'recipient' => $staff ? $staff->display_name : 'Former staff member', 'enquiry' => $row['public_ref'], 'expires' => $row['expires_at'], 'revoked' => $row['revoked_at'], 'reports' => $refs, 'sections' => $wpdb->get_col($wpdb->prepare('SELECT section_key FROM ' . Core::table('grant_sections') . ' WHERE grant_id=%d', $row['id']))];
        }, $rows);
    }
    public static function revoke($r) {
        global $wpdb;
        $changed = $wpdb->update(Core::table('grants'), ['revoked_at' => Core::now()], ['public_id' => $r['id'], 'owner_id' => get_current_user_id()]);
        if (!$changed) { return Core::error('not_found', 404); }
        Core::audit('revoked', 'grant', $r['id']); return ['ok' => true];
    }
    public static function history($r) {
        global $wpdb;
        $owner = get_current_user_id();
        return $wpdb->get_results($wpdb->prepare('SELECT a.action,a.target_ref,a.created_at,a.actor_id FROM ' . Core::table('audit') . ' a JOIN ' . Core::table('reports') . ' r ON a.target_ref=r.public_id AND a.target_type=%s WHERE r.owner_id=%d AND a.action IN (%s,%s) ORDER BY a.id DESC LIMIT 100', 'report', $owner, 'previewed', 'downloaded'), ARRAY_A);
    }
    public static function staff_records($r) {
        global $wpdb;
        $enquiry = Enquiries::find($r['id']);
        if (!$enquiry || (int) $enquiry['assignee_id'] !== get_current_user_id()) { return Core::error('not_found', 404); }
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('grants') . ' WHERE enquiry_id=%d AND recipient_id=%d AND revoked_at IS NULL AND expires_at>%s', $enquiry['id'], get_current_user_id(), Core::now()), ARRAY_A);
        $reports = []; $sections = [];
        foreach ($rows as $grant) {
            $rr = $wpdb->get_results($wpdb->prepare('SELECT r.* FROM ' . Core::table('reports') . ' r JOIN ' . Core::table('grant_reports') . ' x ON r.id=x.report_id WHERE x.grant_id=%d AND r.deleted_at IS NULL', $grant['id']), ARRAY_A);
            foreach ($rr as $report) { if (self::can_read((int) $report['id'])) { $reports[$report['public_id']] = Records::safe($report, false); } }
            if ($enquiry['status'] !== 'closed' && !get_user_meta((int) $grant['owner_id'], 'iaai_restricted', true) && get_user_meta((int) $grant['owner_id'], 'iaai_storage_consent', true)) {
                foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('grant_sections') . ' WHERE grant_id=%d', $grant['id']), ARRAY_A) as $section) {
                    $sections[] = ['section' => $section['section_key'], 'snapshot' => Core::open($section['snapshot'])];
                    Core::audit('profile_shared_read', 'grant', $grant['public_id']);
                }
            }
        }
        return ['reports' => array_values($reports), 'sections' => $sections];
    }
}
