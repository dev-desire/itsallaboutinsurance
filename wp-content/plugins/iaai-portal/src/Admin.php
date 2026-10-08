<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Admin {
    public static function boot(): void {
        add_action('admin_menu', static function () {
            add_menu_page('IAAI Portal', 'IAAI Portal', 'iaai_view_assigned_enquiries', 'iaai-portal', [self::class, 'queue'], 'dashicons-shield', 28);
            add_submenu_page('iaai-portal', 'Setup', 'Setup', 'iaai_manage_settings', 'iaai-setup', [self::class, 'settings']);
            add_submenu_page('iaai-portal', 'Safe audit', 'Audit', 'iaai_review_audit', 'iaai-audit', [self::class, 'audit']);
        });
        add_action('admin_enqueue_scripts', static function ($hook) {
            if (!str_contains($hook, 'iaai-portal')) { return; }
            wp_enqueue_style('iaai-portal', IAAI_URL . 'assets/portal.css', [], IAAI_VERSION);
            wp_enqueue_script('iaai-portal', IAAI_URL . 'assets/portal.js', [], IAAI_VERSION, true);
            wp_localize_script('iaai-portal', 'IAAI', ['api' => rest_url('iaai/v1/'), 'nonce' => wp_create_nonce('wp_rest'), 'locale' => 'en', 'home' => home_url('/'), 'loggedIn' => true, 'canExportLeads'=>current_user_can('manage_options')&&current_user_can('iaai_export_leads')]);
        });
    }
    public static function queue(): void {
        echo '<div class="wrap"><h1>Enquiry workspace</h1><p>Assigned enquiries and explicitly shared records. Staff access requires a configured MFA adapter.</p>' . Elementor::shell('staff', []) . '</div>';
    }
    public static function settings(): void {
        if (!current_user_can('iaai_manage_settings')) { return; }
        if (!Mfa::verified(false, get_current_user_id(), wp_get_session_token())) { echo '<p>Use a TOTP-protected staff session to configure launch settings.</p>'; return; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('iaai_setup');
            if (($_POST['iaai_action'] ?? '') === 'install_pages') {
                $result = Templates::import(IAAI_PATH . 'templates', IAAI_VERSION);
                echo '<div class="notice notice-info"><p>' . esc_html(wp_json_encode($result)) . '</p></div>';
            } else {
            foreach (['phone', 'whatsapp', 'contact_email', 'staff_notification_email', 'legal_identity', 'service_role', 'hours', 'privacy_contact', 'retention_policy', 'fee_policy'] as $field) {
                update_option('iaai_' . $field, $field === 'contact_email' || $field === 'staff_notification_email' ? sanitize_email($_POST[$field] ?? '') : Core::text(wp_unslash($_POST[$field] ?? ''), 3000), false);
            }
            update_option('iaai_retention_approved', !empty($_POST['retention_approved']) && !empty($_POST['retention_policy']) && !empty($_POST['privacy_contact']));
            foreach (['mail_verified', 'backup_restore_verified', 'support_procedure_approved'] as $field) { update_option('iaai_' . $field, !empty($_POST[$field]), false); }
            $hashes = [];
            if (!empty($_POST['notices_approved'])) {
                global $wpdb;
                foreach (['privacy', 'terms', 'disclosures'] as $key) { foreach (['en', 'hi'] as $locale) {
                    $id = (int) $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . Core::table('managed_templates') . ' WHERE deployment_key=%s AND locale=%s', $key, $locale));
                    if ($id && get_post_status($id) === 'publish') { $hashes[$key . '/' . $locale] = Templates::hash($id); }
                } }
            }
            update_option('iaai_notice_hashes', $hashes, false); update_option('iaai_notices_approved', count($hashes) === 6, false);
            echo '<div class="notice notice-success"><p>Settings saved. Infrastructure checks below remain authoritative.</p></div>';
            }
        }
        echo '<div class="wrap"><h1>IAAI setup</h1><form method="post">'; wp_nonce_field('iaai_setup'); echo '<input type="hidden" name="iaai_action" value="install_pages">'; submit_button('Install / reconcile separate Elementor pages', 'secondary'); echo '</form>';
        echo '<p>Secrets and private storage paths must be configured on the host, never in page templates.</p><form method="post">';
        wp_nonce_field('iaai_setup');
        echo '<table class="form-table">';
        foreach (['phone', 'whatsapp', 'contact_email', 'staff_notification_email', 'legal_identity', 'service_role', 'hours', 'privacy_contact', 'retention_policy', 'fee_policy'] as $field) {
            echo '<tr><th><label for="' . esc_attr($field) . '">' . esc_html(ucwords(str_replace('_', ' ', $field))) . '</label></th><td><textarea class="large-text" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" rows="2">' . esc_textarea(get_option('iaai_' . $field, '')) . '</textarea></td></tr>';
        }
        echo '</table><label><input type="checkbox" name="retention_approved" value="1" ' . checked(get_option('iaai_retention_approved'), true, false) . '> Owner has approved the actual retention/deletion policy and privacy contact</label>';
        foreach (['notices_approved' => 'All six published English/Hindi notices have been reviewed against the real business and current collection purposes', 'mail_verified' => 'A real verification and recovery message reached the test inbox through the configured mail provider', 'backup_restore_verified' => 'A documented restore drill recovered the database, private files and separately protected encryption key', 'support_procedure_approved' => 'The exceptional support procedure, issue validation and 15-minute access scope are approved'] as $field => $label) {
            echo '<p><label><input type="checkbox" name="' . esc_attr($field) . '" value="1" ' . checked(get_option('iaai_' . $field), true, false) . '> ' . esc_html($label) . '</label></p>';
        }
        submit_button(); echo '</form><h2>Infrastructure readiness</h2><pre>' . esc_html(wp_json_encode(Records::readiness(), JSON_PRETTY_PRINT)) . '</pre><p>Use the host scheduler to run: wp iaai jobs every minute. No real uploads are accepted while readiness is false.</p></div>';
    }
    public static function audit(): void {
        if (!current_user_can('iaai_review_audit') || !apply_filters('iaai_mfa_verified', false, get_current_user_id(), wp_get_session_token())) { echo '<p>Protected staff sign-in is required.</p>'; return; }
        global $wpdb;
        $rows = $wpdb->get_results('SELECT action,target_type,target_ref,result,created_at FROM ' . Core::table('audit') . ' ORDER BY id DESC LIMIT 100', ARRAY_A);
        echo '<div class="wrap"><h1>Safe audit</h1><table class="widefat"><thead><tr><th>Action</th><th>Type</th><th>Reference</th><th>Result</th><th>Date (UTC)</th></tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr>'; foreach ($row as $value) { echo '<td>' . esc_html($value) . '</td>'; } echo '</tr>'; }
        echo '</tbody></table></div>';
    }
}
