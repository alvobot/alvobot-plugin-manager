<?php
/**
 * Pixel Tracking Module - Frontend JS Injection
 *
 * Injects tracking JavaScript on public-facing pages.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend injector for tracking config and conversion triggers.
 */
class AlvoBotPro_PixelTracking_Frontend {

	/**
	 * Module instance.
	 *
	 * @var AlvoBotPro_PixelTracking
	 */
	private $module;

	/**
	 * Bot user-agent signatures to exclude from tracking.
	 *
	 * @var string[]
	 */
	private static $bot_signatures = array(
		'bot',
		'Bot',
		'crawl',
		'spider',
		'HTTrack',
		'facebookexternalhit',
		'WP Rocket',
		'Preload',
		'GTmetrix',
		'Lighthouse',
		'Page Speed',
		'Googlebot',
		'Bingbot',
		'Slurp',
		'DuckDuckBot',
		'Baidu',
	);

	public function __construct( $module ) {
		$this->module = $module;

		// Only inject on frontend (non-admin) pages
		if ( ! is_admin() ) {
			add_action( 'wp_head', array( $this, 'inject_tracking' ), 10 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );
		}
	}

	/**
	 * Check if the current request is from a bot.
	 */
	public function is_bot() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true;
		}

		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		foreach ( self::$bot_signatures as $sig ) {
			if ( stripos( $ua, $sig ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the current user should be excluded from tracking.
	 */
	private function is_user_excluded() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings       = $this->module->get_settings();
		$excluded_roles = isset( $settings['excluded_roles'] ) ? $settings['excluded_roles'] : array();

		if ( empty( $excluded_roles ) ) {
			return false;
		}

		$user  = wp_get_current_user();
		$roles = $user->roles;

		return ! empty( array_intersect( $roles, $excluded_roles ) );
	}

	/**
	 * Enqueue the tracking.js script.
	 */
	public function enqueue_tracking_script() {
		if ( ! $this->should_track() ) {
			return;
		}

		$module_dir = plugin_dir_path( __FILE__ );
		$module_url = plugin_dir_url( __FILE__ );

		if ( file_exists( $module_dir . 'assets/js/tracking.js' ) ) {
			$script_path    = $module_dir . 'assets/js/tracking.js';
			$asset_version  = (string) filemtime( $script_path );
			if ( empty( $asset_version ) ) {
				$asset_version = ALVOBOT_PRO_VERSION;
			}

			wp_enqueue_script(
				'alvobot-pixel-tracking',
				$module_url . 'assets/js/tracking.js',
				array(),
				$asset_version,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		if ( file_exists( $module_dir . 'assets/js/ad-tracker.js' ) ) {
			$ad_script_path   = $module_dir . 'assets/js/ad-tracker.js';
			$ad_asset_version = (string) filemtime( $ad_script_path );
			if ( empty( $ad_asset_version ) ) {
				$ad_asset_version = ALVOBOT_PRO_VERSION;
			}

			wp_enqueue_script(
				'alvobot-ad-tracker',
				$module_url . 'assets/js/ad-tracker.js',
				array( 'alvobot-pixel-tracking' ),
				$ad_asset_version,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}

	/**
	 * Inject inline tracking configuration and conversion event scripts.
	 */
	public function inject_tracking() {
		if ( ! $this->should_track() ) {
			return;
		}

		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
		$debug_enabled = class_exists( 'AlvoBotPro' ) && method_exists( 'AlvoBotPro', 'is_debug_enabled' )
			? (bool) AlvoBotPro::is_debug_enabled( 'pixel-tracking' )
			: false;

		if ( empty( $pixels ) ) {
			return;
		}

		$pixel_ids        = implode( ',', array_column( $pixels, 'pixel_id' ) );
		$consent_cookie   = isset( $settings['consent_cookie'] ) ? $settings['consent_cookie'] : 'alvobot_tracking_consent';
		$consent_check    = ! empty( $settings['consent_check'] );
		$tracking_nonce   = wp_create_nonce( 'alvobot_pixel_tracking' );
		$cf_trace_enabled = isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] );

		$page_id    = get_queried_object_id();
		$page_title = wp_get_document_title();

		// Page context for content enrichment
		$content_type     = '';
		$content_category = '';
		$queried_object   = get_queried_object();
		if ( $queried_object instanceof WP_Post ) {
			$content_type = $queried_object->post_type;
			$categories   = get_the_category( $page_id );
			if ( ! empty( $categories ) ) {
				$content_category = $categories[0]->name;
			}
		}

		// WP logged-in user data (pre-hashed server-side for CAPI matching)
		$user_data_hashed = array();
		if ( is_user_logged_in() ) {
			$wp_user = wp_get_current_user();
			if ( $wp_user->user_email ) {
				$user_data_hashed['em'] = AlvoBotPro_PixelTracking_CPT::hash_pii( $wp_user->user_email );
			}
			if ( $wp_user->first_name ) {
				$user_data_hashed['fn'] = AlvoBotPro_PixelTracking_CPT::hash_pii( $wp_user->first_name );
			}
			if ( $wp_user->last_name ) {
				$user_data_hashed['ln'] = AlvoBotPro_PixelTracking_CPT::hash_pii( $wp_user->last_name );
			}
			$user_data_hashed['external_id'] = AlvoBotPro_PixelTracking_CPT::hash_pii( (string) $wp_user->ID );
		}

		// Generate config object
		?>
		<script>
			var alvobot_pixel_config = {
				pixel_ids: <?php echo wp_json_encode( $pixel_ids ); ?>,
			api_event: <?php echo wp_json_encode( esc_url_raw( rest_url( 'alvobot-pro/v1/pixel-tracking/events/track' ) ) ); ?>,
			api_lead: <?php echo wp_json_encode( esc_url_raw( rest_url( 'alvobot-pro/v1/pixel-tracking/leads/track' ) ) ); ?>,
			nonce: <?php echo wp_json_encode( $tracking_nonce ); ?>,
			page_id: <?php echo wp_json_encode( (string) $page_id ); ?>,
			page_title: <?php echo wp_json_encode( $page_title ); ?>,
			content_type: <?php echo wp_json_encode( $content_type ); ?>,
			content_category: <?php echo wp_json_encode( $content_category ); ?>,
				consent_cookie: <?php echo wp_json_encode( $consent_cookie ); ?>,
				consent_check: <?php echo $consent_check ? 'true' : 'false'; ?>,
				debug_enabled: <?php echo $debug_enabled ? 'true' : 'false'; ?>,
				cf_trace_enabled: <?php echo $cf_trace_enabled ? 'true' : 'false'; ?>,
				user_data_hashed: <?php echo wp_json_encode( $user_data_hashed ); ?>
			};
		</script>
		<?php

		// Generate conversion event scripts
		$this->inject_conversion_scripts( $settings, $page_id );
	}

	/**
	 * Generate per-conversion inline JS based on trigger type.
	 */
	private function inject_conversion_scripts( $settings, $current_page_id ) {
		$conversions = $this->module->cpt->get_conversions();

		if ( empty( $conversions ) ) {
			return;
		}

		$current_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current_path = strtok( $current_path, '?' ); // Remove query string

		$scripts = array();
		foreach ( $conversions as $conv ) {
			// Check if this conversion applies to the current page
			if ( ! $this->conversion_applies_to_page( $conv, $current_page_id, $current_path ) ) {
				continue;
			}

			$event_type   = get_post_meta( $conv->ID, '_event_type', true );
			$event_name   = 'CustomEvent' === $event_type
				? get_post_meta( $conv->ID, '_event_custom_name', true )
				: $event_type;
			$is_custom    = 'CustomEvent' === $event_type;
			$trigger      = get_post_meta( $conv->ID, '_trigger_type', true );
			$trigger_val  = get_post_meta( $conv->ID, '_trigger_value', true );
			$selector     = get_post_meta( $conv->ID, '_css_selector', true );
			$content_name = get_post_meta( $conv->ID, '_content_name', true );
			$pixel_ids    = get_post_meta( $conv->ID, '_pixel_ids', true );

			$event_config = wp_json_encode(
				array(
					'event_name'   => $event_name,
					'event_custom' => $is_custom,
					'content_name' => $content_name,
					'fb_pixels'    => $pixel_ids,
				)
			);

			$js = $this->generate_trigger_js( $trigger, $trigger_val, $selector, $event_config, $conv->ID );
			if ( $js ) {
				$scripts[] = $js;
			}
		}

		if ( ! empty( $scripts ) ) {
			echo "\n<script>\n";
			echo "document.addEventListener('DOMContentLoaded', function() {\n";
			echo "  if (!window.alvobot_pixel || !window.alvobot_pixel.ready) return;\n";
			echo "  window.alvobot_pixel.ready().then(function() {\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $scripts contains generated JS snippets for inline execution.
			echo implode( "\n", $scripts );
			echo "\n  });\n";
			echo "});\n";
			echo "</script>\n";
		}
	}

	/**
	 * Generate JS for a specific trigger type.
	 */
	private function generate_trigger_js( $trigger, $trigger_val, $selector, $event_config, $conv_id ) {
		$safe_id = 'alvobot_conv_' . $conv_id;

		switch ( $trigger ) {
			case 'page_load':
				return "  window.alvobot_pixel.send_event({$event_config});";

			case 'page_time':
				$seconds = max( 1, absint( $trigger_val ) );
				return "  setTimeout(function() { window.alvobot_pixel.send_event({$event_config}); }, " . ( $seconds * 1000 ) . ');';

			case 'form_submit':
				$selector = trim( (string) $selector );
				if ( '' === $selector ) {
					return '';
				}
				$selector_literal = wp_json_encode( $selector );
				return "  (function() {
    var tracked_{$safe_id} = false;
    function bind_{$safe_id}() {
      var elements = [];
      try { elements = document.querySelectorAll({$selector_literal}); } catch (e) { return; }
      elements.forEach(function(el) {
        if (el.classList.contains('alvobot_tracked_{$safe_id}')) return;
        el.classList.add('alvobot_tracked_{$safe_id}');
        el.addEventListener('submit', function() {
          if (!tracked_{$safe_id}) { tracked_{$safe_id} = true; window.alvobot_pixel.send_event({$event_config}); }
        });
      });
    }
    bind_{$safe_id}();
    setInterval(bind_{$safe_id}, 5000);
  })();";

			case 'click':
				$selector = trim( (string) $selector );
				if ( '' === $selector ) {
					return '';
				}
				$selector_literal = wp_json_encode( $selector );
				return "  (function() {
    var elements = [];
    try { elements = document.querySelectorAll({$selector_literal}); } catch (e) { return; }
    elements.forEach(function(el) {
      el.addEventListener('click', function() { window.alvobot_pixel.send_event({$event_config}); });
    });
  })();";

			case 'scroll':
				$pct = max( 1, min( 100, absint( $trigger_val ) ) );
				return "  (function() {
    var fired_{$safe_id} = false;
    window.addEventListener('scroll', function() {
      if (fired_{$safe_id}) return;
      var scrollPct = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100;
      if (scrollPct >= {$pct}) { fired_{$safe_id} = true; window.alvobot_pixel.send_event({$event_config}); }
    });
  })();";

			case 'view_element':
				$selector = trim( (string) $selector );
				if ( '' === $selector ) {
					return '';
				}
				$selector_literal = wp_json_encode( $selector );
				return "  (function() {
    var el;
    try { el = document.querySelector({$selector_literal}); } catch (e) { return; }
    if (!el) return;
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting && !entry.target.classList.contains('alvobot_tracked')) {
          entry.target.classList.add('alvobot_tracked');
          window.alvobot_pixel.send_event({$event_config});
        }
      });
    }, { threshold: 0.5 });
    observer.observe(el);
  })();";

			default:
				return '';
		}
	}

	/**
	 * Check if a conversion rule applies to the current page.
	 */
	private function conversion_applies_to_page( $conv, $current_page_id, $current_path ) {
		$display_on = get_post_meta( $conv->ID, '_display_on', true );

		if ( 'all' === $display_on || empty( $display_on ) ) {
			return true;
		}

		if ( 'specific' === $display_on ) {
			$page_ids = get_post_meta( $conv->ID, '_page_ids', true );
			if ( is_array( $page_ids ) ) {
				return in_array( $current_page_id, array_map( 'absint', $page_ids ), true );
			}
			return false;
		}

		if ( 'path' === $display_on ) {
			$page_paths = get_post_meta( $conv->ID, '_page_paths', true );
			if ( $page_paths ) {
				$paths = array_map( 'trim', explode( ',', $page_paths ) );
				foreach ( $paths as $path ) {
					if ( $path && strpos( $current_path, $path ) !== false ) {
						return true;
					}
				}
			}
			return false;
		}

		return true;
	}

	/**
	 * Check if tracking should be injected for the current request.
	 */
	private function should_track() {
		$settings = $this->module->get_settings();
		$pixels   = isset( $settings['pixels'] ) ? $settings['pixels'] : array();

		// No pixels configured
		if ( empty( $pixels ) ) {
			return false;
		}

		// Bot detection
		if ( $this->is_bot() ) {
			return false;
		}

		// Role exclusion
		if ( $this->is_user_excluded() ) {
			return false;
		}

		return true;
	}
}
