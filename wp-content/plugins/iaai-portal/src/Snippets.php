<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Snippets {
    private static array $registry = [];
    private static bool $invalid = false;
    public static function register(string $id, string $version, string $checksum): void {
        if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
        if (!defined('IAAI_VERSION') || IAAI_VERSION !== $version) { self::$invalid = true; return; }
        $manifest = json_decode(file_get_contents(IAAI_PATH . 'module-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        $module = $manifest['modules'][$id] ?? null;
        if (!$module || !hash_equals($module['sha256'], $checksum) || !hash_equals(hash_file('sha256', IAAI_PATH . $module['file']), $checksum)) { self::$invalid = true; return; }
        if (isset(self::$registry[$id]) && self::$registry[$id] !== $checksum) { self::$invalid = true; return; }
        self::$registry[$id] = $checksum;
    }
    public static function start(): void {
        if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
        $manifest = json_decode(file_get_contents(IAAI_PATH . 'module-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        if (self::$invalid || array_diff(array_keys($manifest['modules']), array_keys(self::$registry))) {
            add_action('admin_notices', static fn() => print('<div class="notice notice-error"><p>IAAI snippet runtime is inactive: validate and activate all managed modules plus the runtime bootstrap.</p></div>'));
            return;
        }
        Core::boot('snippets');
    }
    public static function cli(): void {
        if (!defined('WP_CLI') || !WP_CLI) { return; }
        \WP_CLI::add_command('iaai sync-snippets', static function ($args, $flags) {
            try { $result=self::sync(!empty($flags['activate'])); }
            catch(\Throwable $e) { \WP_CLI::error($e->getMessage()); return; }
            \WP_CLI::line(wp_json_encode($result));
            if ($result['conflicts']) { \WP_CLI::error('Manual/unmanaged snippets preserved; reconcile conflicts before selecting snippet mode.'); }
        });
    }
    /** Maintainer-only code synchronizer; there is deliberately no HTTP route. */
    public static function sync(bool $activate=false): array {
            if (!function_exists('Code_Snippets\\save_snippet')) { throw new \RuntimeException('Activate the tested Code Snippets plugin first.'); }
            $directory = IAAI_PATH . 'snippets';
            $files = glob($directory . '/*.json'); sort($files);
            $map = (array) get_option('iaai_snippet_map', []); $conflicts = []; $updated = [];
            foreach ($files as $file) {
                $item = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR)['snippets'][0];
                $key = $item['name']; $previous = $map[$key] ?? null;
                $existing = $previous ? \Code_Snippets\get_snippet((int) $previous['id']) : null;
                if ($existing && !hash_equals($previous['hash'], hash('sha256', $existing->code))) { $conflicts[] = $key; continue; }
                if (!$existing) {
                    foreach (\Code_Snippets\get_snippets() as $candidate) { if ($candidate->name === $key) { if (hash_equals(hash('sha256', $item['code']), hash('sha256', $candidate->code))) { $existing = $candidate; break; } $conflicts[] = $key; continue 2; } }
                }
                $model = new \Code_Snippets\Model\Snippet($item);
                if ($existing) { $model->id = $existing->id; }
                $model->active = $activate;
                $saved = \Code_Snippets\save_snippet($model);
                if (!$saved || !$saved->id) { throw new \RuntimeException('Managed snippet could not be saved.'); }
                $map[$key] = ['id' => $saved->id, 'hash' => hash('sha256', $saved->code)]; $updated[] = $key;
            }
            update_option('iaai_snippet_map', $map, false);
            return ['updated'=>$updated,'conflicts'=>$conflicts];
    }
}
