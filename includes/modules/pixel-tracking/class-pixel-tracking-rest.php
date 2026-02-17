<?php
/**
 * Pixel Tracking Module - REST API Endpoints
 *
 * Public endpoints for browser tracking + authenticated endpoints for app.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for pixel-tracking.
 */
class AlvoBotPro_PixelTracking_REST {

	private $namespace = 'alvobot-pro/v1/pixel-tracking';
	private $module;

	public function __construct( $module ) {
		$this->module = $module;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'bypass_cookie_nonce_error_for_public_tracking' ), 999 );
	}

	public function register_routes() {
		// Public: Track event
		register_rest_route(
			$this->namespace,
			'/events/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_event' ),
				'permission_callback' => '__return_true',
			)
		);

		// Public: Track lead
		register_rest_route(
			$this->namespace,
			'/leads/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_lead' ),
				'permission_callback' => '__return_true',
			)
		);

		// Authenticated: Settings
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Authenticated: Pixels
		register_rest_route(
			$this->namespace,
			'/settings/pixels',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_pixels' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_pixel' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/pixels/(?P<pixel_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'remove_pixel' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Authenticated: Conversions
		register_rest_route(
			$this->namespace,
			'/conversions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_conversions' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_conversion' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/conversions/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_conversion' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_conversion' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_conversion' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Authenticated: Events
		register_rest_route(
			$this->namespace,
			'/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_events' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event_stats' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_events_action' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>(?!(stats|bulk)$)[^/]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_event' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_event' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Authenticated: Leads
		register_rest_route(
			$this->namespace,
			'/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_leads' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_lead_stats' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<lead_id>(?!stats$)[^/]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_lead' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Authenticated: Status
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Authenticated: Actions
		register_rest_route(
			$this->namespace,
			'/actions/send-pending',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'action_send_pending' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/actions/cleanup',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'action_cleanup' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/actions/refresh-pixels',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'action_refresh_pixels' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/actions/test-pixel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'action_test_pixel' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/actions/resend-event/(?P<event_id>[^/]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'action_resend_event' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

	}

	public function check_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Public: Track an event from browser JS.
	 */
	public function track_event( $request ) {
		$validation = $this->validate_public_tracking_request( $request );
		if ( true !== $validation ) {
			return $validation;
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$browser_ip = $this->extract_browser_ip_from_payload( $data );
		$ip         = $this->get_client_ip( $browser_ip );

		// Rate limiting: max 100 events/min per IP
		$rate_key   = 'alvobot_pt_rate_' . md5( $ip );
		$rate_count = (int) get_transient( $rate_key );
		if ( $rate_count >= 100 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Rate limited',
				),
				429
			);
		}
		set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

		// Server-side IP/UA override (always more reliable than browser-reported).
		$data['ip']         = $ip;
		$data['browser_ip'] = $browser_ip;
		$data['user_agent'] = (string) $request->get_header( 'User-Agent' );

		// Server-side geo fallback when browser geo is missing or empty.
		$has_browser_geo = ! empty( $data['geo'] ) && is_array( $data['geo'] ) && ! empty( $data['geo']['city'] );
		if ( ! $has_browser_geo && $ip && '0.0.0.0' !== $ip && ! $this->is_private_or_loopback_ip( $ip ) ) {
			$server_geo = $this->lookup_geo_by_ip( $ip );
			if ( $server_geo ) {
				$data['geo'] = $server_geo;
			}
		}

		$post_id = $this->module->cpt->create_event( $data );

		if ( is_wp_error( $post_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $post_id->get_error_message(),
				),
				500
			);
		}

		$settings          = $this->module->get_settings();
		$realtime_dispatch = ! isset( $settings['realtime_dispatch'] ) || ! empty( $settings['realtime_dispatch'] );
		if ( $realtime_dispatch && isset( $this->module->capi ) ) {
			$this->module->capi->schedule_immediate_dispatch( 'track_event', (int) $post_id );
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'event_id'     => isset( $data['event_id'] ) ? $data['event_id'] : '',
				'resolved_ip'  => $ip,
				'resolved_geo' => isset( $data['geo'] ) && is_array( $data['geo'] ) ? $data['geo'] : array(),
			),
			200
		);
	}

	/**
	 * Public: Track a lead from browser JS.
	 */
	public function track_lead( $request ) {
		$validation = $this->validate_public_tracking_request( $request );
		if ( true !== $validation ) {
			return $validation;
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$browser_ip = $this->extract_browser_ip_from_payload( $data );
		$ip         = $this->get_client_ip( $browser_ip );
		$rate_key   = 'alvobot_pt_rate_' . md5( $ip );
		$rate_count = (int) get_transient( $rate_key );
		if ( $rate_count >= 100 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Rate limited',
				),
				429
			);
		}
		set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

		// Server-side IP/UA override (always more reliable than browser-reported).
		$data['ip']         = $ip;
		$data['browser_ip'] = $browser_ip;
		$data['user_agent'] = (string) $request->get_header( 'User-Agent' );

		// Server-side geo fallback when browser geo is missing or empty.
		$has_browser_geo = ! empty( $data['geo'] ) && is_array( $data['geo'] ) && ! empty( $data['geo']['city'] );
		if ( ! $has_browser_geo && $ip && '0.0.0.0' !== $ip && ! $this->is_private_or_loopback_ip( $ip ) ) {
			$server_geo = $this->lookup_geo_by_ip( $ip );
			if ( $server_geo ) {
				$data['geo'] = $server_geo;
			}
		}

		$post_id = $this->module->cpt->create_lead( $data );

		if ( is_wp_error( $post_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $post_id->get_error_message(),
				),
				500
			);
		}

		$lead_id = get_post_meta( $post_id, '_lead_id', true );
		return new WP_REST_Response(
			array(
				'success'      => true,
				'lead_id'      => $lead_id,
				'resolved_ip'  => $ip,
				'resolved_geo' => isset( $data['geo'] ) && is_array( $data['geo'] ) ? $data['geo'] : array(),
			),
			200
		);
	}

	/**
	 * Extract browser-side IP candidate from payload.
	 *
	 * @param array $data Tracking payload.
	 * @return string
	 */
	private function extract_browser_ip_from_payload( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		$candidates = array();
		if ( isset( $data['browser_ip'] ) ) {
			$candidates[] = (string) $data['browser_ip'];
		}
		if ( isset( $data['ip'] ) ) {
			$candidates[] = (string) $data['ip'];
		}

		foreach ( $candidates as $candidate ) {
			$ip = $this->first_valid_ip_from_list( $this->sanitize_ip_value( $candidate ), true );
			if ( $ip ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Validate public tracking requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_REST_Response True when valid, otherwise error response.
	 */
	private function validate_public_tracking_request( $request ) {
		$same_origin = $this->verify_same_origin( $request );
		$nonce_valid = $this->verify_tracking_nonce( $request );

		// Fallback: when Origin/Referer headers are unavailable, a valid nonce is enough.
		if ( ! $same_origin && $nonce_valid ) {
			return true;
		}

		if ( ! $same_origin ) {
			AlvoBotPro::debug_log(
				'pixel-tracking',
				'Public tracking blocked: forbidden origin. Origin=' . (string) $request->get_header( 'Origin' ) . ' Referer=' . (string) $request->get_header( 'Referer' ) . ' Sec-Fetch-Site=' . (string) $request->get_header( 'Sec-Fetch-Site' )
			);
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Forbidden origin',
				),
				403
			);
		}

		if ( ! $nonce_valid && is_user_logged_in() ) {
			AlvoBotPro::debug_log( 'pixel-tracking', 'Public tracking blocked: invalid nonce for logged-in user.' );
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid nonce',
				),
				403
			);
		}

		return true;
	}

	/**
	 * Verify nonce for public tracking endpoints.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	private function verify_tracking_nonce( $request ) {
		$nonce = $request->get_header( 'X-Alvobot-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_alvobot_nonce' );
		}
		// Backward compatibility with older frontend bundles.
		if ( empty( $nonce ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, 'alvobot_pixel_tracking' );
	}

	/**
	 * Ignore cookie-auth nonce errors for public tracking endpoints.
	 *
	 * WordPress core interprets X-WP-Nonce as wp_rest cookie nonce and can
	 * reject requests before this controller runs. Our tracking endpoints are
	 * intentionally public and perform their own validation.
	 *
	 * @param mixed $result Current authentication result.
	 * @return mixed
	 */
	public function bypass_cookie_nonce_error_for_public_tracking( $result ) {
		if ( ! is_wp_error( $result ) || 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
			return $result;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$rest_route  = isset( $_GET['rest_route'] ) ? (string) sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';

		$is_public_pixel_track = false !== strpos( $request_uri, '/wp-json/alvobot-pro/v1/pixel-tracking/events/track' ) ||
			false !== strpos( $request_uri, '/wp-json/alvobot-pro/v1/pixel-tracking/leads/track' ) ||
			false !== strpos( $rest_route, '/alvobot-pro/v1/pixel-tracking/events/track' ) ||
			false !== strpos( $rest_route, '/alvobot-pro/v1/pixel-tracking/leads/track' );

		if ( $is_public_pixel_track ) {
			return true;
		}

		return $result;
	}

	/**
	 * Authenticated: Get module settings.
	 */
	public function get_settings() {
		$settings = $this->module->get_settings();
		// Remove sensitive tokens from response
		if ( isset( $settings['pixels'] ) ) {
			foreach ( $settings['pixels'] as &$pixel ) {
				if ( isset( $pixel['api_token'] ) ) {
					$pixel['api_token'] = '***' . substr( $pixel['api_token'], -4 );
				}
			}
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $settings,
			)
		);
	}

	/**
	 * Authenticated: Update module settings.
	 */
	public function update_settings( $request ) {
		$params  = $request->get_json_params();
		$current = $this->module->get_settings();
		$merged  = array_merge( $current, $params );

		// If pixels come as an array from REST, convert to pixels_json for sanitize_settings.
		if ( isset( $merged['pixels'] ) && ! isset( $merged['pixels_json'] ) ) {
			$merged['pixels_json'] = wp_json_encode( $merged['pixels'] );
		}

		$this->module->save_settings( $merged );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: Get configured pixels.
	 */
	public function get_pixels() {
		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
		foreach ( $pixels as &$pixel ) {
			if ( isset( $pixel['api_token'] ) ) {
				$pixel['api_token'] = '***' . substr( $pixel['api_token'], -4 );
			}
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $pixels,
			)
		);
	}

	/**
	 * Authenticated: Add a pixel.
	 */
	public function add_pixel( $request ) {
		$params   = $request->get_json_params();
		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		$pixel_id = isset( $params['pixel_id'] ) ? sanitize_text_field( $params['pixel_id'] ) : '';
		if ( ! preg_match( '/^\d{15,16}$/', $pixel_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid Pixel ID format',
				),
				400
			);
		}

		$pixels[] = array(
			'pixel_id'      => $pixel_id,
			'api_token'     => isset( $params['api_token'] ) ? sanitize_text_field( $params['api_token'] ) : '',
			'source'        => isset( $params['source'] ) ? sanitize_text_field( $params['source'] ) : 'manual',
			'connection_id' => isset( $params['connection_id'] ) ? sanitize_text_field( $params['connection_id'] ) : '',
			'label'         => isset( $params['label'] ) ? sanitize_text_field( $params['label'] ) : '',
		);

		$settings['pixels'] = $pixels;
		$this->module->save_settings( $settings );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: Remove a pixel.
	 */
	public function remove_pixel( $request ) {
		$pixel_id = $request->get_param( 'pixel_id' );
		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		$settings['pixels'] = array_values(
			array_filter(
				$pixels,
				function ( $p ) use ( $pixel_id ) {
					return $p['pixel_id'] !== $pixel_id;
				}
			)
		);

		$this->module->save_settings( $settings );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: Get conversions.
	 */
	public function get_conversions() {
		$conversions = $this->module->cpt->get_conversions( true );
		$result      = array();
		foreach ( $conversions as $conv ) {
			$result[] = $this->format_conversion( $conv );
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Authenticated: Get single conversion.
	 */
	public function get_conversion( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$post = get_post( $id );
		if ( ! $post || 'alvo_pixel_conv' !== $post->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not found',
				),
				404
			);
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->format_conversion( $post ),
			)
		);
	}

	/**
	 * Authenticated: Create conversion.
	 */
	public function create_conversion( $request ) {
		$params = $request->get_json_params();
		$result = $this->module->cpt->create_conversion( $params );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				500
			);
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'id' => $result ),
			)
		);
	}

	/**
	 * Authenticated: Update conversion.
	 */
	public function update_conversion( $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		$result = $this->module->cpt->update_conversion( $id, $params );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				500
			);
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: Delete conversion.
	 */
	public function delete_conversion( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$post = get_post( $id );
		if ( ! $post || 'alvo_pixel_conv' !== $post->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not found',
				),
				404
			);
		}
		wp_delete_post( $id, true );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: List events.
	 */
	public function list_events( $request ) {
		$limit_param  = $request->get_param( 'limit' );
		$offset_param = $request->get_param( 'offset' );

		$args = array(
			'post_type'      => 'alvobot_pixel_event',
			'posts_per_page' => min( 100, absint( null !== $limit_param ? $limit_param : 50 ) ),
			'offset'         => absint( null !== $offset_param ? $offset_param : 0 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$status = $request->get_param( 'status' );
		if ( $status && in_array( $status, array( 'pixel_pending', 'pixel_sent', 'pixel_error' ), true ) ) {
			$args['post_status'] = $status;
		} else {
			$args['post_status'] = array( 'pixel_pending', 'pixel_sent', 'pixel_error' );
		}

		$event_name = $request->get_param( 'event_name' );
		if ( $event_name ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_event_name',
					'value' => sanitize_text_field( $event_name ),
				),
			);
		}

		$query  = new WP_Query( $args );
		$events = array();
		foreach ( $query->posts as $post ) {
			$server_ip          = get_post_meta( $post->ID, '_ip', true );
			$browser_ip         = get_post_meta( $post->ID, '_browser_ip', true );
			$display_ip         = $this->select_preferred_display_ip( $server_ip, $browser_ip );
			$target_pixel_ids   = get_post_meta( $post->ID, '_pixel_ids', true );
			$delivered_pixel_ids = get_post_meta( $post->ID, '_fb_pixel_ids', true );

			$events[] = array(
				'id'          => $post->ID,
				'event_id'    => get_post_meta( $post->ID, '_event_id', true ),
				'event_name'  => get_post_meta( $post->ID, '_event_name', true ),
				'status'      => $post->post_status,
				'page_url'    => get_post_meta( $post->ID, '_page_url', true ),
				'page_title'  => get_post_meta( $post->ID, '_page_title', true ),
				'ip'          => $display_ip,
				'browser_ip'  => $browser_ip,
				'fbp'         => get_post_meta( $post->ID, '_fbp', true ),
				'fbc'         => get_post_meta( $post->ID, '_fbc', true ),
					'geo_city'    => get_post_meta( $post->ID, '_geo_city', true ),
					'geo_country' => get_post_meta( $post->ID, '_geo_country_code', true ),
					'pixel_ids'   => $target_pixel_ids ? $target_pixel_ids : $delivered_pixel_ids,
					'fb_pixel_ids' => $delivered_pixel_ids,
					'dispatch_channel' => get_post_meta( $post->ID, '_dispatch_channel', true ),
					'retry_count' => (int) get_post_meta( $post->ID, '_fb_retry_count', true ),
					'created_at'  => $post->post_date,
					'sent_at'     => get_post_meta( $post->ID, '_fb_sent_at', true ),
					'error'       => get_post_meta( $post->ID, '_fb_error_message', true ),
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $events,
				'meta'    => array(
					'total'    => $query->found_posts,
					'page'     => floor( $args['offset'] / $args['posts_per_page'] ) + 1,
					'per_page' => $args['posts_per_page'],
				),
			)
		);
	}

	/**
	 * Authenticated: Get event detail by event_id.
	 *
	 * Returns ALL stored metadata for deep debugging. This is the
	 * endpoint used by the "View Details" modal in the Events tab.
	 */
	public function get_event( $request ) {
		$event_id = sanitize_text_field( (string) $request->get_param( 'event_id' ) );

		// Guard: WordPress REST may match the dynamic route for /events/stats.
		if ( 'stats' === $event_id ) {
			return $this->get_event_stats();
		}

		if ( '' === $event_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid event_id',
				),
				400
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_event',
				'post_status'    => array( 'pixel_pending', 'pixel_sent', 'pixel_error' ),
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_event_id',
						'value' => $event_id,
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not found',
				),
				404
			);
		}

		$post = $query->posts[0];
		$pid  = $post->ID;
		$server_ip  = get_post_meta( $pid, '_ip', true );
		$browser_ip = get_post_meta( $pid, '_browser_ip', true );
		$display_ip = $this->select_preferred_display_ip( $server_ip, $browser_ip );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'id'               => $pid,
					'event_id'         => get_post_meta( $pid, '_event_id', true ),
					'event_name'       => get_post_meta( $pid, '_event_name', true ),
					'event_time'       => get_post_meta( $pid, '_event_time', true ),
					'status'           => $post->post_status,
					'page_url'         => get_post_meta( $pid, '_page_url', true ),
					'page_title'       => get_post_meta( $pid, '_page_title', true ),
					'page_id'          => get_post_meta( $pid, '_page_id', true ),
					'referrer'         => get_post_meta( $pid, '_referrer', true ),
					'ip'               => $display_ip,
					'browser_ip'       => $browser_ip,
					'user_agent'       => get_post_meta( $pid, '_user_agent', true ),
					'fbp'              => get_post_meta( $pid, '_fbp', true ),
					'fbc'              => get_post_meta( $pid, '_fbc', true ),
					'lead_id'          => get_post_meta( $pid, '_lead_id', true ),
					'pixel_ids'        => get_post_meta( $pid, '_pixel_ids', true ),
					'geo_city'         => get_post_meta( $pid, '_geo_city', true ),
					'geo_state'        => get_post_meta( $pid, '_geo_state', true ),
					'geo_country'      => get_post_meta( $pid, '_geo_country', true ),
					'geo_country_code' => get_post_meta( $pid, '_geo_country_code', true ),
					'geo_zipcode'      => get_post_meta( $pid, '_geo_zipcode', true ),
					'geo_timezone'     => get_post_meta( $pid, '_geo_timezone', true ),
					'utm_source'       => get_post_meta( $pid, '_utm_source', true ),
					'utm_medium'       => get_post_meta( $pid, '_utm_medium', true ),
					'utm_campaign'     => get_post_meta( $pid, '_utm_campaign', true ),
					'utm_content'      => get_post_meta( $pid, '_utm_content', true ),
					'utm_term'         => get_post_meta( $pid, '_utm_term', true ),
					'custom_data'      => get_post_meta( $pid, '_custom_data', true ),
					'wp_em'            => get_post_meta( $pid, '_wp_em', true ) ? '(hashed)' : '',
					'wp_fn'            => get_post_meta( $pid, '_wp_fn', true ) ? '(hashed)' : '',
					'wp_ln'            => get_post_meta( $pid, '_wp_ln', true ) ? '(hashed)' : '',
						'wp_external_id'   => get_post_meta( $pid, '_wp_external_id', true ) ? '(hashed)' : '',
						'retry_count'      => (int) get_post_meta( $pid, '_fb_retry_count', true ),
						'fb_pixel_ids'     => get_post_meta( $pid, '_fb_pixel_ids', true ),
						'dispatch_channel' => get_post_meta( $pid, '_dispatch_channel', true ),
						'sent_at'          => get_post_meta( $pid, '_fb_sent_at', true ),
						'error'            => get_post_meta( $pid, '_fb_error_message', true ),
						'request_payload'  => get_post_meta( $pid, '_fb_request_payload', true ),
					'response_payload' => get_post_meta( $pid, '_fb_response_payload', true ),
					'created_at'       => $post->post_date,
				),
			)
		);
	}

	/**
	 * Authenticated: Event stats.
	 */
	public function get_event_stats() {
		global $wpdb;

		$stats = array();
		foreach ( array( 'pixel_pending', 'pixel_sent', 'pixel_error' ) as $status ) {
			$stats[ $status ] = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'alvobot_pixel_event' AND post_status = %s",
					$status
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $stats,
			)
		);
	}

	/**
	 * Authenticated: List leads.
	 */
	public function list_leads( $request ) {
		$limit_param  = $request->get_param( 'limit' );
		$offset_param = $request->get_param( 'offset' );

		$args = array(
			'post_type'      => 'alvobot_pixel_lead',
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, absint( null !== $limit_param ? $limit_param : 50 ) ),
			'offset'         => absint( null !== $offset_param ? $offset_param : 0 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$query = new WP_Query( $args );
		$leads = array();
		foreach ( $query->posts as $post ) {
			$leads[] = array(
				'id'         => $post->ID,
				'lead_id'    => get_post_meta( $post->ID, '_lead_id', true ),
				'email'      => get_post_meta( $post->ID, '_email', true ),
				'name'       => get_post_meta( $post->ID, '_name', true ),
				'phone'      => get_post_meta( $post->ID, '_phone', true ),
				'first_seen' => get_post_meta( $post->ID, '_first_seen', true ),
				'last_seen'  => get_post_meta( $post->ID, '_last_seen', true ),
				'created_at' => $post->post_date,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $leads,
				'meta'    => array(
					'total'    => $query->found_posts,
					'page'     => floor( $args['offset'] / $args['posts_per_page'] ) + 1,
					'per_page' => $args['posts_per_page'],
				),
			)
		);
	}

	/**
	 * Authenticated: Get lead detail by lead_id.
	 */
	public function get_lead( $request ) {
		$lead_id = sanitize_text_field( (string) $request->get_param( 'lead_id' ) );

		// Guard: WordPress REST may match the dynamic route for /leads/stats.
		if ( 'stats' === $lead_id ) {
			return $this->get_lead_stats();
		}

		if ( '' === $lead_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid lead_id',
				),
				400
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_lead',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_lead_id',
						'value' => $lead_id,
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not found',
				),
				404
			);
		}

		$post = $query->posts[0];

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'id'                 => $post->ID,
					'lead_id'            => get_post_meta( $post->ID, '_lead_id', true ),
					'email'              => get_post_meta( $post->ID, '_email', true ),
					'name'               => get_post_meta( $post->ID, '_name', true ),
					'phone'              => get_post_meta( $post->ID, '_phone', true ),
					'first_seen'         => get_post_meta( $post->ID, '_first_seen', true ),
					'last_seen'          => get_post_meta( $post->ID, '_last_seen', true ),
					'fbp'                => get_post_meta( $post->ID, '_fbp', true ),
					'fbc'                => get_post_meta( $post->ID, '_fbc', true ),
					'first_utm_source'   => get_post_meta( $post->ID, '_first_utm_source', true ),
					'first_utm_medium'   => get_post_meta( $post->ID, '_first_utm_medium', true ),
					'first_utm_campaign' => get_post_meta( $post->ID, '_first_utm_campaign', true ),
					'last_utm_source'    => get_post_meta( $post->ID, '_last_utm_source', true ),
					'last_utm_medium'    => get_post_meta( $post->ID, '_last_utm_medium', true ),
					'last_utm_campaign'  => get_post_meta( $post->ID, '_last_utm_campaign', true ),
					'created_at'         => $post->post_date,
				),
			)
		);
	}

	/**
	 * Authenticated: Lead stats.
	 */
	public function get_lead_stats() {
		global $wpdb;
		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'alvobot_pixel_lead',
				'publish'
			)
		);
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'total' => $total ),
			)
		);
	}

	/**
	 * Authenticated: Module status.
	 */
	public function get_status() {
		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		global $wpdb;
		$pending_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'alvobot_pixel_event',
				'pixel_pending'
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'active'            => true,
					'pixels_configured' => count( $pixels ),
					'pending_events'    => $pending_count,
					'test_mode'         => ! empty( $settings['test_mode'] ),
					'realtime_dispatch' => ! isset( $settings['realtime_dispatch'] ) || ! empty( $settings['realtime_dispatch'] ),
					'mode'              => isset( $settings['mode'] ) ? $settings['mode'] : 'alvobot',
				),
			)
		);
	}

	/**
	 * Authenticated: Force send pending events.
	 */
	public function action_send_pending() {
		$result = $this->module->capi->process_pending_events();
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Authenticated: Force cleanup.
	 */
	public function action_cleanup() {
		// Trigger cleanup scheduler hook directly.
		do_action( 'alvobot_pixel_cleanup' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated: Refresh pixels from AlvoBot.
	 */
	public function action_refresh_pixels() {
		delete_transient( 'alvobot_pixel_tracking_pixels' );
		$pixels = $this->module->fetch_alvobot_pixels( true );
		if ( is_wp_error( $pixels ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $pixels->get_error_message(),
				),
				500
			);
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $pixels,
			)
		);
	}

	/**
	 * Authenticated: Send test event.
	 */
	public function action_test_pixel() {
		$results = $this->module->capi->send_test_event();
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $results,
			)
		);
	}

	/**
	 * Authenticated: Bulk action for selected events.
	 *
	 * Supported actions:
	 * - resend: reset selected events to pending and trigger immediate dispatch
	 * - delete: delete selected events permanently
	 */
	public function bulk_events_action( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$action = isset( $params['action'] ) ? sanitize_key( (string) $params['action'] ) : '';
		if ( ! in_array( $action, array( 'resend', 'delete' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid bulk action',
				),
				400
			);
		}

		$raw_event_ids = isset( $params['event_ids'] ) && is_array( $params['event_ids'] ) ? $params['event_ids'] : array();
		$event_ids     = array();
		foreach ( $raw_event_ids as $raw_event_id ) {
			$event_id = sanitize_text_field( (string) $raw_event_id );
			if ( '' !== $event_id ) {
				$event_ids[ $event_id ] = true;
			}
		}
		$event_ids = array_keys( $event_ids );

		if ( empty( $event_ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'No event_ids provided',
				),
				400
			);
		}

		$processed = 0;
		$failed    = array();

		foreach ( $event_ids as $event_id ) {
			$post = $this->get_event_post_by_event_id( $event_id );
			if ( ! $post ) {
				$failed[] = array(
					'event_id' => $event_id,
					'error'    => 'Event not found',
				);
				continue;
			}

			if ( 'resend' === $action ) {
				$this->reset_event_for_resend( $post->ID );
				++$processed;
				continue;
			}

			$deleted = wp_delete_post( $post->ID, true );
			if ( $deleted ) {
				++$processed;
			} else {
				$failed[] = array(
					'event_id' => $event_id,
					'error'    => 'Failed to delete event',
				);
			}
		}

		if ( 'resend' === $action && $processed > 0 ) {
			$this->module->capi->schedule_immediate_dispatch();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'action'      => $action,
					'requested'   => count( $event_ids ),
					'processed'   => $processed,
					'failed'      => $failed,
					'failed_count' => count( $failed ),
				),
			)
		);
	}

	/**
	 * Authenticated: Resend a specific event to CAPI.
	 *
	 * Resets the event to pending status and clears delivery tracking,
	 * then triggers an immediate CAPI dispatch.
	 */
	public function action_resend_event( $request ) {
		$event_id = sanitize_text_field( (string) $request->get_param( 'event_id' ) );

		if ( '' === $event_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid event_id',
				),
				400
			);
		}

		$post = $this->get_event_post_by_event_id( $event_id );
		if ( ! $post ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Event not found',
				),
				404
			);
		}

		$this->reset_event_for_resend( $post->ID );

		// Trigger immediate dispatch.
		$this->module->capi->schedule_immediate_dispatch();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Event queued for resend.',
			)
		);
	}

	/**
	 * Authenticated: Delete a specific event.
	 */
	public function delete_event( $request ) {
		$event_id = sanitize_text_field( (string) $request->get_param( 'event_id' ) );

		// Guard: WordPress REST may match the dynamic route for /events/stats.
		if ( 'stats' === $event_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Cannot delete stats endpoint',
				),
				400
			);
		}

		if ( '' === $event_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid event_id',
				),
				400
			);
		}

		$post = $this->get_event_post_by_event_id( $event_id );
		if ( ! $post ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Event not found',
				),
				404
			);
		}

		wp_delete_post( $post->ID, true );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Find an event post by public event_id.
	 *
	 * @param string $event_id Event UUID.
	 * @return WP_Post|null
	 */
	private function get_event_post_by_event_id( $event_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_event',
				'post_status'    => array( 'pixel_pending', 'pixel_sent', 'pixel_error' ),
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_event_id',
						'value' => $event_id,
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return $query->posts[0];
	}

	/**
	 * Reset an event to pending for CAPI resend.
	 *
	 * @param int $post_id Event post ID.
	 */
	private function reset_event_for_resend( $post_id ) {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pixel_pending',
			)
		);
		delete_post_meta( $post_id, '_fb_pixel_ids' );
		delete_post_meta( $post_id, '_fb_sent_at' );
		delete_post_meta( $post_id, '_fb_error_message' );
		update_post_meta( $post_id, '_fb_retry_count', 0 );
	}

	/**
	 * Format a conversion post for API response.
	 */
	private function format_conversion( $post ) {
		return array(
			'id'                => $post->ID,
			'name'              => $post->post_title,
			'status'            => $post->post_status,
			'event_type'        => get_post_meta( $post->ID, '_event_type', true ),
			'event_custom_name' => get_post_meta( $post->ID, '_event_custom_name', true ),
			'trigger_type'      => get_post_meta( $post->ID, '_trigger_type', true ),
			'trigger_value'     => get_post_meta( $post->ID, '_trigger_value', true ),
			'display_on'        => get_post_meta( $post->ID, '_display_on', true ),
			'page_ids'          => get_post_meta( $post->ID, '_page_ids', true ),
			'page_paths'        => get_post_meta( $post->ID, '_page_paths', true ),
			'css_selector'      => get_post_meta( $post->ID, '_css_selector', true ),
			'content_name'      => get_post_meta( $post->ID, '_content_name', true ),
			'pixel_ids'         => get_post_meta( $post->ID, '_pixel_ids', true ),
		);
	}

	/**
	 * Verify request originates from the same WordPress site.
	 * Checks Origin header first, then Referer as fallback.
	 */
	private function verify_same_origin( $request ) {
		$allowed_hosts = $this->get_allowed_hosts();
		if ( empty( $allowed_hosts ) ) {
			return false;
		}

		$origin = $request->get_header( 'Origin' );
		if ( $origin ) {
			$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
			if ( $this->host_matches_allowed( $origin_host, $allowed_hosts ) ) {
				return true;
			}
		}

		$referer = $request->get_header( 'Referer' );
		if ( $referer ) {
			$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
			if ( $this->host_matches_allowed( $referer_host, $allowed_hosts ) ) {
				return true;
			}
		}

		$sec_fetch_site = strtolower( (string) $request->get_header( 'Sec-Fetch-Site' ) );
		if ( in_array( $sec_fetch_site, array( 'same-origin', 'same-site' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build allowed host aliases for same-origin validation.
	 *
	 * @return string[]
	 */
	private function get_allowed_hosts() {
		$candidates = array(
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( site_url(), PHP_URL_HOST ),
			isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',
			isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '',
		);

		$hosts = array();
		foreach ( $candidates as $candidate ) {
			$normalized = $this->normalize_host( $candidate );
			if ( $normalized ) {
				$hosts[] = $normalized;
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Normalize host for safe comparison.
	 *
	 * @param string $host Raw host.
	 * @return string
	 */
	private function normalize_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		$host = strtolower( trim( $host ) );
		$host = preg_replace( '/:\d+$/', '', $host );
		$host = rtrim( $host, '.' );

		if ( strpos( $host, 'www.' ) === 0 ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Check if host matches any allowed host alias.
	 *
	 * @param string   $host          Host to test.
	 * @param string[] $allowed_hosts Normalized allowed hosts.
	 * @return bool
	 */
	private function host_matches_allowed( $host, $allowed_hosts ) {
		$normalized = $this->normalize_host( $host );
		if ( '' === $normalized ) {
			return false;
		}

		return in_array( $normalized, $allowed_hosts, true );
	}

	/**
	 * Choose the most useful IP for admin display.
	 *
	 * Prioritizes public IPs and falls back to any available value only
	 * when no public candidate exists.
	 *
	 * @param string $server_ip  IP resolved server-side.
	 * @param string $browser_ip IP reported by browser capture.
	 * @return string
	 */
	private function select_preferred_display_ip( $server_ip, $browser_ip ) {
		$server_candidate  = $this->first_valid_ip_from_list( $this->sanitize_ip_value( (string) $server_ip ), true );
		$browser_candidate = $this->first_valid_ip_from_list( $this->sanitize_ip_value( (string) $browser_ip ), true );

		if ( $server_candidate && ! $this->is_private_or_loopback_ip( $server_candidate ) ) {
			return $server_candidate;
		}

		if ( $browser_candidate && ! $this->is_private_or_loopback_ip( $browser_candidate ) ) {
			return $browser_candidate;
		}

		return '';
	}

	/**
	 * Get the client IP address with conservative proxy trust.
	 */
	private function get_client_ip( $browser_ip = '' ) {
		// Use $_SERVER directly — filter_input(INPUT_SERVER, ...) is unreliable in PHP-FPM.
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$remote_addr = $this->sanitize_ip_value( $remote_addr );
		$remote_ip   = $this->first_valid_ip_from_list( $remote_addr, true );

		// Direct public client IP.
		if ( $remote_ip && ! $this->is_private_or_loopback_ip( $remote_ip ) ) {
			return $remote_ip;
		}

		// Only trust forwarded headers when request came from local/private proxy.
		$proxy_headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' );
		foreach ( $proxy_headers as $header ) {
			$raw_header = isset( $_SERVER[ $header ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) : '';
			if ( empty( $raw_header ) ) {
				continue;
			}

			$header_value = $this->sanitize_ip_value( (string) $raw_header );
			$forwarded_ip = $this->first_valid_ip_from_list( $header_value, true );
			if ( $forwarded_ip ) {
				return $forwarded_ip;
			}
		}

		// Browser-provided fallback is only used when server-side signals are private/loopback.
		$browser_candidate = $this->first_valid_ip_from_list( $this->sanitize_ip_value( (string) $browser_ip ), true );
		if ( $browser_candidate && ( ! $remote_ip || $this->is_private_or_loopback_ip( $remote_ip ) ) ) {
			return $browser_candidate;
		}

		return $remote_ip ? $remote_ip : '0.0.0.0';
	}

	/**
	 * Extract first valid IP from a CSV list.
	 *
	 * @param string $value         Raw header value.
	 * @param bool   $prefer_public Prefer public IPs when available.
	 * @return string
	 */
	private function first_valid_ip_from_list( $value, $prefer_public = false ) {
		if ( '' === $value ) {
			return '';
		}

		$parts = array_map( 'trim', explode( ',', $value ) );
		$fallback_ip = '';
		foreach ( $parts as $part ) {
			$candidate = $this->normalize_ip_candidate( $part );
			if ( false === filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			if ( ! $prefer_public || ! $this->is_private_or_loopback_ip( $candidate ) ) {
				return $candidate;
			}

			if ( '' === $fallback_ip ) {
				$fallback_ip = $candidate;
			}
		}

		return $fallback_ip;
	}

	/**
	 * Normalize potentially formatted IP candidates.
	 *
	 * @param string $candidate Raw candidate string.
	 * @return string
	 */
	private function normalize_ip_candidate( $candidate ) {
		$candidate = trim( (string) $candidate );
		$candidate = trim( $candidate, "\"'" );

		if ( 0 === stripos( $candidate, 'for=' ) ) {
			$candidate = substr( $candidate, 4 );
			$candidate = trim( $candidate, "\"'" );
		}

		// [IPv6]:port.
		if ( preg_match( '/^\[([0-9a-fA-F:]+)\](?::\d+)?$/', $candidate, $matches ) ) {
			return $matches[1];
		}

		// IPv4:port.
		if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $candidate, $matches ) ) {
			return $matches[1];
		}

		return $candidate;
	}

	/**
	 * Check whether IP is private or loopback.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_private_or_loopback_ip( $ip ) {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Sanitize incoming IP header values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_ip_value( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Server-side geo lookup by IP (fallback when browser geo fails).
	 *
	 * Uses ip-api.com Pro as primary with ipwho.is fallback. Results are
	 * cached via WordPress transients for 24 hours per IP.
	 *
	 * @param string $ip Client IP address.
	 * @return array|false Geo data array or false on failure.
	 */
	private function lookup_geo_by_ip( $ip ) {
		if ( ! $ip || '0.0.0.0' === $ip || $this->is_private_or_loopback_ip( $ip ) ) {
			return false;
		}

		// Check transient cache (24h TTL).
		$cache_key = 'alvobot_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$geo = false;

		// Primary: ip-api.com Pro (HTTPS, higher rate limit).
		$response = wp_remote_get(
			'https://pro.ip-api.com/json/' . rawurlencode( $ip ) . '?key=TOLoWxdNIA0zIZm&fields=status,city,regionName,country,countryCode,zip,currency,timezone',
			array( 'timeout' => 5 )
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['city'] ) && ( ! isset( $body['status'] ) || 'success' === $body['status'] ) ) {
				$geo = array(
					'city'         => $body['city'],
					'state'        => isset( $body['regionName'] ) ? $body['regionName'] : '',
					'country'      => isset( $body['country'] ) ? $body['country'] : '',
					'country_code' => isset( $body['countryCode'] ) ? $body['countryCode'] : '',
					'zipcode'      => isset( $body['zip'] ) ? $body['zip'] : '',
					'currency'     => isset( $body['currency'] ) ? $body['currency'] : '',
					'timezone'     => isset( $body['timezone'] ) ? $body['timezone'] : '',
				);
			}
		}

		// Fallback: ipwho.is (HTTPS, free, no key required).
		if ( ! $geo ) {
			$response = wp_remote_get(
				'https://ipwho.is/' . rawurlencode( $ip ),
				array( 'timeout' => 5 )
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $body['success'] ) && ! empty( $body['city'] ) ) {
					$geo = array(
						'city'         => $body['city'],
						'state'        => isset( $body['region'] ) ? $body['region'] : '',
						'country'      => isset( $body['country'] ) ? $body['country'] : '',
						'country_code' => isset( $body['country_code'] ) ? $body['country_code'] : '',
						'zipcode'      => isset( $body['postal'] ) ? $body['postal'] : '',
						'currency'     => isset( $body['currency']['code'] ) ? $body['currency']['code'] : '',
						'timezone'     => isset( $body['timezone']['id'] ) ? $body['timezone']['id'] : '',
					);
				}
			}
		}

		// Cache result for 24 hours.
		if ( $geo ) {
			set_transient( $cache_key, $geo, DAY_IN_SECONDS );
		}

		return $geo;
	}
}
