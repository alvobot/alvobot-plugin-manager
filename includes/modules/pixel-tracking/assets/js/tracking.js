/**
 * AlvoBot Pixel Tracking - Browser Tracking Script
 *
 * Handles Facebook Pixel SDK, data capture, and event dispatch.
 */
(function () {
	'use strict';

			class AlvoBotPixelTracking {
					constructor() {
						this.data        = {};
						this.config      = {};
						this.initialized = false;
						this.tracking_enabled = false;
						this.meta_browser_allowed = false;
						this.google_browser_allowed = false;
						this.google_consent_state = '';
						this.initial_pageview_sent = false;
						this._readyPromise = null;
					this._resolveReady = null;
					this._readyResolved = false;
					this.debug_enabled = false;
					this.debug_prefix  = '[AlvoBot Pixel][FRONT]';
				}

		/**
		 * Returns a promise that resolves when the tracker has finished initializing.
		 */
				ready() {
					if (this.initialized || this._readyResolved) {
						this.log_debug( 'ready(): already initialized' );
						return Promise.resolve();
					}
				if ( ! this._readyPromise) {
					var self = this;
					this._readyPromise = new Promise( function (resolve) {
						self._resolveReady = resolve;
					});
					this.log_debug( 'ready(): promise created' );
					}
					return this._readyPromise;
				}

				resolve_ready() {
					if (this._readyResolved) {
						return;
					}
					this._readyResolved = true;
					if (this._resolveReady) {
						this._resolveReady();
						this._resolveReady = null;
					}
				}

			clone_for_debug(value) {
				try {
					return JSON.parse( JSON.stringify( value ) );
				} catch (e) {
					return value;
				}
			}

			log_debug(message, payload) {
				if ( ! this.debug_enabled || ! window.console || ! window.console.log) {
					return;
				}
				if (typeof payload === 'undefined') {
					window.console.log( this.debug_prefix + ' ' + message );
					return;
				}
				window.console.log( this.debug_prefix + ' ' + message, this.clone_for_debug( payload ) );
			}

			log_warn(message, payload) {
				if ( ! this.debug_enabled || ! window.console || ! window.console.warn) {
					return;
				}
				if (typeof payload === 'undefined') {
					window.console.warn( this.debug_prefix + ' ' + message );
					return;
				}
				window.console.warn( this.debug_prefix + ' ' + message, this.clone_for_debug( payload ) );
			}

			log_error(message, payload) {
				if ( ! this.debug_enabled || ! window.console || ! window.console.error) {
					return;
				}
				if (typeof payload === 'undefined') {
					window.console.error( this.debug_prefix + ' ' + message );
					return;
				}
				window.console.error( this.debug_prefix + ' ' + message, this.clone_for_debug( payload ) );
			}

		/**
		 * Initialize tracking with config from PHP inline script.
		 */
				async start(config) {
					this.config = config || window.alvobot_pixel_config || {};
					this.debug_enabled = !! this.config.debug_enabled;
				this.log_debug(
					'start() called',
					{
						href: window.location.href,
						page_title: document.title,
						config: this.config,
					}
				);

					var hasGoogleTrackers = this.config.google_trackers && this.config.google_trackers.length > 0;
					if ( ! this.config.pixel_ids && ! this.config.google_analytics_id && ! this.config.google_ads_id && ! hasGoogleTrackers) {
						this.log_warn( 'start aborted: no tracking IDs configured' );
						this.tracking_enabled = false;
						this.resolve_ready();
						return;
					}

					// Check if bot
					if (this.is_bot()) {
						this.log_warn( 'start aborted: bot detected', { user_agent: navigator.userAgent } );
						this.tracking_enabled = false;
						this.resolve_ready();
						return;
					}

						this.tracking_enabled = true;
						this.refresh_meta_pixel_state();
						this.refresh_google_tag_state();

				// Capture visitor data
				this.data.fbp        = this.get_fbp();
				this.data.fbc        = this.get_fbc();
				this.data.utms       = this.get_utms();
					this.data.user_agent = navigator.userAgent;

						// Capture Google click identifiers for browser dispatch, diagnostics, and future server uploads.
						this.data.gclid = this.get_click_id( 'gclid', '_alvo_gclid' );
						this.data.gbraid = this.get_click_id( 'gbraid', '_alvo_gbraid' );
						this.data.wbraid = this.get_click_id( 'wbraid', '_alvo_wbraid' );
						this.data.dclid = this.get_click_id( 'dclid', '_alvo_dclid' );

						// Capture GA4 client_id for Measurement Protocol matching (Phase 2 prep)
						this.data.ga_client_id = '';
					this.capture_ga_client_id();

				this.log_debug(
					'visitor base data captured',
					{
							fbp: this.data.fbp,
							fbc: this.data.fbc,
							gclid: this.data.gclid,
							gbraid: this.data.gbraid,
							wbraid: this.data.wbraid,
							dclid: this.data.dclid,
							utms: this.data.utms,
							user_agent: this.data.user_agent,
						}
				);

					// Start form monitoring for lead capture
					this.monitor_forms();

					// Always dispatch a default PageView through browser + server with shared event_id.
					this.dispatch_initial_pageview();

					this.initialized = true;
					this.resolve_ready();
					this.log_debug( 'tracker initialized', { initialized: this.initialized } );
					this.capture_async_context();
				}

					capture_ga_client_id() {
						var ga4TrackerId = this.get_primary_ga4_tracker_id();
						if ( ! window.gtag || ! ga4TrackerId) {
							return;
						}

						var self = this;
						try {
							gtag( 'get', ga4TrackerId, 'client_id', function (cid) {
								self.data.ga_client_id = cid || '';
								self.log_debug( 'GA4 client_id captured', { tracker_id: ga4TrackerId, client_id: cid } );
							});
						} catch (e) {
							this.log_error( 'gtag get client_id failed', e && e.message ? e.message : e );
					}
				}

				async capture_async_context() {
					var self = this;
					var timeoutHandle = null;
					var asyncTimeout = new Promise( function (resolve) {
						timeoutHandle = setTimeout( function () {
							self.log_warn( 'async capture timed out after 8s — continuing without IP/geo' );
							resolve( 'timeout' );
						}, 8000 );
					});
					var asyncCapture = (async function () {
						try {
							self.data.ip = await self.get_ip();
						} catch (e) {
							self.data.ip = '';
							self.log_error( 'get_ip() threw', e && e.message ? e.message : e );
						}

						try {
							self.data.geolocation = await self.get_geolocation();
						} catch (e) {
							self.data.geolocation = {};
							self.log_error( 'get_geolocation() threw', e && e.message ? e.message : e );
						}
						return 'done';
					})();
						await Promise.race( [asyncCapture, asyncTimeout] );
						if (timeoutHandle) {
							clearTimeout( timeoutHandle );
						}
						this.log_debug( 'async capture complete', { ip: this.data.ip, geolocation: this.data.geolocation } );
					}

			/**
			 * Initialize Facebook Pixel SDK.
			 *
			 * The PHP base code may have already injected fbevents.js and called fbq('init')
			 * for every pixel synchronously in <head>. This method is also the runtime
			 * fallback for cases where Meta browser tracking only becomes allowed after
			 * consent is granted in the browser.
			 */
				init_fb_sdk(pixel_ids_str) {
				var ids = pixel_ids_str.split( ',' ).map(
					function (s) {
						return s.trim(); }
				).filter( Boolean ).filter(
					function (id, index, arr) {
						return arr.indexOf( id ) === index;
					}
				);
				if ( ! ids.length) {
					this.log_warn( 'init_fb_sdk aborted: no valid pixel IDs', { pixel_ids_str: pixel_ids_str } );
					return;
				}
				this.log_debug( 'init_fb_sdk', { requested: pixel_ids_str, deduped_ids: ids } );

				// If fbq stub already exists (base code ran in <head>), skip stub creation
				// and SDK script injection — fbevents.js is already loading.
				if ( ! window.fbq) {
					this.log_debug( 'loading Facebook SDK script (base code was not present)' );
					var n = window.fbq = function () {
					n.callMethod ? n.callMethod.apply( n, arguments ) : n.queue.push( arguments );
				};
				if ( ! window._fbq) {
					window._fbq = n;
				}
				n.push    = n;
				n.loaded  = true;
				n.version = '2.0';
				n.queue   = [];

				var t   = document.createElement( 'script' );
				t.async = true;
				t.src   = 'https://connect.facebook.net/en_US/fbevents.js';
				var s   = document.getElementsByTagName( 'script' )[0];
				if (s && s.parentNode) {
					s.parentNode.insertBefore( t, s );
				}

					// fbq('init') not yet called — do it now for each pixel.
					for (var i = 0; i < ids.length; i++) {
						window.fbq( 'init', ids[i] );
						this.log_debug( 'fbq init (late — base code absent)', { pixel_id: ids[i] } );
					}
					} else {
						this.log_debug( 'fbq already present from base code — skipping stub + SDK load', { ids: ids } );
					}
				}

					refresh_meta_pixel_state() {
					if ( ! this.config.pixel_ids) {
						this.meta_browser_allowed = false;
						return false;
					}

					var consentGranted = ! this.config.consent_check || this.check_consent();
					if (consentGranted && ! this.meta_browser_allowed) {
						this.log_debug( 'Meta browser tracking enabled' );
					}
					if ( ! consentGranted && this.meta_browser_allowed) {
						this.log_warn( 'Meta browser tracking disabled after consent change' );
					}

					this.meta_browser_allowed = consentGranted;
					if (this.meta_browser_allowed) {
						this.init_fb_sdk( this.config.pixel_ids );
					}

						return this.meta_browser_allowed;
					}

					get_google_trackers() {
						var trackers = Array.isArray( this.config.google_trackers ) ? this.config.google_trackers.slice() : [];
						if ( ! trackers.length) {
							if (this.config.google_analytics_id) {
								trackers.push(
									{
										tracker_id: this.config.google_analytics_id,
										type: 'ga4',
										conversion_label: '',
									}
								);
							}
							if (this.config.google_ads_id) {
								trackers.push(
									{
										tracker_id: this.config.google_ads_id,
										type: 'google_ads',
										conversion_label: this.config.google_ads_conversion_label || '',
									}
								);
							}
						}

						return trackers.filter(
							function (tracker) {
								if ( ! tracker || ! tracker.tracker_id) {
									return false;
								}

								if (tracker.type === 'external' && tracker.tracker_id === 'sitekit_gtag') {
									return true;
								}

								return /^(G-|AW-)/.test( tracker.tracker_id );
							}
						);
					}

					has_google_trackers() {
						return this.get_google_trackers().length > 0;
					}

					get_primary_ga4_tracker_id() {
						var trackers = this.get_google_trackers();
						for (var i = 0; i < trackers.length; i++) {
							if (trackers[i] && trackers[i].type === 'ga4' && trackers[i].tracker_id) {
								return trackers[i].tracker_id;
							}
						}

						return this.config.google_analytics_id || '';
					}

					update_google_consent_mode(consentGranted) {
						if ( ! window.gtag) {
							return;
						}

						var state = consentGranted ? 'granted' : 'denied';
						if (this.google_consent_state === state) {
							return;
						}

						window.gtag(
							'consent',
							'update',
							{
								ad_storage: state,
								analytics_storage: state,
								ad_user_data: state,
								ad_personalization: state,
							}
						);
						this.google_consent_state = state;
						this.log_debug( 'Google consent mode updated', { state: state } );
					}

					refresh_google_tag_state() {
						if ( ! this.has_google_trackers()) {
							this.google_browser_allowed = false;
							return false;
						}

						var consentGranted = ! this.config.consent_check || this.check_consent();
						if (consentGranted && ! this.google_browser_allowed) {
							this.log_debug( 'Google browser tracking enabled' );
						}
						if ( ! consentGranted && this.google_browser_allowed) {
							this.log_warn( 'Google browser tracking disabled after consent change' );
						}

						this.google_browser_allowed = consentGranted;
						this.update_google_consent_mode( consentGranted );
						return this.google_browser_allowed;
					}

		/**
		 * Send the default PageView once per page load.
		 *
		 * When the PHP base code is present, fbq('track','PageView') was already
		 * called synchronously in <head> with a server-generated event_id.
		 * Here we only POST to the CAPI endpoint using that same event_id so that
		 * Meta can deduplicate the browser and server events — no double count.
		 *
			 * When the base code was NOT output, send_event() still sends the server-side
			 * event immediately and only fires the Meta browser event if consent is
			 * currently granted.
		 */
			dispatch_initial_pageview() {
				if (this.initial_pageview_sent) {
					this.log_debug( 'initial PageView skipped: already sent' );
					return;
				}

				this.initial_pageview_sent = true;

						var pageview_params = {
							event_name: 'PageView',
							event_custom: false,
							content_name: this.config.page_title || document.title || '',
							fb_pixels: this.config.pixel_ids || '',
							is_initial_pageview: true,
						};

					if ( ! this.config.pixel_ids) {
						pageview_params.platforms = 'google_only';
					}

					if (this.config.pageview_event_id) {
					// Base code already fired fbq() in <head> — only send CAPI.
					this.log_debug( 'initial PageView: base code present, sending CAPI only', { event_id: this.config.pageview_event_id } );
					pageview_params.event_id_override = this.config.pageview_event_id;
					pageview_params.skip_fbq = true;
					} else {
						// No base code — server-side always proceeds; Meta browser dispatch
						// only fires if consent is currently granted.
						this.log_debug( 'initial PageView: base code absent, sending with runtime Meta consent check' );
					}

				this.send_event( pageview_params );
			}

		/**
		 * Get visitor IP via Cloudflare cdn-cgi/trace.
		 */
			async get_ip() {
				var cachedIp = '';
				this.log_debug( 'get_ip(): start' );
				try {
					cachedIp = sessionStorage.getItem( 'alvobot_ip' ) || '';
				} catch (e) {
					cachedIp = '';
				}

				if (cachedIp) {
					if ( ! this.is_private_ip( cachedIp )) {
						this.log_debug( 'get_ip(): using cached public IP', { ip: cachedIp } );
						return cachedIp;
					}
					this.log_warn( 'get_ip(): cached IP is private/reserved; removing', { ip: cachedIp } );
					try {
						sessionStorage.removeItem( 'alvobot_ip' );
					} catch (e) {
						/* ignore */ }
				}

				if (this.config.cf_trace_enabled) {
					this.log_debug( 'get_ip(): trying /cdn-cgi/trace' );
					try {
						var cfResp  = await this.fetch_with_timeout( '/cdn-cgi/trace', {}, 2500 );
						if (cfResp && cfResp.ok) {
							var cfText  = await cfResp.text();
							var cfMatch = cfText.match( /ip=([^\n]+)/ );
							if (cfMatch && cfMatch[1] && ! this.is_private_ip( cfMatch[1] )) {
								this.log_debug( 'get_ip(): resolved by Cloudflare trace', { ip: cfMatch[1] } );
								try {
									sessionStorage.setItem( 'alvobot_ip', cfMatch[1] );
								} catch (e) {
									/* ignore */ }
								return cfMatch[1];
							}
							this.log_warn( 'get_ip(): Cloudflare trace had no usable IP' );
						}
					} catch (e) {
						this.log_warn( 'get_ip(): Cloudflare trace request failed', e && e.message ? e.message : e );
						/* continue fallback */ }
				}

				try {
					this.log_debug( 'get_ip(): trying api64.ipify.org' );
					var ipifyResp = await this.fetch_with_timeout( 'https://api64.ipify.org?format=json', {}, 2500 );
					if (ipifyResp && ipifyResp.ok) {
						var ipifyData = await ipifyResp.json();
						if (ipifyData && ipifyData.ip && ! this.is_private_ip( ipifyData.ip )) {
							this.log_debug( 'get_ip(): resolved by ipify', { ip: ipifyData.ip } );
							try {
								sessionStorage.setItem( 'alvobot_ip', ipifyData.ip );
							} catch (e) {
								/* ignore */ }
							return ipifyData.ip;
						}
					}
				} catch (e) {
					this.log_warn( 'get_ip(): ipify request failed', e && e.message ? e.message : e );
					/* continue fallback */ }

				try {
					this.log_debug( 'get_ip(): trying ipwho.is' );
					var ipwhoResp = await this.fetch_with_timeout( 'https://ipwho.is/', {}, 3000 );
					if (ipwhoResp && ipwhoResp.ok) {
						var ipwhoData = await ipwhoResp.json();
						if (ipwhoData && ipwhoData.ip && ! this.is_private_ip( ipwhoData.ip )) {
							this.log_debug( 'get_ip(): resolved by ipwho.is', { ip: ipwhoData.ip } );
							try {
								sessionStorage.setItem( 'alvobot_ip', ipwhoData.ip );
							} catch (e) {
								/* ignore */ }
							return ipwhoData.ip;
						}
					}
				} catch (e) {
					this.log_warn( 'get_ip(): ipwho.is request failed', e && e.message ? e.message : e );
					/* ignore */ }

				this.log_warn( 'get_ip(): failed to resolve a public IP' );
				return '';
			}

		/**
		 * Fetch helper with timeout support.
		 */
			async fetch_with_timeout(url, options, timeoutMs) {
				var opts = options || {};
				var ms   = timeoutMs || 3000;
				this.log_debug( 'fetch_with_timeout()', { url: url, timeout_ms: ms } );

			if (typeof AbortController === 'undefined') {
				return fetch( url, opts );
			}

			var controller = new AbortController();
			var timer      = setTimeout(
				function () {
					controller.abort();
				},
				ms
			);

			try {
				return await fetch(
					url,
					Object.assign(
						{},
						opts,
						{
							signal: controller.signal,
						}
					)
				);
			} finally {
				clearTimeout( timer );
			}
		}

		/**
		 * Get geolocation via ip-api.com Pro with ipwho.is fallback.
		 * Results are cached in localStorage per IP with 24h TTL.
		 * If browser geo fails, the server-side fallback handles it.
		 */
			async get_geolocation() {
				var ip = this.data.ip;
				if ( ! ip) {
					this.log_warn( 'get_geolocation(): skipped because IP is empty' );
					return {};
				}
				this.log_debug( 'get_geolocation(): start', { ip: ip } );

			var GEO_CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours

			// Check localStorage cache with TTL
			var cacheKey = 'alvobot_geo_' + ip;
			try {
				var cached = localStorage.getItem( cacheKey );
					if (cached) {
						var parsed = JSON.parse( cached );
						if (parsed && parsed.city && parsed._ts && (Date.now() - parsed._ts) < GEO_CACHE_TTL) {
							this.log_debug( 'get_geolocation(): cache hit', parsed );
							return parsed;
						}
						localStorage.removeItem( cacheKey );
					}
			} catch (e) {
				/* ignore */ }

			var geo = null;
			var geoKey = (this.config && this.config.geo_api_key) || '';

			// Primary: ipwho.is (HTTPS, free, no key required — works on all sites)
				try {
					this.log_debug( 'get_geolocation(): trying ipwho.is' );
					var resp2 = await this.fetch_with_timeout( 'https://ipwho.is/' + ip, {}, 5000 );
					if (resp2.ok) {
						var data2 = await resp2.json();
						if (data2 && data2.success && data2.city) {
						geo = {
							city: data2.city,
							state: data2.region,
							country: data2.country,
							country_code: data2.country_code,
							zipcode: data2.postal || '',
							currency: data2.currency && data2.currency.code ? data2.currency.code : '',
								timezone: data2.timezone && data2.timezone.id ? data2.timezone.id : '',
								_ts: Date.now(),
							};
							this.log_debug( 'get_geolocation(): resolved via ipwho.is', geo );
						}
					}
				} catch (e) {
					this.log_warn( 'get_geolocation(): ipwho.is failed', e && e.message ? e.message : e );
				}

			// Fallback: ip-api.com Pro (only when geo_api_key is configured — free tier is HTTP-only)
				if ( ! geo && geoKey) {
					try {
						this.log_debug( 'get_geolocation(): trying ip-api.com Pro' );
						var geoUrl = 'https://pro.ip-api.com/json/' + ip + '?key=' + geoKey + '&fields=status,city,regionName,country,countryCode,zip,currency,timezone';
						var resp = await this.fetch_with_timeout( geoUrl, {}, 5000 );
						if (resp.ok) {
							var data = await resp.json();
							if (data && data.status === 'success' && data.city) {
							geo = {
								city: data.city,
								state: data.regionName,
								country: data.country,
								country_code: data.countryCode,
								zipcode: data.zip || '',
								currency: data.currency || '',
									timezone: data.timezone || '',
									_ts: Date.now(),
								};
								this.log_debug( 'get_geolocation(): resolved via ip-api.com Pro', geo );
							}
						}
					} catch (e) {
						this.log_warn( 'get_geolocation(): ip-api.com Pro failed', e && e.message ? e.message : e );
					}
				}

			if (geo) {
					try {
						localStorage.setItem( cacheKey, JSON.stringify( geo ) );
					} catch (e) {
						/* ignore */ }
					this.log_debug( 'get_geolocation(): final geo', geo );
					return geo;
				}

				this.log_warn( 'get_geolocation(): no geo available' );
				return {};
			}

		/**
		 * Get or generate _fbp cookie.
		 */
			get_fbp() {
				var match = document.cookie.match( /(^|;)\s*_fbp=([^;]+)/ );
				if (match) {
					this.log_debug( 'get_fbp(): cookie found', { fbp: match[2] } );
					return match[2];
				}

			// Generate fbp
			var ts   = Date.now();
				var rand = Math.floor( Math.random() * 10000000000 );
				var fbp  = 'fb.1.' + ts + '.' + rand;
				this.set_cookie( '_fbp', fbp );
				this.log_debug( 'get_fbp(): generated new fbp', { fbp: fbp } );
				return fbp;
			}

		/**
		 * Get or generate _fbc from fbclid URL param.
		 */
			get_fbc() {
				var match = document.cookie.match( /(^|;)\s*_fbc=([^;]+)/ );
				if (match) {
					this.log_debug( 'get_fbc(): cookie found', { fbc: match[2] } );
					return match[2];
				}

			// Check for fbclid in URL
			var params = new URLSearchParams( window.location.search );
				var fbclid = params.get( 'fbclid' );
				if (fbclid) {
					var fbc = 'fb.1.' + Date.now() + '.' + fbclid;
					this.set_cookie( '_fbc', fbc );
					this.log_debug( 'get_fbc(): built from fbclid', { fbc: fbc, fbclid: fbclid } );
					return fbc;
				}

				this.log_debug( 'get_fbc(): no value resolved' );
				return '';
			}

		/**
		 * Read a cookie value safely.
		 */
			get_cookie_value(name) {
				var escaped = String( name ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
				var match = document.cookie.match( new RegExp( '(^|;)\\s*' + escaped + '=([^;]+)' ) );
				if ( ! match) {
					return '';
				}

				try {
					return decodeURIComponent( match[2] );
				} catch (e) {
					return match[2];
				}
			}

		/**
		 * Capture and persist Google click identifiers from URL or first-party cookie.
		 */
			get_click_id(paramName, cookieName) {
				var params = new URLSearchParams( window.location.search );
				var value  = params.get( paramName );
				if (value) {
					this.set_cookie( cookieName, value, 7776000 ); // 90 days
					this.log_debug( 'get_click_id(): captured from URL', { key: paramName, value: value } );
					return value;
				}

				value = this.get_cookie_value( cookieName );
				if (value) {
					this.log_debug( 'get_click_id(): cookie found', { key: paramName, value: value } );
					return value;
				}

				this.log_debug( 'get_click_id(): no value resolved', { key: paramName } );
				return '';
			}

		/**
		 * Backward-compatible wrapper for existing gclid call sites.
		 */
			get_gclid() {
				return this.get_click_id( 'gclid', '_alvo_gclid' );
			}

		/**
		 * Normalize currency codes to ISO-4217-ish three-letter uppercase format.
		 */
			normalize_currency_code(currency) {
				if ( ! currency) {
					return '';
				}

				var normalized = String( currency ).trim().toUpperCase();
				return /^[A-Z]{3}$/.test( normalized ) ? normalized : '';
			}

		/**
		 * Resolve the currency to use for browser-side Google events.
		 */
			resolve_google_currency(params, custom_data) {
				var candidates = [
					params && params.currency ? params.currency : '',
					custom_data && custom_data.currency ? custom_data.currency : '',
					this.config && this.config.site_currency ? this.config.site_currency : '',
					this.data && this.data.geolocation && this.data.geolocation.currency ? this.data.geolocation.currency : '',
					'BRL',
				];

				for (var i = 0; i < candidates.length; i++) {
					var normalized = this.normalize_currency_code( candidates[i] );
					if (normalized) {
						return normalized;
					}
				}

				return 'BRL';
			}

		/**
		 * Resolve numeric conversion value when available.
		 */
			resolve_google_value(params, custom_data) {
				var candidates = [
					params && typeof params.gads_conversion_value !== 'undefined' ? params.gads_conversion_value : '',
					params && typeof params.value !== 'undefined' ? params.value : '',
					custom_data && typeof custom_data.value !== 'undefined' ? custom_data.value : '',
				];

				for (var i = 0; i < candidates.length; i++) {
					if (candidates[i] === '' || candidates[i] === null || typeof candidates[i] === 'undefined') {
						continue;
					}

					var parsed = parseFloat( candidates[i] );
					if ( ! isNaN( parsed ) && isFinite( parsed )) {
						return parsed;
					}
				}

				return null;
			}

		/**
		 * Build shared browser-side Google event params.
		 */
			build_google_event_params(params, tracker, event_name, custom_data, is_ads_conversion) {
				var googleParams = {};
				if (tracker && tracker.tracker_id && tracker.type !== 'external') {
					googleParams.send_to = tracker.tracker_id;
				}

				googleParams.page_location = window.location.href;
				googleParams.page_title = this.config.page_title || document.title || '';

				if (params.content_name || custom_data.content_name) {
					googleParams.content_name = params.content_name || custom_data.content_name;
				}
				if (custom_data.content_type) {
					googleParams.content_type = custom_data.content_type;
				}
				if (custom_data.content_category) {
					googleParams.content_category = custom_data.content_category;
				}
				if (custom_data.ad_position) {
					googleParams.ad_position = custom_data.ad_position;
				}
				if (custom_data.ad_slot_id) {
					googleParams.ad_slot_id = custom_data.ad_slot_id;
				}
				if (this.data.lead_id) {
					googleParams.lead_id = this.data.lead_id;
				}

				var numericValue = this.resolve_google_value( params, custom_data );
				if (null !== numericValue) {
					googleParams.value = numericValue;
					googleParams.currency = this.resolve_google_currency( params, custom_data );
				}

				if (is_ads_conversion) {
					googleParams.transport_type = 'beacon';
					googleParams.event_timeout = 2000;
					googleParams.event_callback = function () {};
				}

				if (event_name === 'purchase' && ! googleParams.transaction_id) {
					googleParams.transaction_id = params.event_id_override || '';
				}

				return googleParams;
			}

		/**
		 * Map Meta standard event names to GA4 recommended event names.
		 */
			map_to_ga4_event(meta_event) {
				var map = {
					'PageView': 'page_view',
					'ViewContent': 'view_item',
					'Lead': 'generate_lead',
					'CompleteRegistration': 'sign_up',
					'AddToCart': 'add_to_cart',
					'AddPaymentInfo': 'add_payment_info',
					'Purchase': 'purchase',
					'InitiateCheckout': 'begin_checkout',
					'AddToWishlist': 'add_to_wishlist',
					'Search': 'search',
					'Contact': 'contact',
					'Schedule': 'schedule',
				};
				return map[meta_event] || meta_event;
			}

		/**
		 * Parse UTM parameters from URL.
		 */
			get_utms() {
				var params = new URLSearchParams( window.location.search );
			var keys   = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content', 'src', 'sck'];
			var utms   = {};
				for (var i = 0; i < keys.length; i++) {
				var val = params.get( keys[i] );
				if (val) {
					utms[keys[i]] = val;
				}
				}
				this.log_debug( 'get_utms()', utms );
				return utms;
			}

		/**
		 * Check if tracking consent is given.
		 */
			check_consent() {
				var cookieName = this.config.consent_cookie || 'alvobot_tracking_consent';

			// Check cookie
				if (document.cookie.indexOf( cookieName + '=true' ) !== -1) {
					this.log_debug( 'check_consent(): true via cookie=true', { cookie: cookieName } );
					return true;
				}
				if (document.cookie.indexOf( cookieName + '=1' ) !== -1) {
					this.log_debug( 'check_consent(): true via cookie=1', { cookie: cookieName } );
					return true;
				}

			// Check JS variable
				if (window.alvobot_tracking_consent === true) {
					this.log_debug( 'check_consent(): true via window.alvobot_tracking_consent' );
					return true;
				}

			// CookieYes integration
			if (window.CookieYes && typeof window.CookieYes.getConsent === 'function') {
					if (window.CookieYes.getConsent( 'analytics' ) === 'yes') {
						this.log_debug( 'check_consent(): true via CookieYes analytics=yes' );
						return true;
					}
				}

			// Complianz integration
			if (typeof window.cmplz_get_consent === 'function') {
					if (window.cmplz_get_consent( 'statistics' ) === 'allow') {
						this.log_debug( 'check_consent(): true via Complianz statistics=allow' );
						return true;
					}
				}

				this.log_warn( 'check_consent(): consent not granted' );
				return false;
			}

		/**
		 * Check if current visitor is a bot.
		 */
			is_bot() {
				var ua   = navigator.userAgent;
			var bots = ['bot', 'Bot', 'crawl', 'spider', 'HTTrack', 'Lighthouse', 'GTmetrix', 'Page Speed'];
			for (var i = 0; i < bots.length; i++) {
					if (ua.indexOf( bots[i] ) !== -1) {
						this.log_warn( 'is_bot(): matched signature', { signature: bots[i], user_agent: ua } );
						return true;
					}
				}
				this.log_debug( 'is_bot(): false' );
				return false;
			}

		/**
		 * Returns true when the IP is private, loopback, or reserved.
		 */
		is_private_ip(ip) {
			var value = (ip || '').trim().toLowerCase();
			if ( ! value) {
				return true;
			}

			if (value === '::1' || value === '::' || value === 'localhost') {
				return true;
			}

			if (value.indexOf( '::ffff:' ) === 0) {
				value = value.slice( 7 );
			}

			var isV4 = /^(\d{1,3})(\.\d{1,3}){3}$/.test( value );
			if (isV4) {
				var octets = value.split( '.' ).map( Number );
				if (octets.some( function (n) { return n < 0 || n > 255; } )) {
					return true;
				}

				if (octets[0] === 10 || octets[0] === 127 || octets[0] === 0) {
					return true;
				}
				if (octets[0] === 192 && octets[1] === 168) {
					return true;
				}
				if (octets[0] === 172 && octets[1] >= 16 && octets[1] <= 31) {
					return true;
				}
				if (octets[0] === 169 && octets[1] === 254) {
					return true;
				}

				return false;
			}

			if (value.indexOf( 'fe80:' ) === 0 || value.indexOf( 'fc' ) === 0 || value.indexOf( 'fd' ) === 0) {
				return true;
			}

			return false;
		}

		/**
		 * Generate a unique event ID.
		 */
			generate_event_id() {
				if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
					var uuid = crypto.randomUUID();
					this.log_debug( 'generate_event_id(): crypto.randomUUID', { event_id: uuid } );
					return uuid;
				}
				// UUID v4 fallback
				var fallbackUuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
					/[xy]/g,
					function (c) {
						var r = Math.random() * 16 | 0;
						var v = c === 'x' ? r : (r & 0x3 | 0x8);
						return v.toString( 16 );
					}
				);
				this.log_debug( 'generate_event_id(): fallback UUID', { event_id: fallbackUuid } );
				return fallbackUuid;
			}

		/**
		 * Hash a value with SHA-256 using Web Crypto API.
		 */
		async hash_sha256(value) {
			if ( ! value) {
				return '';
			}
			var normalized = value.trim().toLowerCase();
			try {
				var encoder   = new TextEncoder();
				var data      = encoder.encode( normalized );
				var hash      = await crypto.subtle.digest( 'SHA-256', data );
				var hashArray = Array.from( new Uint8Array( hash ) );
				return hashArray.map(
					function (b) {
						return b.toString( 16 ).padStart( 2, '0' ); }
				).join( '' );
			} catch (e) {
				return '';
			}
		}

		/**
		 * Send a tracking event (browser + server).
		 *
		 * params.event_id_override — use this event_id instead of generating a new one
		 *   (used when the base code already fired fbq with a server-generated event_id)
		 * params.skip_fbq — if true, skip the fbq() call (base code already fired it)
		 * params.skip_google_ads — if true, skip managed Google Ads trackers in gtag
		 *   (used when a wrapper already dispatched the conversion snippet immediately)
			 */
				async send_event(params) {
					params = params || {};
					if ( ! this.tracking_enabled) {
						this.log_warn( 'send_event() skipped: tracking disabled', { params: params } );
						return;
					}

					var event_id   = params.event_id_override || this.generate_event_id();
					var event_name = params.event_name || 'PageView';
					var self       = this;
					var metaBrowserAllowed = this.refresh_meta_pixel_state();
					var googleBrowserAllowed = this.refresh_google_tag_state();

			// Build enriched custom_data
			var custom_data = {
				content_name: params.content_name || '',
			};
			if (this.config.content_type) {
				custom_data.content_type = this.config.content_type;
			}
			if (this.config.content_category) {
				custom_data.content_category = this.config.content_category;
			}
				var defaultCurrency = this.resolve_google_currency( params, custom_data );
				if (defaultCurrency) {
					custom_data.currency = defaultCurrency;
				}
				if (params.custom_data_extra && typeof params.custom_data_extra === 'object') {
					for (var extraKey in params.custom_data_extra) {
						if (Object.prototype.hasOwnProperty.call( params.custom_data_extra, extraKey )) {
							var extraValue = params.custom_data_extra[extraKey];
							if (extraValue !== '' && extraValue !== null && typeof extraValue !== 'undefined') {
								custom_data[extraKey] = extraValue;
							}
						}
					}
				}
				this.log_debug(
					'send_event(): prepared',
					{
						event_id: event_id,
						event_name: event_name,
						skip_fbq: !! params.skip_fbq,
						skip_google_ads: !! params.skip_google_ads,
						meta_browser_allowed: metaBrowserAllowed,
						google_browser_allowed: googleBrowserAllowed,
						params: params,
						custom_data: custom_data,
					}
				);

			// Parse per-pixel selection (comma-separated IDs from conversion rules).
				var selected_ids  = params.fb_pixels ? params.fb_pixels.split( ',' ).map( function (s) { return s.trim(); } ).filter( Boolean ) : [];
				var filter_active = selected_ids.length > 0;

				// Derive platforms from selected IDs when not explicitly set.
				var platforms = params.platforms || 'all';
				if (filter_active && ! params.platforms) {
					var hasMeta   = selected_ids.some( function (id) { return /^\d{15,16}$/.test( id ); } );
					var hasGoogle = selected_ids.some( function (id) { return /^(G-|AW-)/.test( id ) || id === 'sitekit_gtag'; } );
					if (hasMeta && ! hasGoogle) { platforms = 'meta_only'; }
					else if (hasGoogle && ! hasMeta) { platforms = 'google_only'; }
				}

				// 1a. Fire browser-side via fbq
					if ( ! params.skip_fbq && metaBrowserAllowed && window.fbq && platforms !== 'google_only') {
					if (filter_active) {
						// Per-pixel dispatch: use trackSingle/trackSingleCustom for each selected Meta pixel.
						var fbq_single_method = params.event_custom ? 'trackSingleCustom' : 'trackSingle';
						selected_ids.forEach( function (pid) {
							if ( /^\d{15,16}$/.test( pid ) ) {
								self.log_debug( 'send_event(): fbq trackSingle', { pixel: pid, event: event_name } );
								window.fbq(
									fbq_single_method,
									pid,
									event_name,
									custom_data,
									{ eventID: event_id }
								);
							}
						});
					} else {
						// Global dispatch: fire for all initialized pixels.
						var fbq_method = params.event_custom ? 'trackCustom' : 'track';
						this.log_debug( 'send_event(): fbq dispatch', { method: fbq_method, event_name: event_name, event_id: event_id } );
						window.fbq(
							fbq_method,
							event_name,
							custom_data,
							{ eventID: event_id }
						);
					}
				}

					// 1b. Fire Google events via gtag (multi-tracker loop)
					if (window.gtag && googleBrowserAllowed && platforms !== 'meta_only') {
						var trackers = self.get_google_trackers();
						var hasManagedGoogleTarget = trackers.some(
							function (tracker) {
								if (tracker.type === 'external') {
									return false;
								}
								if (filter_active && selected_ids.indexOf( tracker.tracker_id ) === -1) {
									return false;
								}
								return true;
							}
						);
						trackers.forEach( function (tracker) {
						// Skip tracker if per-pixel selection is active and this tracker is not selected.
						if (filter_active && selected_ids.indexOf( tracker.tracker_id ) === -1) {
							return;
						}
						if (params.skip_google_ads && tracker.type === 'google_ads') {
							return;
						}

							// External tracker (Site Kit / GTM): fire without send_to — goes to all configs on the page.
							if (tracker.type === 'external') {
								if (hasManagedGoogleTarget) {
									return;
								}
								var ext_event_name = params.event_custom ? event_name : self.map_to_ga4_event( event_name );
								if (params.is_initial_pageview && ext_event_name === 'page_view') {
									return;
								}
								var ext_params = self.build_google_event_params( params, null, ext_event_name, custom_data, false );
							window.gtag( 'event', ext_event_name, ext_params );
							self.log_debug( 'send_event(): gtag external dispatch (no send_to)', { event: ext_event_name } );
							return;
						}

						if (tracker.type === 'ga4') {
							var ga_event_name = params.event_custom ? event_name : self.map_to_ga4_event( event_name );
							var ga_params = self.build_google_event_params( params, tracker, ga_event_name, custom_data, false );
							window.gtag( 'event', ga_event_name, ga_params );
							self.log_debug( 'send_event(): gtag GA4 dispatch', { tracker: tracker.tracker_id, event: ga_event_name } );
						}

						if (tracker.type === 'google_ads') {
							// Per-tracker label: labels_map[tracker_id] > legacy single label > tracker default
							var labels_map = params.gads_labels_map || {};
							var gads_label = labels_map[tracker.tracker_id] || params.gads_conversion_label || tracker.conversion_label;
							if (gads_label) {
								var gads_params = self.build_google_event_params( params, tracker, 'conversion', custom_data, true );
								gads_params.send_to = tracker.tracker_id + '/' + gads_label;
								window.gtag( 'event', 'conversion', gads_params );
								self.log_debug( 'send_event(): gtag Ads conversion', { tracker: tracker.tracker_id, label: gads_label } );
							}
						}
					});
				} else if (window.gtag && platforms !== 'meta_only' && this.has_google_trackers()) {
					this.log_warn( 'send_event(): Google browser dispatch skipped because consent is not granted', { event_name: event_name } );
				}

				// 2. POST to WordPress for server-side dispatch (Meta CAPI — skip for google_only events)
				if (platforms === 'google_only') {
					return; // No server-side dispatch needed for Google-only events
				}
				try {
					var eventPayload = {
						event_id: event_id,
						event_name: event_name,
						event_url: window.location.href,
						page_url: window.location.href,
						page_title: document.title,
						page_id: this.config.page_id,
						referrer: document.referrer || '',
						fbp: this.data.fbp,
						fbc: this.data.fbc,
						ip: this.data.ip,
						browser_ip: this.data.ip,
						user_agent: this.data.user_agent,
						geo: this.data.geolocation,
						utms: this.data.utms,
						lead_id: this.data.lead_id || '',
						user_data_hashed: this.config.user_data_hashed || {},
						custom_data: custom_data,
						pixel_ids: params.fb_pixels || this.config.pixel_ids,
						gclid: this.data.gclid || '',
						gbraid: this.data.gbraid || '',
						wbraid: this.data.wbraid || '',
						dclid: this.data.dclid || '',
						ga_client_id: this.data.ga_client_id || '',
						gads_conversion_label: params.gads_conversion_label || '',
						gads_labels_map: params.gads_labels_map || {},
						gads_conversion_value: params.gads_conversion_value || '',
					};
					this.log_debug( 'send_event(): POST /events/track payload', eventPayload );
					fetch(
						this.config.api_event,
						{
								method: 'POST',
								headers: {
									'Content-Type': 'application/json',
									'X-Alvobot-Nonce': this.config.nonce || '',
								},
							body: JSON.stringify( eventPayload ),
						keepalive: true,
						}
					).then(
						function (resp) {
							self.log_debug(
								'send_event(): /events/track response headers',
								{
									ok: !! (resp && resp.ok),
									status: resp ? resp.status : 0,
								}
							);
							if ( ! resp || ! resp.ok) {
								return null;
							}
						return resp.json().catch(
							function () {
								return null;
							}
						);
					}
					).then(
						function (result) {
							if ( ! result) {
								self.log_warn( 'send_event(): empty/non-OK response body from /events/track' );
								return;
							}
							self.log_debug( 'send_event(): /events/track response body', result );

						if (result.resolved_ip) {
							var currentIsPublic  = self.data.ip && ! self.is_private_ip( self.data.ip );
							var resolvedIsPublic = ! self.is_private_ip( result.resolved_ip );
								if (resolvedIsPublic && ! currentIsPublic) {
									self.data.ip = result.resolved_ip;
									self.log_debug( 'send_event(): updated in-memory IP from server', { resolved_ip: result.resolved_ip } );
									try {
										sessionStorage.setItem( 'alvobot_ip', result.resolved_ip );
									} catch (e) {
									/* ignore */ }
							}
						}

							if (result.resolved_geo && result.resolved_geo.city && ( ! self.data.geolocation || ! self.data.geolocation.city )) {
								self.data.geolocation = result.resolved_geo;
								self.log_debug( 'send_event(): updated in-memory geo from server', result.resolved_geo );
							}
						}
					).catch(
						function (err) {
							self.log_error( 'send_event(): fetch failed', err && err.message ? err.message : err );
							/* ignore */ }
					);
				} catch (e) {
					this.log_error( 'send_event(): unexpected error before fetch', e && e.message ? e.message : e );
					// Fire-and-forget
				}
			}

		/**
		 * Monitor forms for lead capture.
		 */
			monitor_forms() {
				var self = this;
				this.log_debug( 'monitor_forms(): starting DOM scan loop' );

				function scanForms() {
					document.querySelectorAll( 'form' ).forEach(
						function (form) {
							if (form.classList.contains( 'alvobot_tracked' )) {
								return;
							}
							form.classList.add( 'alvobot_tracked' );
							self.log_debug(
								'monitor_forms(): form instrumented',
								{
									action: form.getAttribute( 'action' ) || '',
									id: form.getAttribute( 'id' ) || '',
									name: form.getAttribute( 'name' ) || '',
								}
							);

						form.addEventListener(
							'submit',
							function () {
								self.capture_form_data( form );
							}
						);

						// Listen for email-like inputs early
						form.querySelectorAll( 'input[type="email"], input[name*="email"], input[name*="Email"]' ).forEach(
							function (input) {
								input.addEventListener(
									'change',
									function () {
										if (input.value && input.value.indexOf( '@' ) !== -1) {
												self.capture_form_data( form );
										}
									}
								);
							}
						);
					}
				);
			}

			scanForms();
			setInterval( scanForms, 5000 );
		}

		/**
		 * Extract lead data from a form and send to server.
		 */
			capture_form_data(form) {
				var data   = {};
				var inputs = form.querySelectorAll( 'input, select, textarea' );

			for (var i = 0; i < inputs.length; i++) {
				var input = inputs[i];
				var name  = (input.name || '').toLowerCase();
				var type  = (input.type || '').toLowerCase();
				var val   = input.value;

				if ( ! val) {
					continue;
				}

				// Email detection
				if (type === 'email' || name.indexOf( 'email' ) !== -1 || name.indexOf( 'e-mail' ) !== -1) {
					if (val.indexOf( '@' ) !== -1) {
						data.email = val;
					}
				}
				// Phone detection
				else if (type === 'tel' || name.indexOf( 'phone' ) !== -1 || name.indexOf( 'tel' ) !== -1 || name.indexOf( 'fone' ) !== -1 || name.indexOf( 'celular' ) !== -1) {
					data.phone = val;
				}
				// Name detection
				else if (name === 'name' || name === 'nome' || name === 'full_name' || name === 'fullname') {
					data.name = val;
				} else if (name === 'fname' || name === 'first_name' || name === 'primeiro_nome') {
					data.first_name = val;
				} else if (name === 'lname' || name === 'last_name' || name === 'sobrenome') {
					data.last_name = val;
				}
			}

				// Only send if we have at least an email
				if (data.email) {
					this.log_debug( 'capture_form_data(): lead candidate found', data );
					this.send_lead_data( data );
				} else {
					this.log_debug( 'capture_form_data(): skipped, no email found' );
				}
			}

		/**
		 * Send lead data to the WordPress REST API.
		 */
			async send_lead_data(formData) {
				var lead_id = this.data.lead_id || this.generate_event_id();

				var payload = {
				lead_id: lead_id,
				email: formData.email || '',
				name: formData.name || '',
				first_name: formData.first_name || '',
				last_name: formData.last_name || '',
				phone: formData.phone || '',
				fbp: this.data.fbp,
				fbc: this.data.fbc,
				ip: this.data.ip,
				browser_ip: this.data.ip,
				user_agent: this.data.user_agent,
				geo: this.data.geolocation,
				utms: this.data.utms,
				src: this.data.utms ? this.data.utms.src : '',
				gclid: this.data.gclid || '',
				gbraid: this.data.gbraid || '',
				wbraid: this.data.wbraid || '',
				dclid: this.data.dclid || '',
				ga_client_id: this.data.ga_client_id || '',
					sck: this.data.utms ? this.data.utms.sck : '',
				};
				this.log_debug( 'send_lead_data(): POST /leads/track payload', payload );

				try {
					var resp = await fetch(
					this.config.api_lead,
					{
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-Alvobot-Nonce': this.config.nonce || '',
							},
						body: JSON.stringify( payload ),
						}
					);
					this.log_debug(
						'send_lead_data(): /leads/track response headers',
						{
							ok: !! (resp && resp.ok),
							status: resp ? resp.status : 0,
						}
					);

					var result = await resp.json();
					this.log_debug( 'send_lead_data(): /leads/track response body', result );
					if (result && result.success && result.lead_id) {
							this.data.lead_id = result.lead_id;
							this.set_cookie( 'alvobot_lead_id', result.lead_id );
							this.log_debug( 'send_lead_data(): lead_id updated', { lead_id: result.lead_id } );
					}
				if (result && result.resolved_ip) {
					var currentIsPublic  = this.data.ip && ! this.is_private_ip( this.data.ip );
					var resolvedIsPublic = ! this.is_private_ip( result.resolved_ip );
						if (resolvedIsPublic && ! currentIsPublic) {
							this.data.ip = result.resolved_ip;
							this.log_debug( 'send_lead_data(): updated in-memory IP from server', { resolved_ip: result.resolved_ip } );
							try {
							sessionStorage.setItem( 'alvobot_ip', result.resolved_ip );
						} catch (e) {
							/* ignore */ }
					}
				}
					if (result && result.resolved_geo && result.resolved_geo.city && ( ! this.data.geolocation || ! this.data.geolocation.city )) {
						this.data.geolocation = result.resolved_geo;
						this.log_debug( 'send_lead_data(): updated in-memory geo from server', result.resolved_geo );
					}
				} catch (e) {
					this.log_error( 'send_lead_data(): request failed', e && e.message ? e.message : e );
					// Silent failure for lead capture
				}
			}

		/**
		 * Set a cookie with standard tracking defaults.
		 */
			set_cookie(name, value, max_age_seconds) {
			var expires = new Date();
			var ttl     = (typeof max_age_seconds !== 'undefined' && max_age_seconds !== null) ? max_age_seconds : (180 * 24 * 60 * 60); // Default 180 days
			expires.setTime( expires.getTime() + (ttl * 1000) );
			var cookie = name + '=' + encodeURIComponent( value ) +
			';expires=' + expires.toUTCString() +
			';path=/;SameSite=Lax';
				if (window.location.protocol === 'https:') {
					cookie += ';Secure';
				}
				document.cookie = cookie;
				this.log_debug( 'set_cookie()', { name: name, value: value } );
			}
	}

	// Create global instance and auto-start
	var tracker          = new AlvoBotPixelTracking();
	window.alvobot_pixel = tracker;

	// Auto-start when config is available
	if (window.alvobot_pixel_config) {
		if (document.readyState === 'loading') {
			document.addEventListener(
				'DOMContentLoaded',
				function () {
					tracker.start( window.alvobot_pixel_config );
				}
			);
		} else {
			tracker.start( window.alvobot_pixel_config );
		}
	}
})();
