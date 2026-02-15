<?php
/**
 * AlvoBot Pro - Uninstall
 *
 * Remove all plugin data when uninstalled via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin options
$options_to_delete = array(
	'alvobot_site_token',
	'alvobot_settings',
	'alvobot_activity_log',
	'alvobot_pro_active_modules',
	'alvobot_pro_debug_modules',
	'alvobot_pro_version',
	'alvobot_connection_status',
	'alvobot_options_migrated',
	'alvobot_pro_author_box',
	'alvobot_quiz_version',
	'alvobot_smart_links_settings',
	// Legacy options (may still exist on older installations)
	'grp_site_token',
	'grp_settings',
	'grp_activity_log',
	'grp_registration_status',
	'grp_site_code',
	'grp_app_password',
	'grp_registered',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Delete module-specific settings (pattern: alvobot_pro_*_settings)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'alvobot_pro_%_settings'
	)
);

// Delete transients
$transient_patterns = array(
	'_transient_alvobot_ai_credits',
	'_transient_timeout_alvobot_ai_credits',
	'_transient_alvobot_ai_costs',
	'_transient_timeout_alvobot_ai_costs',
);

foreach ( $transient_patterns as $transient ) {
	delete_option( $transient );
}

// Clean up rate limiting transients (pattern: alvobot_rl_*, alvobot_contact_rl_*)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient%alvobot_rl_%',
		'_transient%alvobot_contact_rl_%'
	)
);

// Clean up admin notice transients
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient%alvobot_admin_notices_%'
	)
);

// Delete plugin users
$users_to_delete = array( 'alvobot', 'alvobot_temp' );

foreach ( $users_to_delete as $username ) {
	$user = get_user_by( 'login', $username );
	if ( $user ) {
		// Transfer posts to the first admin before deletion
		$admins      = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'exclude' => array( $user->ID ),
			)
		);
		$reassign_to = ! empty( $admins ) ? $admins[0]->ID : null;

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID, $reassign_to );
	}
}

// Clean up Smart Internal Links post meta
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_alvobot_smart_links'" );

// Clean up user meta for all users
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'ab_custom_avatar_id'" );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		'ab_bio_%'
	)
);
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'alvobot_temp_user'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'alvobot_created_by'" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
