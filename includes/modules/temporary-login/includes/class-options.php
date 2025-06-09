<?php
/**
 * Options management for Temporary Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_TemporaryLogin_Options {
    
    /**
     * The meta key used for storing the temporary login token
     */
    const TOKEN_META_KEY = 'alvobot_temporary_login_token';
    
    /**
     * The meta key used for storing the expiration date
     */
    const EXPIRATION_META_KEY = 'alvobot_temporary_login_expiration';
    
    /**
     * The meta key used for storing the user ID who created the temporary user
     */
    const CREATED_BY_META_KEY = 'alvobot_temporary_login_created_by';
    
    /**
     * The user role assigned to temporary users
     */
    const USER_ROLE = 'administrator';
    
    /**
     * Option name for the site token
     */
    const SITE_TOKEN_OPTION = 'alvobot_temporary_login_site_token';

    /**
     * Get all temporary users
     *
     * @return array List of WP_User objects
     */
    public static function get_temporary_users() {
        $users = get_users(array(
            'meta_key' => self::TOKEN_META_KEY,
            'meta_compare' => 'EXISTS'
        ));

        return $users;
    }

    /**
     * Check if a temporary user exists
     *
     * @return bool
     */
    public static function has_temporary_user() {
        $users = self::get_temporary_users();
        return !empty($users);
    }

    /**
     * Generate a temporary user
     *
     * @param int $expiration_hours Hours until expiration (default 168 = 7 days)
     * @param string $description Optional description for the temporary user
     * @return int|WP_Error User ID or WP_Error on failure
     */
    public static function generate_temporary_user($expiration_hours = null, $description = '') {
        // Generate username and password
        $username = 'temp_' . wp_generate_password(8, false);
        $password = wp_generate_password(24, true);
        $email = $username . '@' . self::get_site_domain();

        // Create display name with description if provided
        $display_name = __('Usuário Temporário', 'alvobot-pro');
        if (!empty($description)) {
            $display_name .= ' - ' . $description;
        }

        // Create new user
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $display_name,
            'role' => self::USER_ROLE,
            'show_admin_bar_front' => true,
        ));

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Generate token and set expiration date
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, self::TOKEN_META_KEY, $token);
        update_user_meta($user_id, self::CREATED_BY_META_KEY, get_current_user_id());
        
        // Set expiration date
        if ($expiration_hours === null) {
            $options = get_option('alvobot_pro_temporary-login_settings', array());
            $expiration_days = isset($options['expiration_days']) ? absint($options['expiration_days']) : 7;
            $expiration_hours = $expiration_days * 24;
        }
        
        // Limit maximum expiration to 7 days
        $expiration_hours = min($expiration_hours, 168);
        
        $expiration = time() + ($expiration_hours * HOUR_IN_SECONDS);
        update_user_meta($user_id, self::EXPIRATION_META_KEY, $expiration);
        
        // Store description if provided
        if (!empty($description)) {
            update_user_meta($user_id, 'alvobot_temp_login_description', sanitize_text_field($description));
        }

        return $user_id;
    }

    /**
     * Set the user ID who created this temporary user
     *
     * @param int $user_id The temporary user ID
     */
    public static function set_created_by_user_id($user_id) {
        update_user_meta($user_id, self::CREATED_BY_META_KEY, get_current_user_id());
    }

    /**
     * Get the user ID who created this temporary user
     *
     * @param int $user_id The temporary user ID
     * @return int|false The user ID or false if not found
     */
    public static function get_created_by_user_id($user_id) {
        return get_user_meta($user_id, self::CREATED_BY_META_KEY, true);
    }

    /**
     * Get the login URL for a temporary user
     *
     * @param int $user_id User ID
     * @return string Login URL
     */
    public static function get_login_url($user_id) {
        $token = get_user_meta($user_id, self::TOKEN_META_KEY, true);
        if (empty($token)) {
            return '';
        }
        
        $site_token = self::get_site_token();
        $url = add_query_arg(array(
            'temp-login-token' => $token,
            'tl-site' => $site_token,
        ), home_url());

        return $url;
    }

    /**
     * Get the site token
     *
     * @return string Site token
     */
    public static function get_site_token() {
        // Use the main site token from the plugin manager
        return get_option('grp_site_token', '');
    }

    /**
     * Get the expiration date for a temporary user
     *
     * @param int $user_id User ID
     * @return int|false Unix timestamp or false if not found
     */
    public static function get_expiration($user_id) {
        return get_user_meta($user_id, self::EXPIRATION_META_KEY, true);
    }

    /**
     * Get human-readable expiration date for a temporary user
     *
     * @param int $user_id User ID
     * @return string Human-readable date
     */
    public static function get_expiration_human($user_id) {
        $expiration = self::get_expiration($user_id);
        if (!$expiration) {
            return '';
        }
        
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $format = $date_format . ' ' . $time_format;
        
        return date_i18n($format, $expiration);
    }

    /**
     * Check if a user is a temporary user
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_temporary_user($user_id) {
        $token = get_user_meta($user_id, self::TOKEN_META_KEY, true);
        return !empty($token);
    }

    /**
     * Check if a temporary user's access has expired
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_user_expired($user_id) {
        $expiration = self::get_expiration($user_id);
        if (!$expiration) {
            return true;
        }
        
        return time() > $expiration;
    }

    /**
     * Get a user by their temporary login token
     *
     * @param string $token Token to look up
     * @return WP_User|false User object or false if not found
     */
    public static function get_user_by_token($token) {
        $users = get_users(array(
            'meta_key' => self::TOKEN_META_KEY,
            'meta_value' => $token,
            'number' => 1
        ));
        
        if (empty($users)) {
            return false;
        }
        
        return $users[0];
    }

    /**
     * Extend the expiration date of a temporary user
     *
     * @param int $user_id User ID
     * @return bool Whether the expiration was extended
     */
    public static function extend_expiration($user_id) {
        $current_expiration = self::get_expiration($user_id);
        if (!$current_expiration) {
            return false;
        }
        
        $options = get_option('alvobot_pro_temporary-login_settings', array());
        $expiration_days = isset($options['expiration_days']) ? absint($options['expiration_days']) : 7;
        
        $new_expiration = time() + ($expiration_days * DAY_IN_SECONDS);
        update_user_meta($user_id, self::EXPIRATION_META_KEY, $new_expiration);
        
        return true;
    }

    /**
     * Remove all temporary users
     */
    public static function remove_all_temporary_users() {
        $users = self::get_temporary_users();
        foreach ($users as $user) {
            self::remove_temporary_user($user->ID);
        }
    }

    /**
     * Remove expired temporary users
     */
    public static function remove_expired_temporary_users() {
        $users = self::get_temporary_users();
        foreach ($users as $user) {
            if (self::is_user_expired($user->ID)) {
                self::remove_temporary_user($user->ID);
            }
        }
    }

    /**
     * Remove a temporary user
     *
     * @param int $user_id User ID
     */
    public static function remove_temporary_user($user_id) {
        // Check if reassigning posts is needed
        $created_by_user_id = self::get_created_by_user_id($user_id);
        
        if ($created_by_user_id) {
            wp_delete_user($user_id, $created_by_user_id);
        } else {
            wp_delete_user($user_id);
        }
    }

    /**
     * Get the site domain
     *
     * @return string Domain name
     */
    private static function get_site_domain() {
        $url = parse_url(site_url());
        return isset($url['host']) ? $url['host'] : 'example.com';
    }
}
