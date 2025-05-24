<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AlvoBotPro_TemporaryLogin {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->define_constants();
		$this->init_autoloader(); // Renamed from register_autoloader, called before load_dependencies
		$this->load_dependencies(); // Renamed from initial_components
		$this->init_hooks(); // General hooks for the module itself
		do_action( 'alvobot_pro_temporary_login_loaded' );
	}

	private function define_constants() {
		if ( ! defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE' ) ) {
			define( 'ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE', __FILE__ );
		}
		if ( ! defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_PATH' ) ) {
			define( 'ALVOBOT_PRO_TEMPORARY_LOGIN_PATH', __DIR__ . '/' );
		}
		if ( ! defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_URL' ) ) {
			// Assumes this file is in 'wp-content/plugins/alvobot-pro/includes/modules/temporary-login/'
			// Adjust if the main plugin file ALVOBOT_PRO_PLUGIN_FILE is defined elsewhere and accessible
			// For now, deriving from ALVOBOT_PRO_PLUGIN_URL if available, otherwise a relative guess.
			if (defined('ALVOBOT_PRO_PLUGIN_URL')) {
				define('ALVOBOT_PRO_TEMPORARY_LOGIN_URL', ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/temporary-login/');
			} else {
				// Fallback, might need adjustment depending on actual main plugin structure
				define('ALVOBOT_PRO_TEMPORARY_LOGIN_URL', plugin_dir_url( __FILE__ ));
			}
		}
		if ( ! defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_ASSETS_PATH' ) ) {
			define( 'ALVOBOT_PRO_TEMPORARY_LOGIN_ASSETS_PATH', ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'assets/' );
		}
		if ( ! defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_ASSETS_URL' ) ) {
			define( 'ALVOBOT_PRO_TEMPORARY_LOGIN_ASSETS_URL', ALVOBOT_PRO_TEMPORARY_LOGIN_URL . 'assets/' );
		}
	}

	private function init_autoloader() { // Was register_autoloader
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'autoloader.php';
		// Use the global class name for autoloader
		AlvoBotPro_TemporaryLogin_Autoloader::run( ALVOBOT_PRO_TEMPORARY_LOGIN_PATH, 'AlvoBotPro_TemporaryLogin' );
	}

	private function load_dependencies() { // Was initial_components
		// Load core files using defined path for robustness
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/admin.php';
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/ajax.php';
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/options.php';
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/admin-pointer.php';
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/elementor/connect.php';
		require_once ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'core/rest-api.php'; // Load REST API endpoints

		// Initialize core components that register their own hooks
		if ( class_exists( 'AlvoBotPro_TemporaryLogin_Admin' ) ) {
			AlvoBotPro_TemporaryLogin_Admin::init(); // Global class name
		}
		if ( class_exists( 'AlvoBotPro_TemporaryLogin_Ajax' ) ) {
			AlvoBotPro_TemporaryLogin_Ajax::init(); // Global class name
		}
		if ( class_exists( 'AlvoBotPro_TemporaryLogin_Elementor_Connect' ) ) {
			AlvoBotPro_TemporaryLogin_Elementor_Connect::init(); // Inicializa a integração com o Elementor
		}
		// AlvoBotPro_TemporaryLogin_Options and AlvoBotPro_TemporaryLogin_Admin_Pointer might also need init() calls if they register hooks.
		// Based on previous files, AlvoBotPro_TemporaryLogin_Admin_Pointer self-initializes, Options is mostly static.
	}

	private function init_hooks() { // For module-level hooks
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		// Add other module-specific hooks here if necessary
	}

	public function register_rest_routes() {
		register_rest_route(
			'alvobot-pro/v1/temporary-login', // Namespace for the module's API
			'/generate',                      // Endpoint route
			array(
				'methods'             => \WP_REST_Server::CREATABLE, // Corresponds to POST
				'callback'            => array( $this, 'handle_generate_link_request' ),
				'permission_callback' => array( $this, 'generate_link_permission_check' ),
				'args'                => array(
					'api_key' => array(
						'description'       => __( 'Your API Key for authentication.', 'alvobot-pro' ),
						'type'              => 'string',
						'required'          => false, // Required either in header or body
						'sanitize_callback' => 'sanitize_text_field',
					),
					'duration_value' => array(
						'description'       => __( 'The numerical value for the duration.', 'alvobot-pro' ),
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'required'          => false,
					),
					'duration_unit' => array(
						'description'       => __( 'The unit for the duration (hours or days).', 'alvobot-pro' ),
						'type'              => 'string',
						'enum'              => array( 'hours', 'days' ),
						'sanitize_callback' => 'sanitize_key',
						'required'          => false,
					),
					'user_role' => array(
						'description'       => __( 'Requested role for the temporary user.', 'alvobot-pro' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'administrator',
						'required'          => false,
					),
					'reassign_to_user_id' => array(
						'description'       => __( 'User ID to reassign content to upon temporary user deletion.', 'alvobot-pro' ),
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'required'          => false,
					),
				),
			)
		);
	}

	public function generate_link_permission_check( \WP_REST_Request $request ) {
		$api_key_header = $request->get_header( 'X-AlvoBot-API-Key' );
		$api_key_param = $request->get_param( 'api_key' );
		$provided_api_key = !empty( $api_key_header ) ? $api_key_header : $api_key_param;

		if ( empty( $provided_api_key ) ) {
			return new \WP_Error(
				'rest_forbidden_api_key_missing',
				__( 'API Key is missing.', 'alvobot-pro' ),
				array( 'status' => 401 ) // Unauthorized
			);
		}

		$stored_api_key = get_option( 'alvobot_pro_temporary_login_api_key' );
		if ( empty( $stored_api_key ) || ! hash_equals( $stored_api_key, $provided_api_key ) ) {
			return new \WP_Error(
				'rest_forbidden_api_key_invalid',
				__( 'Invalid API Key.', 'alvobot-pro' ),
				array( 'status' => 403 ) // Forbidden
			);
		}
		return true; // Permissions check passed
	}

	public function handle_generate_link_request( \WP_REST_Request $request ) {
		$duration_value = $request->get_param( 'duration_value' );
		$duration_unit = $request->get_param( 'duration_unit' );
		$user_role = $request->get_param( 'user_role' ); // Already sanitized by 'args'
		$reassign_to_user_id = $request->get_param( 'reassign_to_user_id' ); // Already sanitized

		// Placeholder for logic to be fully implemented/adapted in next step
		// For this step, let's simulate a call to the existing Core\Options methods
		// These methods will need to be adapted in the next subtask to handle these parameters.
		// For now, this simulates a basic user generation.
		$user_id = AlvoBotPro_TemporaryLogin_Options::generate_temporary_user(); // This is a placeholder call.

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'user_generation_failed',
				$user_id->get_error_message(),
				array( 'status' => 500 )
			);
		}
		
		// If 'reassign_to_user_id' is provided, set the meta.
		if ( !empty( $reassign_to_user_id ) && $user_id && !is_wp_error( $user_id ) ) {
			 if ( get_user_by( 'ID', $reassign_to_user_id ) ) {
				update_user_meta( $user_id, '_temporary_login_created_by_user_id', $reassign_to_user_id );
			 } else {
				// Optionally return an error or warning if reassign_to_user_id is invalid
			 }
		}

		// These methods also exist as placeholders or basic versions in Core\Options currently.
		$login_url = AlvoBotPro_TemporaryLogin_Options::get_login_url( $user_id );
		$expiration_timestamp = AlvoBotPro_TemporaryLogin_Options::get_expiration( $user_id );
		$expires_at_formatted = gmdate( 'Y-m-d H:i:s', $expiration_timestamp );

		if ( empty( $login_url ) ) {
			return new \WP_Error(
				'link_generation_failed',
				__( 'Failed to generate login URL.', 'alvobot-pro' ),
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response( array(
			'success'   => true,
			'login_url' => $login_url,
			'expires_at'=> $expires_at_formatted,
		), 200 );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'alvobot-pro',
			false,
			dirname( plugin_basename( ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Renders the admin page for the Temporary Login module.
	 * This is the callback for the submenu registered in AlvoBotPro class.
	 */
	public function render_admin_page() {
		// The Core\Admin class is responsible for the actual page content
		if ( class_exists( 'AlvoBotPro_TemporaryLogin_Admin' ) ) {
			AlvoBotPro_TemporaryLogin_Admin::render_admin_page(); // Global class name
		} else {
			echo '<div class="wrap"><h2>' . esc_html__( 'Error: Admin class not found.', 'alvobot-pro' ) . '</h2></div>';
		}
	}

	// Add other plugin methods here, if any were in the original Plugin class beyond setup.
}
// Instance creation will be handled by the main AlvoBotPro class.
