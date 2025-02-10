<?php
/**
 * Module Name: Logo Generator
 * Description: Gera e define logos personalizados para o site usando IA
 * Version: 1.0.0
 * Author: AlvoBot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the main class files
require_once plugin_dir_path(__FILE__) . 'class-logo-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-logo-generator-api.php';

// Ensure the module is active by default
function alvobot_ensure_logo_generator_active() {
    $active_modules = get_option('alvobot_pro_active_modules', array());
    if (!isset($active_modules['logo_generator'])) {
        $active_modules['logo_generator'] = true;
        update_option('alvobot_pro_active_modules', $active_modules);
    }
}
add_action('init', 'alvobot_ensure_logo_generator_active', 5);

// Initialize the module
function alvobot_init_logo_generator() {
    // Initialize the main logo generator
    $logo_generator = new AlvoBotPro_LogoGenerator();
    
    // Initialize the API
    new AlvoBotPro_LogoGenerator_API($logo_generator);
}

// Initialize after WordPress is fully loaded
add_action('init', 'alvobot_init_logo_generator', 20);
