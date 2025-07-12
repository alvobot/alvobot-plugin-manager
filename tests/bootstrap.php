<?php
/**
 * Bootstrap simples para testes PHPUnit
 */

// Carregar autoloader do Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Definir constantes básicas do WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Definir constantes do plugin
define('ALVOBOT_PRO_VERSION', '2.3.0');
define('ALVOBOT_PRO_PLUGIN_FILE', dirname(__DIR__) . '/alvobot-plugin-manager.php');
define('ALVOBOT_PRO_PLUGIN_DIR', dirname(__DIR__) . '/');
define('ALVOBOT_PRO_PLUGIN_URL', 'http://localhost/wp-content/plugins/alvobot-plugin-manager/');

// Funções básicas do WordPress para os testes
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
    return true;
}

function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
    return true;
}

function get_option($option, $default = false) {
    $options = [
        'alvobot_pro_active_modules' => [
            'quiz-builder' => true,
            'logo-generator' => true,
            'author-box' => true,
            'pre-article' => true,
            'essential-pages' => true,
            'plugin-manager' => true
        ],
        'alvobot_openai_settings' => [
            'api_key' => 'test_key'
        ]
    ];
    return isset($options[$option]) ? $options[$option] : $default;
}

function update_option($option, $value) {
    return true;
}

function esc_html($text) {
    return htmlspecialchars($text);
}

function esc_attr($text) {
    return htmlspecialchars($text);
}

function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function __($text, $domain = 'alvobot-pro') {
    return $text;
}

function _e($text, $domain = 'alvobot-pro') {
    echo $text;
}

function wp_verify_nonce($nonce, $action) {
    return $nonce === 'valid_nonce';
}

function wp_create_nonce($action) {
    return 'test_nonce';
}

function current_user_can($capability) {
    return true;
}

function is_admin() {
    return true;
}

function admin_url($path = '') {
    return 'http://localhost/wp-admin/' . $path;
}

function wp_json_encode($data) {
    return json_encode($data);
}

function wp_die($message = '', $title = '') {
    echo $message;
    exit;
}

echo "Sistema de Testes AlvoBot Pro - PHPUnit\n";
echo "========================================\n\n";