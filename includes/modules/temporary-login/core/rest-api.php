<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * REST API Class for Temporary Login module
 * Handles all REST API endpoints for the Temporary Login module
 */
class AlvoBotPro_TemporaryLogin_REST_API {
    /**
     * Initialize REST API functionality
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Route for getting all temporary users
        register_rest_route(
            'alvobot-pro/v1/temporary-login',
            '/users',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_temporary_users'),
                'permission_callback' => array(__CLASS__, 'admin_permissions_check'),
            )
        );

        // Route for creating a temporary user (same as generate)
        register_rest_route(
            'alvobot-pro/v1/temporary-login',
            '/generate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'create_temporary_user'),
                'permission_callback' => array(__CLASS__, 'admin_permissions_check'),
                'args'                => array(
                    'role'            => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function($param) {
                            return in_array($param, array('administrator', 'editor', 'author', 'contributor', 'subscriber'));
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'duration_value' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                    'duration_unit' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function($param) {
                            return in_array($param, array('hours', 'days'));
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'reassign_to_user_id' => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Route for deleting a temporary user
        register_rest_route(
            'alvobot-pro/v1/temporary-login',
            '/users/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array(__CLASS__, 'delete_temporary_user'),
                'permission_callback' => array(__CLASS__, 'admin_permissions_check'),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Route for extending a temporary user expiration
        register_rest_route(
            'alvobot-pro/v1/temporary-login',
            '/users/(?P<id>\d+)/extend',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'extend_user_expiration'),
                'permission_callback' => array(__CLASS__, 'admin_permissions_check'),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    /**
     * Check if the current user has admin permissions
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if user has permission, WP_Error otherwise
     */
    public static function admin_permissions_check($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You do not have permission to access this endpoint.', 'alvobot-pro'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Get all temporary users
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public static function get_temporary_users($request) {
        $users = self::get_all_temporary_users();
        
        return rest_ensure_response(array(
            'success' => true,
            'users' => $users,
        ));
    }

    /**
     * Create a temporary user
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public static function create_temporary_user($request) {
        $role = $request->get_param('role');
        $duration_value = $request->get_param('duration_value');
        $duration_unit = $request->get_param('duration_unit');
        $reassign_to_user_id = $request->get_param('reassign_to_user_id');

        $args = array(
            'role' => $role,
            'duration_value' => $duration_value,
            'duration_unit' => $duration_unit,
        );

        if (!empty($reassign_to_user_id)) {
            $args['created_by_user_id'] = $reassign_to_user_id;
        }

        $user_id = AlvoBotPro_TemporaryLogin_Options::generate_temporary_user($args);

        if (is_wp_error($user_id)) {
            return new WP_Error(
                'user_generation_failed',
                $user_id->get_error_message(),
                array('status' => 500)
            );
        }

        $login_url = AlvoBotPro_TemporaryLogin_Options::get_login_url($user_id);
        $expiration_timestamp = AlvoBotPro_TemporaryLogin_Options::get_expiration($user_id);
        
        if (empty($login_url)) {
            return new WP_Error(
                'link_generation_failed',
                esc_html__('Failed to generate login URL.', 'alvobot-pro'),
                array('status' => 500)
            );
        }

        $user_data = get_userdata($user_id);
        
        $user = array(
            'id' => $user_id,
            'username' => $user_data->user_login,
            'role' => $role,
            'login_url' => $login_url,
            'created_at' => get_user_meta($user_id, '_temporary_login_created_at', true) ?: current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
            'created_by_user_id' => get_user_meta($user_id, '_temporary_login_created_by_user_id', true) ?: null,
        );

        return rest_ensure_response(array(
            'success' => true,
            'user' => $user,
        ));
    }

    /**
     * Delete a temporary user
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public static function delete_temporary_user($request) {
        $user_id = $request->get_param('id');
        
        // Check if the user is a temporary user
        $is_temporary = get_user_meta($user_id, '_temporary_login_is_temporary', true);
        
        if (!$is_temporary) {
            return new WP_Error(
                'not_temporary_user',
                esc_html__('This user is not a temporary user.', 'alvobot-pro'),
                array('status' => 400)
            );
        }
        
        // Check if there's a user to reassign content to
        $reassign_to = get_user_meta($user_id, '_temporary_login_created_by_user_id', true);
        
        // Delete the user
        if (!empty($reassign_to) && get_user_by('id', $reassign_to)) {
            wp_delete_user($user_id, $reassign_to);
        } else {
            wp_delete_user($user_id);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => esc_html__('Temporary user deleted successfully.', 'alvobot-pro'),
        ));
    }

    /**
     * Extend a temporary user's expiration
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public static function extend_user_expiration($request) {
        $user_id = $request->get_param('id');
        
        // Check if the user is a temporary user
        $is_temporary = get_user_meta($user_id, '_temporary_login_is_temporary', true);
        
        if (!$is_temporary) {
            return new WP_Error(
                'not_temporary_user',
                esc_html__('This user is not a temporary user.', 'alvobot-pro'),
                array('status' => 400)
            );
        }
        
        $success = AlvoBotPro_TemporaryLogin_Options::extend_expiration($user_id);
        
        if (!$success) {
            return new WP_Error(
                'extension_failed',
                esc_html__('Failed to extend expiration.', 'alvobot-pro'),
                array('status' => 500)
            );
        }
        
        $user_data = get_userdata($user_id);
        $expiration_timestamp = AlvoBotPro_TemporaryLogin_Options::get_expiration($user_id);
        $login_url = AlvoBotPro_TemporaryLogin_Options::get_login_url($user_id);
        
        $user = array(
            'id' => $user_id,
            'username' => $user_data->user_login,
            'role' => $user_data->roles[0],
            'login_url' => $login_url,
            'created_at' => get_user_meta($user_id, '_temporary_login_created_at', true) ?: current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
            'created_by_user_id' => get_user_meta($user_id, '_temporary_login_created_by_user_id', true) ?: null,
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => esc_html__('Expiration extended successfully.', 'alvobot-pro'),
            'user' => $user,
        ));
    }

    /**
     * Get all temporary users
     * 
     * @return array Array of temporary users
     */
    private static function get_all_temporary_users() {
        $users = array();
        
        $args = array(
            'meta_key' => '_temporary_login_is_temporary',
            'meta_value' => true,
        );
        
        $user_query = new WP_User_Query($args);
        
        if (!empty($user_query->get_results())) {
            foreach ($user_query->get_results() as $user) {
                $expiration_timestamp = AlvoBotPro_TemporaryLogin_Options::get_expiration($user->ID);
                $login_url = AlvoBotPro_TemporaryLogin_Options::get_login_url($user->ID);
                
                $users[] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'role' => $user->roles[0],
                    'login_url' => $login_url,
                    'created_at' => get_user_meta($user->ID, '_temporary_login_created_at', true) ?: current_time('mysql'),
                    'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
                    'created_by_user_id' => get_user_meta($user->ID, '_temporary_login_created_by_user_id', true) ?: null,
                );
            }
        }
        
        return $users;
    }
}

// Initialize the REST API
AlvoBotPro_TemporaryLogin_REST_API::init();
