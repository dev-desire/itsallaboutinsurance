<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Worker {
    public static function boot(): void {
        add_filter('cron_schedules', static function ($s) { $s['iaai_minute'] = ['interval' => 60, 'display' => 'IAAI every minute']; return $s; });
        add_action('iaai_worker', [self::class, 'run']);
        if (!wp_next_scheduled('iaai_worker')) { wp_schedule_event(time() + 60, 'iaai_minute', 'iaai_worker'); }
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('iaai jobs', static function () { self::run(25); \WP_CLI::success('Processed ready jobs.'); });
        }
    }
    public static function enqueue(string $type, string $ref = '', array $payload = []): void {
        global $wpdb;
        if (!$wpdb->insert(Core::table('jobs'), ['type' => $type, 'resource_ref' => $ref, 'payload' => $payload ? Core::seal($payload) : null, 'run_at' => Core::now(), 'created_at' => Core::now()])) { throw new \RuntimeException('Job not saved'); }
    }
    public static function binary(string $name): string {
        $constant = 'IAAI_' . strtoupper($name) . '_BIN';
        return defined($constant) ? constant($constant) : '/usr/bin/' . $name;
    }
    public static function scanner_ready(): bool {
        if (Core::dev() && has_filter('iaai_test_scan')) { return true; }
        $fresh = defined('IAAI_SCANNER_UPDATED_AT') ? IAAI_SCANNER_UPDATED_AT : getenv('IAAI_SCANNER_UPDATED_AT');
        return is_executable(self::binary('clamscan')) && $fresh && strtotime($fresh) >= time() - 7 * DAY_IN_SECONDS;
    }
    public static function pdf_ready(): bool { return is_executable(self::binary('qpdf')) && is_executable(self::binary('pdftoppm')); }
    public static function command(array $args, int $timeout = 45): array {
        $p = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) { throw new \RuntimeException('Processor unavailable'); }
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $start = time(); $output = ''; $error = ''; $status = proc_get_status($p);
        while ($status['running']) {
            $output .= stream_get_contents($pipes[1]); $error .= stream_get_contents($pipes[2]);
            if (strlen($output) > 12000000 || strlen($error) > 1000000 || time() - $start > $timeout) { proc_terminate($p, 9); break; }
            usleep(20000); $status = proc_get_status($p);
        }
        $output .= stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]);
        $code = $status['exitcode']; $closed = proc_close($p); if ($code < 0) { $code = $closed; }
        return [$code, $output];
    }
    public static function run(int $max = 10): void {
        global $wpdb;
        $table = Core::table('jobs');
        $wpdb->query($wpdb->prepare("UPDATE $table SET state='queued' WHERE state='processing' AND lease_until<%s", Core::now()));
        for ($i = 0; $i < $max; $i++) {
            $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE state='queued' AND run_at<=%s ORDER BY id LIMIT 1", Core::now()), ARRAY_A);
            if (!$job) { break; }
            $lease = gmdate('Y-m-d H:i:s', time() + 900);
            $ok = $wpdb->query($wpdb->prepare("UPDATE $table SET state='processing',lease_until=%s,attempts=attempts+1 WHERE id=%d AND state='queued'", $lease, $job['id']));
            if (!$ok) { continue; }
            try {
                $payload = Core::open($job['payload']);
                switch ($job['type']) {
                    case 'mail':
                        if (time() < ($payload['expires'] ?? 0) && !wp_mail($payload['to'], $payload['subject'], $payload['body'])) { throw new \RuntimeException('Mail transport error'); }
                        break;
                    case 'lead_notice':
                        $to = get_option('iaai_staff_notification_email', '');
                        if ($to && !wp_mail($to, 'New enquiry: ' . $job['resource_ref'], 'Reference: ' . $job['resource_ref'] . "\nService: " . ($payload['service'] ?? ''))) { throw new \RuntimeException('Mail transport error'); }
                        break;
                    case 'scan': self::scan($job['resource_ref']); break;
                    case 'purge_report': self::purge_report($job['resource_ref']); break;
                    case 'export': Privacy::build_export($job['resource_ref']); break;
                    case 'delete_account': Privacy::erase_account((int) $job['resource_ref']); break;
                    case 'purge_export': Privacy::purge_export($job['resource_ref']); break;
                }
                $wpdb->update($table, ['state' => 'done', 'payload' => null, 'lease_until' => null], ['id' => $job['id']]);
            } catch (\Throwable $e) {
                $attempt = (int) $job['attempts'] + 1; $dead = $attempt >= 5;
                $wpdb->update($table, ['state' => $dead ? 'failed' : 'queued', 'last_error' => 'processing_error', 'run_at' => gmdate('Y-m-d H:i:s', time() + [60, 300, 900, 3600, 3600][min($attempt - 1, 4)]), 'lease_until' => null], ['id' => $job['id']]);
            }
        }
        self::cleanup();
        update_option('iaai_worker_heartbeat', time(), false);
    }
    public static function scan(string $public): void {
        global $wpdb;
        $row = Records::row($public);
        if (!$row || !in_array($row['state'], ['pending_scan', 'failed'], true)) { return; }
        if (!self::scanner_ready()) { $wpdb->update(Core::table('reports'), ['state' => 'failed'], ['id' => $row['id']]); throw new \RuntimeException('Scanner unavailable'); }
        $dir = Records::root() . '/work-' . bin2hex(random_bytes(12));
        mkdir($dir, 0700); $file = $dir . '/original'; $previews = [];
        try {
            file_put_contents($file, Records::bytes($row['storage_key'])); chmod($file, 0600);
            if (Core::dev() && has_filter('iaai_test_scan')) { $code = apply_filters('iaai_test_scan', 2, $file); }
            else { [$code] = self::command([self::binary('clamscan'), '--no-summary', '--', $file], 60); }
            if ($code === 1) { self::reject($row); return; }
            if ($code !== 0) { throw new \RuntimeException('Scanner error'); }
            if ($row['mime'] === 'application/pdf') {
                [$code, $json] = self::command([self::binary('qpdf'), '--json', '--', $file]);
                if ($code !== 0) { self::reject($row); return; }
                $pdf = json_decode($json, true);
                if (!$pdf || !empty($pdf['encrypt']['encrypted']) || preg_match('~"(?:/JS|/JavaScript|/OpenAction|/AA|/Launch|/EmbeddedFiles|/RichMedia|/XFA)"~', $json)) { self::reject($row); return; }
                $pageCount = count($pdf['pages'] ?? []);
                if (!$pageCount || $pageCount > (int) get_option('iaai_pdf_page_limit', 50)) { self::reject($row); return; }
                [$render] = self::command([self::binary('pdftoppm'), '-png', '-scale-to', '1600', $file, $dir . '/page'], 120);
                if ($render !== 0) { throw new \RuntimeException('Preview renderer error'); }
                $images = glob($dir . '/page-*.png'); natsort($images);
                if (count($images) !== $pageCount) { throw new \RuntimeException('Incomplete preview'); }
                foreach ($images as $image) { $previews[] = Records::store(file_get_contents($image)); }
            } else {
                $info = @getimagesize($file);
                if (!$info || $info[0] * $info[1] > 25000000) { self::reject($row); return; }
                $image = @imagecreatefromstring(file_get_contents($file)); if (!$image) { self::reject($row); return; }
                ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
                $previews[] = Records::store($png);
            }
            $changed = $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('reports') . " SET state='available',preview_keys=%s WHERE id=%d AND deleted_at IS NULL AND state IN ('pending_scan','failed')", wp_json_encode($previews), $row['id']));
            if (!$changed) { foreach ($previews as $key) { Records::purge($key); } }
            Core::audit('scanned', 'report', $public);
        } catch (\Throwable $e) {
            foreach ($previews as $key) { Records::purge($key); }
            $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('reports') . " SET state='failed' WHERE id=%d AND deleted_at IS NULL", $row['id']));
            throw $e;
        } finally {
            foreach (glob($dir . '/*') as $path) { @unlink($path); } @rmdir($dir);
        }
    }
    public static function reject(array $row): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('reports') . " SET state='rejected' WHERE id=%d AND deleted_at IS NULL", $row['id']));
        self::purge_report($row['public_id']);
        Core::audit('rejected', 'report', $row['public_id']);
    }
    public static function purge_report(string $public): void {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('reports') . ' WHERE public_id=%s', $public), ARRAY_A);
        if (!$row || (!in_array($row['state'], ['rejected', 'deleted'], true))) { return; }
        $keys = array_merge([$row['storage_key']], (array) json_decode($row['preview_keys'] ?: '[]', true));
        foreach ($keys as $key) { if (!Records::purge($key)) { throw new \RuntimeException('Purge failed'); } }
        if ((int) $row['size_bytes'] > 0) {
            $wpdb->query('START TRANSACTION');
            $size = $wpdb->get_var($wpdb->prepare('SELECT size_bytes FROM ' . Core::table('reports') . ' WHERE id=%d FOR UPDATE', $row['id']));
            $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('storage_usage') . ' SET used_bytes=GREATEST(0,used_bytes-%d) WHERE user_id=%d', $size, $row['owner_id']));
            $wpdb->update(Core::table('reports'), ['size_bytes' => 0, 'preview_keys' => null], ['id' => $row['id']]);
            $wpdb->query('COMMIT');
        }
    }
    public static function cleanup(): void {
        global $wpdb;
        foreach (['rate_limits', 'idempotency'] as $name) { $wpdb->query($wpdb->prepare('DELETE FROM ' . Core::table($name) . ' WHERE expires_at<%s', Core::now())); }
        $wpdb->query($wpdb->prepare('DELETE FROM ' . Core::table('auth_tokens') . ' WHERE expires_at<%s', gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)));
        foreach ($wpdb->get_col($wpdb->prepare('SELECT public_id FROM ' . Core::table('privacy_requests') . " WHERE type='export' AND state='ready' AND expires_at<%s", Core::now())) as $public) { self::enqueue('purge_export', $public); }
    }
}
