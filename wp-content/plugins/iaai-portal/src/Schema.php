<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Schema {
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $definitions = [
            'profiles' => "user_id bigint unsigned NOT NULL, payload longtext NOT NULL, version int NOT NULL DEFAULT 1, updated_at datetime NOT NULL, PRIMARY KEY  (user_id)",
            'reports' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(36) NOT NULL, owner_id bigint unsigned NOT NULL, storage_key varchar(100) NOT NULL, metadata longtext NOT NULL, category varchar(40) NOT NULL, report_date date DEFAULT NULL, mime varchar(40) NOT NULL, size_bytes bigint unsigned NOT NULL, state varchar(20) NOT NULL, checksum char(64) NOT NULL, preview_keys longtext DEFAULT NULL, version int NOT NULL DEFAULT 1, deleted_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), UNIQUE KEY storage_key (storage_key), KEY owner_date (owner_id,report_date), KEY owner_state (owner_id,state)",
            'storage_usage' => "user_id bigint unsigned NOT NULL, used_bytes bigint unsigned NOT NULL DEFAULT 0, reserved_bytes bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (user_id)",
            'treatments' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(36) NOT NULL, owner_id bigint unsigned NOT NULL, event_date date NOT NULL, type varchar(30) NOT NULL, payload longtext NOT NULL, version int NOT NULL DEFAULT 1, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), KEY owner_date (owner_id,event_date)",
            'treatment_reports' => "treatment_id bigint unsigned NOT NULL, report_id bigint unsigned NOT NULL, owner_id bigint unsigned NOT NULL, PRIMARY KEY  (treatment_id,report_id)",
            'enquiries' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_ref varchar(40) NOT NULL, user_id bigint unsigned DEFAULT NULL, service varchar(30) NOT NULL, payload longtext NOT NULL, locale varchar(5) NOT NULL, source_code varchar(64) NOT NULL DEFAULT '', urgency varchar(25) NOT NULL DEFAULT '', status varchar(30) NOT NULL DEFAULT 'new', assignee_id bigint unsigned DEFAULT NULL, followup_at datetime DEFAULT NULL, close_reason varchar(40) DEFAULT NULL, version int NOT NULL DEFAULT 1, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_ref (public_ref), KEY queue (assignee_id,status,followup_at), KEY owner (user_id)",
            'enquiry_events' => "id bigint unsigned NOT NULL AUTO_INCREMENT, enquiry_id bigint unsigned NOT NULL, actor_id bigint unsigned NOT NULL, event_type varchar(40) NOT NULL, payload longtext DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY enquiry (enquiry_id,created_at)",
            'grants' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(36) NOT NULL, owner_id bigint unsigned NOT NULL, enquiry_id bigint unsigned NOT NULL, recipient_id bigint unsigned NOT NULL, purpose varchar(100) NOT NULL, expires_at datetime NOT NULL, revoked_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), KEY access_check (enquiry_id,recipient_id,expires_at)",
            'grant_reports' => "grant_id bigint unsigned NOT NULL, report_id bigint unsigned NOT NULL, PRIMARY KEY  (grant_id,report_id)",
            'grant_sections' => "grant_id bigint unsigned NOT NULL, section_key varchar(30) NOT NULL, snapshot longtext NOT NULL, PRIMARY KEY  (grant_id,section_key)",
            'consents' => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned DEFAULT NULL, enquiry_id bigint unsigned DEFAULT NULL, purpose varchar(40) NOT NULL, text_version varchar(30) NOT NULL, decision tinyint NOT NULL, withdrawn_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY owner (user_id,purpose)",
            'auth_tokens' => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, purpose varchar(30) NOT NULL, token_hash char(64) NOT NULL, payload longtext DEFAULT NULL, expires_at datetime NOT NULL, consumed_at datetime DEFAULT NULL, PRIMARY KEY  (id), UNIQUE KEY token_hash (token_hash)",
            'idempotency' => "scope varchar(30) NOT NULL, actor_hash char(64) NOT NULL, key_hash char(64) NOT NULL, payload_hmac char(64) NOT NULL, result_ref longtext DEFAULT NULL, expires_at datetime NOT NULL, PRIMARY KEY  (scope,actor_hash,key_hash)",
            'audit' => "id bigint unsigned NOT NULL AUTO_INCREMENT, actor_id bigint unsigned NOT NULL DEFAULT 0, action varchar(50) NOT NULL, target_type varchar(30) NOT NULL, target_ref varchar(80) NOT NULL, result varchar(20) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY target (target_type,target_ref,created_at)",
            'jobs' => "id bigint unsigned NOT NULL AUTO_INCREMENT, type varchar(30) NOT NULL, resource_ref varchar(80) NOT NULL DEFAULT '', payload longtext DEFAULT NULL, state varchar(20) NOT NULL DEFAULT 'queued', attempts int NOT NULL DEFAULT 0, run_at datetime NOT NULL, lease_until datetime DEFAULT NULL, last_error varchar(50) DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY ready (state,run_at)",
            'privacy_requests' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(36) NOT NULL, owner_id bigint unsigned NOT NULL, type varchar(20) NOT NULL, state varchar(20) NOT NULL, storage_key varchar(100) DEFAULT NULL, expires_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), KEY owner (owner_id)",
            'managed_templates' => "deployment_key varchar(100) NOT NULL, locale varchar(5) NOT NULL, post_id bigint unsigned NOT NULL, base_hash char(64) NOT NULL, incoming_version varchar(64) NOT NULL, release_id varchar(64) NOT NULL, PRIMARY KEY  (deployment_key,locale)",
            'rate_limits' => "bucket_hash char(64) NOT NULL, window_start datetime NOT NULL, count int NOT NULL, expires_at datetime NOT NULL, PRIMARY KEY  (bucket_hash,window_start)",
            'support_access' => "id bigint unsigned NOT NULL AUTO_INCREMENT, public_id varchar(36) NOT NULL, actor_id bigint unsigned NOT NULL, owner_id bigint unsigned NOT NULL, issue_ref varchar(80) NOT NULL, reason_code varchar(30) NOT NULL, expires_at datetime NOT NULL, revoked_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), KEY actor_expiry (actor_id,expires_at)",
            'support_resources' => "access_id bigint unsigned NOT NULL, report_id bigint unsigned NOT NULL, PRIMARY KEY  (access_id,report_id)",
            'migrations' => "migration_id varchar(50) NOT NULL, checksum char(64) NOT NULL, applied_at datetime NOT NULL, PRIMARY KEY  (migration_id)",
        ];
        foreach ($definitions as $name => $sql) {
            $sql = preg_replace('/, (?=(?:[a-z_]+ (?:bigint|longtext|varchar|datetime|int|date|char|tinyint)|PRIMARY KEY|UNIQUE KEY|KEY))/', ",\n", $sql);
            dbDelta('CREATE TABLE ' . Core::table($name) . " (\n$sql\n) ENGINE=InnoDB $charset;");
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(Core::table($name)))) !== Core::table($name)) { throw new \RuntimeException('Schema installation failed'); }
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', Core::table($name)));
            if (strtolower((string) $engine) !== 'innodb') { throw new \RuntimeException('Transactional storage is required'); }
        }
        $member = ['read' => true, 'iaai_manage_own_records' => true];
        add_role('iaai_portal_user', 'Portal Member', $member);
        add_role('iaai_service_agent', 'Service Agent', ['read' => true, 'iaai_view_assigned_enquiries' => true, 'iaai_update_assigned_enquiries' => true]);
        add_role('iaai_manager', 'IAAI Manager', ['read' => true, 'iaai_view_assigned_enquiries' => true, 'iaai_update_assigned_enquiries' => true, 'iaai_assign_enquiries' => true, 'iaai_manage_settings' => true, 'iaai_review_audit' => true, 'iaai_export_leads' => true]);
        add_role('iaai_content_editor', 'IAAI Content Editor', ['read' => true, 'edit_pages' => true, 'edit_others_pages' => true, 'edit_posts' => true, 'upload_files' => true]);
        add_role('iaai_reviewer', 'IAAI Reviewer', ['read' => true, 'edit_pages' => true, 'edit_others_pages' => true, 'publish_pages' => true]);
        $admin = get_role('administrator');
        foreach (['iaai_view_assigned_enquiries', 'iaai_update_assigned_enquiries', 'iaai_assign_enquiries', 'iaai_manage_settings', 'iaai_review_audit', 'iaai_export_leads', 'iaai_request_support_access'] as $cap) { if ($admin) { $admin->add_cap($cap); } }
        update_option('iaai_schema_version', '2', false);
        $wpdb->replace(Core::table('migrations'), ['migration_id' => '002_scoped_support', 'checksum' => hash('sha256', wp_json_encode($definitions)), 'applied_at' => Core::now()]);
    }
}
