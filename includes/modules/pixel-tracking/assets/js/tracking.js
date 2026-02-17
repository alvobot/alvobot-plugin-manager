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
			this.initial_pageview_sent = false;
			this._readyPromise = null;
			this._resolveReady = null;
		}

		/**
		 * Returns a promise that resolves when the tracker has finished initializing.
		 */
		ready() {
			if (this.initialized) {
				return Promise.resolve();
			}
			if ( ! this._readyPromise) {
				var self = this;
				this._readyPromise = new Promise( function (resolve) {
					self._resolveReady = resolve;
				});
			}
			return this._readyPromise;
		}

		/**
		 * Initialize tracking with config from PHP inline script.
		 */
		async start(config) {
			this.config = config || window.alvobot_pixel_config || {};

			if ( ! this.config.pixel_ids) {
				return;
			}

			// Check consent if required
			if (this.config.consent_check && ! this.check_consent()) {
				return;
			}

			// Check if bot
			if (this.is_bot()) {
				return;
			}

			// Initialize Facebook Pixel SDK
			this.init_fb_sdk( this.config.pixel_ids );

			// Capture visitor data
			this.data.fbp        = this.get_fbp();
			this.data.fbc        = this.get_fbc();
			this.data.utms       = this.get_utms();
			this.data.user_agent = navigator.userAgent;

			// Async data capture
			try {
				this.data.ip = await this.get_ip();
			} catch (e) {
				this.data.ip = '';
			}

			try {
				this.data.geolocation = await this.get_geolocation();
			} catch (e) {
				this.data.geolocation = {};
			}

			// Start form monitoring for lead capture
			this.monitor_forms();

			// Always dispatch a default PageView through browser + server with shared event_id.
			this.dispatch_initial_pageview();

			this.initialized = true;
			if (this._resolveReady) {
				this._resolveReady();
			}
		}

		/**
		 * Initialize Facebook Pixel SDK.
		 *
		 * PageView is dispatched by send_event() so browser and server share
		 * the same event_id for deduplication.
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
				return;
			}

			// Load fbevents.js if not already loaded
			if ( ! window.fbq) {
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
			}

			// Init each pixel
			for (var i = 0; i < ids.length; i++) {
				window.fbq( 'init', ids[i] );
			}

		}

		/**
		 * Send the default PageView once per page load.
		 */
		dispatch_initial_pageview() {
			if (this.initial_pageview_sent) {
				return;
			}

			this.initial_pageview_sent = true;
			this.send_event(
				{
					event_name: 'PageView',
					event_custom: false,
					content_name: this.config.page_title || document.title || '',
					fb_pixels: this.config.pixel_ids || '',
				}
			);
		}

		/**
		 * Get visitor IP via Cloudflare cdn-cgi/trace.
		 */
		async get_ip() {
			var cachedIp = '';
			try {
				cachedIp = sessionStorage.getItem( 'alvobot_ip' ) || '';
			} catch (e) {
				cachedIp = '';
			}

			if (cachedIp) {
				if ( ! this.is_private_ip( cachedIp )) {
					return cachedIp;
				}
				try {
					sessionStorage.removeItem( 'alvobot_ip' );
				} catch (e) {
					/* ignore */ }
			}

			if (this.config.cf_trace_enabled) {
				try {
					var cfResp  = await this.fetch_with_timeout( '/cdn-cgi/trace', {}, 2500 );
					if (cfResp && cfResp.ok) {
						var cfText  = await cfResp.text();
						var cfMatch = cfText.match( /ip=([^\n]+)/ );
						if (cfMatch && cfMatch[1] && ! this.is_private_ip( cfMatch[1] )) {
							try {
								sessionStorage.setItem( 'alvobot_ip', cfMatch[1] );
							} catch (e) {
								/* ignore */ }
							return cfMatch[1];
						}
					}
				} catch (e) {
					/* continue fallback */ }
			}

			try {
				var ipifyResp = await this.fetch_with_timeout( 'https://api64.ipify.org?format=json', {}, 2500 );
				if (ipifyResp && ipifyResp.ok) {
					var ipifyData = await ipifyResp.json();
					if (ipifyData && ipifyData.ip && ! this.is_private_ip( ipifyData.ip )) {
						try {
							sessionStorage.setItem( 'alvobot_ip', ipifyData.ip );
						} catch (e) {
							/* ignore */ }
						return ipifyData.ip;
					}
				}
			} catch (e) {
				/* continue fallback */ }

			try {
				var ipwhoResp = await this.fetch_with_timeout( 'https://ipwho.is/', {}, 3000 );
				if (ipwhoResp && ipwhoResp.ok) {
					var ipwhoData = await ipwhoResp.json();
					if (ipwhoData && ipwhoData.ip && ! this.is_private_ip( ipwhoData.ip )) {
						try {
							sessionStorage.setItem( 'alvobot_ip', ipwhoData.ip );
						} catch (e) {
							/* ignore */ }
						return ipwhoData.ip;
					}
				}
			} catch (e) {
				/* ignore */ }

			return '';
		}

		/**
		 * Fetch helper with timeout support.
		 */
		async fetch_with_timeout(url, options, timeoutMs) {
			var opts = options || {};
			var ms   = timeoutMs || 3000;

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
				return {};
			}

			var GEO_CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours

			// Check localStorage cache with TTL
			var cacheKey = 'alvobot_geo_' + ip;
			try {
				var cached = localStorage.getItem( cacheKey );
				if (cached) {
					var parsed = JSON.parse( cached );
					if (parsed && parsed.city && parsed._ts && (Date.now() - parsed._ts) < GEO_CACHE_TTL) {
						return parsed;
					}
					localStorage.removeItem( cacheKey );
				}
			} catch (e) {
				/* ignore */ }

			var geo = null;

			// Primary: ip-api.com Pro (HTTPS, higher rate limit)
			try {
				var resp = await fetch( 'https://pro.ip-api.com/json/' + ip + '?key=TOLoWxdNIA0zIZm&fields=status,city,regionName,country,countryCode,zip,currency,timezone' );
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
					}
				}
			} catch (e) {
				/* fallback */ }

			// Fallback: ipwho.is (HTTPS, free, no key)
			if ( ! geo) {
				try {
					var resp2 = await fetch( 'https://ipwho.is/' + ip );
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
						}
					}
				} catch (e) {
					/* server-side fallback will handle geo */ }
			}

			if (geo) {
				try {
					localStorage.setItem( cacheKey, JSON.stringify( geo ) );
				} catch (e) {
					/* ignore */ }
				return geo;
			}

			return {};
		}

		/**
		 * Get or generate _fbp cookie.
		 */
		get_fbp() {
			var match = document.cookie.match( /(^|;)\s*_fbp=([^;]+)/ );
			if (match) {
				return match[2];
			}

			// Generate fbp
			var ts   = Date.now();
			var rand = Math.floor( Math.random() * 10000000000 );
			var fbp  = 'fb.1.' + ts + '.' + rand;
			this.set_cookie( '_fbp', fbp );
			return fbp;
		}

		/**
		 * Get or generate _fbc from fbclid URL param.
		 */
		get_fbc() {
			var match = document.cookie.match( /(^|;)\s*_fbc=([^;]+)/ );
			if (match) {
				return match[2];
			}

			// Check for fbclid in URL
			var params = new URLSearchParams( window.location.search );
			var fbclid = params.get( 'fbclid' );
			if (fbclid) {
				var fbc = 'fb.1.' + Date.now() + '.' + fbclid;
				this.set_cookie( '_fbc', fbc );
				return fbc;
			}

			return '';
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
			return utms;
		}

		/**
		 * Check if tracking consent is given.
		 */
		check_consent() {
			var cookieName = this.config.consent_cookie || 'alvobot_tracking_consent';

			// Check cookie
			if (document.cookie.indexOf( cookieName + '=true' ) !== -1) {
				return true;
			}
			if (document.cookie.indexOf( cookieName + '=1' ) !== -1) {
				return true;
			}

			// Check JS variable
			if (window.alvobot_tracking_consent === true) {
				return true;
			}

			// CookieYes integration
			if (window.CookieYes && typeof window.CookieYes.getConsent === 'function') {
				if (window.CookieYes.getConsent( 'analytics' ) === 'yes') {
					return true;
				}
			}

			// Complianz integration
			if (typeof window.cmplz_get_consent === 'function') {
				if (window.cmplz_get_consent( 'statistics' ) === 'allow') {
					return true;
				}
			}

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
					return true;
				}
			}
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
				return crypto.randomUUID();
			}
			// UUID v4 fallback
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
				/[xy]/g,
				function (c) {
					var r = Math.random() * 16 | 0;
					var v = c === 'x' ? r : (r & 0x3 | 0x8);
					return v.toString( 16 );
				}
			);
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
		 */
		async send_event(params) {
			var event_id   = this.generate_event_id();
			var event_name = params.event_name || 'PageView';
			var self       = this;

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
			if (this.data.geolocation && this.data.geolocation.currency) {
				custom_data.currency = this.data.geolocation.currency;
			}

			// 1. Fire browser-side via fbq with enriched data
				if (window.fbq) {
					var fbq_method = params.event_custom ? 'trackCustom' : 'track';
					window.fbq(
						fbq_method,
						event_name,
						{
							content_name: params.content_name || '',
							content_type: custom_data.content_type || '',
							content_category: custom_data.content_category || '',
							currency: custom_data.currency || '',
						},
						{
							eventID: event_id,
						}
					);
				}

			// 2. POST to WordPress for server-side dispatch
			try {
				fetch(
					this.config.api_event,
					{
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-Alvobot-Nonce': this.config.nonce || '',
							},
						body: JSON.stringify(
							{
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
							}
						),
					keepalive: true,
					}
				).then(
					function (resp) {
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
							return;
						}

						if (result.resolved_ip) {
							var currentIsPublic  = self.data.ip && ! self.is_private_ip( self.data.ip );
							var resolvedIsPublic = ! self.is_private_ip( result.resolved_ip );
							if (resolvedIsPublic && ! currentIsPublic) {
								self.data.ip = result.resolved_ip;
								try {
									sessionStorage.setItem( 'alvobot_ip', result.resolved_ip );
								} catch (e) {
									/* ignore */ }
							}
						}

						if (result.resolved_geo && result.resolved_geo.city && ( ! self.data.geolocation || ! self.data.geolocation.city )) {
							self.data.geolocation = result.resolved_geo;
						}
					}
				).catch(
					function () {
						/* ignore */ }
				);
			} catch (e) {
				// Fire-and-forget
			}
		}

		/**
		 * Monitor forms for lead capture.
		 */
		monitor_forms() {
			var self = this;

			function scanForms() {
				document.querySelectorAll( 'form' ).forEach(
					function (form) {
						if (form.classList.contains( 'alvobot_tracked' )) {
							return;
						}
						form.classList.add( 'alvobot_tracked' );

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
				this.send_lead_data( data );
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
				sck: this.data.utms ? this.data.utms.sck : '',
			};

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

				var result = await resp.json();
				if (result && result.success && result.lead_id) {
						this.data.lead_id = result.lead_id;
						this.set_cookie( 'alvobot_lead_id', result.lead_id );
				}
				if (result && result.resolved_ip) {
					var currentIsPublic  = this.data.ip && ! this.is_private_ip( this.data.ip );
					var resolvedIsPublic = ! this.is_private_ip( result.resolved_ip );
					if (resolvedIsPublic && ! currentIsPublic) {
						this.data.ip = result.resolved_ip;
						try {
							sessionStorage.setItem( 'alvobot_ip', result.resolved_ip );
						} catch (e) {
							/* ignore */ }
					}
				}
				if (result && result.resolved_geo && result.resolved_geo.city && ( ! this.data.geolocation || ! this.data.geolocation.city )) {
					this.data.geolocation = result.resolved_geo;
				}
			} catch (e) {
				// Silent failure for lead capture
			}
		}

		/**
		 * Set a cookie with standard tracking defaults.
		 */
		set_cookie(name, value) {
			var expires = new Date();
			expires.setTime( expires.getTime() + (180 * 24 * 60 * 60 * 1000) ); // 180 days
			var cookie = name + '=' + encodeURIComponent( value ) +
			';expires=' + expires.toUTCString() +
			';path=/;SameSite=Lax';
			if (window.location.protocol === 'https:') {
				cookie += ';Secure';
			}
			document.cookie = cookie;
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
