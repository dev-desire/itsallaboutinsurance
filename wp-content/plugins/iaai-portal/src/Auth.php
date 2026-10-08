<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Auth {
    public static function boot(): void {
        add_action('set_logged_in_cookie', static function ($cookie, $expire, $expiration, $user, $scheme, $token) {
            set_transient('iaai_session_' . hash('sha256', $token), ['start' => time(), 'last' => time(), 'reauth' => 0], 8 * HOUR_IN_SECONDS);
        }, 10, 6);
        add_filter('password_reset_expiration', static fn() => HOUR_IN_SECONDS);
        add_filter('retrieve_password_message', static function ($message, $key, $login) {
            $link = add_query_arg(['mode' => 'reset', 'key' => $key, 'login' => $login], home_url('/account/'));
            return "A password reset was requested for your account. / आपके अकाउंट का पासवर्ड रीसेट करने का अनुरोध आया है.\n\nOpen this link to choose a new password:\n" . $link . "\n\nIf you did not request this, you can ignore this message. / अनुरोध आपने नहीं किया तो इस संदेश को अनदेखा करें।";
        }, 10, 3);
        add_action('admin_init', static function () {
            if (current_user_can('iaai_manage_own_records') && !wp_doing_ajax()) {
                wp_safe_redirect(home_url('/my-dashboard/')); exit;
            }
        });
        add_filter('show_admin_bar', static fn($show) => current_user_can('iaai_manage_own_records') ? false : $show);
    }
    public static function routes(): void {
        Core::route('/auth/context', 'GET', [Core::class, 'context'], '__return_true');
        foreach (['register', 'login', 'forgot', 'reset', 'verify', 'resend', 'claim_request', 'claim_complete'] as $action) {
            $paths = ['forgot' => 'forgot-password', 'reset' => 'reset-password', 'verify' => 'verification/complete', 'resend' => 'verification/request', 'claim_request' => 'claim-request', 'claim_complete' => 'claim-complete'];
            Core::route('/auth/' . ($paths[$action] ?? $action), 'POST', [self::class, $action], [Core::class, 'public_permission']);
        }
        Core::route('/auth/logout', 'POST', static function () { wp_logout(); return ['ok' => true]; }, [Core::class, 'signed_in']);
        Core::route('/account/session', 'GET', [self::class, 'session'], [Core::class, 'signed_in']);
        Core::route('/account/reauth', 'POST', [self::class, 'reauth'], [Core::class, 'member']);
        Core::route('/account/settings', 'POST', [self::class, 'settings'], [Core::class, 'member']);
        Core::route('/account/email-change/complete', 'POST', [self::class, 'email_complete'], [Core::class, 'member']);
    }
    public static function session($r) {
        $u = wp_get_current_user();
        return ['name' => $u->display_name, 'email' => $u->user_email, 'verified' => (bool) get_user_meta($u->ID, 'iaai_verified_at', true), 'locale' => Core::locale(), 'nonce' => wp_create_nonce('wp_rest'), 'storageConsent' => (bool) get_user_meta($u->ID, 'iaai_storage_consent', true), 'readiness' => Records::readiness()];
    }
    public static function fresh_session(bool $reauth = false) {
        $token = wp_get_session_token();
        $key = 'iaai_session_' . hash('sha256', $token);
        $state = get_transient($key);
        if (!$token || !get_current_user_id() || get_user_meta(get_current_user_id(), 'iaai_restricted', true) || !\WP_Session_Tokens::get_instance(get_current_user_id())->verify($token) || !$state || time() - $state['last'] > 1800 || time() - $state['start'] > 28800) { return Core::error('session', 401); }
        if ($reauth && time() - $state['reauth'] > 600) { return Core::error('session', 403, 'Re-enter your password to confirm this action.'); }
        $state['last'] = time(); set_transient($key, $state, 28800);
        return true;
    }
    public static function register($r) {
        if (!Core::notices_ready() || !get_option('iaai_mail_verified')) { return Core::error('unavailable', 503); }
        $p = Core::json($r);
        $rate = Core::rate('registration', 5); if (is_wp_error($rate)) { return $rate; }
        $email = sanitize_email($p['email'] ?? '');
        $password = $p['password'] ?? '';
        if (!is_email($email) || !is_string($password) || strlen($password) < 12 || strlen($password) > 256 || $password !== ($p['confirm_password'] ?? '') || empty($p['account_consent']) || !Core::text($p['name'] ?? '', 100)) {
            return Core::error('invalid', 400, 'Enter your name, a valid email and matching passwords of at least 12 characters. Accept the account notice.');
        }
        Core::key();
        if (!email_exists($email)) {
            $id = wp_insert_user(['user_login' => 'member_' . bin2hex(random_bytes(8)), 'user_email' => $email, 'user_pass' => $password, 'display_name' => Core::text($p['name'], 100), 'role' => 'iaai_portal_user']);
            if (is_wp_error($id)) { return Core::error('unavailable', 503); }
            update_user_meta($id, 'iaai_locale', Core::locale());
            Core::consent('account', 'account-v1', $id);
            if (!empty($p['marketing'])) { Core::consent('marketing', 'marketing-v1', $id); }
            self::issue($id, 'verification', [], 86400);
            Core::audit('registered', 'account', (string) $id);
        }
        return ['ok' => true, 'message' => 'If this address can be registered, a verification message has been queued. Check your email or request another verification message.'];
    }
    public static function login($r) {
        $p = Core::json($r);
        $identity = Core::text($p['email'] ?? '', 254);
        foreach ([['login', 10, 900, strtolower($identity)], ['login-network', 60, 900, $_SERVER['REMOTE_ADDR'] ?? 'unknown']] as $bucket) {
            $rate = Core::rate(...$bucket); if (is_wp_error($rate)) { return $rate; }
        }
        if (!is_string($p['password'] ?? null)) { return Core::error('invalid'); }
        $user = wp_authenticate($identity, $p['password']);
        if (is_wp_error($user)) { return Core::error('invalid', 401, 'The sign-in details could not be verified.'); }
        if (!$user->has_cap('iaai_manage_own_records')) {
            return Core::error('session', 403, 'Staff must use the MFA-protected WordPress sign-in.');
        }
        if (get_user_meta($user->ID, 'iaai_restricted', true)) { return Core::error('session', 403); }
        $signed = wp_signon(['user_login' => $user->user_login, 'user_password' => $p['password'], 'remember' => !empty($p['remember'])], is_ssl());
        if (is_wp_error($signed)) { return Core::error('invalid', 401); }
        wp_set_current_user($signed->ID);
        return ['ok' => true, 'redirect' => home_url(get_user_meta($signed->ID, 'iaai_verified_at', true) ? '/my-dashboard/' : '/account/?mode=pending')];
    }
    public static function issue(int $id, string $purpose, array $payload = [], int $ttl = 86400): void {
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $table = Core::table('auth_tokens');
        $wpdb->query($wpdb->prepare("UPDATE $table SET consumed_at=%s WHERE user_id=%d AND purpose=%s AND consumed_at IS NULL", Core::now(), $id, $purpose));
        if (!$wpdb->insert($table, ['user_id' => $id, 'purpose' => $purpose, 'token_hash' => hash('sha256', $token), 'payload' => Core::seal($payload), 'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl)])) { throw new \RuntimeException('Token not saved'); }
        $user = get_userdata($id);
        $destination = $purpose === 'email_change' ? $payload['email'] : $user->user_email;
        $mode = $purpose === 'verification' ? 'verify' : ($purpose === 'email_change' ? 'email-change' : 'claim');
        $link = add_query_arg(['mode' => $mode, 'token' => $token], home_url('/account/'));
        Worker::enqueue('mail', '', ['to' => $destination, 'subject' => "It's All About Insurance — confirm your request", 'body' => "Please open the link and confirm this request. / लिंक खोलकर अनुरोध की पुष्टि करें:\n" . $link . "\n\nThis link expires. If you did not request this, ignore this message. / यह लिंक सीमित समय के लिए है। अनुरोध आपने नहीं किया तो इस संदेश को अनदेखा करें।", 'expires' => time() + $ttl]);
    }
    public static function consume(string $token, string $purpose, int $subject = 0) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return false; }
        $table = Core::table('auth_tokens');
        $wpdb->query('START TRANSACTION');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token_hash=%s AND purpose=%s AND consumed_at IS NULL AND expires_at>%s FOR UPDATE", hash('sha256', $token), $purpose, Core::now()), ARRAY_A);
        if (!$row || ($subject && (int) $row['user_id'] !== $subject)) { $wpdb->query('ROLLBACK'); return false; }
        if (!$wpdb->update($table, ['consumed_at' => Core::now()], ['id' => $row['id']])) { $wpdb->query('ROLLBACK'); return false; }
        $wpdb->query('COMMIT'); return $row;
    }
    public static function verify($r) {
        $row = self::consume(Core::text(Core::json($r)['token'] ?? '', 64), 'verification');
        if (!$row) { return Core::error('invalid', 400, 'This confirmation link is invalid, expired or already used.'); }
        update_user_meta((int) $row['user_id'], 'iaai_verified_at', Core::now());
        Core::audit('verified', 'account', (string) $row['user_id']);
        return ['ok' => true, 'message' => 'Email verified. You can now sign in.'];
    }
    public static function resend($r) {
        $email = sanitize_email(Core::json($r)['email'] ?? '');
        $rate = Core::rate('resend', 5, 3600, strtolower($email)); if (is_wp_error($rate)) { return $rate; }
        $cooldown = Core::rate('resend-cooldown', 1, 60, strtolower($email)); if (is_wp_error($cooldown)) { return $cooldown; }
        $user = get_user_by('email', $email);
        if ($user && $user->has_cap('iaai_manage_own_records') && !get_user_meta($user->ID, 'iaai_verified_at', true)) { self::issue($user->ID, 'verification'); }
        return ['ok' => true, 'message' => 'If verification is needed, a confirmation message has been queued.'];
    }
    public static function forgot($r) {
        $identity = Core::text(Core::json($r)['email'] ?? '', 254);
        $rate = Core::rate('recovery', 5, 3600, strtolower($identity)); if (is_wp_error($rate)) { return $rate; }
        $result = retrieve_password($identity);
        if (is_wp_error($result)) { Core::audit('recovery_attempt', 'account', '', 'error'); }
        return ['ok' => true, 'message' => 'If a matching account exists, password recovery will be attempted. If you do not receive a message, retry later or contact the verified support channel.'];
    }
    public static function reset($r) {
        global $wpdb;
        $p = Core::json($r);
        $rate = Core::rate('reset', 10, 900); if (is_wp_error($rate)) { return $rate; }
        if (!is_string($p['password'] ?? null) || strlen($p['password']) < 12 || strlen($p['password']) > 256 || $p['password'] !== ($p['confirm_password'] ?? '')) { return Core::error('invalid'); }
        $login = Core::text($p['login'] ?? '', 100);
        $lock = 'iaai_reset_' . hash('sha256', $login);
        if (!$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock))) { return Core::error('conflict', 409); }
        try {
            $user = check_password_reset_key(Core::text($p['key'] ?? '', 100), $login);
            if (is_wp_error($user)) { return Core::error('invalid', 400, 'The reset link is invalid, expired or already used.'); }
            reset_password($user, $p['password']);
            \WP_Session_Tokens::get_instance($user->ID)->destroy_all();
            Core::audit('password_reset', 'account', (string) $user->ID);
            return ['ok' => true, 'message' => 'Password updated. Sign in with your new password.'];
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    public static function reauth($r) {
        $p = Core::json($r);
        $rate = Core::rate('reauth', 10, 900, (string) get_current_user_id()); if (is_wp_error($rate)) { return $rate; }
        if (!is_string($p['password'] ?? null) || !wp_check_password($p['password'], wp_get_current_user()->user_pass, get_current_user_id())) { return Core::error('invalid', 401); }
        $key = 'iaai_session_' . hash('sha256', wp_get_session_token());
        $state = get_transient($key); $state['reauth'] = time(); set_transient($key, $state, 28800);
        return ['ok' => true];
    }
    public static function settings($r) {
        $p = Core::json($r); $id = get_current_user_id();
        if (($p['action'] ?? '') === 'locale') {
            $locale = ($p['locale'] ?? '') === 'hi' ? 'hi' : 'en'; update_user_meta($id, 'iaai_locale', $locale);
            return ['ok' => true, 'locale' => $locale];
        }
        $fresh = self::fresh_session(true); if (is_wp_error($fresh)) { return $fresh; }
        switch ($p['action'] ?? '') {
            case 'password':
                if (!is_string($p['password'] ?? null) || strlen($p['password']) < 12 || strlen($p['password']) > 256 || $p['password'] !== ($p['confirm_password'] ?? '')) { return Core::error('invalid'); }
                wp_set_password($p['password'], $id); \WP_Session_Tokens::get_instance($id)->destroy_all(); wp_logout();
                return ['ok' => true, 'logout' => true];
            case 'sessions':
                \WP_Session_Tokens::get_instance($id)->destroy_others(wp_get_session_token()); return ['ok' => true];
            case 'email':
                $email = sanitize_email($p['email'] ?? '');
                if (!is_email($email) || email_exists($email)) { return Core::error('invalid'); }
                self::issue($id, 'email_change', ['email' => $email], 3600); return ['ok' => true, 'message' => 'Confirmation has been queued for the new address. Your current email stays active.'];
            case 'withdraw_storage':
                update_user_meta($id, 'iaai_storage_consent', false);
                global $wpdb;
                $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('grants') . ' SET revoked_at=%s WHERE owner_id=%d AND revoked_at IS NULL', Core::now(), $id));
                Core::consent('storage', 'storage-v1', $id, 0, false);
                return ['ok' => true, 'message' => 'New storage and sharing are disabled. Existing records can still be exported or deleted.'];
        }
        return Core::error('invalid');
    }
    public static function email_complete($r) {
        if (!empty(Core::json($r)['password'])) { $check = self::reauth($r); if (is_wp_error($check)) { return $check; } }
        $fresh = self::fresh_session(true); if (is_wp_error($fresh)) { return $fresh; }
        $row = self::consume(Core::text(Core::json($r)['token'] ?? '', 64), 'email_change', get_current_user_id());
        if (!$row || (int) $row['user_id'] !== get_current_user_id()) { return Core::error('invalid'); }
        $p = Core::open($row['payload']); $old = wp_get_current_user()->user_email;
        if (email_exists($p['email'])) { return Core::error('invalid'); }
        $result = wp_update_user(['ID' => get_current_user_id(), 'user_email' => $p['email']]);
        if (is_wp_error($result)) { return Core::error('invalid'); }
        Worker::enqueue('mail', '', ['to' => $old, 'subject' => 'Account email changed', 'body' => 'Your account email was changed after confirmation. Contact the verified support channel if you did not authorize this.', 'expires' => time() + 86400]);
        Core::audit('email_changed', 'account', (string) get_current_user_id());
        return ['ok' => true];
    }
    public static function claim_request($r) {
        global $wpdb;
        $p = Core::json($r);
        $permission = Core::member($r); if (is_wp_error($permission)) { return $permission; }
        $rate = Core::rate('claim_enquiry', 5); if (is_wp_error($rate)) { return $rate; }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Core::table('enquiries') . ' WHERE public_ref=%s AND user_id IS NULL', Core::text($p['reference'] ?? '', 40)), ARRAY_A);
        if ($row) {
            $data = Core::open($row['payload']);
            if (!empty($data['email'])) {
                $token = bin2hex(random_bytes(32));
                $wpdb->insert(Core::table('auth_tokens'), ['user_id' => get_current_user_id(), 'purpose' => 'enquiry_claim', 'token_hash' => hash('sha256', $token), 'payload' => Core::seal(['enquiry_id' => (int) $row['id']]), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)]);
                Worker::enqueue('mail', '', ['to' => $data['email'], 'subject' => 'Confirm enquiry linking', 'body' => add_query_arg(['mode' => 'claim', 'token' => $token], home_url('/account/')), 'expires' => time() + 3600]);
            }
        }
        return ['ok' => true, 'message' => 'If this enquiry has a verified email contact, linking instructions will be attempted at its saved address.'];
    }
    public static function claim_complete($r) {
        global $wpdb;
        $permission = Core::member($r); if (is_wp_error($permission)) { return $permission; }
        $row = self::consume(Core::text(Core::json($r)['token'] ?? '', 64), 'enquiry_claim', get_current_user_id());
        if (!$row || (int) $row['user_id'] !== get_current_user_id()) { return Core::error('invalid'); }
        $data = Core::open($row['payload']);
        $updated = $wpdb->query($wpdb->prepare('UPDATE ' . Core::table('enquiries') . ' SET user_id=%d WHERE id=%d AND user_id IS NULL', get_current_user_id(), $data['enquiry_id']));
        return $updated ? ['ok' => true] : Core::error('conflict', 409);
    }
}
