<?php
/**
 * Pixel Tracking Module - First-party attribution capture.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures durable first-party attribution before browser JavaScript runs.
 */
class AlvoBotPro_PixelTracking_Attribution {

	const UID_COOKIE            = '_alvo_uid';
	const FIRST_TOUCH_COOKIE    = 'alvobot_attr_ft';
	const LAST_TOUCH_COOKIE     = 'alvobot_attr_lt';
	const LANDING_FIRST_COOKIE  = 'alvobot_landing_first';
	const REFERRER_FIRST_COOKIE = 'alvobot_referrer_first';
	const FIRST_SEEN_COOKIE     = 'alvobot_first_seen';
	const SESSION_COOKIE        = 'alvobot_session_id';
	const VISIT_COUNT_COOKIE    = 'alvobot_visit_count';
	const BOT_SCORE_COOKIE      = 'alvobot_bot_score';

	const UID_TTL       = 34560000; // 400 days.
	const ATTR_TTL      = 15552000; // 180 days.
	const CLICK_ID_TTL  = 7776000;  // 90 days.
	const SESSION_TTL   = 1800;     // 30 minutes.
	const BOT_SCORE_TTL = 3600;

	/**
	 * Sensitive URL parameters never persisted to cookies or event metadata.
	 *
	 * @var string[]
	 */
	private static $sensitive_query_keys = array(
		'email',
		'e-mail',
		'mail',
		'phone',
		'telefone',
		'tel',
		'celular',
		'cpf',
		'cnpj',
		'password',
		'pass',
		'token',
		'access_token',
		'refresh_token',
		'jwt',
		'code',
		'auth',
		'session',
		'session_id',
		'nonce',
		'_wpnonce',
	);

