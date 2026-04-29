<?php
/**
 * Pixel Tracking Module - Main Class
 *
 * Provides Meta Pixel browser tracking + Facebook Conversion API
 * server-side event dispatch for WordPress sites.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main module orchestration for pixel-tracking.
 */
class AlvoBotPro_PixelTracking extends AlvoBotPro_Module_Base {

	/**
	 * CPT handler instance.
	 *
	 * @var AlvoBotPro_PixelTracking_CPT
	 */
	public $cpt;

	/**
	 * REST API handler instance.
	 *
	 * @var AlvoBotPro_PixelTracking_REST
	 */
	private $rest;

	/**
	 * Frontend handler instance.
	 *
	 * @var AlvoBotPro_PixelTracking_Frontend
	 */
	private $frontend;

	/**
	 * CAPI sender instance.
	 *
	 * @var AlvoBotPro_PixelTracking_CAPI
	 */
	public $capi;

	/**
	 * Cleanup handler instance.
	 *
	 * @var AlvoBotPro_PixelTracking_Cleanup
	 */
	private $cleanup;

	protected function define_module_properties() {
		$this->module_id          = 'pixel-tracking';
		$this->module_name        = 'Pixel Tracking';
		$this->module_description = 'Rastreamento de eventos com Meta Pixel e Facebook Conversion API.';
		$this->module_icon        = 'activity';
	}

