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
			'/events/(?P<event_id>[a-f0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event' ),
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
			'/leads/(?P<lead_id>[a-f0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_lead' ),
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

		// Rate limiting: max 100 events/min per IP
		$ip         = $this->get_client_ip();
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

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Server-side IP/UA override (always more reliable than browser-reported).
		$data['ip']         = $ip;
		$data['user_agent'] = (string) $request->get_header( 'User-Agent' );

		// Server-side geo fallback when browser geo is missing or empty.
		$has_browser_geo = ! empty( $data['geo'] ) && is_array( $data['geo'] ) && ! empty( $data['geo']['city'] );
		if ( ! $has_browser_geo && $ip && '0.0.0.0' !== $ip ) {
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

		return new WP_REST_Response(
			array(
				'success'  => true,
				'event_id' => isset( $data['event_id'] ) ? $data['event_id'] : '',
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

		$ip         = $this->get_client_ip();
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

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Server-side IP/UA override (always more reliable than browser-reported).
		$data['ip']         = $ip;
		$data['user_agent'] = (string) $request->get_header( 'User-Agent' );

		// Server-side geo fallback when browser geo is missing or empty.
		$has_browser_geo = ! empty( $data['geo'] ) && is_array( $data['geo'] ) && ! empty( $data['geo']['city'] );
		if ( ! $has_browser_geo && $ip && '0.0.0.0' !== $ip ) {
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
				'success' => true,
				'lead_id' => $lead_id,
			),
			200
		);
	}

	/**
	 * Validate public tracking requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_REST_Response True when valid, otherwise error response.
	 */
	private function validate_public_tracking_request( $request ) {
		if ( ! $this->verify_same_origin( $request ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Forbidden origin',
				),
				403
			);
		}

		if ( ! $this->verify_tracking_nonce( $request ) ) {
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
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, 'alvobot_pixel_tracking' );
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
			$events[] = array(
				'id'         => $post->ID,
				'event_id'   => get_post_meta( $post->ID, '_event_id', true ),
				'event_name' => get_post_meta( $post->ID, '_event_name', true ),
				'status'     => $post->post_status,
				'page_url'   => get_post_meta( $post->ID, '_page_url', true ),
				'created_at' => $post->post_date,
				'sent_at'    => get_post_meta( $post->ID, '_fb_sent_at', true ),
				'error'      => get_post_meta( $post->ID, '_fb_error_message', true ),
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
	 */
	public function get_event( $request ) {
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

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'id'               => $post->ID,
					'event_id'         => get_post_meta( $post->ID, '_event_id', true ),
					'event_name'       => get_post_meta( $post->ID, '_event_name', true ),
					'status'           => $post->post_status,
					'page_url'         => get_post_meta( $post->ID, '_page_url', true ),
					'page_title'       => get_post_meta( $post->ID, '_page_title', true ),
					'created_at'       => $post->post_date,
					'lead_id'          => get_post_meta( $post->ID, '_lead_id', true ),
					'pixel_ids'        => get_post_meta( $post->ID, '_pixel_ids', true ),
					'sent_at'          => get_post_meta( $post->ID, '_fb_sent_at', true ),
					'error'            => get_post_meta( $post->ID, '_fb_error_message', true ),
					'request_payload'  => get_post_meta( $post->ID, '_fb_request_payload', true ),
					'response_payload' => get_post_meta( $post->ID, '_fb_response_payload', true ),
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
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $site_host ) {
			return false;
		}

		$origin = $request->get_header( 'Origin' );
		if ( $origin ) {
			$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
			return $origin_host === $site_host;
		}

		$referer = $request->get_header( 'Referer' );
		if ( $referer ) {
			$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
			return $referer_host === $site_host;
		}

		return false;
	}

	/**
	 * Get the client IP address with conservative proxy trust.
	 */
	private function get_client_ip() {
		$remote_addr = (string) filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$remote_addr = $this->sanitize_ip_value( $remote_addr );
		$remote_ip   = $this->first_valid_ip_from_list( $remote_addr );

		// Only trust forwarded headers when request came from local/private proxy.
		if ( $remote_ip && $this->is_private_or_loopback_ip( $remote_ip ) ) {
			$proxy_headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' );
			foreach ( $proxy_headers as $header ) {
				$raw_header = filter_input( INPUT_SERVER, $header, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( empty( $raw_header ) ) {
					continue;
				}

				$header_value = $this->sanitize_ip_value( (string) $raw_header );
				$forwarded_ip = $this->first_valid_ip_from_list( $header_value );
				if ( $forwarded_ip ) {
					return $forwarded_ip;
				}
			}
		}

		return $remote_ip ? $remote_ip : '0.0.0.0';
	}

	/**
	 * Extract first valid IP from a CSV list.
	 *
	 * @param string $value Raw header value.
	 * @return string
	 */
	private function first_valid_ip_from_list( $value ) {
		if ( '' === $value ) {
			return '';
		}

		$parts = array_map( 'trim', explode( ',', $value ) );
		foreach ( $parts as $part ) {
			if ( false !== filter_var( $part, FILTER_VALIDATE_IP ) ) {
				return $part;
			}
		}

		return '';
	}

	/**
	 * Check whether IP is private or loopback.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_private_or_loopback_ip( $ip ) {
		$private = false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		return ! $private;
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
		if ( ! $ip || '0.0.0.0' === $ip ) {
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
