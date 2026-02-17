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

		// AJAX handler for pixel fetch (AlvoBot mode)
		add_action( 'wp_ajax_alvobot_pixel_tracking_fetch_pixels', array( $this, 'ajax_fetch_pixels' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_refresh_token', array( $this, 'ajax_refresh_token' ) );

		// AJAX handlers for conversion CRUD
		add_action( 'wp_ajax_alvobot_pixel_tracking_save_conversion', array( $this, 'ajax_save_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_delete_conversion', array( $this, 'ajax_delete_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_toggle_conversion', array( $this, 'ajax_toggle_conversion' ) );
		add_action( 'wp_ajax_alvobot_pixel_tracking_get_conversions', array( $this, 'ajax_get_conversions' ) );

		// Admin notice for expired tokens.
		add_action( 'admin_notices', array( $this, 'admin_notice_expired_tokens' ) );

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
		$data          = array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'event_type'        => isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : 'PageView',
			'event_custom_name' => isset( $_POST['event_custom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_custom_name'] ) ) : '',
			'trigger_type'      => isset( $_POST['trigger_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_type'] ) ) : 'page_load',
			'trigger_value'     => isset( $_POST['trigger_value'] ) ? absint( $_POST['trigger_value'] ) : 0,
			'display_on'        => isset( $_POST['display_on'] ) ? sanitize_text_field( wp_unslash( $_POST['display_on'] ) ) : 'all',
			'page_ids'          => isset( $_POST['page_ids'] ) ? array_map( 'absint', (array) $_POST['page_ids'] ) : array(),
			'page_paths'        => isset( $_POST['page_paths'] ) ? sanitize_text_field( wp_unslash( $_POST['page_paths'] ) ) : '',
			'css_selector'      => isset( $_POST['css_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['css_selector'] ) ) : '',
			'content_name'      => isset( $_POST['content_name'] ) ? sanitize_text_field( wp_unslash( $_POST['content_name'] ) ) : '',
			'pixel_ids'         => isset( $_POST['pixel_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['pixel_ids'] ) ) : '',
		);

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

	/**
	 * Override base: use tab-based admin page template.
	 */
	protected function render_settings_template() {
		// Auto-sync expired tokens from AlvoBot (throttled, once per hour).
		$this->maybe_sync_tokens();

		$settings = $this->get_settings();
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
		} elseif ( 'settings' === $active_tab ) {
			// Settings tab fields — checkboxes absent = unchecked.
			$merged['test_mode']       = isset( $posted['test_mode'] ) ? $posted['test_mode'] : '';
			$merged['test_event_code'] = isset( $posted['test_event_code'] ) ? $posted['test_event_code'] : '';
			$merged['realtime_dispatch'] = isset( $posted['realtime_dispatch'] ) ? $posted['realtime_dispatch'] : '';
			$merged['consent_check']   = isset( $posted['consent_check'] ) ? $posted['consent_check'] : '';
			$merged['consent_cookie']  = isset( $posted['consent_cookie'] ) ? $posted['consent_cookie'] : '';
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
					'debug_enabled' => class_exists( 'AlvoBotPro' ) && method_exists( 'AlvoBotPro', 'is_debug_enabled' ) ? (bool) AlvoBotPro::is_debug_enabled( 'pixel-tracking' ) : false,
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

		$sanitized['consent_check']  = ! isset( $settings['consent_check'] ) || ! empty( $settings['consent_check'] );
		$sanitized['consent_cookie'] = isset( $settings['consent_cookie'] ) ? sanitize_text_field( $settings['consent_cookie'] ) : 'alvobot_tracking_consent';

		$sanitized['excluded_roles'] = array();
		if ( isset( $settings['excluded_roles'] ) && is_array( $settings['excluded_roles'] ) ) {
			$sanitized['excluded_roles'] = array_map( 'sanitize_text_field', $settings['excluded_roles'] );
		}

		$sanitized['retention_days'] = isset( $settings['retention_days'] ) ? max( 1, min( 365, absint( $settings['retention_days'] ) ) ) : 7;
		$sanitized['max_events']     = isset( $settings['max_events'] ) ? max( 1000, min( 500000, absint( $settings['max_events'] ) ) ) : 50000;
		$sanitized['max_leads']      = isset( $settings['max_leads'] ) ? max( 1000, min( 100000, absint( $settings['max_leads'] ) ) ) : 10000;

		return $sanitized;
	}

	protected function render_additional_content( $settings ) {
		// Content now rendered via tab view files.
	}
}