	protected function init() {
		// Load sub-components
		$module_dir = plugin_dir_path( __FILE__ );

		require_once $module_dir . 'class-pixel-tracking-cpt.php';
		$this->cpt = new AlvoBotPro_PixelTracking_CPT();

		require_once $module_dir . 'class-pixel-tracking-rest.php';
		$this->rest = new AlvoBotPro_PixelTracking_REST( $this );

		require_once $module_dir . 'class-pixel-tracking-frontend.php';
		$this->frontend = new AlvoBotPro_PixelTracking_Frontend( $this );

		require_once $module_dir . 'class-pixel-tracking-capi.php';
		$this->capi = new AlvoBotPro_PixelTracking_CAPI( $this );

		require_once $module_dir . 'class-pixel-tracking-cleanup.php';
		$this->cleanup = new AlvoBotPro_PixelTracking_Cleanup( $this );

		// AJAX handlers for pixel/tracker fetch (AlvoBot mode)
		add_action( 'wp_ajax_alvobot_pixel_tracking_fetch_pixels', array( $this, 'ajax_fetch_pixels' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_fetch_google_trackers', array( $this, 'ajax_fetch_google_trackers' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_fetch_conversion_actions', array( $this, 'ajax_fetch_google_conversion_actions' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_create_conversion_action', array( $this, 'ajax_create_google_conversion_action' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_update_conversion_action', array( $this, 'ajax_update_google_conversion_action' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_refresh_token', array( $this, 'ajax_refresh_token' ) );

		// AJAX handlers for conversion CRUD
		add_action( 'wp_ajax_alvobot_pixel_tracking_save_conversion', array( $this, 'ajax_save_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_delete_conversion', array( $this, 'ajax_delete_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_toggle_conversion', array( $this, 'ajax_toggle_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_get_conversions', array( $this, 'ajax_get_conversions' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_bulk_delete_conversions', array( $this, 'ajax_bulk_delete_conversions' ) );

		// Admin notice for expired tokens.
		add_action( 'admin_notices', array( $this, 'admin_notice_expired_tokens' ) );
		// Admin notice for orphaned conversion rules (selected trackers removed).
		add_action( 'admin_notices', array( $this, 'admin_notice_orphaned_conversions' ) );

		AlvoBotPro::debug_log( 'pixel-tracking', 'Module initialized' );
	}

	/**
	 * Fetch pixels from AlvoBot platform via api_plugin Edge Function.
	 *
	 * @param bool $force Force refresh (skip transient cache).
	 * @return array|WP_Error Array of pixels or error.
	 */
	public function fetch_alvobot_pixels( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( 'alvobot_pixel_tracking_pixels' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$server_url = defined( 'ALVOBOT_SERVER_URL' ) ? ALVOBOT_SERVER_URL : '';
		$site_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $site_token ) ) {
			return new WP_Error( 'no_token', __( 'Site nao conectado ao AlvoBot. Configure o token primeiro.', 'alvobot-pro' ) );
		}

		if ( empty( $server_url ) ) {
			return new WP_Error( 'no_server', __( 'URL do servidor AlvoBot nao configurada.', 'alvobot-pro' ) );
		}

		$response = wp_remote_post(
			$server_url,
			array(
				'body'    => wp_json_encode(
					array(
						'action'   => 'get_meta_pixels',
						'site_url' => get_site_url(),
						'token'    => $site_token,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'pixel-tracking', 'Erro ao buscar pixels: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			AlvoBotPro::debug_log( 'pixel-tracking', "Erro HTTP {$code} ao buscar pixels" );
			/* translators: %d: HTTP status code */
			return new WP_Error( 'http_error', sprintf( __( 'Erro ao buscar pixels (HTTP %d)', 'alvobot-pro' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_response', __( 'Resposta invalida ao buscar pixels.', 'alvobot-pro' ) );
		}

		// Backward/forward compatibility: some responses return data, others pixels.
		$pixels = array();
		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$pixels = $body['data'];
		} elseif ( isset( $body['pixels'] ) && is_array( $body['pixels'] ) ) {
			$pixels = $body['pixels'];
		}

		set_transient( 'alvobot_pixel_tracking_pixels', $pixels, 15 * MINUTE_IN_SECONDS );

		AlvoBotPro::debug_log( 'pixel-tracking', 'Pixels obtidos: ' . count( $pixels ) );
		return $pixels;
	}

	/**
	 * AJAX handler to fetch AlvoBot pixels.
	 */
	public function ajax_fetch_pixels() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$pixels = $this->fetch_alvobot_pixels( true );

		if ( is_wp_error( $pixels ) ) {
			wp_send_json_error( $pixels->get_error_message() );
		}

		wp_send_json_success( array( 'pixels' => $pixels ) );
	}

	/**
	 * Fetch Google trackers (GA4 + Google Ads) from AlvoBot platform.
	 *
	 * @param bool $force Force refresh (skip transient cache).
	 * @return array|WP_Error Array of trackers or error.
	 */
	public function fetch_alvobot_google_trackers( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( 'alvobot_pixel_tracking_google_trackers' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$server_url = defined( 'ALVOBOT_SERVER_URL' ) ? ALVOBOT_SERVER_URL : '';
		$site_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $site_token ) ) {
			return new WP_Error( 'no_token', __( 'Site nao conectado ao AlvoBot. Configure o token primeiro.', 'alvobot-pro' ) );
		}

		if ( empty( $server_url ) ) {
			return new WP_Error( 'no_server', __( 'URL do servidor AlvoBot nao configurada.', 'alvobot-pro' ) );
		}

		$response = wp_remote_post(
			$server_url,
			array(
				'body'    => wp_json_encode(
					array(
						'action'   => 'get_google_trackers',
						'site_url' => get_site_url(),
						'token'    => $site_token,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'pixel-tracking', 'Erro ao buscar Google trackers: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			AlvoBotPro::debug_log( 'pixel-tracking', "Erro HTTP {$code} ao buscar Google trackers" );
			return new WP_Error( 'http_error', sprintf( __( 'Erro ao buscar trackers (HTTP %d)', 'alvobot-pro' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_response', __( 'Resposta invalida ao buscar Google trackers.', 'alvobot-pro' ) );
		}

		$trackers = array();
		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$trackers = $body['data'];
		} elseif ( isset( $body['trackers'] ) && is_array( $body['trackers'] ) ) {
			$trackers = $body['trackers'];
		}

		set_transient( 'alvobot_pixel_tracking_google_trackers', $trackers, 15 * MINUTE_IN_SECONDS );

		AlvoBotPro::debug_log( 'pixel-tracking', 'Google trackers obtidos: ' . count( $trackers ) );
		return $trackers;
	}

	/**
	 * AJAX handler to fetch AlvoBot Google trackers.
	 */
	public function ajax_fetch_google_trackers() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$trackers = $this->fetch_alvobot_google_trackers( true );

		if ( is_wp_error( $trackers ) ) {
			wp_send_json_error( $trackers->get_error_message() );
		}

		wp_send_json_success( array( 'trackers' => $trackers ) );
	}

	/**
	 * Fetch Google Ads ConversionActions from the Google Ads API via Edge Function.
	 *
	 * @param string $connection_id Connection UUID from Supabase.
	 * @param string $customer_id   Google Ads customer ID (without hyphens).
	 * @return array|WP_Error Array of conversion actions or error.
	 */
	public function fetch_google_conversion_actions( $connection_id, $customer_id ) {
		$server_url = defined( 'ALVOBOT_SERVER_URL' ) ? ALVOBOT_SERVER_URL : '';
		$site_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $site_token ) || empty( $server_url ) ) {
			return new WP_Error( 'no_config', __( 'Site nao conectado ao AlvoBot.', 'alvobot-pro' ) );
		}

		$response = wp_remote_post(
			$server_url,
			array(
				'body'    => wp_json_encode(
					array(
						'action'        => 'get_google_conversion_actions',
						'site_url'      => get_site_url(),
						'token'         => $site_token,
						'connection_id' => $connection_id,
						'customer_id'   => $customer_id,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'pixel-tracking', 'Erro ao buscar ConversionActions: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = isset( $body['error'] ) ? $body['error'] : sprintf( 'HTTP %d', $code );
			AlvoBotPro::debug_log( 'pixel-tracking', "Erro ao buscar ConversionActions: {$msg}" );
			return new WP_Error( 'api_error', $msg );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			$msg = isset( $body['error'] ) ? $body['error'] : __( 'Resposta invalida.', 'alvobot-pro' );
			return new WP_Error( 'invalid_response', $msg );
		}

		$actions = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		AlvoBotPro::debug_log( 'pixel-tracking', 'ConversionActions obtidas: ' . count( $actions ) );
		return $actions;
	}

	/**
	 * Create a new ConversionAction in Google Ads via Edge Function.
	 */
	public function create_google_conversion_action( $connection_id, $customer_id, $params ) {
		$server_url = defined( 'ALVOBOT_SERVER_URL' ) ? ALVOBOT_SERVER_URL : '';
		$site_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $site_token ) || empty( $server_url ) ) {
			return new WP_Error( 'no_config', __( 'Site nao conectado ao AlvoBot.', 'alvobot-pro' ) );
		}

		$response = wp_remote_post(
			$server_url,
			array(
				'body'    => wp_json_encode(
					array_merge(
						$params,
						array(
							'action'        => 'create_google_conversion_action',
							'site_url'      => get_site_url(),
							'token'         => $site_token,
							'connection_id' => $connection_id,
							'customer_id'   => $customer_id,
						)
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['success'] ) ) {
			$msg = isset( $body['error'] ) ? $body['error'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'api_error', $msg );
		}

		return isset( $body['data'] ) ? $body['data'] : array();
	}

	/**
	 * Update an existing ConversionAction in Google Ads via Edge Function.
	 */
	public function update_google_conversion_action( $connection_id, $customer_id, $conversion_action_id, $params ) {
		$server_url = defined( 'ALVOBOT_SERVER_URL' ) ? ALVOBOT_SERVER_URL : '';
		$site_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $site_token ) || empty( $server_url ) ) {
			return new WP_Error( 'no_config', __( 'Site nao conectado ao AlvoBot.', 'alvobot-pro' ) );
		}

		$response = wp_remote_post(
			$server_url,
			array(
				'body'    => wp_json_encode(
					array_merge(
						$params,
						array(
							'action'               => 'update_google_conversion_action',
							'site_url'             => get_site_url(),
							'token'                => $site_token,
							'connection_id'        => $connection_id,
							'customer_id'          => $customer_id,
							'conversion_action_id' => $conversion_action_id,
						)
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['success'] ) ) {
			$msg = isset( $body['error'] ) ? $body['error'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'api_error', $msg );
		}

		return isset( $body['data'] ) ? $body['data'] : array();
	}

	/**
	 * AJAX: Create a ConversionAction in Google Ads.
	 */
	public function ajax_create_google_conversion_action() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$connection_id = isset( $_POST['connection_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : '';
		$customer_id   = isset( $_POST['customer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) : '';
		$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( empty( $connection_id ) || empty( $customer_id ) || empty( $name ) ) {
			wp_send_json_error( __( 'Campos obrigatorios: connection_id, customer_id, name.', 'alvobot-pro' ) );
		}

		$params = array(
			'name'          => $name,
			'category'      => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'DEFAULT',
			'counting_type' => isset( $_POST['counting_type'] ) ? sanitize_text_field( wp_unslash( $_POST['counting_type'] ) ) : 'ONE_PER_CLICK',
			'default_value' => isset( $_POST['default_value'] ) ? floatval( $_POST['default_value'] ) : 0,
			'currency'      => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'BRL',
		);

		$result = $this->create_google_conversion_action( $connection_id, $customer_id, $params );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Update a ConversionAction in Google Ads.
	 */
	public function ajax_update_google_conversion_action() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$connection_id        = isset( $_POST['connection_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : '';
		$customer_id          = isset( $_POST['customer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) : '';
		$conversion_action_id = isset( $_POST['conversion_action_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conversion_action_id'] ) ) : '';

		if ( empty( $connection_id ) || empty( $customer_id ) || empty( $conversion_action_id ) ) {
			wp_send_json_error( __( 'Campos obrigatorios: connection_id, customer_id, conversion_action_id.', 'alvobot-pro' ) );
		}

		$params = array();
		if ( isset( $_POST['name'] ) ) {
			$params['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		}
		if ( isset( $_POST['status'] ) ) {
			$params['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
		}
		if ( isset( $_POST['default_value'] ) ) {
			$params['default_value'] = floatval( $_POST['default_value'] );
			$params['currency']      = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'BRL';
		}

		$result = $this->update_google_conversion_action( $connection_id, $customer_id, $conversion_action_id, $params );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler to fetch Google Ads ConversionActions.
	 */
	public function ajax_fetch_google_conversion_actions() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$connection_id = isset( $_POST['connection_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : '';
		$customer_id   = isset( $_POST['customer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) : '';

		if ( empty( $connection_id ) || empty( $customer_id ) ) {
			wp_send_json_error( __( 'connection_id e customer_id sao obrigatorios.', 'alvobot-pro' ) );
		}

		$actions = $this->fetch_google_conversion_actions( $connection_id, $customer_id );

		if ( is_wp_error( $actions ) ) {
			wp_send_json_error( $actions->get_error_message() );
		}

		wp_send_json_success( array( 'conversion_actions' => $actions ) );
	}

	/**
	 * AJAX handler to refresh a single pixel's token from AlvoBot.
	 *
	 * Fetches fresh data from the platform and updates the stored token
	 * for the specified pixel_id. Clears token_expired flag on success.
	 */
	public function ajax_refresh_token() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$pixel_id = isset( $_POST['pixel_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pixel_id'] ) ) : '';
		if ( empty( $pixel_id ) ) {
			wp_send_json_error( __( 'Pixel ID obrigatorio.', 'alvobot-pro' ) );
		}

		$fresh_pixels = $this->fetch_alvobot_pixels( true );
		if ( is_wp_error( $fresh_pixels ) ) {
			wp_send_json_error( $fresh_pixels->get_error_message() );
		}

		// Find the pixel in the fresh data.
		$new_token = '';
		$new_label = '';
		foreach ( $fresh_pixels as $fp ) {
			if ( isset( $fp['pixel_id'] ) && $fp['pixel_id'] === $pixel_id ) {
				$new_token = isset( $fp['access_token'] ) ? $fp['access_token'] : '';
				$new_label = isset( $fp['pixel_name'] ) ? $fp['pixel_name'] : '';
				break;
			}
		}

		if ( empty( $new_token ) ) {
			wp_send_json_error( __( 'Pixel nao encontrado no AlvoBot ou token indisponivel.', 'alvobot-pro' ) );
		}

		// Update the token in saved settings.
		$settings = $this->get_settings();
		$updated  = false;
		if ( isset( $settings['pixels'] ) ) {
			foreach ( $settings['pixels'] as &$pixel ) {
				if ( isset( $pixel['pixel_id'] ) && $pixel['pixel_id'] === $pixel_id ) {
					$pixel['api_token']     = sanitize_text_field( $new_token );
					$pixel['token_expired'] = false;
					if ( $new_label ) {
						$pixel['label'] = sanitize_text_field( $new_label );
					}
					$updated = true;
					break;
				}
			}
		}

		if ( $updated ) {
			$this->save_settings( $settings );
			// Trigger immediate CAPI dispatch for pending event backlog.
			$this->capi->schedule_immediate_dispatch();
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Token renovado com sucesso!', 'alvobot-pro' ),
				'api_token' => $new_token,
				'label'     => $new_label,
			)
		);
	}

	/**
	 * Show admin notice when any configured pixel has an expired token.
	 */
	public function admin_notice_expired_tokens() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		$expired = array();
		foreach ( $pixels as $pixel ) {
			if ( ! empty( $pixel['token_expired'] ) ) {
				$expired[] = isset( $pixel['label'] ) && $pixel['label'] ? $pixel['label'] : $pixel['pixel_id'];
			}
		}

		if ( empty( $expired ) ) {
			return;
		}

		$page_url = admin_url( 'admin.php?page=alvobot-pro-pixel-tracking&tab=pixels' );
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s: %s. <a href="%s">%s</a></p></div>',
			esc_html__( 'Pixel Tracking:', 'alvobot-pro' ),
			esc_html__( 'Token expirado para', 'alvobot-pro' ),
			esc_html( implode( ', ', $expired ) ),
			esc_url( $page_url ),
			esc_html__( 'Renovar token', 'alvobot-pro' )
		);
	}

	/**
	 * Show admin notice when any conversion rule references a tracker that is no
	 * longer present in the pixels tab configuration. Prevents silent data loss
	 * that previously drafted the rule and wiped its labels map.
	 */
	public function admin_notice_orphaned_conversions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$orphaned = new WP_Query(
			array(
				'post_type'      => 'alvo_pixel_conv',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 10,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_orphaned_pixel_ids',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $orphaned->posts ) ) {
			return;
		}

		$names = array();
		foreach ( $orphaned->posts as $conv_id ) {
			$names[] = get_the_title( $conv_id );
		}
		$page_url = admin_url( 'admin.php?page=alvobot-pro-pixel-tracking&tab=conversoes' );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s: %s. <a href="%s">%s</a></p></div>',
			esc_html__( 'Pixel Tracking:', 'alvobot-pro' ),
			esc_html__( 'Conversoes com trackers ausentes', 'alvobot-pro' ),
			esc_html( implode( ', ', $names ) ),
			esc_url( $page_url ),
			esc_html__( 'Revisar', 'alvobot-pro' )
		);
	}

	/**
	 * Sync tokens from AlvoBot for all AlvoBot-sourced pixels on settings page load.
	 *
	 * Runs once per hour (throttled via transient) when admin visits the Pixels tab.
	 * Updates tokens and clears token_expired flags for any pixel with a fresh token.
	 */
	public function maybe_sync_tokens() {
		// Throttle: run once per hour.
		$cache_key = 'alvobot_pixel_token_sync';
		if ( false !== get_transient( $cache_key ) ) {
			return;
		}

		$settings    = $this->get_settings();
		$pixels      = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
		$has_alvobot = false;
		$has_expired = false;

		foreach ( $pixels as $pixel ) {
			if ( 'alvobot' === ( isset( $pixel['source'] ) ? $pixel['source'] : '' ) ) {
				$has_alvobot = true;
			}
			if ( ! empty( $pixel['token_expired'] ) ) {
				$has_expired = true;
			}
		}

		// Only sync if we have AlvoBot pixels AND at least one is expired.
		if ( ! $has_alvobot || ! $has_expired ) {
			set_transient( $cache_key, 1, HOUR_IN_SECONDS );
			return;
		}

		AlvoBotPro::debug_log( 'pixel-tracking', 'Auto-syncing tokens from AlvoBot (expired tokens detected)...' );

		$fresh_pixels = $this->fetch_alvobot_pixels( true );
		if ( is_wp_error( $fresh_pixels ) || ! is_array( $fresh_pixels ) ) {
			set_transient( $cache_key, 1, HOUR_IN_SECONDS );
			return;
		}

		// Build lookup: pixel_id → access_token.
		$token_map = array();
		foreach ( $fresh_pixels as $fp ) {
			if ( ! empty( $fp['pixel_id'] ) && ! empty( $fp['access_token'] ) ) {
				$token_map[ $fp['pixel_id'] ] = $fp['access_token'];
			}
		}

		$changed = false;
		foreach ( $settings['pixels'] as &$pixel ) {
			$pid = isset( $pixel['pixel_id'] ) ? $pixel['pixel_id'] : '';
			if ( ! empty( $pixel['token_expired'] ) && isset( $token_map[ $pid ] ) ) {
				$pixel['api_token']     = sanitize_text_field( $token_map[ $pid ] );
				$pixel['token_expired'] = false;
				$changed                = true;
				AlvoBotPro::debug_log( 'pixel-tracking', "Auto-sync: token refreshed for pixel {$pid}" );
			}
		}

		if ( $changed ) {
			$this->save_settings( $settings );
			// Trigger immediate CAPI dispatch for pending event backlog.
			$this->capi->schedule_immediate_dispatch();
		}

		set_transient( $cache_key, 1, HOUR_IN_SECONDS );
	}

	/**
	 * AJAX: Save a conversion rule.
	 */
	public function ajax_save_conversion() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		$allowed_triggers = array( 'page_load', 'page_time', 'form_submit', 'click', 'scroll', 'view_element', 'ad_impression', 'ad_click', 'ad_vignette_open', 'ad_vignette_click' );
		$posted_trigger   = isset( $_POST['trigger_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_type'] ) ) : '';
		if ( '' !== $posted_trigger && ! in_array( $posted_trigger, $allowed_triggers, true ) ) {
			/* translators: %s: trigger type received */
			wp_send_json_error( sprintf( __( 'Gatilho invalido: %s', 'alvobot-pro' ), $posted_trigger ) );
		}
		$data = array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'event_type'        => isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : 'PageView',
			'event_custom_name' => isset( $_POST['event_custom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_custom_name'] ) ) : '',
			'trigger_type'      => '' !== $posted_trigger ? $posted_trigger : 'page_load',
			'trigger_value'     => isset( $_POST['trigger_value'] ) ? absint( $_POST['trigger_value'] ) : 0,
			'display_on'        => isset( $_POST['display_on'] ) ? sanitize_text_field( wp_unslash( $_POST['display_on'] ) ) : 'all',
			'page_ids'          => isset( $_POST['page_ids'] ) ? array_map( 'absint', (array) $_POST['page_ids'] ) : array(),
			'page_paths'        => isset( $_POST['page_paths'] ) ? sanitize_text_field( wp_unslash( $_POST['page_paths'] ) ) : '',
			'css_selector'      => isset( $_POST['css_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['css_selector'] ) ) : '',
			'content_name'      => isset( $_POST['content_name'] ) ? sanitize_text_field( wp_unslash( $_POST['content_name'] ) ) : '',
			'pixel_ids'              => isset( $_POST['pixel_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['pixel_ids'] ) ) : '',
			'gads_conversion_label'  => isset( $_POST['gads_conversion_label'] ) ? sanitize_text_field( wp_unslash( $_POST['gads_conversion_label'] ) ) : '',
			'gads_labels_map'        => isset( $_POST['gads_labels_map'] ) ? wp_unslash( $_POST['gads_labels_map'] ) : '{}', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in CPT save_conversion_meta.
			'gads_conversion_value'  => isset( $_POST['gads_conversion_value'] )
				? (string) min( 1000000, max( 0, floatval( wp_unslash( $_POST['gads_conversion_value'] ) ) ) ) : '',
		);

		// Derive platforms from selected pixel_ids.
		$ids_array = array_filter( array_map( 'trim', explode( ',', $data['pixel_ids'] ) ) );
		$has_meta  = false;
		$has_google = false;
		foreach ( $ids_array as $pid ) {
			if ( preg_match( '/^\d{15,16}$/', $pid ) ) {
				$has_meta = true;
			}
			if ( preg_match( '/^(G-|AW-)/', $pid ) || 'sitekit_gtag' === $pid ) {
				$has_google = true;
			}
		}
		if ( empty( $ids_array ) ) {
			$data['platforms'] = 'all';
		} elseif ( $has_meta && $has_google ) {
			$data['platforms'] = 'all';
		} elseif ( $has_meta ) {
			$data['platforms'] = 'meta_only';
		} elseif ( $has_google ) {
			$data['platforms'] = 'google_only';
		} else {
			$data['platforms'] = 'all';
		}

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( __( 'Nome da conversao e obrigatorio.', 'alvobot-pro' ) );
		}

		if ( $conversion_id > 0 ) {
			// Update existing
			$result = $this->cpt->update_conversion( $conversion_id, $data );
		} else {
			// Create new
			$result = $this->cpt->create_conversion( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array( 'conversion_id' => $result ) );
	}

	/**
	 * AJAX: Delete a conversion rule.
	 */
	public function ajax_delete_conversion() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		if ( ! $conversion_id ) {
			wp_send_json_error( __( 'ID invalido.', 'alvobot-pro' ) );
		}

		$post = get_post( $conversion_id );
		if ( ! $post || 'alvo_pixel_conv' !== $post->post_type ) {
			wp_send_json_error( __( 'Conversao nao encontrada.', 'alvobot-pro' ) );
		}

		wp_delete_post( $conversion_id, true );
		wp_send_json_success();
	}

	/**
	 * AJAX: Bulk delete conversion rules.
	 */
	public function ajax_bulk_delete_conversions() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IDs are cast to int below.
		$ids = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
		if ( is_string( $ids ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', $ids ) ) );
		} elseif ( is_array( $ids ) ) {
			$ids = array_filter( array_map( 'absint', $ids ) );
		} else {
			$ids = array();
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( __( 'Nenhuma conversao selecionada.', 'alvobot-pro' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 'alvo_pixel_conv' === $post->post_type ) {
				wp_delete_post( $id, true );
				++$deleted;
			}
		}

		wp_send_json_success( array( 'deleted' => $deleted ) );
	}

	/**
	 * AJAX: Toggle conversion active/inactive.
	 */
	public function ajax_toggle_conversion() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		$active        = isset( $_POST['active'] ) && sanitize_text_field( wp_unslash( $_POST['active'] ) ) === '1';

		if ( ! $conversion_id ) {
			wp_send_json_error( __( 'ID invalido.', 'alvobot-pro' ) );
		}

		$post = get_post( $conversion_id );
		if ( ! $post || 'alvo_pixel_conv' !== $post->post_type ) {
			wp_send_json_error( __( 'Conversao nao encontrada.', 'alvobot-pro' ) );
		}

		wp_update_post(
			array(
				'ID'          => $conversion_id,
				'post_status' => $active ? 'publish' : 'draft',
			)
		);

		wp_send_json_success();
	}

	/**
	 * AJAX: Get all conversion rules.
	 */
	public function ajax_get_conversions() {
		check_ajax_referer( 'pixel-tracking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissao negada.', 'alvobot-pro' ) );
		}

		$conversions = $this->cpt->get_conversions( true );
		$result      = array();

		foreach ( $conversions as $conv ) {
			$result[] = array(
				'id'                => $conv->ID,
				'name'              => $conv->post_title,
				'status'            => $conv->post_status,
				'event_type'        => get_post_meta( $conv->ID, '_event_type', true ),
				'event_custom_name' => get_post_meta( $conv->ID, '_event_custom_name', true ),
				'trigger_type'      => get_post_meta( $conv->ID, '_trigger_type', true ),
				'trigger_value'     => get_post_meta( $conv->ID, '_trigger_value', true ),
				'display_on'        => get_post_meta( $conv->ID, '_display_on', true ),
				'page_ids'          => get_post_meta( $conv->ID, '_page_ids', true ),
				'page_paths'        => get_post_meta( $conv->ID, '_page_paths', true ),
				'css_selector'      => get_post_meta( $conv->ID, '_css_selector', true ),
				'content_name'      => get_post_meta( $conv->ID, '_content_name', true ),
				'pixel_ids'         => get_post_meta( $conv->ID, '_pixel_ids', true ),
				'platforms'              => get_post_meta( $conv->ID, '_platforms', true ),
				'gads_conversion_label'  => get_post_meta( $conv->ID, '_gads_conversion_label', true ),
				'gads_labels_map'        => get_post_meta( $conv->ID, '_gads_labels_map', true ),
				'gads_conversion_value'  => get_post_meta( $conv->ID, '_gads_conversion_value', true ),
				'is_system'              => get_post_meta( $conv->ID, '_is_system', true ),
			);
		}

		wp_send_json_success( array( 'conversions' => $result ) );
	}

	/**
	 * Handle pending events when a pixel is removed from settings.
	 *
	 * @param string $removed_pixel_id The pixel ID that was removed.
	 */
	private function handle_removed_pixel( $removed_pixel_id ) {
		$pending = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_event',
				'post_status'    => 'pixel_pending',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_pixel_ids',
						'value'   => $removed_pixel_id,
						'compare' => 'LIKE',
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $pending->posts ) ) {
			$this->cpt->mark_events_error( $pending->posts, 'pixel_removed' );
			AlvoBotPro::debug_log( 'pixel-tracking', 'Discarded ' . count( $pending->posts ) . " events for removed pixel {$removed_pixel_id}" );
		}
	}

	private function derive_platforms_from_target_ids( $ids_array ) {
		$ids_array  = array_filter( array_map( 'trim', (array) $ids_array ) );
		$has_meta   = false;
		$has_google = false;

		foreach ( $ids_array as $target_id ) {
			if ( preg_match( '/^\d{15,16}$/', $target_id ) ) {
				$has_meta = true;
			}
			if ( preg_match( '/^(G-|AW-)/', $target_id ) || 'sitekit_gtag' === $target_id ) {
				$has_google = true;
			}
		}

		if ( empty( $ids_array ) || ( $has_meta && $has_google ) ) {
			return 'all';
		}
		if ( $has_meta ) {
			return 'meta_only';
		}
		if ( $has_google ) {
			return 'google_only';
		}

		return 'all';
	}

	private function sync_conversion_targets_to_active_trackers( $active_target_ids ) {
		$active_target_ids = array_values( array_filter( array_map( 'strval', (array) $active_target_ids ) ) );
		$valid_lookup      = array_fill_keys( $active_target_ids, true );
		$conversions       = $this->cpt->get_conversions( true );

		foreach ( $conversions as $conv ) {
			$raw_pixel_ids = (string) get_post_meta( $conv->ID, '_pixel_ids', true );
			$selected_ids  = array_values( array_filter( array_map( 'trim', explode( ',', $raw_pixel_ids ) ) ) );

			// Empty pixel_ids means "all active" — skip filtering entirely.
			if ( empty( $selected_ids ) ) {
				continue;
			}

			$valid_ids     = array_values(
				array_filter(
					$selected_ids,
					function ( $target_id ) use ( $valid_lookup ) {
						return isset( $valid_lookup[ $target_id ] );
					}
				)
			);

			$labels_map_raw = get_post_meta( $conv->ID, '_gads_labels_map', true );
			$labels_map     = array();
			if ( is_string( $labels_map_raw ) && '' !== $labels_map_raw ) {
				$decoded_labels_map = json_decode( $labels_map_raw, true );
				if ( is_array( $decoded_labels_map ) ) {
					$labels_map = $decoded_labels_map;
				}
			}

			$filtered_labels_map = array();
			foreach ( $labels_map as $tracker_id => $label ) {
				if ( in_array( $tracker_id, $valid_ids, true ) || in_array( $tracker_id, $active_target_ids, true ) ) {
					$filtered_labels_map[ $tracker_id ] = $label;
				}
			}

			$pixel_ids_changed   = implode( ',', $selected_ids ) !== implode( ',', $valid_ids );
			$labels_map_changed  = wp_json_encode( $labels_map ) !== wp_json_encode( $filtered_labels_map );
			$had_explicit_target = ! empty( $selected_ids );

			if ( ! $pixel_ids_changed && ! $labels_map_changed ) {
				continue;
			}

			if ( $had_explicit_target && empty( $valid_ids ) ) {
				// All selected trackers orphaned. Preserve the original selection so that
				// a subsequent save (which may re-introduce the tracker) restores the rule
				// without data loss. Flag as orphaned so the admin UI can surface a notice.
				update_post_meta( $conv->ID, '_orphaned_pixel_ids', $raw_pixel_ids );
				AlvoBotPro::debug_log( 'pixel-tracking', 'Conversion ' . $conv->ID . ' marcada como orfa (trackers ausentes): ' . $raw_pixel_ids );
				continue;
			}

			delete_post_meta( $conv->ID, '_orphaned_pixel_ids' );
			update_post_meta( $conv->ID, '_pixel_ids', implode( ',', $valid_ids ) );
			update_post_meta( $conv->ID, '_platforms', $this->derive_platforms_from_target_ids( $valid_ids ) );
			update_post_meta( $conv->ID, '_gads_labels_map', wp_json_encode( $filtered_labels_map ) );
			if ( empty( $filtered_labels_map ) ) {
				update_post_meta( $conv->ID, '_gads_conversion_label', '' );
			}
			AlvoBotPro::debug_log( 'pixel-tracking', 'Conversion ' . $conv->ID . ' teve trackers sincronizados com a configuracao ativa.' );
		}
	}

	/**
	 * Override base: use tab-based admin page template.
	 */
	protected function render_settings_template() {
		// Auto-sync expired tokens from AlvoBot (throttled, once per hour).
		$this->maybe_sync_tokens();

		$settings = $this->get_settings();

		// Re-localize after process_settings_form() so the JS sees the freshly-saved
		// pixels/google_trackers. wp_localize_script in admin_enqueue_scripts ran with
		// the pre-save values; without this overlay the user would see stale data
		// until the next page reload (e.g. the Conversoes tab Pixel selector would
		// be empty even though the tracker was just saved).
		$pixel_labels = array();
		if ( isset( $settings['pixels'] ) && is_array( $settings['pixels'] ) ) {
			foreach ( $settings['pixels'] as $pixel ) {
				$pixel_id = isset( $pixel['pixel_id'] ) ? sanitize_text_field( (string) $pixel['pixel_id'] ) : '';
				if ( '' === $pixel_id ) {
					continue;
				}
				$pixel_labels[ $pixel_id ] = isset( $pixel['label'] ) ? sanitize_text_field( (string) $pixel['label'] ) : '';
			}
		}
		$google_trackers = isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ? $settings['google_trackers'] : array();

		// JSON_HEX_* flags neutralize </script> and quote-injection inside the inline <script> tag.
		$inline_payload = wp_json_encode(
			array(
				'pixel_labels'    => $pixel_labels,
				'google_trackers' => $google_trackers,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( false !== $inline_payload ) {
			wp_add_inline_script(
				'alvobot-pro-pixel-tracking',
				'window.alvobot_pixel_tracking_extra = Object.assign(window.alvobot_pixel_tracking_extra || {}, ' . $inline_payload . ');',
				'before'
			);
		}

		include plugin_dir_path( __FILE__ ) . 'views/admin-page.php';
	}

	/**
	 * Override base: merge POST data with existing settings for partial tab submissions.
	 */
	protected function process_settings_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_settings_page.
		$active_tab = isset( $_POST['active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['active_tab'] ) ) : '';
		$existing   = $this->get_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_settings_page.
		$posted = $_POST;
		unset( $posted['submit'], $posted['_wpnonce'], $posted['_wp_http_referer'], $posted['active_tab'] );

		// Merge: start from existing, overlay what was posted.
		$merged = $existing;

		if ( 'pixels' === $active_tab ) {
			// Only pixel-related fields from this tab.
			if ( isset( $posted['mode'] ) ) {
				$merged['mode'] = $posted['mode'];
			}
			if ( isset( $posted['pixels_json'] ) ) {
				$merged['pixels_json'] = $posted['pixels_json'];
			}
			// Google Tracking fields (same tab).
			if ( isset( $posted['google_trackers_json'] ) ) {
				$merged['google_trackers_json'] = $posted['google_trackers_json'];
			}
		} elseif ( 'settings' === $active_tab ) {
			// Settings tab fields — checkboxes absent = unchecked.
			$merged['test_mode']       = isset( $posted['test_mode'] ) ? $posted['test_mode'] : '';
			$merged['test_event_code'] = isset( $posted['test_event_code'] ) ? $posted['test_event_code'] : '';
			$merged['realtime_dispatch'] = isset( $posted['realtime_dispatch'] ) ? $posted['realtime_dispatch'] : '';
			$merged['excluded_roles']  = isset( $posted['excluded_roles'] ) ? $posted['excluded_roles'] : array();
			$merged['retention_days']  = isset( $posted['retention_days'] ) ? $posted['retention_days'] : '';
			$merged['max_events']      = isset( $posted['max_events'] ) ? $posted['max_events'] : '';
			$merged['max_leads']       = isset( $posted['max_leads'] ) ? $posted['max_leads'] : '';
		}

		if ( $this->save_settings( $merged ) ) {
			$this->add_admin_notice( __( 'Configuracoes salvas com sucesso!', 'alvobot-pro' ), 'success' );
		} else {
			$this->add_admin_notice( __( 'Erro ao salvar configuracoes.', 'alvobot-pro' ), 'error' );
		}
	}


	/**
	 * Override base: add REST data for status tab JS.
	 */
	public function enqueue_admin_assets( $hook ) {
		parent::enqueue_admin_assets( $hook );

		if ( strpos( $hook, 'alvobot-pro-pixel-tracking' ) === false ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab display logic.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'pixels';
		$settings   = $this->get_settings();
		$pixels     = isset( $settings['pixels'] ) && is_array( $settings['pixels'] ) ? $settings['pixels'] : array();
		$pixel_labels = array();
		foreach ( $pixels as $pixel ) {
			$pixel_id = isset( $pixel['pixel_id'] ) ? sanitize_text_field( (string) $pixel['pixel_id'] ) : '';
			if ( '' === $pixel_id ) {
				continue;
			}
			$pixel_labels[ $pixel_id ] = isset( $pixel['label'] ) ? sanitize_text_field( (string) $pixel['label'] ) : '';
		}

			wp_localize_script(
				'alvobot-pro-pixel-tracking',
				'alvobot_pixel_tracking_extra',
				array(
					'rest_url'      => esc_url_raw( rest_url( 'alvobot-pro/v1/pixel-tracking/' ) ),
					'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
					'active_tab'    => $active_tab,
					'pixel_labels'  => $pixel_labels,
					'debug_enabled'    => class_exists( 'AlvoBotPro' ) && method_exists( 'AlvoBotPro', 'is_debug_enabled' ) ? (bool) AlvoBotPro::is_debug_enabled( 'pixel-tracking' ) : false,
					'google_trackers'  => isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ? $settings['google_trackers'] : array(),
				)
			);
		}

	protected function render_settings_sections( $settings ) {
		// Kept for backward compatibility — content now rendered via tab view files.
	}

	protected function sanitize_settings( $settings ) {
		$sanitized = array();
		$old_settings = $this->get_settings();
		$old_pixels   = isset( $old_settings['pixels'] ) && is_array( $old_settings['pixels'] ) ? $old_settings['pixels'] : array();

		$sanitized['mode'] = isset( $settings['mode'] ) && in_array( $settings['mode'], array( 'alvobot', 'manual' ), true )
			? $settings['mode'] : 'alvobot';

		// Parse pixels: use pixels_json (from pixels tab POST) or fall back to existing pixels array.
		if ( isset( $settings['pixels_json'] ) ) {
			$decoded_pixels = null;
			if ( is_string( $settings['pixels_json'] ) ) {
				$decoded_pixels = json_decode( wp_unslash( $settings['pixels_json'] ), true );
			}
			if ( is_array( $decoded_pixels ) ) {
				$pixels = $decoded_pixels;
			} else {
				// Preserve previously saved pixels when payload is malformed to prevent silent wipes.
				$pixels = isset( $settings['pixels'] ) && is_array( $settings['pixels'] ) ? $settings['pixels'] : $old_pixels;
				AlvoBotPro::debug_log( 'pixel-tracking', 'sanitize_settings: pixels_json inválido; mantendo pixels existentes para evitar perda de dados.' );
			}
		} else {
			$pixels = isset( $settings['pixels'] ) && is_array( $settings['pixels'] ) ? $settings['pixels'] : $old_pixels;
		}

		// Detect removed pixels and handle pending events
		$old_ids      = array_column( $old_pixels, 'pixel_id' );
		$new_ids      = array_column( $pixels, 'pixel_id' );
		$removed_ids  = array_diff( $old_ids, $new_ids );

		foreach ( $removed_ids as $removed_id ) {
			$this->handle_removed_pixel( $removed_id );
		}

		// Validate each pixel
		$sanitized_pixels = array();
		foreach ( $pixels as $pixel ) {
			$pixel_id = isset( $pixel['pixel_id'] ) ? sanitize_text_field( $pixel['pixel_id'] ) : '';
			if ( ! preg_match( '/^\d{15,16}$/', $pixel_id ) ) {
				continue;
			}
			$sanitized_pixels[] = array(
				'pixel_id'      => $pixel_id,
				'api_token'     => isset( $pixel['api_token'] ) ? sanitize_text_field( $pixel['api_token'] ) : '',
				'source'        => isset( $pixel['source'] ) && in_array( $pixel['source'], array( 'alvobot', 'manual' ), true ) ? $pixel['source'] : 'manual',
				'connection_id' => isset( $pixel['connection_id'] ) ? sanitize_text_field( $pixel['connection_id'] ) : '',
				'label'         => isset( $pixel['label'] ) ? sanitize_text_field( $pixel['label'] ) : '',
				'token_expired' => ! empty( $pixel['token_expired'] ),
			);
		}
		$sanitized['pixels'] = $sanitized_pixels;

		$sanitized['test_mode']       = ! empty( $settings['test_mode'] );
		$sanitized['test_event_code'] = isset( $settings['test_event_code'] ) ? sanitize_text_field( $settings['test_event_code'] ) : '';
		$sanitized['realtime_dispatch'] = ! isset( $settings['realtime_dispatch'] ) || ! empty( $settings['realtime_dispatch'] );

		$sanitized['excluded_roles'] = array();
		if ( isset( $settings['excluded_roles'] ) && is_array( $settings['excluded_roles'] ) ) {
			$sanitized['excluded_roles'] = array_map( 'sanitize_text_field', $settings['excluded_roles'] );
		}

		$sanitized['retention_days'] = isset( $settings['retention_days'] ) ? max( 1, min( 365, absint( $settings['retention_days'] ) ) ) : 7;
		$sanitized['max_events']     = isset( $settings['max_events'] ) ? max( 1000, min( 500000, absint( $settings['max_events'] ) ) ) : 50000;
		$sanitized['max_leads']      = isset( $settings['max_leads'] ) ? max( 1000, min( 100000, absint( $settings['max_leads'] ) ) ) : 10000;

		// Google Trackers (array of objects, migrated from flat fields).
		$google_trackers = array();

		if ( isset( $settings['google_trackers_json'] ) ) {
			$decoded = null;
			if ( is_string( $settings['google_trackers_json'] ) ) {
				$decoded = json_decode( wp_unslash( $settings['google_trackers_json'] ), true );
			}
			if ( is_array( $decoded ) ) {
				$google_trackers = $decoded;
			} elseif ( isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ) {
				$google_trackers = $settings['google_trackers'];
			}
		} elseif ( isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ) {
			$google_trackers = $settings['google_trackers'];
		} else {
			// Migration: convert flat fields to array on first save after upgrade.
			$old_ga  = isset( $old_settings['google_analytics_id'] ) ? $old_settings['google_analytics_id'] : '';
			$old_ads = isset( $old_settings['google_ads_id'] ) ? $old_settings['google_ads_id'] : '';
			if ( $old_ga ) {
				$google_trackers[] = array(
					'tracker_id'       => $old_ga,
					'type'             => 'ga4',
					'label'            => 'Google Analytics',
					'conversion_label' => '',
				);
			}
			if ( $old_ads ) {
				$google_trackers[] = array(
					'tracker_id'       => $old_ads,
					'type'             => 'google_ads',
					'label'            => 'Google Ads',
					'conversion_label' => isset( $old_settings['google_ads_conversion_label'] ) ? $old_settings['google_ads_conversion_label'] : '',
					'tag_id'           => $old_ads,
					'customer_id'      => '',
				);
			}
		}

		// Validate each tracker.
		$sanitized_trackers = array();
		$has_external       = false;
		foreach ( $google_trackers as $tracker ) {
			$tracker_id = isset( $tracker['tracker_id'] ) ? sanitize_text_field( $tracker['tracker_id'] ) : '';
			$type       = '';

			// External tracker (Site Kit / GTM — no real ID, uses existing gtag on page).
			if ( 'sitekit_gtag' === $tracker_id && ( ! isset( $tracker['type'] ) || 'external' === $tracker['type'] ) ) {
				if ( ! $has_external ) { // Only allow one external entry.
					$sanitized_trackers[] = array(
						'tracker_id'       => 'sitekit_gtag',
						'type'             => 'external',
						'label'            => isset( $tracker['label'] ) ? sanitize_text_field( $tracker['label'] ) : 'Google Tag existente',
						'conversion_label' => '',
					);
					$has_external = true;
				}
				continue;
			}

				if ( preg_match( '/^G-[A-Z0-9]{7,12}$/', $tracker_id ) ) {
					$type = 'ga4';
				} elseif ( preg_match( '/^AW-\d{6,15}$/', $tracker_id ) ) {
					$type = 'google_ads';
				} else {
					AlvoBotPro::debug_log( 'pixel-tracking', 'sanitize_settings: tracker_id descartado por formato invalido: ' . $tracker_id );
					continue; // Invalid tracker ID, skip.
				}

				$conv_label = isset( $tracker['conversion_label'] ) ? sanitize_text_field( $tracker['conversion_label'] ) : '';
				if ( $conv_label && ! preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $conv_label ) ) {
					$conv_label = '';
				}
				$tag_id = isset( $tracker['tag_id'] ) ? sanitize_text_field( $tracker['tag_id'] ) : '';
				if ( $tag_id && preg_match( '/^\d{6,15}$/', $tag_id ) ) {
					$tag_id = 'AW-' . $tag_id;
				}
				if ( $tag_id && ! preg_match( '/^AW-\d{6,15}$/', $tag_id ) ) {
					$tag_id = '';
				}
				if ( 'google_ads' === $type && empty( $tag_id ) && empty( $tracker['connection_id'] ) ) {
					$tag_id = $tracker_id;
				}

				$customer_id = isset( $tracker['customer_id'] ) ? preg_replace( '/\D+/', '', (string) $tracker['customer_id'] ) : '';

				$sanitized_trackers[] = array(
					'tracker_id'       => $tracker_id,
					'type'             => $type,
					'label'            => isset( $tracker['label'] ) ? sanitize_text_field( $tracker['label'] ) : '',
					'conversion_label' => $conv_label,
					'connection_id'    => isset( $tracker['connection_id'] ) ? sanitize_text_field( $tracker['connection_id'] ) : '',
					'tag_id'           => $tag_id,
					'customer_id'      => $customer_id,
				);
			}
		$sanitized['google_trackers'] = $sanitized_trackers;

		// Derive flat fields for backward compatibility.
		$sanitized['google_analytics_id']         = '';
		$sanitized['google_ads_id']               = '';
		$sanitized['google_ads_conversion_label']  = '';
			foreach ( $sanitized_trackers as $t ) {
			if ( 'ga4' === $t['type'] && '' === $sanitized['google_analytics_id'] ) {
				$sanitized['google_analytics_id'] = $t['tracker_id'];
			}
				if ( 'google_ads' === $t['type'] && '' === $sanitized['google_ads_id'] ) {
					$sanitized['google_ads_id']               = ! empty( $t['tag_id'] ) ? $t['tag_id'] : $t['tracker_id'];
					$sanitized['google_ads_conversion_label'] = $t['conversion_label'];
				}
		}

			$active_target_ids = array_merge(
				array_column( $sanitized_pixels, 'pixel_id' ),
				array_column( $sanitized_trackers, 'tracker_id' )
			);
			$this->sync_conversion_targets_to_active_trackers( $active_target_ids );

			return $sanitized;
		}

	protected function render_additional_content( $settings ) {
		// Content now rendered via tab view files.
	}

	/**
	 * Schedule cleanup without blocking the current request.
	 *
	 * @param int $delay_seconds Delay before the cleanup run.
	 * @return void
	 */
	public function schedule_cleanup_run( $delay_seconds = 15 ) {
		if ( $this->cleanup instanceof AlvoBotPro_PixelTracking_Cleanup ) {
			$this->cleanup->schedule_urgent_cleanup( $delay_seconds );
		}
	}
}
