<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', static function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('html5', ['search-form', 'gallery', 'caption']); });
add_action('wp_enqueue_scripts', static function () { wp_enqueue_style('iaai-base', get_stylesheet_uri(), [], '0.1.0'); });
add_filter('language_attributes', static function ($attrs) {
    if (class_exists('\\IAAI\\Portal\\Core') && \IAAI\Portal\Core::private_page()) { return 'lang="' . (\IAAI\Portal\Core::locale() === 'hi' ? 'hi-IN' : 'en-IN') . '"'; }
    if (is_singular()) { $lang = get_post_meta(get_queried_object_id(), '_iaai_locale', true); if ($lang) { return 'lang="' . esc_attr($lang === 'hi' ? 'hi-IN' : 'en-IN') . '"'; } }
    return $attrs;
});
function iaai_render_global(string $key): bool {
    if (!class_exists('\IAAI\Portal\Templates') || !class_exists('\Elementor\Plugin')) { return false; }
    $id = \IAAI\Portal\Templates::global_id($key);
    if (!$id) { return false; }
    echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id); return true;
}
