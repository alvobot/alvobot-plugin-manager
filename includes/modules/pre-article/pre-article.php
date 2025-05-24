<?php
/**
 * Plugin Name: Alvobot Pre Article
 * Plugin URI: https://github.com/alvobot/alvobot-pre-article
 * Description: Gere páginas de pré-artigo automaticamente para seus posts existentes.
 * Version: 1.4.16
 * Author: Alvobot - Cris Franklin
 * Author URI: https://github.com/alvobot
 * Text Domain: alvobot-pre-artigo
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

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
require_once plugin_dir_path(__FILE__) . 'includes/class-alvobot-pre-article.php';

// Instancia a classe
if (class_exists('Alvobot_Pre_Article')) {
    $alvobot_pre_article = new Alvobot_Pre_Article();
    $alvobot_pre_article->run();
}
