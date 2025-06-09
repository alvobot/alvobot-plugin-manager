<?php
/**
 * Admin functionality for Temporary Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_TemporaryLogin_Admin {

    const USER_CAPABILITY = 'manage_options';
    const ADMIN_SLUG = 'alvobot-temporary-login';

    /**
     * Register hooks
     */
    public static function register_hooks() {
        // Plugin deactivation hook
        // register_deactivation_hook() should be called from the main plugin file
        
        // Disable password reset for temporary users
        add_filter('allow_password_reset', [__CLASS__, 'disable_password_reset'], 10, 2);
        
        // Prevent temporary users from logging in through standard login forms
        add_filter('wp_authenticate_user', [__CLASS__, 'disallow_temporary_user_login']);
        
        // Handle login and logout
        add_action('init', [__CLASS__, 'maybe_login_temporary_user']);
        add_action('init', [__CLASS__, 'maybe_logout_expired_users']);
        
        // Cleanup expired users
        add_action('admin_init', [__CLASS__, 'maybe_remove_temporary_users']);
    }

    /**
     * Disable password reset for temporary users
     *
     * @param bool $allow
     * @param int $user_ID
     * @return bool
     */
    public static function disable_password_reset($allow, $user_ID) {
        if (!empty($user_ID) && AlvoBotPro_TemporaryLogin_Options::is_temporary_user($user_ID)) {
            $allow = false;
        }

        return $allow;
    }

    /**
     * Prevent temporary users from logging in through standard login forms
     *
     * @param WP_User|WP_Error $user
     * @return WP_User|WP_Error
     */
    public static function disallow_temporary_user_login($user) {
        if ($user instanceof \WP_User && AlvoBotPro_TemporaryLogin_Options::is_temporary_user($user->ID)) {
            $user = new \WP_Error(
                'invalid_username',
                __('<strong>Erro:</strong> O usuário não está registrado neste site. Se você não tem certeza do seu nome de usuário, tente seu endereço de e-mail.', 'alvobot-pro')
            );
        }

        return $user;
    }

    /**
     * Handle temporary user login
     */
    public static function maybe_login_temporary_user() {
        if (empty($_GET['temp-login-token'])) {
            return;
        }

        $token = sanitize_key($_GET['temp-login-token']);

        $is_site_token_validated = true;

        $site_token = AlvoBotPro_TemporaryLogin_Options::get_site_token();
        if (!empty($site_token)) {
            $is_site_token_validated = !empty($_GET['tl-site']) && $site_token === $_GET['tl-site'];
        }

        $user = AlvoBotPro_TemporaryLogin_Options::get_user_by_token($token);

        if (!$user || !$is_site_token_validated || AlvoBotPro_TemporaryLogin_Options::is_user_expired($user->ID)) {
            wp_safe_redirect(home_url());
            die;
        }

        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            if ($user->ID !== $current_user_id) {
                wp_logout();
            }
        }

        $action = '';
        if (!empty($_GET['temp-login-action'])) {
            $action = sanitize_key($_GET['temp-login-action']);
        }

        if ('info' === $action) {
            self::print_token_details($user);
        }

        if ('revoke' === $action) {
            self::process_remote_revoke_access();
        }

        self::process_login($user);
    }

    /**
     * Process user login
     *
     * @param WP_User $user
     */
    private static function process_login(\WP_User $user) {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);

        do_action('wp_login', $user->user_login, $user);

        wp_safe_redirect(admin_url());
        die;
    }

    /**
     * Print token details as JSON response
     *
     * @param WP_User $user
     */
    private static function print_token_details(\WP_User $user) {
        $data = [
            'expiration' => AlvoBotPro_TemporaryLogin_Options::get_expiration($user->ID),
        ];

        wp_send_json_success($data);
    }

    /**
     * Process remote revoke access
     */
    private static function process_remote_revoke_access() {
        AlvoBotPro_TemporaryLogin_Options::remove_all_temporary_users();

        wp_send_json_success();
    }

    /**
     * Logout expired users
     */
    public static function maybe_logout_expired_users() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if (!AlvoBotPro_TemporaryLogin_Options::is_temporary_user($user_id)) {
            return;
        }

        if (!AlvoBotPro_TemporaryLogin_Options::is_user_expired($user_id)) {
            return;
        }

        wp_logout();
        wp_safe_redirect(home_url());
        die;
    }

    /**
     * Remove expired temporary users
     */
    public static function maybe_remove_temporary_users() {
        AlvoBotPro_TemporaryLogin_Options::remove_expired_temporary_users();
    }
}
