<?php
/*
Plugin Name: AlvoBot Pro
Plugin URI: https://app.alvobot.com/
Description: Suite completa de ferramentas AlvoBot incluindo gerador de logo, author box e gerenciamento de plugins.
Version: 2.9.25
Author: Alvobot - Cris Franklin
Author URI: https://app.alvobot.com/
Text Domain: alvobot-pro
Domain Path: /languages
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constantes do plugin
define( 'ALVOBOT_PRO_VERSION', '2.9.25' );
define( 'ALVOBOT_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALVOBOT_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALVOBOT_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALVOBOT_PRO_PLUGIN_FILE', __FILE__ );
define( 'ALVOBOT_SERVER_URL', 'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/api_plugin' );
define( 'ALVOBOT_AI_URL', 'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/ai_plugin' );

// Carrega autoload do Composer se existir
$alvobot_autoload = ALVOBOT_PRO_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $alvobot_autoload ) ) {
	require_once $alvobot_autoload;
}

// Carrega o arquivo da classe principal
require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro.php';
require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-updater.php';

// Inicializa o plugin
AlvoBotPro::get_instance();

// Inicializa o sistema de atualizações
if ( is_admin() ) {
	new AlvoBotPro_Updater( __FILE__ );
}

// Repara o transient update_plugins se no_update ou response forem stdClass em vez de array.
// Roda em todas as requisições (frontend + admin) para sanar sites afetados pela versão 2.9.13
// que gravava stdClass e causava fatal error em class-wp-plugins-list-table.php:206 (PHP 8.x).
function alvobot_pro_repair_update_transient() {
	$transient = get_site_transient( 'update_plugins' );
	if ( empty( $transient ) ) {
		return;
	}

	$repaired = false;

	if ( isset( $transient->no_update ) && ! is_array( $transient->no_update ) ) {
		$transient->no_update = is_object( $transient->no_update ) ? (array) $transient->no_update : array();
		$repaired             = true;
	}

	if ( isset( $transient->response ) && ! is_array( $transient->response ) ) {
		$transient->response = is_object( $transient->response ) ? (array) $transient->response : array();
		$repaired            = true;
	}

	if ( $repaired ) {
		set_site_transient( 'update_plugins', $transient );
	}
}
add_action( 'init', 'alvobot_pro_repair_update_transient', 1 );

// Hook para inicialização completa do plugin
function alvobot_pro_init() {
	AlvoBotPro::get_instance()->init();
}
add_action( 'init', 'alvobot_pro_init' );

// Ativação do plugin
register_activation_hook( __FILE__, 'alvobot_pro_activate' );
function alvobot_pro_activate() {
	AlvoBotPro::get_instance()->activate();
}

// Desativação do plugin
register_deactivation_hook( __FILE__, 'alvobot_pro_deactivate' );
function alvobot_pro_deactivate() {
	AlvoBotPro::get_instance()->deactivate();
}
