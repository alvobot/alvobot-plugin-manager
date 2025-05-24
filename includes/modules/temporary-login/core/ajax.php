<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AlvoBotPro_TemporaryLogin_Ajax {
	const AJAX_NONCE_ACTION = 'alvobot_pro_temporary_login_ajax_actions';

	public static function init() {
		$ajax_actions = [
			'get_app_data',
			'generate_temporary_user',
			'revoke_temporary_users',
			'extend_access',
			'send_login_by_elementor_connect',
		];

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_alvobot_pro_temporary_login_' . $action, [ __CLASS__, $action . '_handler' ] );
		}

		// Add new action for API key generation
		add_action( 'wp_ajax_alvobot_pro_temporary_login_generate_api_key', [ __CLASS__, 'generate_api_key_handler' ] );
	}

	private static function verify_nonce( $action = self::AJAX_NONCE_ACTION, $nonce_field = '_ajax_nonce' ) {
		// Assumes the nonce is sent in a field named '_ajax_nonce' (common practice)
		// or 'nonce' as per some examples. Let's stick to '_ajax_nonce' for clarity with check_ajax_referer.
		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce field is missing.', 'alvobot-pro' ) ], 400 );
		}
		check_ajax_referer( $action, $nonce_field );
	}

	public static function generate_api_key_handler() {
		self::verify_nonce( 'alvobot_pro_temporary_login_api_key_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage API keys.', 'alvobot-pro' ) ], 403 );
		}

		// Generate a secure random string (e.g., 40 characters)
		$new_api_key = wp_generate_password( 40, false, true );

		update_option( 'alvobot_pro_temporary_login_api_key', $new_api_key );

		wp_send_json_success( [
			'message' => __( 'New API Key generated and saved successfully.', 'alvobot-pro' ),
			'api_key' => $new_api_key,
		] );
	}

	public static function get_app_data_handler() {
		self::verify_nonce(); // Uses default AJAX_NONCE_ACTION
		// Placeholder logic
		wp_send_json_success( [
			'message' => 'App data would be here.',
			'settings' => [], // Example
			'logins' => [],   // Example
		] );
	}

	public static function generate_temporary_user_handler() {
		self::verify_nonce(); // Uses default AJAX_NONCE_ACTION (alvobot_pro_temporary_login_ajax_actions)

		if ( ! current_user_can( 'manage_options' ) ) { // Or a more specific capability
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'alvobot-pro' ) ], 403 );
		}

		$gen_args = array();

		// Role (assuming it might be passed from an admin UI form)
		if ( ! empty( $_POST['role'] ) && is_string( $_POST['role'] ) ) {
			$gen_args['role'] = sanitize_key( $_POST['role'] );
		} else {
			$gen_args['role'] = 'administrator'; // Default for UI-generated users if not specified
		}
		
		// Duration (assuming it might be passed from an admin UI form)
		// Example: if UI sends 'duration' as "7_days" or similar, parse it.
		// For now, let's assume UI might send specific duration_value and duration_unit.
		if ( ! empty( $_POST['duration_value'] ) && is_numeric( $_POST['duration_value'] ) ) {
			$gen_args['duration_value'] = intval( $_POST['duration_value'] );
			if ( ! empty( $_POST['duration_unit'] ) && in_array( $_POST['duration_unit'], [ 'hours', 'days' ], true ) ) {
				$gen_args['duration_unit'] = sanitize_key( $_POST['duration_unit'] );
			} else {
				$gen_args['duration_unit'] = 'days'; // Default unit if value is present but unit is not or invalid
			}
		}
		// If duration_value is not set, Options::generate_temporary_user will use its internal default.

		// Keep user posts / Reassign content
		if ( ! empty( $_POST['is_keep_user_posts'] ) && ( $_POST['is_keep_user_posts'] === 'true' || $_POST['is_keep_user_posts'] === '1' ) ) {
			$current_user_id = get_current_user_id();
			if ( $current_user_id ) {
				$gen_args['created_by_user_id'] = $current_user_id;
			}
		}

		$user_id_or_error = Options::generate_temporary_user( $gen_args );

		if ( is_wp_error( $user_id_or_error ) ) {
			wp_send_json_error( [ 'message' => $user_id_or_error->get_error_message() ], 500 );
		} elseif ( $user_id_or_error ) {
			$login_url = Options::get_login_url( $user_id_or_error );
			$expiration = Options::get_expiration( $user_id_or_error );
			wp_send_json_success( [
				'message'    => __( 'Temporary user generated successfully.', 'alvobot-pro' ),
				'user_id'    => $user_id_or_error,
				'login_url'  => $login_url,
				'expires_at' => gmdate( 'Y-m-d H:i:s', $expiration ),
			] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to generate temporary user for an unknown reason.', 'alvobot-pro' ) ], 500 );
		}
	}

	public static function revoke_temporary_users_handler() {
		self::verify_nonce(); // Uses default AJAX_NONCE_ACTION
		// Placeholder logic for revoking users
		wp_send_json_success( [ 'message' => 'Temporary users revoked (placeholder).' ] );
	}

	public static function extend_access_handler() {
		self::verify_nonce(); // Uses default AJAX_NONCE_ACTION
		// Placeholder logic for extending access
		wp_send_json_success( [ 'message' => 'Access extended (placeholder).' ] );
	}

	public static function send_login_by_elementor_connect_handler() {
		self::verify_nonce(); // Uses default AJAX_NONCE_ACTION
		// Placeholder logic for Elementor connect
		wp_send_json_success( [ 'message' => 'Login sent via Elementor Connect (placeholder).' ] );
	}
}

// Ajax::init(); // Initialization is handled by AlvoBotPro_TemporaryLogin class
// The AlvoBotPro_TemporaryLogin class should call Ajax::init() in its load_dependencies or init_hooks.
// Based on previous step, it's called in load_dependencies in AlvoBotPro_TemporaryLogin.
