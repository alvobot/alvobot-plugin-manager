<?php
/**
 * Inicializa o módulo Essential Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Carrega a classe principal
require_once plugin_dir_path( __FILE__ ) . 'class-essential-pages.php';

// Inicializa o módulo
function alvobot_essential_pages_init() {
	return new AlvoBotPro_EssentialPages();
}
