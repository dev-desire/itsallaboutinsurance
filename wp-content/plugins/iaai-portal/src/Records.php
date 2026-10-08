<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Records {
    const CATEGORIES = ['blood_lab', 'imaging', 'prescription', 'consultation', 'treatment', 'discharge', 'insurance_claim', 'other'];
    public static function routes(): void {
        foreach ([
            ['/dashboard', 'GET', 'dashboard'], ['/profile', 'GET', 'profile'], ['/profile', 'PATCH', 'save_profile'],
            ['/reports', 'GET', 'reports'], ['/reports/search', 'POST', 'reports'], ['/reports/upload', 'POST', 'upload'],
            ['/reports/(?P<id>[a-f0-9-]+)', 'PATCH', 'edit_report'], ['/reports/(?P<id>[a-f0-9-]+)', 'DELETE', 'delete_report'],
            ['/treatments', 'GET', 'treatments'], ['/treatments', 'POST', 'save_treatment'],
            ['/treatments/(?P<id>[a-f0-9-]+)', 'PATCH', 'save_treatment'], ['/treatments/(?P<id>[a-f0-9-]+)', 'DELETE', 'delete_treatment'],
        ] as [$route, $method, $action]) { Core::route($route, $method, [self::class, $action], [Core::class, 'member']); }
        Core::route('/reports/(?P<id>[a-f0-9-]+)', 'GET', [self::class, 'get_report'], [self::class, 'reader']);
        Core::route('/reports/(?P<id>[a-f0-9-]+)/content', 'GET', [self::class, 'content'], [self::class, 'reader']);
    }
    public static function reader($r) { return current_user_can('iaai_manage_own_records') ? Core::member($r) : Core::staff($r); }
    public static function root(): string {
        $value = defined('IAAI_PRIVATE_ROOT') ? IAAI_PRIVATE_ROOT : getenv('IAAI_PRIVATE_ROOT');
        $root = $value ? realpath($value) : false;
        $web = realpath(ABSPATH);
        if (!$root || !is_dir($root) || !is_writable($root) || ($web && ($root === $web || str_starts_with($root . '/', $web . '/')))) { throw new \RuntimeException('Private storage configuration unavailable'); }
        return $root;
    }
    public static function readiness(): array {
        $checks = ['https' => is_ssl() || Core::dev(), 'encryption' => false, 'storage' => false, 'scanner' => false, 'pdf' => false, 'retention' => (bool) get_option('iaai_retention_approved', false), 'notices' => Core::notices_ready(), 'backup_restore' => (bool) get_option('iaai_backup_restore_verified', false), 'worker' => (int) get_option('iaai_worker_heartbeat', 0) >= time() - 300];
        try { Core::key(); $checks['encryption'] = true; self::root(); $checks['storage'] = true; } catch (\Throwable $e) {}
        $checks['scanner'] = Worker::scanner_ready();
        $checks['pdf'] = Worker::pdf_ready();
        $checks['ready'] = !in_array(false, $checks, true);
        return $checks;
    }
    public static function store(string $bytes): string {
        $key = bin2hex(random_bytes(24));
        $nonce = random_bytes(24);
        $cipher = $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($bytes, 'iaai:file:v1', $nonce, Core::key());
        $file = self::root() . '/' . $key . '.bin';
        if (file_put_contents($file, $cipher, LOCK_EX) !== strlen($cipher)) { @unlink($file); throw new \RuntimeException('Storage write failed'); }
        chmod($file, 0600); return $key;
    }
    public static function bytes(string $key): string {
        if (!preg_match('/^[a-f0-9]{48}$/', $key)) { throw new \RuntimeException('Invalid storage key'); }
        $raw = file_get_contents(self::root() . '/' . $key . '.bin');
        if (!$raw || strlen($raw) < 40) { throw new \RuntimeException('Missing file'); }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, 24), 'iaai:file:v1', substr($raw, 0, 24), Core::key());
        if ($plain === false) { throw new \RuntimeException('File authentication failed'); }
        return $plain;
    }
    public static function purge(string $key): bool {
        if (!preg_match('/^[a-f0-9]{48}$/', $key)) { return false; }
        $path = self::root() . '/' . $key . '.bin';
        return !is_file($path) || unlink($path);
    }
    public static function date($value, bool $required = false) {
        if (!$value && !$required) { return null; }
        if (!is_string($value)) { return false; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : false;
    }
    public static function profile($r) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('profiles') . ' WHERE user_id=%d', get_current_user_id()), ARRAY_A);
        return ['data' => $row ? Core::open($row['payload']) : [], 'version' => $row ? (int) $row['version'] : 0, 'updated_at' => $row['updated_at'] ?? null, 'storageConsent' => (bool) get_user_meta(get_current_user_id(), 'iaai_storage_consent', true)];
    }
    public static function save_profile($r) {
        global $wpdb;
        $p = Core::json($r); $id = get_current_user_id();
        if (!empty($p['storage_consent'])) { Core::consent('storage', 'storage-v1', $id); update_user_meta($id, 'iaai_storage_consent', 'storage-v1'); }
        if (!get_user_meta($id, 'iaai_storage_consent', true)) { return Core::error('consent', 403); }
        $allowed = ['phone', 'city', 'year_of_birth', 'occupation', 'purpose', 'conditions', 'allergies', 'medications', 'surgeries', 'history'];
        $data = [];
        foreach ($allowed as $field) { $data[$field] = Core::text($p[$field] ?? '', in_array($field, ['conditions', 'allergies', 'medications', 'surgeries', 'history'], true) ? 2000 : 200); }
        $version = (int) ($p['version'] ?? 0); $payload = Core::seal($data); $table = Core::table('profiles');
        if (!$version) {
            $ok = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (user_id,payload,version,updated_at) VALUES (%d,%s,1,%s)", $id, $payload, Core::now()));
        } else {
            $ok = $wpdb->query($wpdb->prepare("UPDATE $table SET payload=%s,version=version+1,updated_at=%s WHERE user_id=%d AND version=%d", $payload, Core::now(), $id, $version));
        }
        if (!$ok) { return Core::error('conflict', 409); }
        Core::audit('profile_changed', 'profile', (string) $id); return ['ok' => true, 'version' => $version + 1];
    }
    public static function dashboard($r) {
        global $wpdb; $id = get_current_user_id(); $table = Core::table('reports');
        return [
            'name' => wp_get_current_user()->display_name,
            'reports' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE owner_id=%d AND deleted_at IS NULL", $id)),
            'available' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE owner_id=%d AND deleted_at IS NULL AND state='available'", $id)),
            'treatments' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Core::table('treatments') . ' WHERE owner_id=%d', $id)),
            'enquiries' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Core::table('enquiries') . ' WHERE user_id=%d', $id)),
            'recentReports' => self::reports($r), 'readiness' => self::readiness(),
            'usedBytes' => (int) $wpdb->get_var($wpdb->prepare('SELECT used_bytes FROM ' . Core::table('storage_usage') . ' WHERE user_id=%d', $id)),
            'quotaBytes' => (int) get_option('iaai_quota_bytes', 250000000),
        ];
    }
    public static function reports($r) {
        global $wpdb;
        $p = $r->get_method() === 'POST' ? Core::json($r) : $r->get_params();
        $where = $wpdb->prepare('owner_id=%d AND deleted_at IS NULL', get_current_user_id());
        if (in_array($p['category'] ?? '', self::CATEGORIES, true)) { $where .= $wpdb->prepare(' AND category=%s', $p['category']); }
        foreach (['from' => '>=', 'to' => '<='] as $field => $op) {
            if (self::date($p[$field] ?? '') && !empty($p[$field])) { $where .= $wpdb->prepare(" AND report_date $op %s", $p[$field]); }
        }
        $offset = max(0, min(10000, (int) ($p['offset'] ?? 0)));
        $rows = $wpdb->get_results('SELECT * FROM ' . Core::table('reports') . " WHERE $where ORDER BY report_date DESC,id DESC LIMIT 100 OFFSET $offset", ARRAY_A);
        $search = mb_strtolower(Core::text($p['search'] ?? '', 200));
        $provider = mb_strtolower(Core::text($p['provider'] ?? '', 150));
        $result = [];
        foreach ($rows as $row) {
            $meta = Core::open($row['metadata']);
            if ($provider && mb_strtolower($meta['provider']??'')!==$provider) { continue; }
            if ($search && !str_contains(mb_strtolower(implode(' ', [$meta['title'], $meta['provider'] ?? '', $meta['note'] ?? ''])), $search)) { continue; }
            $result[] = self::safe($row, true);
        }
        return ['items' => $result, 'nextOffset' => count($rows) === 100 ? $offset + 100 : null];
    }
    public static function row(string $public) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('reports') . ' WHERE public_id=%s AND deleted_at IS NULL', $public), ARRAY_A);
    }
    public static function allowed(array $row, bool $bytes = false): bool {
        if ($bytes && $row['state'] !== 'available') { return false; }
        if ((int) $row['owner_id'] === get_current_user_id() && current_user_can('iaai_manage_own_records')) { return !is_wp_error(Auth::fresh_session(false)) && (bool) get_user_meta(get_current_user_id(), 'iaai_verified_at', true); }
        return $row['state'] === 'available' && (Sharing::can_read((int) $row['id']) || Support::can_read((int) $row['id']));
    }
    public static function safe(array $row, bool $owner): array {
        $meta = Core::open($row['metadata']);
        if (!$owner) { unset($meta['note'], $meta['filename']); }
        return ['id' => $row['public_id'], 'category' => $row['category'], 'date' => $row['report_date'], 'mime' => $row['mime'], 'size' => (int) $row['size_bytes'], 'status' => $row['state'], 'version' => (int) $row['version'], 'created_at' => $row['created_at'], 'metadata' => $meta, 'previewPages' => count((array) json_decode($row['preview_keys'] ?: '[]', true))];
    }
    public static function get_report($r) {
        $row = self::row($r['id']); if (!$row || !self::allowed($row)) { return Core::error('not_found', 404); }
        return self::safe($row, (int) $row['owner_id'] === get_current_user_id());
    }
    public static function upload($r) {
        global $wpdb;
        if (!self::readiness()['ready']) { return Core::error('unavailable', 503, 'Private storage, encryption, scanning, PDF tools and an approved retention policy are required before uploads.'); }
        $p = $r->get_body_params(); $id = get_current_user_id();
        if (!empty($p['storage_consent'])) { Core::consent('storage', 'storage-v1', $id); update_user_meta($id, 'iaai_storage_consent', 'storage-v1'); }
        if (!get_user_meta($id, 'iaai_storage_consent', true)) { return Core::error('consent', 403); }
        $files = $r->get_file_params()['files'] ?? null;
        if (!$files || !is_array($files['name'])) { return Core::error('invalid'); }
        if (count($files['name']) < 1 || count($files['name']) > 5) { return Core::error('invalid', 400, 'Upload one to five files.'); }
        $category = sanitize_key($p['category'] ?? 'other'); $date = self::date($p['date'] ?? '');
        if (!in_array($category, self::CATEGORIES, true) || $date === false) { return Core::error('invalid'); }
        $replaces=Core::text($p['replaces']??'',80);
        if($replaces){$original=self::row($replaces);if(!$original||(int)$original['owner_id']!==$id){return Core::error('not_found',404);}}
        $validated = []; $total = 0; $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ($files['name'] as $i => $name) {
            $tmp = $files['tmp_name'][$i] ?? ''; $size = is_file($tmp) ? filesize($tmp) : 0;
            if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK || (!is_uploaded_file($tmp) && !Core::dev()) || !$size || $size > 10000000) { return Core::error('invalid', 413, 'Each readable file must be 10 MB or smaller.'); }
            $mime = $finfo->file($tmp); $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $valid = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
            if (!isset($valid[$mime]) || !in_array($ext, $valid[$mime], true)) { return Core::error('invalid', 415, 'Only PDF, JPG and PNG files with matching content are accepted.'); }
            $validated[] = ['tmp' => $tmp, 'size' => $size, 'mime' => $mime, 'name' => sanitize_file_name($name), 'hash' => hash_file('sha256', $tmp)];
            $total += $size;
        }
        $key = (string) $r->get_header('Idempotency-Key');
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/', $key)) { return Core::error('invalid'); }
        $actor = hash('sha256', 'upload:' . $id); $kh = hash('sha256', $key);
        $digest = hash_hmac('sha256', wp_json_encode([$p, array_column($validated, 'hash')]), Core::key());
        $idem = Core::table('idempotency'); $usage = Core::table('storage_usage'); $written = [];
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $idem (scope,actor_hash,key_hash,payload_hmac,expires_at) VALUES ('upload',%s,%s,%s,%s)", $actor, $kh, $digest, gmdate('Y-m-d H:i:s', time() + 86400)));
            $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM $idem WHERE scope='upload' AND actor_hash=%s AND key_hash=%s FOR UPDATE", $actor, $kh), ARRAY_A);
            if (!$saved || !hash_equals($saved['payload_hmac'], $digest)) { $wpdb->query('ROLLBACK'); return Core::error('conflict', 409); }
            if ($saved['result_ref']) { $wpdb->query('COMMIT'); return ['items' => json_decode($saved['result_ref'], true), 'status' => 'pending_scan']; }
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $usage (user_id,used_bytes) VALUES (%d,0)", $id));
            $used = (int) $wpdb->get_var($wpdb->prepare("SELECT used_bytes FROM $usage WHERE user_id=%d FOR UPDATE", $id));
            if ($used + $total > (int) get_option('iaai_quota_bytes', 250000000)) { $wpdb->query('ROLLBACK'); return Core::error('invalid', 413, 'Your account storage quota would be exceeded.'); }
            $items = [];
            foreach ($validated as $file) {
                $storage = self::store(file_get_contents($file['tmp'])); $written[] = $storage;
                $public = wp_generate_uuid4();
                $meta = ['title' => Core::text($p['title'] ?? '', 150) ?: pathinfo($file['name'], PATHINFO_FILENAME), 'provider' => Core::text($p['provider'] ?? '', 150), 'note' => Core::text($p['note'] ?? '', 1000), 'filename' => $file['name'], 'replaces'=>$replaces];
                if (!$wpdb->insert(Core::table('reports'), ['public_id' => $public, 'owner_id' => $id, 'storage_key' => $storage, 'metadata' => Core::seal($meta), 'category' => $category, 'report_date' => $date, 'mime' => $file['mime'], 'size_bytes' => $file['size'], 'state' => 'pending_scan', 'checksum' => $file['hash'], 'created_at' => Core::now()])) { throw new \RuntimeException('Report not saved'); }
                Worker::enqueue('scan', $public); $items[] = $public;
                Core::audit('uploaded', 'report', $public);
            }
            $wpdb->query($wpdb->prepare("UPDATE $usage SET used_bytes=used_bytes+%d WHERE user_id=%d", $total, $id));
            $wpdb->query($wpdb->prepare("UPDATE $idem SET result_ref=%s WHERE scope='upload' AND actor_hash=%s AND key_hash=%s", wp_json_encode($items), $actor, $kh));
            $wpdb->query('COMMIT');
            return new \WP_REST_Response(['items' => $items, 'status' => 'pending_scan'], 202);
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK'); foreach ($written as $storage) { self::purge($storage); }
            return Core::error('unavailable', 503);
        }
    }
    public static function edit_report($r) {
        global $wpdb;
        $row = self::row($r['id']); if (!$row || (int) $row['owner_id'] !== get_current_user_id()) { return Core::error('not_found', 404); }
        $p = Core::json($r); $category = sanitize_key($p['category'] ?? $row['category']); $date = self::date($p['date'] ?? $row['report_date']);
        if (!in_array($category, self::CATEGORIES, true) || $date === false) { return Core::error('invalid'); }
        $meta = Core::open($row['metadata']);
        foreach (['title' => 150, 'provider' => 150, 'note' => 1000] as $field => $limit) { if (isset($p[$field])) { $meta[$field] = Core::text($p[$field], $limit); } }
        $ok = $wpdb->update(Core::table('reports'), ['metadata' => Core::seal($meta), 'category' => $category, 'report_date' => $date, 'version' => (int) $row['version'] + 1], ['id' => $row['id'], 'version' => (int) ($p['version'] ?? 0)]);
        if (!$ok) { return Core::error('conflict', 409); }
        Core::audit('metadata_changed', 'report', $row['public_id']); return ['ok' => true];
    }
    public static function delete_report($r) {
        $row = self::row($r['id']); if (!$row || (int) $row['owner_id'] !== get_current_user_id()) { return Core::error('not_found', 404); }
        self::mark_deleted($row); return ['ok' => true];
    }
    public static function mark_deleted(array $row): void {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $changed = $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('reports') . ' SET deleted_at=%s,state=%s WHERE id=%d AND deleted_at IS NULL', Core::now(), 'deleted', $row['id']));
        if ($changed) {
            $wpdb->delete(Core::table('grant_reports'), ['report_id' => $row['id']]);
            $wpdb->delete(Core::table('treatment_reports'), ['report_id' => $row['id']]);
            Worker::enqueue('purge_report', $row['public_id']);
            Core::audit('deleted', 'report', $row['public_id']);
        }
        $wpdb->query('COMMIT');
    }
    public static function content($r) {
        $row = self::row($r['id']); if (!$row || !self::allowed($row, true)) { return Core::error('not_found', 404); }
        $preview = !empty($r['preview']);
        $keys = json_decode($row['preview_keys'] ?: '[]', true);
        $page = max(0, (int) ($r['page'] ?? 0));
        $key = $preview ? ($keys[$page] ?? '') : $row['storage_key'];
        if (!$key) { return Core::error('not_found', 404); }
        $meta = Core::open($row['metadata']);
        Core::audit($preview ? 'previewed' : 'downloaded', 'report', $row['public_id']);
        $response = new \WP_REST_Response(['iaai_binary' => ['key' => $key, 'mime' => $preview ? 'image/png' : $row['mime'], 'filename' => $preview ? 'preview.png' : $meta['filename'], 'report' => $row['public_id']]], 200);
        return $response;
    }
    public static function stream($served, $result, $request, $server) {
        if (!str_starts_with($request->get_route(), '/iaai/v1/')) { return $served; }
        $data = $result->get_data();
        if (!is_array($data) || empty($data['iaai_binary'])) { return $served; }
        $b = $data['iaai_binary'];
        $authorized = isset($b['export']) ? Privacy::allowed_export($b['export']) : (($row = self::row($b['report'])) && self::allowed($row, true));
        if (!$authorized) { status_header(404); Core::headers(); return true; }
        try { $bytes = self::bytes($b['key']); } catch (\Throwable $e) { status_header(503); Core::headers(); return true; }
        Core::headers();
        header('Content-Type: ' . $b['mime']);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($b['filename']) . '"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Length: ' . strlen($bytes)); echo $bytes; return true;
    }
    public static function treatments($r) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Core::table('treatments') . ' WHERE owner_id=%d ORDER BY event_date DESC LIMIT 100', get_current_user_id()), ARRAY_A);
        return array_map(static function ($row) use ($wpdb) {
            $refs = $wpdb->get_col($wpdb->prepare('SELECT r.public_id FROM ' . Core::table('treatment_reports') . ' x JOIN ' . Core::table('reports') . ' r ON x.report_id=r.id WHERE x.treatment_id=%d AND r.deleted_at IS NULL', $row['id']));
            return ['id' => $row['public_id'], 'date' => $row['event_date'], 'type' => $row['type'], 'version' => (int) $row['version'], 'data' => Core::open($row['payload']), 'reports' => $refs];
        }, $rows);
    }
    public static function save_treatment($r) {
        global $wpdb;
        $p = Core::json($r); $date = self::date($p['date'] ?? '', true); $id = get_current_user_id();
        if (!$date || !Core::text($p['title'] ?? '', 150) || !in_array($p['type'] ?? '', ['consultation', 'test', 'medication_record', 'procedure', 'hospitalization'], true) || !get_user_meta($id, 'iaai_storage_consent', true)) { return Core::error('invalid'); }
        $links = [];
        foreach (array_slice((array) ($p['reports'] ?? []), 0, 50) as $ref) {
            $report = self::row((string) $ref);
            if (!$report || (int) $report['owner_id'] !== $id) { return Core::error('not_found', 404); }
            $links[] = $report['id'];
        }
        $data = ['owner_id' => $id, 'event_date' => $date, 'type' => $p['type'], 'payload' => Core::seal(['title' => Core::text($p['title'], 150), 'provider' => Core::text($p['provider'] ?? '', 150), 'note' => Core::text($p['note'] ?? '', 2000)])];
        $wpdb->query('START TRANSACTION');
        try {
            if (!empty($r['id'])) {
                $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('treatments') . ' WHERE public_id=%s AND owner_id=%d FOR UPDATE', $r['id'], $id), ARRAY_A);
                if (!$row) { $wpdb->query('ROLLBACK'); return Core::error('not_found', 404); }
                $data['version'] = (int) $row['version'] + 1;
                if (!$wpdb->update(Core::table('treatments'), $data, ['id' => $row['id'], 'version' => (int) ($p['version'] ?? 0)])) { $wpdb->query('ROLLBACK'); return Core::error('conflict', 409); }
                $tid = (int) $row['id'];
            } else {
                $data['public_id'] = wp_generate_uuid4(); $data['created_at'] = Core::now();
                if (!$wpdb->insert(Core::table('treatments'), $data)) { throw new \RuntimeException('Timeline save failed'); }
                $tid = (int) $wpdb->insert_id;
            }
            $wpdb->delete(Core::table('treatment_reports'), ['treatment_id' => $tid]);
            foreach ($links as $rid) { $wpdb->insert(Core::table('treatment_reports'), ['treatment_id' => $tid, 'report_id' => $rid, 'owner_id' => $id]); }
            $wpdb->query('COMMIT'); Core::audit('timeline_changed', 'treatment', (string) $tid); return ['ok' => true];
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); return Core::error('unavailable', 503); }
    }
    public static function delete_treatment($r) {
        global $wpdb;
        $tid = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Core::table('treatments') . ' WHERE public_id=%s AND owner_id=%d', $r['id'], get_current_user_id()));
        if (!$tid) { return Core::error('not_found', 404); }
        $wpdb->delete(Core::table('treatment_reports'), ['treatment_id' => $tid]);
        $wpdb->delete(Core::table('treatments'), ['id' => $tid]); return ['ok' => true];
    }
}