	/**
	 * Supported paid-media click identifiers.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_click_id_fields() {
		return array(
			'fbclid'      => array(
				'cookie' => '_alvo_fbclid',
				'source' => 'facebook',
				'medium' => 'paid_social',
			),
			'gclid'       => array(
				'cookie' => '_alvo_gclid',
				'source' => 'google',
				'medium' => 'cpc',
			),
			'gbraid'      => array(
				'cookie' => '_alvo_gbraid',
				'source' => 'google',
				'medium' => 'cpc',
			),
			'wbraid'      => array(
				'cookie' => '_alvo_wbraid',
				'source' => 'google',
				'medium' => 'cpc',
			),
			'gclsrc'      => array(
				'cookie' => '_alvo_gclsrc',
				'source' => 'google',
				'medium' => 'cpc',
			),
			'dclid'       => array(
				'cookie' => '_alvo_dclid',
				'source' => 'google',
				'medium' => 'display',
			),
			'gad_source'  => array(
				'cookie' => '_alvo_gad_source',
				'source' => 'google',
				'medium' => 'cpc',
			),
			'msclkid'     => array(
				'cookie' => '_alvo_msclkid',
				'source' => 'bing',
				'medium' => 'cpc',
			),
			'ttclid'      => array(
				'cookie' => '_alvo_ttclid',
				'source' => 'tiktok',
				'medium' => 'paid_social',
			),
			'twclid'      => array(
				'cookie' => '_alvo_twclid',
				'source' => 'twitter',
				'medium' => 'paid_social',
			),
			'li_fat_id'   => array(
				'cookie' => '_alvo_li_fat_id',
				'source' => 'linkedin',
				'medium' => 'paid_social',
			),
			'sccid'       => array(
				'cookie' => '_alvo_sccid',
				'source' => 'snapchat',
				'medium' => 'paid_social',
			),
			'epik'        => array(
				'cookie' => '_alvo_epik',
				'source' => 'pinterest',
				'medium' => 'paid_social',
			),
			'rdt_cid'     => array(
				'cookie' => '_alvo_rdt_cid',
				'source' => 'reddit',
				'medium' => 'paid_social',
			),
			'tblci'       => array(
				'cookie' => '_alvo_tblci',
				'source' => 'taboola',
				'medium' => 'native',
			),
			'ob_orig_url' => array(
				'cookie' => '_alvo_ob_orig_url',
				'source' => 'outbrain',
				'medium' => 'native',
			),
			'qclid'       => array(
				'cookie' => '_alvo_qclid',
				'source' => 'quora',
				'medium' => 'cpc',
			),
			'ymclk'       => array(
				'cookie' => '_alvo_ymclk',
				'source' => 'yahoo',
				'medium' => 'cpc',
			),
		);
	}

	/**
	 * Capture attribution cookies for a public frontend request.
	 *
	 * @return array<string,mixed>
	 */
	public static function maybe_capture_request() {
		if ( ! self::should_capture_request() ) {
			return self::get_current_context();
		}
		if ( ! self::has_tracking_consent() ) {
			return self::get_current_context();
		}

		$landing_url = self::sanitize_url_for_storage( self::get_current_url() );
		$referrer    = self::sanitize_url_for_storage( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
		$click_ids   = self::capture_click_ids();
		$utms        = self::capture_utms();
		$external_id = self::resolve_external_id();
		$edge        = self::get_edge_context();

		if ( ! empty( $click_ids['fbclid'] ) && '' === self::get_cookie( '_fbc' ) ) {
			self::set_cookie( '_fbc', 'fb.1.' . time() . '.' . $click_ids['fbclid'], self::CLICK_ID_TTL );
		}

		if ( '' === self::get_cookie( '_fbp' ) ) {
			self::set_cookie( '_fbp', 'fb.1.' . time() . '.' . wp_rand( 100000, 999999 ) . wp_rand( 1000, 9999 ), self::CLICK_ID_TTL );
		}

		$first_touch = self::get_json_cookie( self::FIRST_TOUCH_COOKIE );
		if ( empty( $first_touch ) ) {
			$first_touch = self::build_attribution_snapshot( $utms, $landing_url, $referrer, $click_ids );
			self::set_json_cookie( self::FIRST_TOUCH_COOKIE, $first_touch, self::ATTR_TTL );
		}

		$last_touch = self::build_attribution_snapshot( $utms, $landing_url, $referrer, $click_ids );
		self::set_json_cookie( self::LAST_TOUCH_COOKIE, $last_touch, self::ATTR_TTL );

		if ( '' === self::get_cookie( self::LANDING_FIRST_COOKIE ) && '' !== $landing_url ) {
			self::set_cookie( self::LANDING_FIRST_COOKIE, $landing_url, self::ATTR_TTL );
		}
		if ( '' === self::get_cookie( self::REFERRER_FIRST_COOKIE ) && '' !== $referrer ) {
			self::set_cookie( self::REFERRER_FIRST_COOKIE, $referrer, self::ATTR_TTL );
		}
		if ( '' === self::get_cookie( self::FIRST_SEEN_COOKIE ) ) {
			self::set_cookie( self::FIRST_SEEN_COOKIE, gmdate( 'c' ), self::ATTR_TTL );
		}

		if ( '' === self::get_cookie( self::SESSION_COOKIE ) ) {
			$visit_count = absint( self::get_cookie( self::VISIT_COUNT_COOKIE ) );
			self::set_cookie( self::VISIT_COUNT_COOKIE, (string) ( $visit_count + 1 ), self::ATTR_TTL );
			self::set_cookie( self::SESSION_COOKIE, self::generate_uuid(), self::SESSION_TTL );
		} else {
			self::set_cookie( self::SESSION_COOKIE, self::get_cookie( self::SESSION_COOKIE ), self::SESSION_TTL );
		}

		if ( '' !== $edge['bot']['score'] ) {
			self::set_cookie( self::BOT_SCORE_COOKIE, $edge['bot']['score'], self::BOT_SCORE_TTL );
		}

		return self::get_current_context( $external_id );
	}

	/**
	 * Get current first-party attribution context from cookies/server.
	 *
	 * @param string $external_id Optional known external ID.
	 * @return array<string,mixed>
	 */
	public static function get_current_context( $external_id = '' ) {
		$click_ids = array();
		foreach ( self::get_click_id_fields() as $key => $config ) {
			$value = self::get_query_value( $key );
			if ( '' === $value ) {
				$value = self::get_cookie( $config['cookie'] );
			}
			if ( '' !== $value ) {
				$click_ids[ $key ] = $value;
			}
		}

		$utms = self::read_utms_from_request_or_cookies();

		$context = array(
			'external_id'    => '' !== $external_id ? $external_id : self::get_cookie( self::UID_COOKIE ),
			'fbp'            => self::get_cookie( '_fbp' ),
			'fbc'            => self::get_cookie( '_fbc' ),
			'click_ids'      => $click_ids,
			'utms'           => $utms,
			'first_touch'    => self::sanitize_attribution_payload( self::get_json_cookie( self::FIRST_TOUCH_COOKIE ) ),
			'last_touch'     => self::sanitize_attribution_payload( self::get_json_cookie( self::LAST_TOUCH_COOKIE ) ),
			'landing_first'  => self::get_cookie( self::LANDING_FIRST_COOKIE ),
			'referrer_first' => self::get_cookie( self::REFERRER_FIRST_COOKIE ),
			'first_seen'     => self::get_cookie( self::FIRST_SEEN_COOKIE ),
			'session_id'     => self::get_cookie( self::SESSION_COOKIE ),
			'visit_count'    => absint( self::get_cookie( self::VISIT_COUNT_COOKIE ) ),
			'edge'           => self::get_edge_context(),
		);

		return $context;
	}

	/**
	 * Build a normalized attribution snapshot.
	 *
	 * @param array  $utms        UTM/query values.
	 * @param string $landing_url Sanitized landing URL.
	 * @param string $referrer    Sanitized referrer URL.
	 * @param array  $click_ids   Click IDs.
	 * @return array<string,mixed>
	 */
	public static function build_attribution_snapshot( $utms, $landing_url, $referrer, $click_ids = array() ) {
		$utms      = is_array( $utms ) ? $utms : array();
		$click_ids = is_array( $click_ids ) ? $click_ids : array();

		$source = isset( $utms['utm_source'] ) ? sanitize_text_field( (string) $utms['utm_source'] ) : '';
		$medium = isset( $utms['utm_medium'] ) ? sanitize_text_field( (string) $utms['utm_medium'] ) : '';

		if ( '' === $source || '' === $medium ) {
			foreach ( self::get_click_id_fields() as $key => $config ) {
				if ( empty( $click_ids[ $key ] ) ) {
					continue;
				}
				if ( '' === $source ) {
					$source = $config['source'];
				}
				if ( '' === $medium ) {
					$medium = $config['medium'];
				}
				break;
			}
		}

		$snapshot = array(
			'captured_at'  => gmdate( 'c' ),
			'landing_url'  => self::sanitize_url_for_storage( $landing_url ),
			'referrer'     => self::sanitize_url_for_storage( $referrer ),
			'utm_source'   => $source,
			'utm_medium'   => $medium,
			'utm_campaign' => isset( $utms['utm_campaign'] ) ? sanitize_text_field( (string) $utms['utm_campaign'] ) : '',
			'utm_id'       => isset( $utms['utm_id'] ) ? sanitize_text_field( (string) $utms['utm_id'] ) : '',
			'utm_term'     => isset( $utms['utm_term'] ) ? sanitize_text_field( (string) $utms['utm_term'] ) : '',
			'utm_content'  => isset( $utms['utm_content'] ) ? sanitize_text_field( (string) $utms['utm_content'] ) : '',
			'src'          => isset( $utms['src'] ) ? sanitize_text_field( (string) $utms['src'] ) : '',
			'sck'          => isset( $utms['sck'] ) ? sanitize_text_field( (string) $utms['sck'] ) : '',
			'click_ids'    => self::sanitize_click_ids( $click_ids ),
		);

		return array_filter(
			$snapshot,
			function ( $value ) {
				return '' !== $value && array() !== $value && null !== $value;
			}
		);
	}

	/**
	 * Sanitize URL for safe cookie/meta storage.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function sanitize_url_for_storage( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
			foreach ( array_keys( $query ) as $key ) {
				if ( in_array( strtolower( (string) $key ), self::$sensitive_query_keys, true ) ) {
					unset( $query[ $key ] );
				}
			}
		}

		$sanitized = strtolower( (string) $parts['scheme'] ) . '://' . strtolower( (string) $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$sanitized .= ':' . absint( $parts['port'] );
		}
		$sanitized .= isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( ! empty( $query ) ) {
			$sanitized .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$sanitized .= '#' . sanitize_key( (string) $parts['fragment'] );
		}

		return esc_url_raw( $sanitized );
	}

	/**
	 * Return optional edge/CDN context. Never requires Cloudflare.
	 *
	 * @param array|null $server Server array override for tests.
	 * @return array<string,mixed>
	 */
	public static function get_edge_context( $server = null ) {
		$server = is_array( $server ) ? $server : $_SERVER;
		$score  = isset( $server['HTTP_CF_BOT_SCORE'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_CF_BOT_SCORE'] ) ) : '';

		return array(
			'cloudflare_detected' => isset( $server['HTTP_CF_RAY'] ) || isset( $server['HTTP_CF_CONNECTING_IP'] ) || isset( $server['HTTP_CF_IPCOUNTRY'] ),
			'geo'                 => array_filter(
				array(
					'country_code' => isset( $server['HTTP_CF_IPCOUNTRY'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_CF_IPCOUNTRY'] ) ) : '',
					'colo'         => isset( $server['HTTP_CF_RAY'] ) ? self::extract_cf_colo( sanitize_text_field( wp_unslash( $server['HTTP_CF_RAY'] ) ) ) : '',
				)
			),
			'network'             => array_filter(
				array(
					'cf_ray'   => isset( $server['HTTP_CF_RAY'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_CF_RAY'] ) ) : '',
					'cdn_loop' => isset( $server['HTTP_CDN_LOOP'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_CDN_LOOP'] ) ) : '',
				)
			),
			'bot'                 => array(
				'score'    => is_numeric( $score ) ? (string) max( 1, min( 99, absint( $score ) ) ) : '',
				'verified' => ! empty( $server['HTTP_CF_VERIFIED_BOT'] ) && 'true' === strtolower( sanitize_text_field( wp_unslash( $server['HTTP_CF_VERIFIED_BOT'] ) ) ),
			),
		);
	}

	/**
	 * Sanitize a click ID map.
	 *
	 * @param mixed $value Raw map.
	 * @return array<string,string>
	 */
	public static function sanitize_click_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = self::get_click_id_fields();
		$clean   = array();
		foreach ( $allowed as $key => $_config ) {
			if ( empty( $value[ $key ] ) ) {
				continue;
			}
			$clean[ $key ] = self::sanitize_limited_text( $value[ $key ], 512 );
		}

		return $clean;
	}

	/**
	 * Sanitize attribution payloads from browser requests.
	 *
	 * @param mixed $value Raw attribution data.
	 * @return array<string,mixed>
	 */
	public static function sanitize_attribution_payload( $value, $depth = 0 ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		if ( $depth > 2 ) {
			return array();
		}

		$clean = array();
		foreach ( array( 'landing_url', 'referrer', 'landing_first', 'referrer_first' ) as $url_key ) {
			if ( ! empty( $value[ $url_key ] ) ) {
				$clean[ $url_key ] = self::sanitize_url_for_storage( (string) $value[ $url_key ] );
			}
		}

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content', 'src', 'sck', 'captured_at', 'first_seen', 'session_id' ) as $text_key ) {
			if ( ! empty( $value[ $text_key ] ) ) {
				$clean[ $text_key ] = self::sanitize_limited_text( $value[ $text_key ], 180 );
			}
		}

		if ( isset( $value['visit_count'] ) ) {
			$clean['visit_count'] = absint( $value['visit_count'] );
		}

		if ( isset( $value['click_ids'] ) ) {
			$clean['click_ids'] = self::sanitize_click_ids( $value['click_ids'] );
		}
		if ( isset( $value['first_touch'] ) && is_array( $value['first_touch'] ) ) {
			$clean['first_touch'] = self::sanitize_attribution_payload( $value['first_touch'], $depth + 1 );
		}
		if ( isset( $value['last_touch'] ) && is_array( $value['last_touch'] ) ) {
			$clean['last_touch'] = self::sanitize_attribution_payload( $value['last_touch'], $depth + 1 );
		}

		return array_filter(
			$clean,
			function ( $item ) {
				return '' !== $item && array() !== $item && null !== $item;
			}
		);
	}

	/**
	 * Sanitize optional edge/CDN context from browser requests.
	 *
	 * @param mixed $value Raw edge context.
	 * @return array<string,mixed>
	 */
	public static function sanitize_edge_context( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array(
			'cloudflare_detected' => ! empty( $value['cloudflare_detected'] ),
			'geo'                 => array(),
			'network'             => array(),
			'bot'                 => array(),
		);

		$geo     = isset( $value['geo'] ) && is_array( $value['geo'] ) ? $value['geo'] : array();
		$network = isset( $value['network'] ) && is_array( $value['network'] ) ? $value['network'] : array();
		$bot     = isset( $value['bot'] ) && is_array( $value['bot'] ) ? $value['bot'] : array();

		foreach ( array( 'country_code', 'colo' ) as $key ) {
			if ( ! empty( $geo[ $key ] ) ) {
				$clean['geo'][ $key ] = self::sanitize_limited_text( $geo[ $key ], 32 );
			}
		}
		foreach ( array( 'cf_ray', 'cdn_loop' ) as $key ) {
			if ( ! empty( $network[ $key ] ) ) {
				$clean['network'][ $key ] = self::sanitize_limited_text( $network[ $key ], 128 );
			}
		}
		if ( ! empty( $bot['score'] ) ) {
			$clean['bot']['score'] = self::sanitize_limited_text( $bot['score'], 3 );
		}
		if ( isset( $bot['verified'] ) ) {
			$clean['bot']['verified'] = ! empty( $bot['verified'] );
		}

		return array_filter(
			$clean,
			function ( $item ) {
				return array() !== $item && null !== $item;
			}
		);
	}

	/**
	 * Determine whether Cloudflare bot data says this is likely non-human.
	 *
	 * @return bool
	 */
	public static function is_low_quality_edge_bot() {
		$edge = self::get_edge_context();
		if ( ! empty( $edge['bot']['verified'] ) ) {
			return true;
		}

		$score = isset( $edge['bot']['score'] ) ? absint( $edge['bot']['score'] ) : 0;
		return $score > 0 && $score < 30;
	}

	/**
	 * Capture paid-media click IDs from the URL into cookies.
	 *
	 * @return array<string,string>
	 */
	private static function capture_click_ids() {
		$click_ids = array();
		foreach ( self::get_click_id_fields() as $key => $config ) {
			$value = self::get_query_value( $key );
			if ( '' === $value ) {
				$value = self::get_cookie( $config['cookie'] );
			}
			if ( '' === $value ) {
				continue;
			}
			$click_ids[ $key ] = $value;
			self::set_cookie( $config['cookie'], $value, self::CLICK_ID_TTL );
		}

		return $click_ids;
	}

	/**
	 * Capture UTM fields from the URL into first-party cookies.
	 *
	 * @return array<string,string>
	 */
	private static function capture_utms() {
		$utms = self::read_utms_from_request_or_cookies();
		foreach ( $utms as $key => $value ) {
			self::set_cookie( '_alvo_' . sanitize_key( $key ), $value, self::ATTR_TTL );
		}

		return $utms;
	}

	/**
	 * Read supported UTM fields from URL first, then cookies.
	 *
	 * @return array<string,string>
	 */
	private static function read_utms_from_request_or_cookies() {
		$keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content', 'src', 'sck' );
		$utms = array();
		foreach ( $keys as $key ) {
			$value = self::get_query_value( $key );
			if ( '' === $value ) {
				$value = self::get_cookie( '_alvo_' . sanitize_key( $key ) );
			}
			if ( '' !== $value ) {
				$utms[ $key ] = $value;
			}
		}

		return $utms;
	}

	/**
	 * Resolve or create durable anonymous external ID.
	 *
	 * @return string
	 */
	private static function resolve_external_id() {
		$external_id = self::get_cookie( self::UID_COOKIE );
		if ( '' === $external_id ) {
			$external_id = self::generate_uuid();
		}

		self::set_cookie( self::UID_COOKIE, $external_id, self::UID_TTL );
		return $external_id;
	}

	/**
	 * Should this request receive tracking cookies?
	 *
	 * @return bool
	 */
	private static function should_capture_request() {
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( headers_sent() ) {
			self::debug_log( 'Attribution cookies skipped: headers already sent.' );
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Determine whether first-party attribution cookies can be written.
	 *
	 * @return bool
	 */
	private static function has_tracking_consent() {
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'alvobot_pro_pixel_tracking_consent_granted', null );
			if ( is_bool( $filtered ) ) {
				return $filtered;
			}

			$legacy_filtered = apply_filters( 'alvobot_pro_tracking_consent_granted', null );
			if ( is_bool( $legacy_filtered ) ) {
				return $legacy_filtered;
			}
		}

		$settings = function_exists( 'get_option' ) ? get_option( 'alvobot_pro_pixel-tracking_settings', array() ) : array();
		if ( ! is_array( $settings ) || empty( $settings['consent_check'] ) ) {
			return true;
		}

		$cookie_name = ! empty( $settings['consent_cookie'] ) ? sanitize_key( (string) $settings['consent_cookie'] ) : 'alvobot_tracking_consent';
		if ( self::is_truthy_consent_value( self::get_cookie( $cookie_name ) ) ) {
			return true;
		}

		$cookieyes = self::get_cookie( 'cookieyes-consent' );
		if ( '' !== $cookieyes && preg_match( '/(?:analytics|advertisement|functional):(yes|true|1)/i', $cookieyes ) ) {
			return true;
		}

		if ( self::is_truthy_consent_value( self::get_cookie( 'cmplz_statistics' ) ) || self::is_truthy_consent_value( self::get_cookie( 'cmplz_marketing' ) ) ) {
			return true;
		}

		$cookiebot = self::get_cookie( 'CookieConsent' );
		if ( '' !== $cookiebot && preg_match( '/(?:statistics|marketing):true/i', $cookiebot ) ) {
			return true;
		}

		self::debug_log( 'Attribution cookies skipped: tracking consent not granted.' );
		return false;
	}

	/**
	 * Interpret common CMP cookie values.
	 *
	 * @param string $value Raw consent value.
	 * @return bool
	 */
	private static function is_truthy_consent_value( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'y', 'allow', 'allowed', 'granted', 'accept', 'accepted' ), true );
	}

	/**
	 * Build the current request URL using WordPress home_url as trusted origin.
	 *
	 * @return string
	 */
	private static function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $request_uri );
	}

	/**
	 * Read a sanitized query value.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private static function get_query_value( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution capture.
		if ( ! isset( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only attribution capture.
		return self::sanitize_limited_text( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ), 512 );
	}

	/**
	 * Read a first-party cookie.
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	private static function get_cookie( $name ) {
		if ( empty( $_COOKIE[ $name ] ) ) {
			return '';
		}

		return self::sanitize_limited_text( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ), 4096 );
	}

	/**
	 * Read JSON cookie payload.
	 *
	 * @param string $name Cookie name.
	 * @return array<string,mixed>
	 */
	private static function get_json_cookie( $name ) {
		$raw = self::get_cookie( $name );
		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Set a JSON cookie.
	 *
	 * @param string $name    Cookie name.
	 * @param array  $value   Value.
	 * @param int    $max_age Max age in seconds.
	 * @return void
	 */
	private static function set_json_cookie( $name, $value, $max_age ) {
		self::set_cookie( $name, wp_json_encode( $value ), $max_age );
	}

	/**
	 * Set a first-party cookie and update $_COOKIE for the current request.
	 *
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @param int    $max_age Max age in seconds.
	 * @return bool
	 */
	private static function set_cookie( $name, $value, $max_age ) {
		$name  = sanitize_key( $name );
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( '' === $name || '' === $value ) {
			return false;
		}

		if ( headers_sent() ) {
			self::debug_log( "Attribution cookie {$name} not sent: headers already sent." );
			return false;
		}

		$result = setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + absint( $max_age ),
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		if ( $result ) {
			$_COOKIE[ $name ] = $value;
		}

		return $result;
	}

	/**
	 * Sanitize scalar text with a maximum length.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max_len Maximum length.
	 * @return string
	 */
	private static function sanitize_limited_text( $value, $max_len ) {
		$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, absint( $max_len ) );
		}

		return substr( $value, 0, absint( $max_len ) );
	}

	/**
	 * Generate UUID v4 with WordPress fallback.
	 *
	 * @return string
	 */
	private static function generate_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * Extract Cloudflare colo from cf-ray header when available.
	 *
	 * @param string $cf_ray CF-Ray header.
	 * @return string
	 */
	private static function extract_cf_colo( $cf_ray ) {
		$parts = explode( '-', (string) $cf_ray );
		return isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '';
	}

	/**
	 * Debug log wrapper that does not require the global class during unit tests.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function debug_log( $message ) {
		if ( class_exists( 'AlvoBotPro' ) && method_exists( 'AlvoBotPro', 'debug_log' ) ) {
			AlvoBotPro::debug_log( 'pixel-tracking', $message );
		}
	}
}
