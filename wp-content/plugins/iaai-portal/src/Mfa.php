<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Mfa {
    public static function boot(): void {
        add_filter('iaai_mfa_verified', [self::class, 'verified'], 10, 3);
        add_filter('wp_is_application_passwords_available_for_user', static fn($available, $user) => $user->has_cap('iaai_view_assigned_enquiries') || $user->has_cap('iaai_manage_own_records') ? false : $available, 10, 2);
        add_filter('xmlrpc_enabled', '__return_false');
        add_action('admin_init', static function () {
            if (defined('WP_CLI') && WP_CLI) { return; }
            if (!current_user_can('iaai_view_assigned_enquiries') || self::verified(false, get_current_user_id(), wp_get_session_token())) { return; }
            // Only the user's own profile remains reachable for initial TOTP enrollment.
            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($script === 'profile.php') { return; }
            wp_die('Enroll a time-based authenticator in your WordPress profile, then sign out and sign in with its code. Staff operations require that protected session.', 'Staff authentication required', ['response' => 403]);
        }, 1);
        add_filter('rest_authentication_errors', static function ($error) {
            if ($error || !current_user_can('iaai_view_assigned_enquiries')) { return $error; }
            return self::verified(false, get_current_user_id(), wp_get_session_token()) ? $error : Core::error('session', 403);
        }, 100);
    }
    public static function verified($prior, int $user, string $token): bool {
        if (!class_exists('\Two_Factor_Core') || !$user || !$token || !\Two_Factor_Core::is_user_using_two_factor(get_userdata($user))) { return false; }
        $session = \WP_Session_Tokens::get_instance($user)->get($token);
        return $session && !empty($session['two-factor-login']) && $session['two-factor-login'] >= time() - 28800 && in_array($session['two-factor-provider'] ?? '', ['Two_Factor_Totp', 'Two_Factor_Backup_Codes'], true);
    }
}
