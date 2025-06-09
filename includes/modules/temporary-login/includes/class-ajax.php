<?php
/**
 * AJAX handlers for Temporary Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_TemporaryLogin_Ajax {

    const USER_CAPABILITY = 'manage_options';

    /**
     * Register AJAX hooks
     */
    public static function register_hooks() {
        add_action('wp_ajax_alvobot_temporary_login_get_app_data', [__CLASS__, 'get_app_data']);
        add_action('wp_ajax_alvobot_temporary_login_generate_temporary_user', [__CLASS__, 'enable_access']);
        add_action('wp_ajax_alvobot_temporary_login_revoke_temporary_users', [__CLASS__, 'revoke_access']);
        add_action('wp_ajax_alvobot_temporary_login_extend_access', [__CLASS__, 'extend_access']);
    }

    /**
     * Get application data for admin interface
     */
    public static function get_app_data() {
        if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alvobot-temporary-login-admin-' . get_current_user_id())) {
            wp_send_json_error('Unauthorized', 401);
        }

        if (!current_user_can(self::USER_CAPABILITY)) {
            wp_send_json_error(esc_html__("Você não tem permissão para acessar este recurso", 'alvobot-pro'));
        }

        $current_user = wp_get_current_user();

        $data = [
            'status' => 'inactive',
            'current_user_logged_in_display_name' => $current_user->display_name ?? 'Desconhecido',
        ];

        $temporary_users = AlvoBotPro_TemporaryLogin_Options::get_temporary_users();
        if (!empty($temporary_users)) {
            $temporary_user = $temporary_users[0];
            $data = self::get_active_page_data($temporary_user);
        }

        wp_send_json_success($data);
    }

    /**
     * Get data for active temporary user
     * 
     * @param WP_User $temporary_user
     * @return array
     */
    private static function get_active_page_data(\WP_User $temporary_user): array {
        $created_by_user_id = AlvoBotPro_TemporaryLogin_Options::get_created_by_user_id($temporary_user->ID);
        $reassign_to = '';
        $reassign_user_profile_link = '';
        
        if ($created_by_user_id) {
            $created_user = get_user_by('ID', $created_by_user_id);

            if ($created_user) {
                $reassign_to = $created_user->display_name;
                $reassign_user_profile_link = get_edit_user_link($created_user->ID);
            }
        }

        return [
            'status' => 'active',
            'login_url' => AlvoBotPro_TemporaryLogin_Options::get_login_url($temporary_user->ID),
            'expiration_human' => AlvoBotPro_TemporaryLogin_Options::get_expiration_human($temporary_user->ID),
            'reassign_to' => $reassign_to ?? '',
            'reassign_user_profile_link' => $reassign_user_profile_link ?? '',
        ];
    }

    /**
     * Verify request has proper nonce and user capability
     * 
     * @param array $post_data POST data array
     */
    private static function verify_request($post_data) {
        if (empty($post_data['nonce']) || !wp_verify_nonce($post_data['nonce'], 'alvobot-temporary-login-admin-' . get_current_user_id())) {
            wp_send_json_error('Unauthorized', 401);
        }

        if (!current_user_can(self::USER_CAPABILITY)) {
            wp_send_json_error(esc_html__("Você não tem permissão para acessar este recurso", 'alvobot-pro'));
        }
    }

    /**
     * Create a temporary user
     */
    public static function enable_access() {
        self::verify_request($_POST);

        if (AlvoBotPro_TemporaryLogin_Options::has_temporary_user()) {
            // Temporary user already exists
            wp_send_json_success();
        }

        $user_ID = AlvoBotPro_TemporaryLogin_Options::generate_temporary_user();
        if (is_wp_error($user_ID)) {
            wp_send_json_error($user_ID);
        }

        if (!empty($_POST['is_keep_user_posts']) && 'true' === $_POST['is_keep_user_posts']) {
            AlvoBotPro_TemporaryLogin_Options::set_created_by_user_id($user_ID);
        }

        wp_send_json_success();
    }

    /**
     * Revoke access for all temporary users
     */
    public static function revoke_access() {
        self::verify_request($_POST);

        AlvoBotPro_TemporaryLogin_Options::remove_all_temporary_users();

        wp_send_json_success();
    }

    /**
     * Extend access for a temporary user
     */
    public static function extend_access() {
        self::verify_request($_POST);

        $temporary_users = AlvoBotPro_TemporaryLogin_Options::get_temporary_users();
        if (empty($temporary_users)) {
            wp_send_json_error(new \WP_Error('no_temporary_users', 'Nenhum usuário temporário encontrado'));
        }

        $user = $temporary_users[0];

        if (!AlvoBotPro_TemporaryLogin_Options::extend_expiration($user->ID)) {
            wp_send_json_error(new \WP_Error('no_expiration', 'Não foi possível estender a expiração'));
        }

        wp_send_json_success();
    }
}
