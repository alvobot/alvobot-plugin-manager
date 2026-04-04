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
			// Fallback: inject scripts directly in footer if wp_enqueue_scripts is blocked by cache plugins.
			add_action( 'wp_footer', array( $this, 'inject_scripts_fallback' ), 99 );
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

		$has_meta_pixels = ! empty( $pixels );
		$pixel_ids_list  = $has_meta_pixels ? array_filter( array_column( $pixels, 'pixel_id' ) ) : array();
		$pixel_ids        = implode( ',', $pixel_ids_list );
			$meta_base_injected = $has_meta_pixels;
			$tracking_nonce   = wp_create_nonce( 'alvobot_pixel_tracking' );
			$cf_trace_enabled = isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
			$site_currency    = $this->get_site_currency_code();

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

		// Generate a server-side event_id for the initial PageView.
		// Shared with tracking.js so browser fbq() and CAPI use the same ID for deduplication.
		$pageview_event_id = function_exists( 'wp_generate_uuid4' )
			? wp_generate_uuid4()
			: sprintf(
				'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ),
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0x0fff ) | 0x4000,
				wp_rand( 0, 0x3fff ) | 0x8000,
				wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff )
			);

		// ── Standard Facebook Pixel base code ────────────────────────────────
		// Inline script — cannot be deferred or combined by any caching/optimization
		// plugin (LiteSpeed, WP Rocket, Autoptimize, etc.). Fires synchronously in
		// <head> before any JS defer logic runs. fbevents.js loads async in parallel.
		// Base code always injected — consent check removed per site policy.
		// PageView is queued here with a server-generated event_id; tracking.js sends
		// the matching CAPI event for server-side deduplication without double-firing.
		if ( $meta_base_injected ) :
		?>
<!-- Facebook Pixel Code (AlvoBot) -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
<?php foreach ( $pixel_ids_list as $pid ) : ?>
fbq('init',<?php echo wp_json_encode( (string) $pid ); ?><?php if ( ! empty( $user_data_hashed ) ) : ?>,<?php echo wp_json_encode( $user_data_hashed ); ?><?php endif; ?>);
<?php endforeach; ?>
fbq('track','PageView',{},{eventID:<?php echo wp_json_encode( $pageview_event_id ); ?>});
</script>
<?php foreach ( $pixel_ids_list as $pid ) : ?>
<noscript><img height="1" width="1" style="display:none"
 src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pid ); ?>&ev=PageView&noscript=1"/></noscript>
<?php endforeach; ?>
<!-- End Facebook Pixel Code -->
		<?php endif; ?>

		<?php
		// ── Google Tag (gtag.js) — shared by GA4 and Google Ads ──────────────
		$google_trackers = $this->get_google_trackers_from_settings( $settings );

		// Separate real trackers (GA4/Ads we inject) from external (Site Kit/GTM already on page).
		$real_trackers = array_values(
			array_filter(
				$google_trackers,
				function ( $gt ) {
					return ! isset( $gt['type'] ) || 'external' !== $gt['type'];
				}
			)
		);

		// Only inject gtag.js for real (non-external) trackers.
		if ( ! empty( $real_trackers ) ) :
			$primary_id = $real_trackers[0]['tracker_id'];
		?>
<!-- Google Tag (AlvoBot) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $primary_id ); ?>"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
			<?php foreach ( $real_trackers as $gt ) : ?>
			<?php if ( isset( $gt['type'] ) && 'ga4' === $gt['type'] ) : ?>
gtag('config', <?php echo wp_json_encode( $gt['tracker_id'] ); ?>, { send_page_view: false });
			<?php else : ?>
gtag('config', <?php echo wp_json_encode( $gt['tracker_id'] ); ?>);
			<?php endif; ?>
		<?php endforeach; ?>
