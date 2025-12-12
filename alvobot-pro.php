<?php
/*
Plugin Name: AlvoBot Pro
Plugin URI: https://app.alvobot.com/
Description: Suite completa de ferramentas AlvoBot incluindo gerador de logo, author box e gerenciamento de plugins.
Version: 2.7.1
Author: Alvobot - Cris Franklin
Author URI: https://app.alvobot.com/
Text Domain: alvobot-pro
Domain Path: /languages
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('ALVOBOT_PRO_VERSION', '2.7.1');
define('ALVOBOT_PRO_PATH', plugin_dir_path(__FILE__));
define('ALVOBOT_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALVOBOT_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALVOBOT_PRO_PLUGIN_FILE', __FILE__);
define('GRP_SERVER_URL', 'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/api_plugin');

// Carrega o arquivo da classe principal
require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro.php';
require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-updater.php';

// Inicializa o plugin
$GLOBALS['alvobot_pro'] = new AlvoBotPro();

// Inicializa o sistema de atualizações
if (is_admin()) {
    new AlvoBotPro_Updater(__FILE__);
}

// Hook para inicialização completa do plugin
function alvobot_pro_init() {
    global $alvobot_pro;
    $alvobot_pro->init();
}
add_action('init', 'alvobot_pro_init');

// Ativação do plugin
register_activation_hook(__FILE__, 'alvobot_pro_activate');
function alvobot_pro_activate() {
    global $alvobot_pro;
    $alvobot_pro->activate();
}

// Desativação do plugin
register_deactivation_hook(__FILE__, 'alvobot_pro_deactivate');
function alvobot_pro_deactivate() {
    global $alvobot_pro;
    $alvobot_pro->deactivate();
}
