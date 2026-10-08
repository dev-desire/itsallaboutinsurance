<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Core {
    public static function boot(string $runtime): void {
        if (!empty($GLOBALS['iaai_runtime'])) { return; }
        $GLOBALS['iaai_runtime'] = $runtime;
        if (get_option('iaai_schema_version') !== '2') { Schema::install(); }
        load_plugin_textdomain('iaai-portal', false, dirname(plugin_basename(IAAI_PATH . 'iaai-portal.php')) . '/languages');
        foreach ([Auth::class, Enquiries::class, Records::class, Sharing::class, Support::class, Privacy::class] as $class) {
            add_action('rest_api_init', [$class, 'routes']);
        }
        Worker::boot(); Templates::boot(); Admin::boot(); Elementor::boot(); Auth::boot(); Mfa::boot(); Privacy::boot();
        add_action('wp_enqueue_scripts', static function () {
            wp_enqueue_style('iaai-portal', IAAI_URL . 'assets/portal.css', [], IAAI_VERSION);
            wp_enqueue_script('iaai-portal', IAAI_URL . 'assets/portal.js', [], IAAI_VERSION, true);
            wp_localize_script('iaai-portal', 'IAAI', [
                'api' => rest_url('iaai/v1/'), 'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
                'home' => home_url('/'), 'locale' => self::locale(), 'account' => home_url('/account/'),
                'loggedIn' => is_user_logged_in(), 'version' => IAAI_VERSION, 'publicReady' => self::notices_ready(),
            ]);
        });
        add_action('send_headers', static function () {
            if (self::private_page() || is_user_logged_in()) { self::headers(); }
        });
        add_filter('wp_robots', static function ($robots) {
            if (self::private_page()) { $robots['noindex'] = true; $robots['nofollow'] = true; }
            return $robots;
        });
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/iaai/v1/')) {
                $response->header('Cache-Control', 'private, no-store, max-age=0');
                $response->header('X-Robots-Tag', 'noindex, nofollow');
            }
            return $response;
        }, 10, 3);
        add_filter('rest_pre_serve_request', [Records::class, 'stream'], 10, 4);
        add_action('rest_api_init', static function () {
            self::route('/health', 'GET', static fn() => ['version' => IAAI_VERSION, 'release' => get_option('iaai_release', ''), 'schema' => get_option('iaai_schema_version', ''), 'status' => 'installed'], '__return_true');
        });
    }
    public static function table(string $name): string { global $wpdb; return $wpdb->prefix . 'iaai_' . $name; }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function error(string $code, int $status = 400, string $message = ''): \WP_Error {
        return new \WP_Error('iaai_' . $code, Language::message($message ?: self::message($code)), ['status' => $status]);
    }
    public static function message(string $code): string {
        $map = [
            'not_found' => ['Record not found.', 'रिकॉर्ड उपलब्ध नहीं है।'],
            'verified' => ['Please verify your email before accessing private records.', 'निजी रिकॉर्ड खोलने से पहले ईमेल सत्यापित करें।'],
            'session' => ['Please sign in again to continue.', 'जारी रखने के लिए फिर से लॉग इन करें।'],
            'consent' => ['Please provide the required consent.', 'कृपया आवश्यक सहमति दें।'],
            'unavailable' => ['This service is not configured yet. Please try again later.', 'यह सेवा अभी कॉन्फ़िगर नहीं है। बाद में प्रयास करें।'],
            'conflict' => ['This record has changed. Refresh and try again.', 'रिकॉर्ड बदल गया है। रिफ्रेश करके प्रयास करें।'],
            'rate' => ['Too many requests. Please try again later.', 'कई अनुरोध हुए हैं। थोड़ी देर बाद प्रयास करें।'],
            'csrf' => ['Refresh this page and try again.', 'पेज रिफ्रेश करके फिर प्रयास करें।'],
            'invalid' => ['Please check the required fields.', 'कृपया आवश्यक विवरण जाँचें।'],
        ];
        return ($map[$code] ?? ['Unable to complete this request.', 'अनुरोध पूरा नहीं हुआ।'])[self::locale() === 'hi' ? 1 : 0];
    }
    public static function locale(): string {
        $page_locale = function_exists('is_singular') && is_singular('page') ? get_post_meta(get_queried_object_id(), '_iaai_locale', true) : '';
        if (in_array($page_locale, ['en', 'hi'], true) && !self::private_page()) { return $page_locale; }
        $v = is_user_logged_in() ? get_user_meta(get_current_user_id(), 'iaai_locale', true) : '';
        if (!$v) { $v = sanitize_key($_COOKIE['iaai_locale'] ?? 'en'); }
        return $v === 'hi' ? 'hi' : 'en';
    }
    public static function dev(): bool { return defined('IAAI_DEV_MODE') && IAAI_DEV_MODE === true && wp_get_environment_type() !== 'production'; }
    public static function key(): string {
        $raw = defined('IAAI_ENCRYPTION_KEY') ? IAAI_ENCRYPTION_KEY : getenv('IAAI_ENCRYPTION_KEY');
        $key = is_string($raw) ? base64_decode($raw, true) : false;
        if (!$key || strlen($key) !== 32 || !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) { throw new \RuntimeException('Encryption configuration unavailable'); }
        return $key;
    }
    public static function seal($value): string {
        $nonce = random_bytes(24);
        return base64_encode($nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(wp_json_encode($value), 'iaai:v1', $nonce, self::key()));
    }
    public static function open(?string $value) {
        if (!$value) { return []; }
        $raw = base64_decode($value, true);
        if (!$raw || strlen($raw) < 40) { throw new \RuntimeException('Invalid encrypted value'); }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, 24), 'iaai:v1', substr($raw, 0, 24), self::key());
        if ($plain === false) { throw new \RuntimeException('Encrypted value authentication failed'); }
        return json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
    }
    public static function headers(): void {
        if (headers_sent()) { return; }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
    }
    public static function private_page(): bool {
        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        return (bool) preg_match('~(?:^|/)(account|my-dashboard|my-profile|my-reports|my-treatment-history|my-enquiries|account-settings)(?:/|$)~', $path);
    }
    public static function route(string $path, string $method, callable $callback, callable $permission): void {
        register_rest_route('iaai/v1', $path, [
            'methods' => $method, 'permission_callback' => $permission,
            'callback' => static function ($request) use ($callback) {
                try { return Language::response($callback($request)); }
                catch (\Throwable $e) { return self::error('unavailable', 503); }
            },
        ]);
    }
    public static function origin(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            $expected = wp_parse_url(home_url('/'));
            $got = wp_parse_url($origin);
            if (!$got || ($got['scheme'] ?? '') !== ($expected['scheme'] ?? '') || ($got['host'] ?? '') !== ($expected['host'] ?? '') || ($got['port'] ?? null) !== ($expected['port'] ?? null)) { return false; }
        }
        return !in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site'], true);
    }
    public static function context($r) {
        if (!self::origin()) { return self::error('csrf', 403); }
        if (!is_ssl() && !self::dev()) { return self::error('unavailable', 503); }
        $sid = $_COOKIE['iaai_pre_auth'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $sid) || !get_transient('iaai_ctx_' . hash('sha256', $sid))) { $sid = bin2hex(random_bytes(32)); }
        setcookie('iaai_pre_auth', $sid, ['expires' => time() + 900, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict']);
        set_transient('iaai_ctx_' . hash('sha256', $sid), 1, 900);
        return ['csrf' => hash_hmac('sha256', 'context:' . $sid, wp_salt('nonce'))];
    }
    public static function public_permission($r) {
        $sid = $_COOKIE['iaai_pre_auth'] ?? '';
        $token = (string) $r->get_header('X-IAAI-CSRF');
        if (!self::origin() || !preg_match('/^[a-f0-9]{64}$/', $sid) || !get_transient('iaai_ctx_' . hash('sha256', $sid)) || !hash_equals(hash_hmac('sha256', 'context:' . $sid, wp_salt('nonce')), $token)) {
            return self::error('csrf', 403);
        }
        return true;
    }
    public static function member($r) {
        if (!is_user_logged_in() || !self::origin()) { return self::error('session', 401); }
        if (!current_user_can('iaai_manage_own_records')) { return self::error('not_found', 404); }
        if (!get_user_meta(get_current_user_id(), 'iaai_verified_at', true)) { return self::error('verified', 403); }
        if (get_user_meta(get_current_user_id(), 'iaai_restricted', true)) { return self::error('session', 403); }
        return Auth::fresh_session(false);
    }
    public static function signed_in($r) { return is_user_logged_in() && self::origin() ? true : self::error('session', 401); }
    public static function staff($r) {
        if (!is_user_logged_in() || !current_user_can('iaai_view_assigned_enquiries')) { return self::error('not_found', 404); }
        if (!apply_filters('iaai_mfa_verified', false, get_current_user_id(), wp_get_session_token())) { return self::error('session', 403, 'Use the configured MFA-protected staff sign-in.'); }
        return Auth::fresh_session(false);
    }
    public static function notices_ready(): bool {
        global $wpdb;
        if (!get_option('iaai_notices_approved') || !get_option('iaai_legal_identity') || !get_option('iaai_service_role') || !get_option('iaai_privacy_contact')) { return false; }
        $approved = (array) get_option('iaai_notice_hashes', []);
        foreach (['privacy', 'terms', 'disclosures'] as $key) {
            foreach (['en', 'hi'] as $locale) {
                $id = (int) $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . self::table('managed_templates') . ' WHERE deployment_key=%s AND locale=%s', $key, $locale));
                if (!$id || get_post_status($id) !== 'publish' || !isset($approved[$key . '/' . $locale]) || !hash_equals($approved[$key . '/' . $locale], Templates::hash($id))) { return false; }
            }
        }
        return true;
    }
    public static function rate(string $scope, int $limit = 10, int $period = 3600, string $identity = '') {
        global $wpdb;
        $identity = $identity ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $bucket = hash_hmac('sha256', $scope . ':' . $identity, wp_salt('auth'));
        $start = gmdate('Y-m-d H:i:s', intdiv(time(), $period) * $period);
        $table = self::table('rate_limits');
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO $table (bucket_hash,window_start,count,expires_at) VALUES (%s,%s,1,%s) ON DUPLICATE KEY UPDATE count=count+1", $bucket, $start, gmdate('Y-m-d H:i:s', time() + $period * 2)));
        if ($ok === false) { return self::error('unavailable', 503); }
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT count FROM $table WHERE bucket_hash=%s AND window_start=%s", $bucket, $start));
        return $count <= $limit ? true : self::error('rate', 429);
    }
    public static function audit(string $action, string $type = '', string $ref = '', string $result = 'ok'): void {
        global $wpdb;
        $wpdb->insert(self::table('audit'), ['actor_id' => get_current_user_id(), 'action' => $action, 'target_type' => $type, 'target_ref' => $ref, 'result' => $result, 'created_at' => self::now()]);
    }
    public static function consent(string $purpose, string $version, int $user = 0, int $enquiry = 0, bool $decision = true): void {
        global $wpdb;
        if (!$wpdb->insert(self::table('consents'), ['user_id' => $user ?: null, 'enquiry_id' => $enquiry ?: null, 'purpose' => $purpose, 'text_version' => $version, 'decision' => (int) $decision, 'created_at' => self::now()])) { throw new \RuntimeException('Consent not saved'); }
    }
    public static function text($value, int $max = 1000): string {
        return mb_substr(sanitize_textarea_field(is_scalar($value) ? (string) $value : ''), 0, $max);
    }
    public static function json($r): array { return (array) ($r->get_json_params() ?: $r->get_body_params()); }
}
