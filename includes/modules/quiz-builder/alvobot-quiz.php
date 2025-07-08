<?php
/**
 * AlvoBot Quiz Module Bootstrap
 *
 * @package AlvoBotPro
 * @subpackage Modules/QuizBuilder
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define module constants if not already defined
if (!defined('ALVOBOT_QUIZ_VERSION')) {
    define('ALVOBOT_QUIZ_VERSION', '1.0.6');
}

if (!defined('ALVOBOT_QUIZ_PATH')) {
    define('ALVOBOT_QUIZ_PATH', plugin_dir_path(__FILE__));
}

if (!defined('ALVOBOT_QUIZ_URL')) {
    define('ALVOBOT_QUIZ_URL', plugin_dir_url(__FILE__));
}

if (!defined('ALVOBOT_QUIZ_BASENAME')) {
    define('ALVOBOT_QUIZ_BASENAME', 'alvobot-pro/modules/quiz-builder');
}