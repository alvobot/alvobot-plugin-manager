<?php
/**
 * Pixel Tracking Module - Conversion API Sender
 *
 * Dispatches pending events to Facebook Conversion API via Action Scheduler.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

/**
 * Server-side sender for pending events.
 */
class AlvoBotPro_PixelTracking_CAPI {

		private $module;
		private $max_retries = 3;

	public function __construct( $module ) {
			$this->module = $module;
			$this->schedule_recurring();
			add_action( 'alvobot_pixel_send_events', array( $this, 'process_pending_events' ) );
	}

		/**
		 * Schedule the recurring event dispatch action.
		 */
	private function schedule_recurring() {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			// Use Action Scheduler
			if ( ! as_has_scheduled_action( 'alvobot_pixel_send_events' ) ) {
				// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Product requirement: dispatch every 5 minutes.
				as_schedule_recurring_action( time() + 300, 300, 'alvobot_pixel_send_events' );
			}
		} else {
				// Fallback to WP Cron
			if ( ! wp_next_scheduled( 'alvobot_pixel_send_events' ) ) {
					wp_schedule_event( time() + 300, 'five_minutes', 'alvobot_pixel_send_events' );
			}
			// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Product requirement: dispatch every 5 minutes.
			add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
				AlvoBotPro::debug_log( 'pixel-tracking', 'Action Scheduler not available, using WP Cron fallback' );
		}
	}

		/**
		 * Add custom cron interval for WP Cron fallback.
		 */
	public function add_cron_interval( $schedules ) {
		if ( ! isset( $schedules['five_minutes'] ) ) {
				$schedules['five_minutes'] = array(
					'interval' => 300,
					'display'  => __( 'Every 5 Minutes', 'alvobot-pro' ),
				);
		}
			return $schedules;
	}

		/**
		 * Process pending events and send to Facebook Conversion API.
		 *
		 * @return array Results summary.
		 */
	public function process_pending_events() {
			$settings = $this->module->get_settings();
			$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		if ( empty( $pixels ) ) {
				return array(
					'events_sent'   => 0,
					'events_failed' => 0,
				);
		}

			$events = $this->module->cpt->get_pending_events( 250 );

		if ( empty( $events ) ) {
				return array(
					'events_sent'   => 0,
					'events_failed' => 0,
				);
		}

			AlvoBotPro::debug_log( 'pixel-tracking', 'Processing ' . count( $events ) . ' pending events' );

			// Filter out pixels without API token or with expired tokens.
			$active_pixels = array();
		foreach ( $pixels as $pixel_config ) {
			if ( empty( $pixel_config['pixel_id'] ) ) {
						continue;
			}
			if ( empty( $pixel_config['api_token'] ) ) {
					AlvoBotPro::debug_log( 'pixel-tracking', "Skipping pixel {$pixel_config['pixel_id']}: no API token" );
					continue;
			}
			if ( ! empty( $pixel_config['token_expired'] ) ) {
					AlvoBotPro::debug_log( 'pixel-tracking', "Skipping pixel {$pixel_config['pixel_id']}: token expired" );
					continue;
			}
				$active_pixels[] = $pixel_config;
		}

		if ( empty( $active_pixels ) ) {
				AlvoBotPro::debug_log( 'pixel-tracking', 'No active pixels with valid tokens, skipping CAPI dispatch' );
				return array(
					'events_sent'   => 0,
					'events_failed' => 0,
				);
		}

			$sent   = 0;
			$failed = 0;

			// Send to each pixel using split-batch logic on failure.
		foreach ( $active_pixels as $pixel_config ) {
				$res     = $this->process_pixel_batch( $events, $pixel_config, $settings );
				$sent   += $res['sent'];
				$failed += $res['failed'];
		}

			return array(
				'events_sent'   => $sent,
				'events_failed' => $failed,
			);
	}

		/**
		 * Process batch for a specific pixel with split logic.
		 */
	private function process_pixel_batch( $events, $pixel_config, $settings ) {
			$pixel_id  = $pixel_config['pixel_id'];
			$api_token = $pixel_config['api_token'];
			$sent      = 0;
			$failed    = 0;

		$payload_batch = $this->build_batch( $events );
			$result    = $this->send_batch( $pixel_id, $api_token, $payload_batch, $settings );

		if ( $result['success'] ) {
				$ids = wp_list_pluck( $events, 'ID' );
				$this->module->cpt->mark_events_sent( $ids );
			foreach ( $ids as $post_id ) {
				$existing_pixels = get_post_meta( $post_id, '_fb_pixel_ids', true );
				$pixel_list      = $existing_pixels ? explode( ',', $existing_pixels ) : array();
				if ( ! in_array( $pixel_id, $pixel_list, true ) ) {
						$pixel_list[] = $pixel_id;
				}
				update_post_meta( $post_id, '_fb_pixel_ids', implode( ',', $pixel_list ) );
			}
				$sent = count( $events );
		} else {
				// Handle auth errors (401/403) — mark pixel as expired and stop.
			if ( isset( $result['code'] ) && in_array( $result['code'], array( 401, 403 ), true ) ) {
					$this->mark_token_expired( $pixel_id );
					return array(
						'sent'   => 0,
						'failed' => count( $events ),
					);
			}

				// Split batch if possible (FR-025).
			if ( count( $events ) > 1 ) {
					AlvoBotPro::debug_log( 'pixel-tracking', "Batch failure for {$pixel_id}, splitting " . count( $events ) . ' events into smaller groups...' );
					$chunks = array_chunk( $events, ceil( count( $events ) / 2 ) );
				foreach ( $chunks as $chunk ) {
						$res     = $this->process_pixel_batch( $chunk, $pixel_config, $settings );
						$sent   += $res['sent'];
						$failed += $res['failed'];
				}
			} else {
					// Single event failed.
					$this->handle_batch_failure( $events, $result['error'] );
					$failed = count( $events );
			}
		}

			return array(
				'sent'   => $sent,
				'failed' => $failed,
			);
	}

		/**
		 * Build CAPI payload batch from event posts.
		 */
	private function build_batch( $events ) {
			$batch = array();
		foreach ( $events as $event ) {
				$batch[] = $this->build_event_payload( $event );
		}
			return $batch;
	}

		/**
		 * Build a single event payload for Conversion API.
		 *
		 * Maximizes user_data and custom_data per Meta CAPI spec to improve
		 * Event Match Quality (EMQ). Data sources in priority order:
		 *   1. Lead PII (form submissions — most specific)
		 *   2. WP logged-in user data (pre-hashed by Frontend class)
		 *   3. Geo data from browser geolocation
		 *   4. fbp hash as external_id fallback (anonymous visitors)
		 *
		 * @see https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters
		 * @see https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/custom-data
		 */
	private function build_event_payload( $event ) {
			$post_id    = $event->ID;
			$event_name = get_post_meta( $post_id, '_event_name', true );
			$event_id   = get_post_meta( $post_id, '_event_id', true );
			$event_time = get_post_meta( $post_id, '_event_time', true );

			// --- user_data: not-hashed fields ---
			$user_data = array(
				'client_ip_address' => get_post_meta( $post_id, '_ip', true ),
				'client_user_agent' => get_post_meta( $post_id, '_user_agent', true ),
				'fbp'               => get_post_meta( $post_id, '_fbp', true ),
				'fbc'               => get_post_meta( $post_id, '_fbc', true ),
			);

			// --- user_data: hashed geo fields (Meta requires normalization before SHA256) ---
			$country_code = get_post_meta( $post_id, '_geo_country_code', true );
			$city         = get_post_meta( $post_id, '_geo_city', true );
			$state        = get_post_meta( $post_id, '_geo_state', true );
			$zipcode      = get_post_meta( $post_id, '_geo_zipcode', true );

			if ( $country_code ) {
					// Meta: lowercase 2-letter ISO 3166-1 alpha-2.
					$user_data['country'] = array( AlvoBotPro_PixelTracking_CPT::hash_pii( strtolower( $country_code ) ) );
			}
			if ( $city ) {
					// Meta: lowercase, no punctuation/spaces. hash_pii already strips whitespace.
					$normalized_city = preg_replace( '/[^a-z\p{L}]/u', '', strtolower( $city ) );
					$user_data['ct'] = array( hash( 'sha256', $normalized_city ) );
			}
			if ( $state ) {
					// Meta: lowercase 2-char ANSI abbreviation (US) or lowercase text.
					$user_data['st'] = array( AlvoBotPro_PixelTracking_CPT::hash_pii( strtolower( $state ) ) );
			}
			if ( $zipcode ) {
					// Meta: lowercase, no spaces or dashes.
					$normalized_zip  = strtolower( str_replace( array( ' ', '-' ), '', $zipcode ) );
					$user_data['zp'] = array( hash( 'sha256', $normalized_zip ) );
			}

			// --- user_data: Lead PII (highest priority — form submission data) ---
			$lead_id = get_post_meta( $post_id, '_lead_id', true );
			if ( $lead_id ) {
					$lead_query = new WP_Query(
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
							'fields'         => 'ids',
						)
					);

				if ( ! empty( $lead_query->posts ) ) {
					$lead_post_id = $lead_query->posts[0];
					$email_hash   = get_post_meta( $lead_post_id, '_email_hash', true );
					$phone_hash   = get_post_meta( $lead_post_id, '_phone_hash', true );
					$name_hash    = get_post_meta( $lead_post_id, '_name_hash', true );
					$ln_hash      = get_post_meta( $lead_post_id, '_ln_hash', true );

					if ( $email_hash ) {
							$user_data['em'] = array( $email_hash );
					}
					if ( $phone_hash ) {
							$user_data['ph'] = array( $phone_hash );
					}
					if ( $name_hash ) {
							$user_data['fn'] = array( $name_hash );
					}
					if ( $ln_hash ) {
							$user_data['ln'] = array( $ln_hash );
					}
					$user_data['external_id'] = array( AlvoBotPro_PixelTracking_CPT::hash_pii( $lead_id ) );
				}
			}

			// --- user_data: WP logged-in user (fallback where lead didn't provide) ---
			$wp_em          = get_post_meta( $post_id, '_wp_em', true );
			$wp_fn          = get_post_meta( $post_id, '_wp_fn', true );
			$wp_ln          = get_post_meta( $post_id, '_wp_ln', true );
			$wp_external_id = get_post_meta( $post_id, '_wp_external_id', true );

			if ( empty( $user_data['em'] ) && $wp_em ) {
					$user_data['em'] = array( $wp_em );
			}
			if ( empty( $user_data['fn'] ) && $wp_fn ) {
					$user_data['fn'] = array( $wp_fn );
			}
			if ( empty( $user_data['ln'] ) && $wp_ln ) {
					$user_data['ln'] = array( $wp_ln );
			}
			if ( empty( $user_data['external_id'] ) && $wp_external_id ) {
					$user_data['external_id'] = array( $wp_external_id );
			}

			// --- user_data: external_id fallback for anonymous visitors ---
			if ( empty( $user_data['external_id'] ) && ! empty( $user_data['fbp'] ) ) {
					$user_data['external_id'] = array( AlvoBotPro_PixelTracking_CPT::hash_pii( $user_data['fbp'] ) );
			}

			// Remove empty values.
			$user_data = array_filter( $user_data );

			// --- custom_data: content enrichment ---
			$custom_data = get_post_meta( $post_id, '_custom_data', true );
			if ( ! is_array( $custom_data ) ) {
					$custom_data = array();
			}

			// Currency from geo (fallback if not set by browser).
			if ( empty( $custom_data['currency'] ) ) {
					$geo_currency = get_post_meta( $post_id, '_geo_currency', true );
					if ( $geo_currency ) {
							$custom_data['currency'] = strtoupper( $geo_currency );
					}
			}

			// Traffic source (referrer).
			$referrer = get_post_meta( $post_id, '_referrer', true );
			if ( $referrer ) {
					$custom_data['traffic_source'] = $referrer;
			}

			// UTM parameters — helps Meta attribute conversions to campaigns.
			$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
			foreach ( $utm_keys as $utm_key ) {
					$utm_val = get_post_meta( $post_id, '_' . $utm_key, true );
					if ( $utm_val ) {
							$custom_data[ $utm_key ] = $utm_val;
					}
			}

			// Event timing enrichment — helps Meta optimize delivery by day/hour.
			$event_ts = (int) $event_time;
			if ( $event_ts > 0 ) {
					$custom_data['event_day']           = gmdate( 'l', $event_ts );
					$custom_data['event_day_in_month']  = (int) gmdate( 'j', $event_ts );
					$custom_data['event_month']         = gmdate( 'F', $event_ts );
					$hour                               = (int) gmdate( 'G', $event_ts );
					$custom_data['event_time_interval'] = $hour . '-' . ( $hour + 1 );
			}

			$payload = array(
				'event_name'       => $event_name,
				'event_time'       => $event_ts,
				'event_id'         => $event_id,
				'event_source_url' => get_post_meta( $post_id, '_page_url', true ),
				'action_source'    => 'website',
				'user_data'        => $user_data,
			);

			if ( ! empty( $custom_data ) ) {
					$payload['custom_data'] = $custom_data;
			}

			return $payload;
	}

		/**
		 * Send a batch of events to Facebook Conversion API.
		 */
	private function send_batch( $pixel_id, $api_token, $batch, $settings ) {
			$request_body = array(
				'data'         => $batch,
				'access_token' => $api_token,
			);

			// Test mode
			if ( ! empty( $settings['test_mode'] ) && ! empty( $settings['test_event_code'] ) ) {
					$request_body['test_event_code'] = $settings['test_event_code'];
			}

			$url = "https://graph.facebook.com/v24.0/{$pixel_id}/events";

			$response = wp_remote_post(
				$url,
				array(
					'body'    => wp_json_encode( $request_body ),
					'headers' => array( 'Content-Type' => 'application/json' ),
					'timeout' => 30,
				)
			);

		if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'error'   => $response->get_error_message(),
				);
		}

			$code         = wp_remote_retrieve_response_code( $response );
			$raw_body     = wp_remote_retrieve_body( $response );
			$decoded_body = json_decode( $raw_body, true );

		if ( 429 === $code ) {
				return array(
					'success' => false,
					'error'   => 'rate_limited',
					'code'    => 429,
				);
		}

		if ( $code >= 400 ) {
				$error = isset( $decoded_body['error']['message'] ) ? $decoded_body['error']['message'] : "HTTP {$code}";
				return array(
					'success' => false,
					'error'   => $error,
					'code'    => $code,
				);
		}

		if ( ! isset( $decoded_body['events_received'] ) ) {
				return array(
					'success' => false,
					'error'   => 'No events_received in response',
				);
		}

			return array(
				'success'  => true,
				'response' => $decoded_body,
			);
	}

		/**
		 * Handle a batch failure with retry logic.
		 */
	private function handle_batch_failure( $events, $error ) {
		foreach ( $events as $event ) {
				$retry_count = (int) get_post_meta( $event->ID, '_fb_retry_count', true );

			if ( $retry_count >= $this->max_retries ) {
				// Permanent failure
				$this->module->cpt->mark_events_error( array( $event->ID ), $error );
				AlvoBotPro::debug_log( 'pixel-tracking', "Event {$event->ID} permanently failed after {$this->max_retries} retries: {$error}" );
			} else {
					// Increment retry count — will be picked up in next cycle
					update_post_meta( $event->ID, '_fb_retry_count', $retry_count + 1 );
					update_post_meta( $event->ID, '_fb_error_message', $error );
			}
		}
	}

		/**
		 * Mark a pixel's token as expired in settings.
		 */
	private function mark_token_expired( $pixel_id ) {
			$option_key = 'alvobot_module_pixel-tracking_settings';
			$settings   = get_option( $option_key, array() );

		if ( isset( $settings['pixels'] ) ) {
			foreach ( $settings['pixels'] as &$pixel ) {
				if ( isset( $pixel['pixel_id'] ) && $pixel['pixel_id'] === $pixel_id ) {
						$pixel['token_expired'] = true;
						break;
				}
			}
				update_option( $option_key, $settings );
		}
			AlvoBotPro::debug_log( 'pixel-tracking', "Token expired for pixel {$pixel_id}" );
	}

		/**
		 * Send a test event to all configured pixels.
		 */
	public function send_test_event() {
			$settings = $this->module->get_settings();
			$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
			$results  = array();

			$test_event = array(
				array(
					'event_name'       => 'PageView',
					'event_time'       => time(),
					'event_id'         => wp_generate_uuid4(),
					'event_source_url' => get_site_url(),
					'action_source'    => 'website',
					'user_data'        => array(
						'client_ip_address' => '0.0.0.0',
						'client_user_agent' => 'AlvoBot Pixel Tracking Test',
					),
				),
			);

			foreach ( $pixels as $pixel_config ) {
				if ( empty( $pixel_config['api_token'] ) || empty( $pixel_config['pixel_id'] ) ) {
						continue;
				}

					$body = array(
						'data'            => $test_event,
						'access_token'    => $pixel_config['api_token'],
						'test_event_code' => ! empty( $settings['test_event_code'] ) ? $settings['test_event_code'] : 'TEST00000',
					);

					$url      = "https://graph.facebook.com/v24.0/{$pixel_config['pixel_id']}/events";
					$response = wp_remote_post(
						$url,
						array(
							'body'    => wp_json_encode( $body ),
							'headers' => array( 'Content-Type' => 'application/json' ),
							'timeout' => 15,
						)
					);

				if ( is_wp_error( $response ) ) {
					$results[] = array(
						'pixel_id' => $pixel_config['pixel_id'],
						'success'  => false,
						'error'    => $response->get_error_message(),
					);
				} else {
						$code    = wp_remote_retrieve_response_code( $response );
						$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( 200 === $code ) {
								$results[] = array(
									'pixel_id' => $pixel_config['pixel_id'],
									'success'  => true,
									'response' => $decoded,
								);
					} else {
							$error_msg = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : "HTTP {$code}";
							$results[] = array(
								'pixel_id' => $pixel_config['pixel_id'],
								'success'  => false,
								'error'    => $error_msg,
								'code'     => $code,
							);
					}
				}
			}

			return $results;
	}
}
