<?php
/**
 * Pixel Tracking Module - Custom Post Types
 *
 * Registers CPTs for events, leads, and conversion rules.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

/**
 * Data access layer for pixel-tracking CPT entities.
 */
class AlvoBotPro_PixelTracking_CPT {

	public function __construct() {
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'init', array( $this, 'register_post_statuses' ) );
	}

		/**
		 * Register all 3 Custom Post Types.
		 */
	public function register_post_types() {
			register_post_type(
				'alvobot_pixel_event',
				array(
					'labels'          => array( 'name' => 'Pixel Events' ),
					'public'          => false,
					'show_ui'         => false,
					'show_in_rest'    => false,
					'supports'        => array( 'title', 'custom-fields' ),
					'capability_type' => 'post',
				)
			);

			register_post_type(
				'alvobot_pixel_lead',
				array(
					'labels'          => array( 'name' => 'Pixel Leads' ),
					'public'          => false,
					'show_ui'         => true,
					'show_in_rest'    => false,
					'supports'        => array( 'title', 'custom-fields' ),
					'capability_type' => 'post',
				)
			);

			register_post_type(
				'alvo_pixel_conv',
				array(
					'labels'          => array( 'name' => 'Pixel Conversions' ),
					'public'          => false,
					'show_ui'         => false,
					'show_in_rest'    => false,
					'supports'        => array( 'title', 'custom-fields' ),
					'capability_type' => 'post',
				)
			);
	}

		/**
		 * Register custom post statuses for event lifecycle.
		 */
	public function register_post_statuses() {
			register_post_status(
				'pixel_pending',
				array(
					'label'    => __( 'Pending', 'alvobot-pro' ),
					'public'   => false,
					'internal' => true,
				)
			);

			register_post_status(
				'pixel_sent',
				array(
					'label'    => __( 'Sent', 'alvobot-pro' ),
					'public'   => false,
					'internal' => true,
				)
			);

			register_post_status(
				'pixel_error',
				array(
					'label'    => __( 'Error', 'alvobot-pro' ),
					'public'   => false,
					'internal' => true,
				)
			);
	}

		/**
		 * Create a new event CPT.
		 */
	public function create_event( $data ) {
			$event_name = isset( $data['event_name'] ) ? sanitize_text_field( $data['event_name'] ) : 'PageView';
			$event_id   = isset( $data['event_id'] ) ? sanitize_text_field( $data['event_id'] ) : wp_generate_uuid4();

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'alvobot_pixel_event',
					'post_title'  => $event_name . ' - ' . substr( $event_id, 0, 8 ),
					'post_status' => 'pixel_pending',
				)
			);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
				return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'insert_failed', 'wp_insert_post returned 0' );
		}

			$meta_fields = array(
				'_event_id'        => $event_id,
				'_event_name'      => $event_name,
				'_event_time'      => time(),
				'_event_url'       => isset( $data['event_url'] ) ? esc_url_raw( $data['event_url'] ) : '',
				'_page_url'        => isset( $data['page_url'] ) ? esc_url_raw( $data['page_url'] ) : '',
				'_page_title'      => isset( $data['page_title'] ) ? sanitize_text_field( $data['page_title'] ) : '',
				'_page_id'         => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0,
				'_referrer'        => isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : '',
				'_request_referer' => isset( $data['request_referer'] ) ? esc_url_raw( $data['request_referer'] ) : '',
				'_lead_id'         => isset( $data['lead_id'] ) ? sanitize_text_field( $data['lead_id'] ) : '',
				'_fbp'            => isset( $data['fbp'] ) ? sanitize_text_field( $data['fbp'] ) : '',
				'_fbc'            => isset( $data['fbc'] ) ? sanitize_text_field( $data['fbc'] ) : '',
				'_ip'             => isset( $data['ip'] ) ? sanitize_text_field( $data['ip'] ) : '',
				'_browser_ip'     => isset( $data['browser_ip'] ) ? sanitize_text_field( $data['browser_ip'] ) : '',
				'_user_agent'     => isset( $data['user_agent'] ) ? sanitize_text_field( $data['user_agent'] ) : '',
				'_pixel_ids'      => isset( $data['pixel_ids'] ) ? sanitize_text_field( $data['pixel_ids'] ) : '',
				'_custom_data'    => isset( $data['custom_data'] ) && is_array( $data['custom_data'] ) ? array_map( 'sanitize_text_field', $data['custom_data'] ) : array(),
				'_fb_retry_count' => 0,
			);

			// Store pre-hashed WP user data for CAPI matching (em, fn, ln, external_id)
			if ( isset( $data['user_data_hashed'] ) && is_array( $data['user_data_hashed'] ) ) {
				$allowed_hash_keys = array( 'em', 'fn', 'ln', 'external_id' );
				foreach ( $allowed_hash_keys as $hash_key ) {
					if ( ! empty( $data['user_data_hashed'][ $hash_key ] ) ) {
						$meta_fields[ '_wp_' . $hash_key ] = sanitize_text_field( $data['user_data_hashed'][ $hash_key ] );
					}
				}
			}

			if ( isset( $data['geo'] ) && is_array( $data['geo'] ) ) {
					$geo                              = $data['geo'];
					$meta_fields['_geo_city']         = isset( $geo['city'] ) ? sanitize_text_field( $geo['city'] ) : '';
					$meta_fields['_geo_state']        = isset( $geo['state'] ) ? sanitize_text_field( $geo['state'] ) : '';
					$meta_fields['_geo_country']      = isset( $geo['country'] ) ? sanitize_text_field( $geo['country'] ) : '';
					$meta_fields['_geo_country_code'] = isset( $geo['country_code'] ) ? sanitize_text_field( $geo['country_code'] ) : '';
					$meta_fields['_geo_zipcode']      = isset( $geo['zipcode'] ) ? sanitize_text_field( $geo['zipcode'] ) : '';
					$meta_fields['_geo_currency']     = isset( $geo['currency'] ) ? sanitize_text_field( $geo['currency'] ) : '';
					$meta_fields['_geo_timezone']     = isset( $geo['timezone'] ) ? sanitize_text_field( $geo['timezone'] ) : '';
			}

			if ( isset( $data['utms'] ) && is_array( $data['utms'] ) ) {
				foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content' ) as $key ) {
						$meta_fields[ '_' . $key ] = isset( $data['utms'][ $key ] ) ? sanitize_text_field( $data['utms'][ $key ] ) : '';
				}
			}

			foreach ( $meta_fields as $key => $value ) {
					update_post_meta( $post_id, $key, $value );
			}

			return $post_id;
	}

		/**
		 * Create or update a lead CPT.
		 */
	public function create_lead( $data ) {
			$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
			$fbp   = isset( $data['fbp'] ) ? sanitize_text_field( $data['fbp'] ) : '';

			$existing_id = null;
		if ( $email ) {
				$existing_id = $this->find_lead_by_email( $email );
		}
		if ( ! $existing_id && $fbp ) {
				$existing_id = $this->find_lead_by_fbp( $fbp );
		}

			$now     = gmdate( 'c' );
			$lead_id = isset( $data['lead_id'] ) ? sanitize_text_field( $data['lead_id'] ) : wp_generate_uuid4();

		if ( $existing_id ) {
				$this->update_lead_meta( $existing_id, $data, $now );
				return $existing_id;
		}

			$title   = $email ? $email : 'Lead - ' . substr( $lead_id, 0, 8 );
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'alvobot_pixel_lead',
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
				return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'insert_failed', 'wp_insert_post returned 0' );
		}

			update_post_meta( $post_id, '_lead_id', $lead_id );
			update_post_meta( $post_id, '_first_seen', $now );
			update_post_meta( $post_id, '_last_seen', $now );

			$this->set_lead_identity( $post_id, $data );
			$this->set_lead_tracking( $post_id, $data );
			$this->set_lead_geo( $post_id, $data );

		if ( isset( $data['utms'] ) && is_array( $data['utms'] ) ) {
			foreach ( $data['utms'] as $key => $value ) {
					update_post_meta( $post_id, '_first_' . sanitize_key( $key ), sanitize_text_field( $value ) );
					update_post_meta( $post_id, '_last_' . sanitize_key( $key ), sanitize_text_field( $value ) );
			}
		}

		if ( isset( $data['src'] ) ) {
				update_post_meta( $post_id, '_src', sanitize_text_field( $data['src'] ) );
		}
		if ( isset( $data['sck'] ) ) {
				update_post_meta( $post_id, '_sck', sanitize_text_field( $data['sck'] ) );
		}

			return $post_id;
	}

	private function update_lead_meta( $post_id, $data, $now ) {
			update_post_meta( $post_id, '_last_seen', $now );

		if ( ! empty( $data['email'] ) ) {
				$curr_email = get_post_meta( $post_id, '_email', true );
			if ( empty( $curr_email ) ) {
				update_post_meta( $post_id, '_email', sanitize_email( $data['email'] ) );
				update_post_meta( $post_id, '_email_hash', self::hash_pii( $data['email'] ) );
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => sanitize_email( $data['email'] ),
					)
				);
			}
		}

		if ( ! empty( $data['phone'] ) && empty( get_post_meta( $post_id, '_phone', true ) ) ) {
				update_post_meta( $post_id, '_phone', sanitize_text_field( $data['phone'] ) );
				update_post_meta( $post_id, '_phone_hash', self::hash_phone( $data['phone'] ) );
		}

		if ( ! empty( $data['first_name'] ) && empty( get_post_meta( $post_id, '_first_name', true ) ) ) {
				update_post_meta( $post_id, '_first_name', sanitize_text_field( $data['first_name'] ) );
				update_post_meta( $post_id, '_fn_hash', self::hash_pii( $data['first_name'] ) );
		}

		if ( ! empty( $data['last_name'] ) && empty( get_post_meta( $post_id, '_last_name', true ) ) ) {
				update_post_meta( $post_id, '_last_name', sanitize_text_field( $data['last_name'] ) );
				update_post_meta( $post_id, '_ln_hash', self::hash_pii( $data['last_name'] ) );
		}

			$this->set_lead_tracking( $post_id, $data );
			$this->set_lead_geo( $post_id, $data );

		if ( isset( $data['utms'] ) && is_array( $data['utms'] ) ) {
			foreach ( $data['utms'] as $key => $value ) {
					update_post_meta( $post_id, '_last_' . sanitize_key( $key ), sanitize_text_field( $value ) );
			}
		}
	}

	private function set_lead_identity( $post_id, $data ) {
		if ( ! empty( $data['email'] ) ) {
				update_post_meta( $post_id, '_email', sanitize_email( $data['email'] ) );
				update_post_meta( $post_id, '_email_hash', self::hash_pii( $data['email'] ) );
		}
		if ( ! empty( $data['phone'] ) ) {
				update_post_meta( $post_id, '_phone', sanitize_text_field( $data['phone'] ) );
				update_post_meta( $post_id, '_phone_hash', self::hash_phone( $data['phone'] ) );
		}
		if ( ! empty( $data['first_name'] ) ) {
				update_post_meta( $post_id, '_first_name', sanitize_text_field( $data['first_name'] ) );
				update_post_meta( $post_id, '_fn_hash', self::hash_pii( $data['first_name'] ) );
		}
		if ( ! empty( $data['last_name'] ) ) {
				update_post_meta( $post_id, '_last_name', sanitize_text_field( $data['last_name'] ) );
				update_post_meta( $post_id, '_ln_hash', self::hash_pii( $data['last_name'] ) );
		}
		if ( ! empty( $data['name'] ) && empty( $data['first_name'] ) ) {
				update_post_meta( $post_id, '_name', sanitize_text_field( $data['name'] ) );
				update_post_meta( $post_id, '_name_hash', self::hash_pii( $data['name'] ) );
		}
	}

	private function set_lead_tracking( $post_id, $data ) {
		if ( isset( $data['fbp'] ) ) {
				update_post_meta( $post_id, '_fbp', sanitize_text_field( $data['fbp'] ) );
		}
		if ( isset( $data['fbc'] ) ) {
				update_post_meta( $post_id, '_fbc', sanitize_text_field( $data['fbc'] ) );
		}
		if ( isset( $data['ip'] ) ) {
				update_post_meta( $post_id, '_ip', sanitize_text_field( $data['ip'] ) );
		}
		if ( isset( $data['browser_ip'] ) ) {
				update_post_meta( $post_id, '_browser_ip', sanitize_text_field( $data['browser_ip'] ) );
		}
		if ( isset( $data['user_agent'] ) ) {
				update_post_meta( $post_id, '_user_agent', sanitize_text_field( $data['user_agent'] ) );
		}
	}

	private function set_lead_geo( $post_id, $data ) {
		if ( ! isset( $data['geo'] ) || ! is_array( $data['geo'] ) ) {
				return;
		}
			$geo        = $data['geo'];
			$geo_fields = array(
				'city'         => '_geo_city',
				'state'        => '_geo_state',
				'country'      => '_geo_country',
				'country_code' => '_geo_country_code',
				'zipcode'      => '_geo_zipcode',
				'currency'     => '_geo_currency',
				'timezone'     => '_geo_timezone',
			);
			foreach ( $geo_fields as $key => $meta_key ) {
				if ( isset( $geo[ $key ] ) ) {
						$val = sanitize_text_field( $geo[ $key ] );
						update_post_meta( $post_id, $meta_key, $val );

						// Map to CAPI hashed fields
						$capi_map = array(
							'city'         => '_ct_hash',
							'state'        => '_st_hash',
							'country_code' => '_country_hash',
							'zipcode'      => '_zp_hash',
						);
						if ( isset( $capi_map[ $key ] ) ) {
								update_post_meta( $post_id, $capi_map[ $key ], self::hash_pii( $val ) );
						}
				}
			}
	}

	public function find_lead_by_email( $email ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_lead',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_email',
						'value' => $email,
						),
				),
				'fields'         => 'ids',
			)
		);
			return ! empty( $query->posts ) ? $query->posts[0] : null;
	}

	public function find_lead_by_fbp( $fbp ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'alvobot_pixel_lead',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_fbp',
						'value' => $fbp,
						),
				),
				'fields'         => 'ids',
			)
		);
			return ! empty( $query->posts ) ? $query->posts[0] : null;
	}

	public function create_conversion( $data ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'alvo_pixel_conv',
					'post_title'  => sanitize_text_field( $data['name'] ),
					'post_status' => 'publish',
				)
			);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
				return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'insert_failed', 'wp_insert_post returned 0' );
		}
			$this->save_conversion_meta( $post_id, $data );
			return $post_id;
	}

	public function update_conversion( $post_id, $data ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $data['name'] ),
				)
			);
			$this->save_conversion_meta( $post_id, $data );
			return $post_id;
	}

	private function save_conversion_meta( $post_id, $data ) {
			$meta_map = array(
				'event_type'        => '_event_type',
				'event_custom_name' => '_event_custom_name',
				'trigger_type'      => '_trigger_type',
				'trigger_value'     => '_trigger_value',
				'display_on'        => '_display_on',
				'page_ids'          => '_page_ids',
				'page_paths'        => '_page_paths',
				'css_selector'      => '_css_selector',
				'content_name'      => '_content_name',
				'pixel_ids'         => '_pixel_ids',
			);
			foreach ( $meta_map as $data_key => $meta_key ) {
				if ( isset( $data[ $data_key ] ) ) {
						$value = $data[ $data_key ];
					if ( is_array( $value ) ) {
						$value = array_map( 'sanitize_text_field', $value );
					} else {
							$value = sanitize_text_field( $value );
					}
						update_post_meta( $post_id, $meta_key, $value );
				}
			}
	}

	public function get_conversions( $include_inactive = false ) {
			$status = $include_inactive ? array( 'publish', 'draft' ) : 'publish';
			$query  = new WP_Query(
				array(
					'post_type'      => 'alvo_pixel_conv',
					'post_status'    => $status,
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			return $query->posts;
	}

	public function get_pending_events( $limit = 250, $min_age_seconds = 300 ) {
			$date_query = array(
				array(
					'after' => '7 days ago',
				),
			);

		$min_age_seconds = absint( $min_age_seconds );
		if ( $min_age_seconds > 0 ) {
				$date_query[0]['before'] = $min_age_seconds . ' seconds ago';
		}

			$query = new WP_Query(
				array(
					'post_type'      => 'alvobot_pixel_event',
					'post_status'    => 'pixel_pending',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'date_query'     => $date_query,
				)
			);
			return $query->posts;
	}

	public function mark_events_sent( $post_ids ) {
		foreach ( $post_ids as $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'pixel_sent',
					)
				);
				update_post_meta( $post_id, '_fb_sent_at', time() );
		}
	}

	public function mark_events_error( $post_ids, $error ) {
		foreach ( $post_ids as $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'pixel_error',
					)
				);
				update_post_meta( $post_id, '_fb_error_message', sanitize_text_field( $error ) );
		}
	}

	public static function hash_pii( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
			$normalized = strtolower( trim( $value ) );
			$normalized = preg_replace( '/\s+/', '', $normalized );
			return hash( 'sha256', $normalized );
	}

	public static function hash_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}
			$normalized = preg_replace( '/[^0-9]/', '', $phone );
		if ( strlen( $normalized ) <= 11 && strlen( $normalized ) >= 10 ) {
				$normalized = '55' . $normalized;
		}
			return hash( 'sha256', $normalized );
	}
}
