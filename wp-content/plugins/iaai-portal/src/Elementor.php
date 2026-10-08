<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Elementor {
    const VIEWS = ['auth', 'dashboard', 'profile', 'reports', 'upload', 'treatment', 'enquiries', 'sharing', 'settings', 'guidance', 'navigation', 'contact', 'staff'];
    public static function boot(): void {
        Kit::boot();
        add_filter('elementor/frontend/print_google_fonts', '__return_false');
        add_action('elementor/widgets/register', static function ($manager) {
            require_once IAAI_PATH . 'src/ElementorWidget.php';
            foreach (array_diff(self::VIEWS, ['staff']) as $view) { $class = __NAMESPACE__ . '\\' . ucfirst($view) . 'Widget'; $manager->register(new $class()); }
        });
    }
    public static function shell(string $view, array $settings): string {
        if (!in_array($view, self::VIEWS, true)) { return ''; }
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return '<div class="iaai-app"><span class="iaai-eyebrow">Synthetic editor preview</span><h3>' . esc_html($settings['heading'] ?? ucwords($view)) . '</h3><p>Sample layout only. No personal records are loaded in Elementor.</p><div class="iaai-empty">Use the Style tab to edit colors, type, layout and spacing.</div></div>';
        }
        if ($view === 'navigation') {
            $hi = Core::locale() === 'hi';
            $key = get_post_meta(get_queried_object_id(), '_iaai_key', true);
            global $wpdb;
            $target = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . Core::table('managed_templates') . ' WHERE deployment_key=%s AND locale=%s', $key ?: 'home', $hi ? 'en' : 'hi'));
            $switch = $target ? get_permalink($target) : home_url($hi ? '/' : '/hi/');
            $login = is_user_logged_in() ? '<details><summary>' . ($hi ? 'मेरा अकाउंट' : 'My Account') . '</summary><a href="' . esc_url(home_url('/my-dashboard/')) . '">' . ($hi ? 'डैशबोर्ड' : 'Dashboard') . '</a><br><a href="' . esc_url(home_url('/my-profile/')) . '">' . ($hi ? 'मेरी प्रोफ़ाइल' : 'My Profile') . '</a><br><a href="' . esc_url(home_url('/my-reports/')) . '">' . ($hi ? 'मेरी रिपोर्ट' : 'My Reports') . '</a><br><a href="' . esc_url(home_url('/account-settings/')) . '">' . ($hi ? 'अकाउंट सेटिंग्स' : 'Account Settings') . '</a></details>' : '<button class="iaai-button secondary" data-iaai-auth>' . ($hi ? 'यूज़र लॉग इन' : 'User Login') . '</button>';
            return '<div class="iaai-account-control"><a class="iaai-locale" href="' . esc_url($switch) . '" data-iaai-locale="' . ($hi ? 'en' : 'hi') . '">' . ($hi ? 'English' : 'हिन्दी') . '</a>' . $login . '</div>';
        }
        if ($view === 'contact') {
            $html = '<div class="iaai-panel">';
            foreach (['phone', 'whatsapp', 'contact_email', 'hours'] as $field) {
                $value = get_option('iaai_' . $field, '');
                if ($value) { $html .= '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong> ' . esc_html($value) . '</p>'; }
            }
            return $html . '</div>';
        }
        return '<div class="iaai-app" data-iaai-view="' . esc_attr($view) . '" data-iaai-settings="' . esc_attr(wp_json_encode($settings)) . '"><div class="iaai-loading" role="status">Loading…</div></div>';
    }
}
