<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AlvoBotPro_TemporaryLogin_Options {
	const OPTION_KEY = 'alvobot_pro_temporary_login_options'; // Updated option key

	public static function get_options() {
		return get_option( self::OPTION_KEY, [] );
	}

	public static function update_options( $options ) {
		update_option( self::OPTION_KEY, $options );
	}

	public static function get_option( $key, $default = null ) {
		$options = self::get_options();
		return $options[ $key ] ?? $default;
	}

	public static function update_option( $key, $value ) {
		$options = self::get_options();
		$options[ $key ] = $value;
		self::update_options( $options );
	}

	public static function delete_option( $key ) {
		$options = self::get_options();
		if ( isset( $options[ $key ] ) ) {
			unset( $options[ $key ] );
			self::update_options( $options );
		}
	}

	// Example: Register settings if using WordPress settings API
	/*
	public static function register_settings() {
		register_setting(
			'alvobot_pro_temporary_login_settings_group', // Updated option group
			self::OPTION_KEY, // Option name
			[ __CLASS__, 'sanitize_options' ] // Sanitize callback
		);

		// Add settings sections and fields here if needed
		// add_settings_section( ... );
		// add_settings_field( ... );
	}

	public static function sanitize_options( $input ) {
		// Sanitize and validate options here
		$sanitized_input = [];
		// Example: $sanitized_input['some_key'] = sanitize_text_field( $input['some_key'] );
		return $sanitized_input;
	}
	*/

	/**
	 * Generates a temporary user with specified arguments.
	 *
	 * @param array $args Arguments for user generation.
	 * @return int|\WP_Error User ID on success, WP_Error on failure.
	 */
	public static function generate_temporary_user( $args = array() ) {
		$defaults = array(
			'role'               => 'administrator',
			'duration_value'     => null, // Default to plugin's standard duration
			'duration_unit'      => 'days', // Default unit
			'created_by_user_id' => null, // For reassigning posts
		);
		$args = wp_parse_args( $args, $defaults );

		$role = $args['role'];
		if ( ! get_role( $role ) ) {
			$role = 'administrator'; // Fallback to default if role is invalid
		}

		$username_prefix = apply_filters( 'temporary_login_username_prefix', 'temp_' );
		$username = uniqid( $username_prefix );
		$password = wp_generate_password( 24, true, true );
		$email    = $username . '@example.com'; // Placeholder email

		$user_data = array(
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'role'       => $role,
			'user_nicename' => $username,
			'display_name' => 'Temporary User (' . $username . ')',
		);

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return $user_id; // Return WP_Error from wp_insert_user
		}

		// Calculate expiration timestamp
		$expiration_timestamp = current_time( 'timestamp' );
		if ( ! empty( $args['duration_value'] ) && is_numeric( $args['duration_value'] ) ) {
			$value = intval( $args['duration_value'] );
			$unit  = $args['duration_unit'];

			if ( $unit === 'hours' ) {
				$expiration_timestamp += $value * HOUR_IN_SECONDS;
			} elseif ( $unit === 'days' ) {
				$expiration_timestamp += $value * DAY_IN_SECONDS;
			} else {
				// Fallback to default if unit is invalid but value was given
				$expiration_timestamp = static::get_max_expired_time();
			}
			
			// Ensure custom expiration does not exceed a maximum allowed (e.g., 30 days from now, or plugin default)
			$plugin_max_limit = static::get_max_expired_time(true); // Get absolute max
			$expiration_timestamp = min( $expiration_timestamp, $plugin_max_limit );

		} else {
			// Default expiration if no duration provided
			$expiration_timestamp = static::get_max_expired_time();
		}

		update_user_meta( $user_id, '_temporary_login_expiration', $expiration_timestamp );
		update_user_meta( $user_id, '_temporary_login_is_temporary', true ); // Mark as temporary

		// Handle reassign_to_user_id / created_by_user_id
		if ( ! empty( $args['created_by_user_id'] ) ) {
			if ( get_user_by( 'ID', $args['created_by_user_id'] ) ) {
				update_user_meta( $user_id, '_temporary_login_created_by_user_id', $args['created_by_user_id'] );
			}
		}
		
		// Generate a unique login key/token
		$login_key = wp_generate_password( 32, false, false );
		update_user_meta( $user_id, '_temporary_login_key', $login_key );

		return $user_id;
	}

	/**
	 * Gets the maximum/default expiration time for temporary logins.
	 * @param bool $absolute_max If true, returns the absolute maximum allowed (e.g. 30 days from now).
	 *                           If false, returns the default (e.g. 2 weeks from now).
	 * @return int Timestamp.
	 */
	public static function get_max_expired_time( $absolute_max = false ) {
		if ($absolute_max) {
			return current_time( 'timestamp' ) + ( apply_filters( 'temporary_login_absolute_max_duration_days', 30 ) * DAY_IN_SECONDS );
		}
		// Default duration: 2 weeks
		return current_time( 'timestamp' ) + ( apply_filters( 'temporary_login_default_duration_days', 14 ) * DAY_IN_SECONDS );
	}

	/**
	 * Extends the expiration time for a temporary user.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function extend_expiration( $user_id ) {
		if ( ! get_user_by( 'id', $user_id ) ) {
			return false;
		}
		// Extend by 3 days, capped by the absolute maximum.
		$new_expiration = current_time( 'timestamp' ) + ( 3 * DAY_IN_SECONDS );
		$max_limit = static::get_max_expired_time( true );
		$new_expiration = min( $new_expiration, $max_limit );

		update_user_meta( $user_id, '_temporary_login_expiration', $new_expiration );
		return true;
	}

	/**
	 * Gets the unique login URL for a temporary user.
	 *
	 * @param int $user_id User ID.
	 * @return string|false Login URL or false on failure.
	 */
	public static function get_login_url( $user_id ) {
		$login_key = get_user_meta( $user_id, '_temporary_login_key', true );
		if ( empty( $login_key ) ) {
			return false;
		}
		return add_query_arg(
			array(
				'temporary-login-key' => $login_key,
				'user-id'             => $user_id,
			),
			wp_login_url()
		);
	}
	
	/**
	 * Gets the expiration timestamp for a temporary user.
	 *
	 * @param int $user_id User ID.
	 * @return int|false Expiration timestamp or false if not set.
	 */
	public static function get_expiration( $user_id ) {
		return get_user_meta( $user_id, '_temporary_login_expiration', true );
	}

}

// Example: Hook to register settings (not used in the current plugin structure)
// add_action( 'admin_init', [ __NAMESPACE__ . '\Options', 'register_settings' ] );