</script>
<!-- End Google Tag -->
		<?php endif; ?>

		<?php
		// ── Config object for tracking.js ────────────────────────────────────
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
				debug_enabled: <?php echo $debug_enabled ? 'true' : 'false'; ?>,
				cf_trace_enabled: <?php echo $cf_trace_enabled ? 'true' : 'false'; ?>,
				user_data_hashed: <?php echo wp_json_encode( $user_data_hashed ); ?>,
				meta_pixel_base_injected: <?php echo $meta_base_injected ? 'true' : 'false'; ?>,
				pageview_event_id: <?php echo wp_json_encode( $meta_base_injected ? $pageview_event_id : '' ); ?>,
				google_trackers: <?php
				// Strip 'label' from frontend output — only tracker_id, type, and conversion_label are needed.
				echo wp_json_encode(
					array_map(
						function ( $t ) {
							return array(
								'tracker_id'       => $t['tracker_id'],
								'type'             => $t['type'],
								'conversion_label' => isset( $t['conversion_label'] ) ? $t['conversion_label'] : '',
							);
						},
						$google_trackers
					)
				);
				?>,
				google_analytics_id: <?php echo wp_json_encode( isset( $settings['google_analytics_id'] ) ? $settings['google_analytics_id'] : '' ); ?>,
				google_ads_id: <?php echo wp_json_encode( isset( $settings['google_ads_id'] ) ? $settings['google_ads_id'] : '' ); ?>,
					google_ads_conversion_label: <?php echo wp_json_encode( isset( $settings['google_ads_conversion_label'] ) ? $settings['google_ads_conversion_label'] : '' ); ?>,
					site_currency: <?php echo wp_json_encode( $site_currency ); ?>,
					ad_conversions_active: <?php echo $this->has_active_ad_conversions() ? 'true' : 'false'; ?>,
					ad_conversion_triggers: <?php echo wp_json_encode( array_keys( $this->get_active_ad_conversion_triggers() ) ); ?>
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
			$target_ids   = $this->filter_active_conversion_target_ids( $pixel_ids );

			$platforms             = get_post_meta( $conv->ID, '_platforms', true );
			$gads_conversion_label = get_post_meta( $conv->ID, '_gads_conversion_label', true );
			$gads_labels_map_raw   = get_post_meta( $conv->ID, '_gads_labels_map', true );
			$gads_labels_map       = $gads_labels_map_raw ? json_decode( $gads_labels_map_raw, true ) : array();
			$gads_conversion_value = get_post_meta( $conv->ID, '_gads_conversion_value', true );

			if ( '' !== trim( (string) $pixel_ids ) && empty( $target_ids ) ) {
				continue;
			}
			if ( ! empty( $target_ids ) ) {
				$pixel_ids      = implode( ',', $target_ids );
				$platforms      = $this->derive_platforms_from_target_ids( $target_ids );
				$gads_labels_map = $this->filter_gads_labels_map_for_targets( $gads_labels_map, $target_ids );
			}

			$event_config = wp_json_encode(
				array(
					'event_name'            => $event_name,
					'event_custom'          => $is_custom,
					'content_name'          => $content_name,
					'fb_pixels'             => $pixel_ids,
					'platforms'             => $platforms ? $platforms : 'all',
					'gads_conversion_label' => $gads_conversion_label ? $gads_conversion_label : '',
					'gads_labels_map'       => is_array( $gads_labels_map ) ? $gads_labels_map : array(),
					'gads_conversion_value' => $gads_conversion_value ? $gads_conversion_value : '',
				)
			);

			$js = $this->generate_trigger_js( $trigger, $trigger_val, $selector, $event_config, $conv->ID );
			if ( $js ) {
				$scripts[] = $js;
			}
		}

		// Separate ad-event listeners (must register immediately) from other triggers.
		$ad_scripts   = array();
		$other_scripts = array();
		foreach ( $scripts as $s ) {
			if ( strpos( $s, "'alvobot:ad_event'" ) !== false ) {
				$ad_scripts[] = $s;
			} else {
				$other_scripts[] = $s;
			}
		}

		// Ad-event listeners: register immediately so they never miss events from ad-tracker.js.
		// Fire gtag conversion IMMEDIATELY (Google Ads), then also queue send_event for
		// Meta CAPI / REST API when the tracker finishes initializing.
		if ( ! empty( $ad_scripts ) ) {
			echo "\n<script>\n";
			echo "(function() {\n";
			echo "  function alvobotAdSendEvent(cfg) {\n";
			// Immediate gtag dispatch for Google Ads — no waiting.
			echo "    var selectedIds = cfg.fb_pixels ? cfg.fb_pixels.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];\n";
			echo "    var filterActive = selectedIds.length > 0;\n";
			echo "    var tracker = window.alvobot_pixel;\n";
			echo "    var googleBrowserAllowed = true;\n";
			echo "    if (tracker && typeof tracker.refresh_google_tag_state === 'function') { googleBrowserAllowed = tracker.refresh_google_tag_state(); }\n";
			echo "    if (typeof window.gtag === 'function' && cfg.platforms !== 'meta_only' && googleBrowserAllowed) {\n";
			echo "      var trackers = (window.alvobot_pixel_config && window.alvobot_pixel_config.google_trackers) || [];\n";
			echo "      var siteCurrency = (window.alvobot_pixel_config && window.alvobot_pixel_config.site_currency) || 'BRL';\n";
			echo "      var labelsMap = cfg.gads_labels_map || {};\n";
			echo "      trackers.forEach(function(t) {\n";
			echo "        if (t.type !== 'google_ads') return;\n";
			echo "        if (filterActive && selectedIds.indexOf(t.tracker_id) === -1) return;\n";
			echo "        var label = labelsMap[t.tracker_id] || cfg.gads_conversion_label || t.conversion_label;\n";
			echo "        if (!label) return;\n";
			echo "        var p = { send_to: t.tracker_id + '/' + label };\n";
			echo "        p.transport_type = 'beacon';\n";
			echo "        p.event_timeout = 2000;\n";
			echo "        p.event_callback = function(){};\n";
			echo "        if (cfg.gads_conversion_value) { p.value = parseFloat(cfg.gads_conversion_value) || 0; p.currency = siteCurrency; }\n";
			echo "        window.gtag('event', 'conversion', p);\n";
			echo "      });\n";
			echo "    }\n";
			// Also queue send_event for Meta CAPI / server-side when tracker is ready.
			echo "    var trackerPayload = {};\n";
			echo "    for (var key in cfg) {\n";
			echo "      if (Object.prototype.hasOwnProperty.call(cfg, key)) {\n";
			echo "        trackerPayload[key] = cfg[key];\n";
			echo "      }\n";
			echo "    }\n";
			echo "    trackerPayload.skip_google_ads = true;\n";
			echo "    if (tracker && tracker.initialized) { tracker.send_event(trackerPayload); }\n";
			echo "    else if (tracker && typeof tracker.ready === 'function') {\n";
			echo "      tracker.ready().then(function() { tracker.send_event(trackerPayload); });\n";
			echo "    }\n";
			echo "  }\n";
			// Rewrite send_event calls to use the queuing wrapper.
			$patched = str_replace( 'window.alvobot_pixel.send_event(', 'alvobotAdSendEvent(', implode( "\n", $ad_scripts ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $patched contains generated JS snippets for inline execution.
			echo $patched . "\n";
			echo "})();\n";
			echo "</script>\n";
		}

		// Other triggers (page_load, click, scroll, etc.): keep original DOMContentLoaded + ready() flow.
		if ( ! empty( $other_scripts ) ) {
			echo "\n<script>\n";
			echo "document.addEventListener('DOMContentLoaded', function() {\n";
			echo "  if (!window.alvobot_pixel || !window.alvobot_pixel.ready) return;\n";
			echo "  window.alvobot_pixel.ready().then(function() {\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $other_scripts contains generated JS snippets for inline execution.
			echo implode( "\n", $other_scripts );
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
    try { document.querySelectorAll({$selector_literal}); } catch (e) { return; }
    document.addEventListener('submit', function(ev) {
      if (tracked_{$safe_id}) return;
      var target = ev.target;
      if (!target || target.nodeType !== 1) return;
      try {
        if (!target.matches({$selector_literal}) && !target.closest({$selector_literal})) return;
      } catch (err) { return; }
      tracked_{$safe_id} = true;
      window.alvobot_pixel.send_event({$event_config});
    }, true);
  })();";

				case 'click':
					$selector = trim( (string) $selector );
					if ( '' === $selector ) {
						return '';
					}
					$selector_literal = wp_json_encode( $selector );
					return "  (function() {
    try { document.querySelectorAll({$selector_literal}); } catch (e) { return; }
    document.addEventListener('click', function(ev) {
      var target = ev.target;
      if (!target || target.nodeType !== 1) return;
      try {
        target = target.closest({$selector_literal});
      } catch (err) { return; }
      if (!target) return;
      window.alvobot_pixel.send_event({$event_config});
    }, true);
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
    function bind_{$safe_id}() {
      var elements = [];
      try { elements = document.querySelectorAll({$selector_literal}); } catch (e) { return; }
      elements.forEach(function(el) {
        if (el.classList.contains('alvobot_observed_{$safe_id}')) return;
        el.classList.add('alvobot_observed_{$safe_id}');
        observer.observe(el);
      });
    }
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting && !entry.target.classList.contains('alvobot_tracked_{$safe_id}')) {
          entry.target.classList.add('alvobot_tracked_{$safe_id}');
          window.alvobot_pixel.send_event({$event_config});
        }
      });
    }, { threshold: 0.5 });
    bind_{$safe_id}();
    if (typeof MutationObserver !== 'undefined' && document.documentElement) {
      var mutationObserver = new MutationObserver(function() {
        bind_{$safe_id}();
      });
      mutationObserver.observe(document.documentElement, { childList: true, subtree: true });
    } else {
      setInterval(bind_{$safe_id}, 5000);
    }
  })();";

			case 'ad_impression':
			case 'ad_click':
			case 'ad_vignette_open':
			case 'ad_vignette_click':
				$trigger_literal = wp_json_encode( $trigger );
				return "  document.addEventListener('alvobot:ad_event', function(e) {
    if (!e.detail || e.detail.event_name !== {$trigger_literal}) return;
    var payload = {$event_config};
    payload.event_id_override = e.detail.event_id || '';
    payload.custom_data_extra = {
      ad_position: e.detail.ad_position || '',
      ad_slot_id: e.detail.ad_slot_id || ''
    };
    window.alvobot_pixel.send_event(payload);
  });";

			default:
				return '';
		}
	}

	/**
	 * Get active ad conversion trigger types as a set.
	 *
	 * Returns an array of trigger types that have active conversion rules,
	 * so the ad-tracker can skip direct dispatch only for those specific event types
	 * (avoiding double-dispatch) while still dispatching other ad events directly.
	 */
	private function get_active_ad_conversion_triggers() {
		static $result = null;
		if ( null !== $result ) {
			return $result;
		}
		$result      = array();
		$conversions = $this->module->cpt->get_conversions();
		foreach ( $conversions as $conv ) {
			$trigger = get_post_meta( $conv->ID, '_trigger_type', true );
			if ( $trigger && strpos( $trigger, 'ad_' ) === 0 ) {
				$result[ $trigger ] = true;
			}
		}
		return $result;
	}

	/**
	 * Check if ANY ad conversion rules exist (backward compat).
	 */
	private function has_active_ad_conversions() {
		return ! empty( $this->get_active_ad_conversion_triggers() );
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
	 * Fallback: inject tracking scripts directly if wp_enqueue_scripts was blocked
	 * by cache/optimization plugins (e.g. LiteSpeed Cache).
	 */
	public function inject_scripts_fallback() {
		if ( ! $this->should_track() ) {
			return;
		}

		// Check if scripts were already enqueued normally
		if ( wp_script_is( 'alvobot-pixel-tracking', 'done' ) || wp_script_is( 'alvobot-pixel-tracking', 'enqueued' ) ) {
			return;
		}

		$module_dir = plugin_dir_path( __FILE__ );
		$module_url = plugin_dir_url( __FILE__ );

		if ( file_exists( $module_dir . 'assets/js/tracking.js' ) ) {
			$ver = (string) filemtime( $module_dir . 'assets/js/tracking.js' );
			echo '<script defer src="' . esc_url( $module_url . 'assets/js/tracking.js?ver=' . $ver ) . '" data-no-optimize="1" data-no-minify="1"></script>' . "\n";
		}
		if ( file_exists( $module_dir . 'assets/js/ad-tracker.js' ) ) {
			$ver = (string) filemtime( $module_dir . 'assets/js/ad-tracker.js' );
			echo '<script defer src="' . esc_url( $module_url . 'assets/js/ad-tracker.js?ver=' . $ver ) . '" data-no-optimize="1" data-no-minify="1"></script>' . "\n";
		}
	}

	/**
	 * Check if tracking should be injected for the current request.
	 */
	private function should_track() {
		$settings   = $this->module->get_settings();
		$pixels     = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
		$has_google = ! empty( $this->get_google_trackers_from_settings( $settings ) );

		// No tracking configured
		if ( empty( $pixels ) && ! $has_google ) {
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

	/**
	 * Keep only active conversion target IDs for runtime rendering.
	 *
	 * @param string $raw_targets Raw CSV target IDs.
	 * @return string[]
	 */
	private function filter_active_conversion_target_ids( $raw_targets ) {
		$settings = $this->module->get_settings();
		$active_ids = array();

		$pixels = isset( $settings['pixels'] ) && is_array( $settings['pixels'] ) ? $settings['pixels'] : array();
		foreach ( $pixels as $pixel ) {
			if ( ! empty( $pixel['pixel_id'] ) ) {
				$active_ids[] = (string) $pixel['pixel_id'];
			}
		}

		$google_trackers = $this->get_google_trackers_from_settings( $settings );
		foreach ( $google_trackers as $tracker ) {
			if ( ! empty( $tracker['tracker_id'] ) ) {
				$active_ids[] = (string) $tracker['tracker_id'];
			}
		}

		$active_lookup = array_fill_keys( array_unique( $active_ids ), true );
		$target_ids    = array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw_targets ) ) ) );

		return array_values(
			array_filter(
				$target_ids,
				function ( $target_id ) use ( $active_lookup ) {
					return isset( $active_lookup[ $target_id ] );
				}
			)
		);
	}

	/**
	 * Derive runtime platforms from the filtered target IDs.
	 *
	 * @param string[] $target_ids Target IDs.
	 * @return string
	 */
	private function derive_platforms_from_target_ids( $target_ids ) {
		$has_meta   = false;
		$has_google = false;

		foreach ( $target_ids as $target_id ) {
			if ( preg_match( '/^\d{15,16}$/', $target_id ) ) {
				$has_meta = true;
			}
			if ( preg_match( '/^(G-|AW-)/', $target_id ) || 'sitekit_gtag' === $target_id ) {
				$has_google = true;
			}
		}

		if ( $has_meta && ! $has_google ) {
			return 'meta_only';
		}
		if ( $has_google && ! $has_meta ) {
			return 'google_only';
		}

		return 'all';
	}

	/**
	 * Remove Google Ads labels for inactive trackers before rendering.
	 *
	 * @param mixed    $labels_map Raw labels map.
	 * @param string[] $target_ids Filtered target IDs.
	 * @return array
	 */
	private function filter_gads_labels_map_for_targets( $labels_map, $target_ids ) {
		if ( ! is_array( $labels_map ) ) {
			return array();
		}

		$target_lookup = array_fill_keys( $target_ids, true );
		$filtered      = array();
		foreach ( $labels_map as $tracker_id => $label ) {
			if ( isset( $target_lookup[ $tracker_id ] ) ) {
				$filtered[ $tracker_id ] = $label;
			}
		}

		return $filtered;
	}

	/**
	 * Build the frontend Google tracker list, including legacy flat settings.
	 *
	 * @param array $settings Module settings.
	 * @return array<int,array<string,string>>
	 */
	private function get_google_trackers_from_settings( $settings ) {
		$google_trackers = isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ? $settings['google_trackers'] : array();

		if ( empty( $google_trackers ) ) {
			$legacy_ga  = isset( $settings['google_analytics_id'] ) ? sanitize_text_field( (string) $settings['google_analytics_id'] ) : '';
			$legacy_ads = isset( $settings['google_ads_id'] ) ? sanitize_text_field( (string) $settings['google_ads_id'] ) : '';

			if ( $legacy_ga && preg_match( '/^G-[A-Z0-9]{7,12}$/', $legacy_ga ) ) {
				$google_trackers[] = array(
					'tracker_id'       => $legacy_ga,
					'type'             => 'ga4',
					'label'            => 'Google Analytics',
					'conversion_label' => '',
				);
			}

			if ( $legacy_ads && preg_match( '/^AW-\d{7,12}$/', $legacy_ads ) ) {
				$google_trackers[] = array(
					'tracker_id'       => $legacy_ads,
					'type'             => 'google_ads',
					'label'            => 'Google Ads',
					'conversion_label' => isset( $settings['google_ads_conversion_label'] ) ? sanitize_text_field( (string) $settings['google_ads_conversion_label'] ) : '',
				);
			}
		}

		return array_values(
			array_filter(
				$google_trackers,
				function ( $gt ) {
					return ! empty( $gt['tracker_id'] );
				}
			)
		);
	}

	/**
	 * Resolve the best-available site currency code for browser-side Google events.
	 *
	 * @return string
	 */
	private function get_site_currency_code() {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = strtoupper( trim( (string) get_woocommerce_currency() ) );
			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				return $currency;
			}
		}

		$settings = $this->module->get_settings();
		if ( ! empty( $settings['default_currency'] ) && preg_match( '/^[A-Z]{3}$/', $settings['default_currency'] ) ) {
			return $settings['default_currency'];
		}

		return 'USD';
	}
}
