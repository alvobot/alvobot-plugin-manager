<?php
/**
 * Assets management class
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all asset enqueueing for the plugin
 *
 * Manages CSS and JavaScript files for both frontend and admin.
 * Conditionally loads assets based on shortcode parameters and admin pages.
 *
 * @since 1.0.0
 */
class Alvobot_Quiz_Assets {

	public function __construct() {
		// Frontend assets
		add_action( 'init', array( $this, 'register_frontend_assets' ) );

		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register frontend styles and scripts
	 */
	public function register_frontend_assets() {
		// Register frontend CSS
		wp_register_style(
			'alvobot-quiz-frontend',
			ALVOBOT_QUIZ_URL . 'assets/css/quiz-frontend.css',
			array(),
			ALVOBOT_QUIZ_VERSION
		);

		// Register frontend JS
		wp_register_script(
			'alvobot-quiz-frontend',
			ALVOBOT_QUIZ_URL . 'assets/js/quiz-frontend.js',
			array(),
			ALVOBOT_QUIZ_VERSION . '.debug1', // Force cache refresh with debug logs
			true
		);
	}

	/**
	 * Enqueue frontend assets when shortcode is used
	 *
	 * @param array $settings Shortcode settings to determine which assets to load
	 */
	public function enqueue_frontend_assets( $settings = array() ) {
		// Always enqueue CSS
		wp_enqueue_style( 'alvobot-quiz-frontend' );

		// Sempre carrega o script JS, pois precisamos dele para navegação e controle de URL
		wp_enqueue_script( 'alvobot-quiz-frontend' );

		// Localizar o script com as configurações do shortcode (mode suffix is default now)
		wp_localize_script(
			'alvobot-quiz-frontend',
			'alvobot_quiz_settings',
			array(
				'url_mode' => 'suffix', // Always use suffix mode now
			)
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on quiz builder admin pages
		if ( strpos( $hook, 'alvobot-quiz-builder' ) === false ) {
			return;
		}

		// Admin CSS
		wp_enqueue_style(
			'alvobot-quiz-admin',
			ALVOBOT_QUIZ_URL . 'assets/css/admin.css',
			array( 'wp-components', 'wp-admin' ),
			ALVOBOT_QUIZ_VERSION
		);

		// Sortable.js for drag and drop
		wp_enqueue_script(
			'sortable-js',
			'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
			array(),
			'1.15.0',
			true
		);

		// Quiz builder JS (using working version)
		wp_enqueue_script(
			'alvobot-quiz-builder',
			ALVOBOT_QUIZ_URL . 'assets/js/quiz-builder.js',
			array( 'jquery' ),
			ALVOBOT_QUIZ_VERSION . '.3', // Force cache refresh after copy button fix
			true
		);

		// Localize script
		wp_localize_script(
			'alvobot-quiz-builder',
			'alvobot_quiz_admin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'alvobot_quiz_admin_nonce' ),
				'strings' => array(
					'confirm_delete' => __( 'Are you sure you want to delete this question?', 'alvobot-pro' ),
					'confirm_clear'  => __( 'Are you sure you want to clear everything?', 'alvobot-pro' ),
					'copied'         => __( 'Copied!', 'alvobot-pro' ),
					'copy_error'     => __( 'Error copying', 'alvobot-pro' ),
				),
			)
		);
	}
}
