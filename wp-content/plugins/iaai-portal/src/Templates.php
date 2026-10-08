<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Templates {
    public static function boot(): void {
        add_action('wp_head', [self::class, 'seo']);
        add_filter('wp_insert_post_data', [self::class, 'publication_guard'], 10, 2);
        add_action('add_meta_boxes_page', static function () { add_meta_box('iaai-publication', 'IAAI editorial review', [self::class, 'review_box'], 'page', 'side'); });
        add_action('save_post_page', [self::class, 'save_review'], 10, 2);
        add_shortcode('iaai_portal', static function ($attrs) { $view = sanitize_key($attrs['view'] ?? 'dashboard'); return Elementor::shell($view, []); });
        add_filter('wp_sitemaps_posts_query_args', static function ($args, $type) {
            if ($type === 'page') { $args['meta_query'][] = ['key' => '_iaai_private', 'compare' => 'NOT EXISTS']; }
            return $args;
        }, 10, 2);
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('iaai install-pages', static function ($args, $flags) {
                $path = $args[0] ?? IAAI_PATH . 'templates';
                $result = self::import($path, $flags['release'] ?? IAAI_VERSION, !empty($flags['dry-run']));
                \WP_CLI::line(wp_json_encode($result));
                if ($result['conflicts']) { \WP_CLI::error('Manual Elementor edits preserved; template release has conflicts.'); }
                else { \WP_CLI::success('Managed pages synchronized.'); }
            });
            \WP_CLI::add_command('iaai rollback-templates', static function ($args) { $r = self::rollback($args[0] ?? ''); \WP_CLI::line(wp_json_encode($r)); if ($r['conflicts']) { \WP_CLI::error('Subsequent manual edits preserved during rollback.'); } });
            \WP_CLI::add_command('iaai health', static function () {
                \WP_CLI::line(wp_json_encode(['release' => get_option('iaai_release', IAAI_VERSION), 'schema' => get_option('iaai_schema_version'), 'readiness' => Records::readiness()]));
            });
        }
    }
    public static function hash(int $id): string {
        return hash('sha256', wp_json_encode([
            'content' => json_decode(get_post_meta($id, '_elementor_data', true) ?: '[]', true),
            'settings' => get_post_meta($id, '_elementor_page_settings', true),
            'title' => get_the_title($id), 'status' => get_post_status($id), 'slug' => get_post_field('post_name', $id), 'body' => get_post_field('post_content', $id), 'parent' => get_post_field('post_parent', $id), 'description' => get_post_meta($id, '_iaai_description', true),
        ]));
    }
    public static function import(string $root, string $release, bool $dry = false): array {
        global $wpdb;
        $manifest = json_decode(file_get_contents($root . '/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        $result = ['updated' => [], 'unchanged' => [], 'conflicts' => []]; $map = [];
        $lock = 'iaai_templates_' . $wpdb->prefix;
        if (!$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) { throw new \RuntimeException('Template release already active'); }
        try {
            foreach ($manifest['templates'] as $item) {
                $key = $item['key']; $locale = $item['locale'];
                $file = realpath($root . '/' . $item['file']);
                if (!$file || !str_starts_with($file, realpath($root) . DIRECTORY_SEPARATOR)) { throw new \RuntimeException('Template path invalid'); }
                $template = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                $table = Core::table('managed_templates');
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE deployment_key=%s AND locale=%s", $key, $locale), ARRAY_A);
                if ($existing && get_post($existing['post_id'])) {
                    $id = (int) $existing['post_id']; $map[$key . '/' . $locale] = $id;
                    if ($existing['incoming_version'] === $item['hash']) { $result['unchanged'][] = $key . '/' . $locale; continue; }
                    $current = self::hash($id);
                    if ($current !== $existing['base_hash']) {
                        $result['conflicts'][] = $key . '/' . $locale; continue;
                    }
                } else { $id = 0; }
                if ($dry) { $result['updated'][] = $key . '/' . $locale; continue; }
                $snapshot = ['release' => $release, 'created' => !$id, 'managed' => $existing];
                $wpdb->query('START TRANSACTION');
                if ($id) {
                    $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $id));
                    $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d FOR UPDATE", $id));
                    clean_post_cache($id);
                    if (!function_exists('wp_check_post_lock')) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
                    if (wp_check_post_lock($id) || self::hash($id) !== $existing['base_hash']) { $wpdb->query('ROLLBACK'); $result['conflicts'][] = $key . '/' . $locale; continue; }
                    $snapshot['post'] = ['ID' => $id, 'post_title' => get_the_title($id), 'post_status' => get_post_status($id), 'post_name' => get_post_field('post_name', $id), 'post_content' => get_post_field('post_content', $id), 'post_parent' => (int) get_post_field('post_parent', $id)];
                    $snapshot['data'] = get_post_meta($id, '_elementor_data', true);
                    $snapshot['settings'] = get_post_meta($id, '_elementor_page_settings', true);
                    $snapshot['description'] = get_post_meta($id, '_iaai_description', true);
                }
                $parent = !empty($item['parent']) ? ($map[$item['parent']] ?? 0) : 0;
                $private = !empty($item['private']);
                $status = !empty($item['draft']) ? 'draft' : 'publish';
                $post = ['post_title' => $template['title'], 'post_name' => $item['slug'], 'post_type' => $item['global'] ?? false ? 'elementor_library' : 'page', 'post_status' => $status, 'post_parent' => $parent];
                if ($id) { $post['ID'] = $id; }
                $id = wp_insert_post($post, true);
                if (is_wp_error($id)) { throw new \RuntimeException('Template import failed'); }
                update_post_meta($id, '_elementor_edit_mode', 'builder');
                update_post_meta($id, '_elementor_template_type', ($item['global'] ?? false) ? 'section' : 'wp-page');
                update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($template['content'])));
                update_post_meta($id, '_elementor_page_settings', $template['page_settings'] ?? ['hide_title' => 'yes']);
                update_post_meta($id, '_iaai_key', $key); update_post_meta($id, '_iaai_locale', $locale);
                update_post_meta($id, '_iaai_description', $item['description'] ?? '');
                if ($private) { update_post_meta($id, '_iaai_private', 1); }
                $wpdb->replace($table, ['deployment_key' => $key, 'locale' => $locale, 'post_id' => $id, 'base_hash' => self::hash($id), 'incoming_version' => $item['hash'], 'release_id' => $release]);
                $snapshot['expected'] = self::hash($id); add_post_meta($id, '_iaai_release_snapshot', wp_slash(wp_json_encode($snapshot)));
                $wpdb->query('COMMIT');
                $map[$key . '/' . $locale] = $id; $result['updated'][] = $key . '/' . $locale;
                if ($key === 'home' && $locale === 'en') { update_option('show_on_front', 'page'); update_option('page_on_front', $id); }
            }
            if (!$dry) {
                update_option('iaai_release', $release, false); flush_rewrite_rules();
                if (class_exists('\Elementor\Plugin')) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
            }
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        return $result;
    }
    public static function rollback(string $release): array {
        global $wpdb;
        if (!$release) { throw new \RuntimeException('Release identity required'); }
        $result = ['restored' => [], 'conflicts' => []];
        $lock = 'iaai_templates_' . $wpdb->prefix;
        if (!$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) { throw new \RuntimeException('Template release already active'); }
        try {
        $rows = $wpdb->get_results('SELECT post_id FROM ' . Core::table('managed_templates'), ARRAY_A);
        foreach ($rows as $row) {
            $id = (int) $row['post_id'];
            foreach (get_post_meta($id, '_iaai_release_snapshot', false) as $json) {
                $snapshot = json_decode($json, true);
                if (($snapshot['release'] ?? '') !== $release) { continue; }
                $wpdb->query('START TRANSACTION');
                $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $id));
                $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d FOR UPDATE", $id)); clean_post_cache($id);
                if (empty($snapshot['expected']) || !hash_equals($snapshot['expected'], self::hash($id))) { $wpdb->query('ROLLBACK'); $result['conflicts'][] = $id; continue; }
                if (!empty($snapshot['created'])) {
                    $wpdb->delete(Core::table('managed_templates'), ['post_id' => $id]); wp_delete_post($id, true);
                } else {
                    wp_update_post(wp_slash($snapshot['post']));
                    update_post_meta($id, '_elementor_data', wp_slash($snapshot['data'])); update_post_meta($id, '_elementor_page_settings', $snapshot['settings']); update_post_meta($id, '_iaai_description', $snapshot['description']);
                    $wpdb->replace(Core::table('managed_templates'), $snapshot['managed']); delete_post_meta($id, '_iaai_release_snapshot', $json);
                }
                $wpdb->query('COMMIT'); $result['restored'][] = $id;
            }
        }
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        flush_rewrite_rules();
        if (class_exists('\\Elementor\\Plugin')) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
        return $result;
    }
    public static function publication_guard(array $data, array $post): array {
        if (($data['post_status'] ?? '') !== 'publish' || empty($post['ID'])) { return $data; }
        $id = (int) $post['ID']; $key = get_post_meta($id, '_iaai_key', true);
        if (!str_starts_with((string) $key, 'guide-') && $key !== 'case-detail') { return $data; }
        $required = $key === 'case-detail' ? ['consent', 'evidence', 'anonymized', 'outcome', 'review_date'] : ['author', 'reviewer', 'review_date', 'sources'];
        foreach ($required as $field) { if (!get_post_meta($id, '_iaai_review_' . $field, true)) { $data['post_status'] = 'draft'; } }
        if ($key === 'case-detail' && get_post_meta($id, '_iaai_review_outcome', true) === 'paid' && !get_post_meta($id, '_iaai_review_payment_verified', true)) { $data['post_status'] = 'draft'; }
        return $data;
    }
    public static function review_box(\WP_Post $post): void {
        if (!current_user_can('publish_pages')) { echo '<p>A reviewer must approve publication.</p>'; return; }
        wp_nonce_field('iaai_review', 'iaai_review_nonce');
        foreach (['author', 'reviewer', 'review_date', 'sources', 'consent', 'evidence', 'anonymized', 'outcome', 'payment_verified'] as $field) {
            echo '<p><label>' . esc_html(ucwords(str_replace('_', ' ', $field))) . '<input class="widefat" name="iaai_review[' . esc_attr($field) . ']" value="' . esc_attr(get_post_meta($post->ID, '_iaai_review_' . $field, true)) . '"></label></p>';
        }
        echo '<p>Save review details, then publish separately. Case outcome: pending, approved, paid, rejected, withdrawn or partially_settled. Confirm paid outcomes against actual payment evidence.</p>';
    }
    public static function save_review(int $id, $post): void {
        if (wp_is_post_revision($id) || !current_user_can('publish_pages') || !wp_verify_nonce($_POST['iaai_review_nonce'] ?? '', 'iaai_review')) { return; }
        $p = wp_unslash((array) ($_POST['iaai_review'] ?? []));
        foreach (['author', 'reviewer', 'review_date', 'sources', 'consent', 'evidence', 'anonymized', 'outcome', 'payment_verified'] as $field) {
            $value = Core::text($p[$field] ?? '', 1000);
            if ($field === 'review_date' && !Records::date($value)) { $value = ''; }
            if ($field === 'outcome' && !in_array($value, ['pending', 'approved', 'paid', 'rejected', 'withdrawn', 'partially_settled'], true)) { $value = ''; }
            update_post_meta($id, '_iaai_review_' . $field, $value);
        }
    }
    public static function global_id(string $key): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . Core::table('managed_templates') . ' WHERE deployment_key=%s AND locale=%s', $key, Core::locale()));
    }
    public static function seo(): void {
        if (!is_singular('page') || Core::private_page()) { return; }
        $id = get_queried_object_id(); $description = get_post_meta($id, '_iaai_description', true);
        if ($description) { echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n"; }
        echo '<link rel="canonical" href="' . esc_url(get_permalink($id)) . '">' . "\n";
        $key = get_post_meta($id, '_iaai_key', true);
        if ($key) {
            global $wpdb;
            foreach ($wpdb->get_results($wpdb->prepare('SELECT locale,post_id FROM ' . Core::table('managed_templates') . ' WHERE deployment_key=%s', $key), ARRAY_A) as $translation) {
                echo '<link rel="alternate" hreflang="' . esc_attr($translation['locale'] === 'hi' ? 'hi-IN' : 'en-IN') . '" href="' . esc_url(get_permalink($translation['post_id'])) . '">' . "\n";
            }
        }
    }
}
