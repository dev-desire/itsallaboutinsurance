<?php
/**
 * Plugin Name: IAAI Portal
 * Description: Editable insurance guidance pages and an ownership-protected private records portal.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Text Domain: iaai-portal
 */
defined('ABSPATH') || exit;
define('IAAI_VERSION', '0.1.0');
define('IAAI_PATH', plugin_dir_path(__FILE__));
define('IAAI_URL', plugin_dir_url(__FILE__));
foreach (['Snippets', 'Language', 'Core', 'Schema', 'Auth', 'Enquiries', 'Records', 'Sharing', 'Support', 'Mfa', 'Privacy', 'Worker', 'Templates', 'Admin', 'Kit', 'Elementor'] as $module) {
    require_once IAAI_PATH . 'src/' . $module . '.php';
}
\IAAI\Portal\Snippets::cli();
register_activation_hook(__FILE__, ['IAAI\Portal\Schema', 'install']);
add_action('plugins_loaded', static function () {
    if (defined('IAAI_RUNTIME_MODE') && IAAI_RUNTIME_MODE === 'snippets') { return; }
    \IAAI\Portal\Core::boot('plugin');
});
