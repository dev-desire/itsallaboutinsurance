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
            $row = static function (string $href, string $label, string $icon): string {
                return '<a class="iaai-nav-row" href="' . esc_url($href) . '"><span aria-hidden="true">' . $icon . '</span>' . esc_html($label) . '</a>';
            };
            $language = '<details name="iaai-header" class="iaai-nav-dropdown iaai-language"><summary aria-label="' . ($hi ? 'भाषा चुनें' : 'Choose language') . '">' . ($hi ? 'हि' : 'En') . '</summary><div class="iaai-nav-popover">';
            foreach (['en' => 'English', 'hi' => 'हिन्दी'] as $code => $label) {
                $current = $code === ($hi ? 'hi' : 'en');
                $language .= $current ? '<span class="iaai-nav-row" aria-current="true"><span aria-hidden="true">✓</span>' . $label . '</span>' : '<a class="iaai-nav-row" href="' . esc_url($switch) . '" data-iaai-locale="' . $code . '"><span aria-hidden="true">◎</span>' . $label . '</a>';
            }
            $language .= '</div></details>';
            $account = '<details name="iaai-header" class="iaai-nav-dropdown"><summary>' . (is_user_logged_in() ? ($hi ? 'अकाउंट' : 'Account') : ($hi ? 'लॉग इन' : 'Login')) . '</summary><div class="iaai-nav-popover">';
            if (is_user_logged_in()) {
                foreach ([['my-dashboard', 'Dashboard', 'डैशबोर्ड', '▦'], ['my-profile', 'My Profile', 'मेरी प्रोफ़ाइल', '♙'], ['my-reports', 'My Reports', 'मेरी रिपोर्ट', '▤'], ['account-settings', 'Account Settings', 'अकाउंट सेटिंग्स', '⚙']] as $item) {
                    $account .= $row(home_url('/' . $item[0] . '/'), $hi ? $item[2] : $item[1], $item[3]);
                }
            } else {
                $account .= '<button type="button" class="iaai-nav-row" data-iaai-auth><span aria-hidden="true">♙</span>' . ($hi ? 'ग्राहक लॉग इन' : 'Customer Login') . '</button>';
                $account .= $row(admin_url(), $hi ? 'टीम लॉग इन' : 'Team Login', '▣');
            }
            $account .= '</div></details>';
            $menu = '<details name="iaai-header" class="iaai-nav-dropdown iaai-menu"><summary aria-label="' . ($hi ? 'मेन्यू' : 'Menu') . '"><span class="iaai-hamburger" aria-hidden="true"></span></summary><nav class="iaai-nav-popover" aria-label="' . ($hi ? 'मुख्य मेन्यू' : 'Main menu') . '">';
            foreach ([['health-insurance', 'Services', 'सेवाएं', '◇'], ['learn', 'Learn', 'जानें', '▤'], ['stories', 'Stories', 'अनुभव', '☆'], ['about', 'About', 'परिचय', '♙'], ['contact', 'Contact', 'संपर्क', '✉']] as $item) {
                $menu .= $row(home_url(($hi ? '/hi/' : '/') . $item[0] . '/'), $hi ? $item[2] : $item[1], $item[3]);
            }
            $menu .= '</nav></details>';
            $css = '<style>
.iaai-account-control{display:flex;gap:12px;align-items:center;justify-content:flex-end;font-family:inherit;color:#242124}
.iaai-nav-dropdown{position:relative;font-size:16px;line-height:1.4;font-weight:400}
.iaai-nav-dropdown>summary{display:flex;gap:17px;align-items:center;justify-content:center;list-style:none;cursor:pointer;border:1px solid #bbb8bd;border-radius:999px;background:#fff;color:#242124;padding:11px 21px;min-height:46px;font-family:inherit;font-weight:500;font-size:16px;line-height:1.4;white-space:nowrap}
.iaai-nav-dropdown>summary::-webkit-details-marker{display:none}
.iaai-nav-dropdown>summary:after{content:"";width:9px;height:9px;border-right:2px solid;border-bottom:2px solid;transform:rotate(45deg);margin-top:-5px}
.iaai-nav-dropdown[open]>summary:after{transform:rotate(225deg);margin-top:5px}
.iaai-nav-popover{position:absolute;top:calc(100% + 10px);right:0;z-index:9999;display:flex!important;flex-direction:column;gap:0!important;width:270px;max-width:calc(100vw - 32px);padding:0!important;border:1px solid #dedce0;border-radius:14px;background:#fff;box-shadow:0 10px 30px #24133f18;overflow:hidden;text-align:left}
.iaai-language .iaai-nav-popover{left:0;right:auto;width:180px}
.iaai-account-control .iaai-nav-row{box-sizing:border-box;display:flex;align-items:center;gap:17px;width:100%;padding:19px 22px;margin:0;border:0;border-bottom:1px solid #e4e2e5;background:#fff;color:#242124!important;font-family:inherit;font-weight:400;font-size:18px;line-height:1.5;text-decoration:none!important;text-align:left;white-space:normal;cursor:pointer}
.iaai-account-control .iaai-nav-row:last-child{border-bottom:0}
.iaai-account-control .iaai-nav-row>span{display:inline-flex;justify-content:center;width:25px;flex:none;font-size:23px;color:#242124}
.iaai-account-control .iaai-nav-row:hover{background:#f5f0fc;color:#6d28d9!important}
.iaai-account-control :focus-visible{outline:3px solid #7c3aed;outline-offset:3px}
.iaai-menu>summary{border:0;border-radius:8px;padding:10px;width:46px}
.iaai-menu>summary:after{display:none}
.iaai-hamburger{display:block;width:25px;height:18px;border-top:2px solid;border-bottom:2px solid;position:relative}
.iaai-hamburger:after{content:"";position:absolute;left:0;right:0;top:6px;border-top:2px solid}
.iaai-public-header nav[aria-label="Main"]{display:none!important}
.iaai-public-header .e-con{overflow:visible}
@media(max-width:767px){.iaai-account-control{gap:8px;justify-content:center}.iaai-nav-dropdown>summary{padding:10px 15px;font-size:15px}.iaai-nav-popover{width:245px}.iaai-language .iaai-nav-popover{width:160px}.iaai-account-control .iaai-nav-row{padding:17px;font-size:17px}.iaai-menu>summary{padding:9px;width:40px}}
</style>';
            return $css . '<div class="iaai-account-control">' . $language . $account . $menu . '</div>';
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
