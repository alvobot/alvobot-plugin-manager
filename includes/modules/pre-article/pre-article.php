<?php

declare(strict_types=1);

// Se este arquivo for chamado diretamente, aborta
if (!defined('WPINC')) {
    die;
}

// Define constantes do plugin
define('ALVOBOT_PRE_ARTICLE_VERSION', '1.4.16');
define('ALVOBOT_PRE_ARTICLE_FILE', __FILE__);
define('ALVOBOT_PRE_ARTICLE_PATH', plugin_dir_path(__FILE__));
define('ALVOBOT_PRE_ARTICLE_URL', plugin_dir_url(__FILE__));
define('ALVOBOT_PRE_ARTICLE_BASENAME', plugin_basename(__FILE__));

// Carrega a classe principal
require_once plugin_dir_path(__FILE__) . 'includes/class-pre-article.php';

// Registrar função de ativação para lidar com regras de rewrite
register_activation_hook(__FILE__, ['Alvobot_Pre_Article', 'activate']);

// Instancia a classe
$alvobot_pre_article = new Alvobot_Pre_Article();
$alvobot_pre_article->run();

/**
 * Função para logging (apenas quando WP_DEBUG está ativo)
 */
function alvobot_log($message, $data = null) {
    AlvoBotPro::debug_log('pre-article', $message);
    if ($data !== null) {
        AlvoBotPro::debug_log('pre-article', 'Data: ' . print_r($data, true));
    }
}