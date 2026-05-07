<?php
/**
 * PHPStan bootstrap for plugin-level constants.
 *
 * @package AlvoBot
 */

if ( ! defined( 'ALVOBOT_PRO_VERSION' ) ) {
	define( 'ALVOBOT_PRO_VERSION', '2.17.17' );
}

if ( ! defined( 'ALVOBOT_PRO_PLUGIN_DIR' ) ) {
	define( 'ALVOBOT_PRO_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'ALVOBOT_PRO_PATH' ) ) {
	define( 'ALVOBOT_PRO_PATH', ALVOBOT_PRO_PLUGIN_DIR );
}

if ( ! defined( 'ALVOBOT_PRO_PLUGIN_URL' ) ) {
	define( 'ALVOBOT_PRO_PLUGIN_URL', 'https://example.test/wp-content/plugins/alvobot-plugin-manager/' );
}

if ( ! defined( 'ALVOBOT_PRO_PLUGIN_FILE' ) ) {
	define( 'ALVOBOT_PRO_PLUGIN_FILE', __DIR__ . '/alvobot-pro.php' );
}
