(function () {
	var __timeline = [];
	function __tl(type, name, detail) {
		__timeline.push({
			ts: Date.now(),
			iso: /* @__PURE__ */ new Date().toISOString(),
			type,
			name,
			detail: detail || {},
		});
		if (__timeline.length > 300) {
			__timeline.shift();
		}
	}
	window.__alvobot_event_timeline = __timeline;
	window.__alvobot_timeline_push = __tl;
	class AlvoBotPixelTracking {
		constructor() {
			this.data = {};
			this.config = {};
			this.initialized = false;
			this.tracking_enabled = false;
			this.meta_browser_allowed = false;
			this.google_browser_allowed = false;
			this.initial_pageview_sent = false;
			this._readyPromise = null;
			this._resolveReady = null;
			this._readyResolved = false;
			this.debug_enabled = false;
			this.debug_prefix = '[AlvoBot Pixel][FRONT]';
		}
		/**
		 * Returns a promise that resolves when the tracker has finished initializing.
		 */
		ready() {
			if (this.initialized || this._readyResolved) {
				this.log_debug('ready(): already initialized');
				return Promise.resolve();
			}
			if (!this._readyPromise) {
				var self = this;
				this._readyPromise = new Promise(function (resolve) {
					self._resolveReady = resolve;
				});
				this.log_debug('ready(): promise created');
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
				return JSON.parse(JSON.stringify(value));
			} catch (_e) {
				return value;
			}
		}
		log_debug(message, payload) {
			var _a;
			if (!this.debug_enabled || !((_a = window.console) == null ? void 0 : _a.log)) {
				return;
			}
			if (typeof payload === 'undefined') {
				window.console.log(this.debug_prefix + ' ' + message);
				return;
			}
			window.console.log(this.debug_prefix + ' ' + message, this.clone_for_debug(payload));
		}
		log_warn(message, payload) {
			var _a;
			if (!this.debug_enabled || !((_a = window.console) == null ? void 0 : _a.warn)) {
				return;
			}
			if (typeof payload === 'undefined') {
				window.console.warn(this.debug_prefix + ' ' + message);
				return;
			}
			window.console.warn(this.debug_prefix + ' ' + message, this.clone_for_debug(payload));
		}
		log_error(message, payload) {
			var _a;
			if (!this.debug_enabled || !((_a = window.console) == null ? void 0 : _a.error)) {
				return;
			}
			if (typeof payload === 'undefined') {
				window.console.error(this.debug_prefix + ' ' + message);
				return;
			}
			window.console.error(this.debug_prefix + ' ' + message, this.clone_for_debug(payload));
		}
		/**
		 * Initialize tracking with config from PHP inline script.
		 */
		async start(config) {
			this.config = config || window.alvobot_pixel_config || {};
			this.debug_enabled = !!this.config.debug_enabled;
			__tl('system', 'tracker.start', { debug: this.debug_enabled, href: window.location.href });
			this.log_debug('start() called', {
				href: window.location.href,
				page_title: document.title,
				config: this.config,
			});
			var hasGoogleTrackers = this.config.google_trackers && this.config.google_trackers.length > 0;
			if (
				!this.config.pixel_ids &&
				!this.config.google_analytics_id &&
				!this.config.google_ads_id &&
				!hasGoogleTrackers
			) {
				this.log_warn('start aborted: no tracking IDs configured');
				this.tracking_enabled = false;
				this.resolve_ready();
				return;
			}
			if (this.is_bot()) {
				this.log_warn('start aborted: bot detected', { user_agent: navigator.userAgent });
				this.tracking_enabled = false;
				this.resolve_ready();
				return;
			}
			this.tracking_enabled = true;
			this.data.external_id = this.get_external_id();
			this.data.fbp = this.get_fbp();
			this.data.fbc = this.get_fbc();
			this.refresh_meta_pixel_state();
			this.refresh_google_tag_state();
			this.data.utms = this.get_utms();
			this.data.user_agent = navigator.userAgent;
			this.data.click_ids = this.get_click_ids();
			this.data.gclid = this.data.click_ids.gclid || '';
			this.data.gbraid = this.data.click_ids.gbraid || '';
			this.data.wbraid = this.data.click_ids.wbraid || '';
			this.data.dclid = this.data.click_ids.dclid || '';
			this.data.attribution = this.get_attribution_context();
			this.data.edge_context = this.get_edge_context();
			this.data.ga_client_id = '';
			this.capture_ga_client_id();
			this.log_debug('visitor base data captured', {
				external_id: this.data.external_id,
				fbp: this.data.fbp,
				fbc: this.data.fbc,
				click_ids: this.data.click_ids,
				gclid: this.data.gclid,
				gbraid: this.data.gbraid,
				wbraid: this.data.wbraid,
				dclid: this.data.dclid,
				utms: this.data.utms,
				attribution: this.data.attribution,
				edge_context: this.data.edge_context,
				user_agent: this.data.user_agent,
			});
			this.monitor_forms();
			this.dispatch_initial_pageview();
			this.initialized = true;
			this.resolve_ready();
			this.log_debug('tracker initialized', { initialized: this.initialized });
			if (this.debug_enabled && typeof window.alvobot_debug === 'function' && !window.__alvobot_debug_installed) {
				window.alvobot_debug();
			}
			// IP/geo enrichment is best-effort and intentionally runs after ready()
			// so PageView dispatch is not blocked by external network latency.
			this.capture_async_context();
		}
		capture_ga_client_id() {
			var ga4TrackerId = this.get_primary_ga4_tracker_id();
			if (!window.gtag || !ga4TrackerId) {
				return;
			}
			var self = this;
			try {
				gtag('get', ga4TrackerId, 'client_id', function (cid) {
					self.data.ga_client_id = cid || '';
					self.log_debug('GA4 client_id captured', { tracker_id: ga4TrackerId, client_id: cid });
				});
			} catch (e) {
				this.log_error('gtag get client_id failed', (e == null ? void 0 : e.message) ? e.message : e);
			}
		}
		async capture_async_context() {
			var self = this;
			var timeoutHandle = null;
			var asyncTimeout = new Promise(function (resolve) {
				timeoutHandle = setTimeout(function () {
					self.log_warn('async capture timed out after 8s \u2014 continuing without IP/geo');
					resolve('timeout');
				}, 8e3);
			});
			var asyncCapture = (async function () {
				try {
					self.data.ip = await self.get_ip();
				} catch (e) {
					self.data.ip = '';
					self.log_error('get_ip() threw', (e == null ? void 0 : e.message) ? e.message : e);
				}
				try {
					self.data.geolocation = await self.get_geolocation();
				} catch (e) {
					self.data.geolocation = {};
					self.log_error('get_geolocation() threw', (e == null ? void 0 : e.message) ? e.message : e);
				}
				return 'done';
			})();
			await Promise.race([asyncCapture, asyncTimeout]);
			if (timeoutHandle) {
				clearTimeout(timeoutHandle);
			}
			this.log_debug('async capture complete', { ip: this.data.ip, geolocation: this.data.geolocation });
		}
		/**
		 * Initialize Facebook Pixel SDK.
		 *
		 * The PHP base code may have already injected fbevents.js and called fbq('init')
		 * for every pixel synchronously in <head>. This method is also the runtime
		 * fallback for cases where Meta browser tracking only becomes allowed after
		 * consent is granted in the browser.
		 */
		async sha256(str) {
			if (!str) {
				return '';
			}
			try {
				var normalized = String(str).trim().toLowerCase();
				var data = new TextEncoder().encode(normalized);
				var buf = await crypto.subtle.digest('SHA-256', data);
				return Array.from(new Uint8Array(buf))
					.map(function (b) {
						return b.toString(16).padStart(2, '0');
					})
					.join('');
			} catch (_e) {
				return '';
			}
		}
		async init_fb_sdk(pixel_ids_str) {
			var _a;
			var ids = pixel_ids_str
				.split(',')
				.map(function (s2) {
					return s2.trim();
				})
				.filter(Boolean)
				.filter(function (id, index, arr) {
					return arr.indexOf(id) === index;
				});
			if (!ids.length) {
				this.log_warn('init_fb_sdk aborted: no valid pixel IDs', { pixel_ids_str });
				return;
			}
			this.log_debug('init_fb_sdk', { requested: pixel_ids_str, deduped_ids: ids });
			var serverData =
				((_a = this.config) == null ? void 0 : _a.user_data_hashed) &&
				!Array.isArray(this.config.user_data_hashed)
					? this.config.user_data_hashed
					: {};
			var advancedMatchData = Object.assign({}, serverData);
			if (!advancedMatchData.external_id && this.data.fbp) {
				var hashed = await this.sha256(this.data.fbp);
				if (hashed) {
					advancedMatchData.external_id = hashed;
					this.log_debug('init_fb_sdk: external_id hashed client-side from fbp');
				}
			}
			if (!window.fbq) {
				this.log_debug('loading Facebook SDK script (base code was not present)');
				var n = function () {
					var args = Array.prototype.slice.call(arguments);
					n.callMethod ? n.callMethod.apply(n, args) : n.queue.push(args);
				};
				window.fbq = n;
				if (!window._fbq) {
					window._fbq = n;
				}
				n.push = n;
				n.loaded = true;
				n.version = '2.0';
				n.queue = [];
				var t = document.createElement('script');
				t.async = true;
				t.src = 'https://connect.facebook.net/en_US/fbevents.js';
				var s = document.getElementsByTagName('script')[0];
				if (s == null ? void 0 : s.parentNode) {
					s.parentNode.insertBefore(t, s);
				} else {
					(document.head || document.body).appendChild(t);
				}
				for (var i = 0; i < ids.length; i++) {
					window.fbq('init', ids[i], advancedMatchData);
					this.log_debug('fbq init (late \u2014 base code absent)', {
						pixel_id: ids[i],
						advanced_match_keys: Object.keys(advancedMatchData),
					});
				}
			} else {
				if (Object.keys(advancedMatchData).length > 0) {
					for (var j = 0; j < ids.length; j++) {
						window.fbq('init', ids[j], advancedMatchData);
						this.log_debug('fbq init (update Advanced Matching on existing pixel)', {
							pixel_id: ids[j],
							advanced_match_keys: Object.keys(advancedMatchData),
						});
					}
				} else {
					this.log_debug('fbq already present from base code \u2014 no Advanced Matching data to update', {
						ids,
					});
				}
			}
		}
		refresh_meta_pixel_state() {
			if (!this.config.pixel_ids) {
				this.meta_browser_allowed = false;
				return false;
			}
			this.meta_browser_allowed = true;
			this.init_fb_sdk(this.config.pixel_ids);
			return true;
		}
		get_google_trackers() {
			var trackers = Array.isArray(this.config.google_trackers) ? this.config.google_trackers.slice() : [];
			if (!trackers.length) {
				if (this.config.google_analytics_id) {
					trackers.push({
						tracker_id: this.config.google_analytics_id,
						type: 'ga4',
						conversion_label: '',
					});
				}
				if (this.config.google_ads_id) {
					trackers.push({
						tracker_id: this.config.google_ads_id,
						type: 'google_ads',
						conversion_label: this.config.google_ads_conversion_label || '',
						tag_id: this.config.google_ads_id,
					});
				}
			}
			return trackers.filter(function (tracker2) {
				if (!(tracker2 == null ? void 0 : tracker2.tracker_id)) {
					return false;
				}
				if (tracker2.type === 'external' && tracker2.tracker_id === 'sitekit_gtag') {
					return true;
				}
				if (tracker2.type === 'google_ads') {
					return /^AW-/.test(tracker2.tag_id || tracker2.tracker_id || '');
				}
				return /^(G-|AW-)/.test(tracker2.tracker_id);
			});
		}
		get_google_ads_send_id(tracker2) {
			if (!tracker2) {
				return '';
			}
			return tracker2.type === 'google_ads'
				? tracker2.tag_id || tracker2.tracker_id || ''
				: tracker2.tracker_id || '';
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
		refresh_google_tag_state() {
			if (!this.has_google_trackers()) {
				this.google_browser_allowed = false;
				return false;
			}
			this.google_browser_allowed = true;
			return true;
		}
		/**
		 * Send the default PageView once per page load.
		 *
		 * When the PHP base code is present, fbq('track','PageView') was already
		 * called synchronously in <head> with a server-generated event_id.
		 * Here we only POST to the CAPI endpoint using that same event_id so that
		 * Meta can deduplicate the browser and server events — no double count.
		 */
		dispatch_initial_pageview() {
			if (this.initial_pageview_sent) {
				this.log_debug('initial PageView skipped: already sent');
				return;
			}
			this.initial_pageview_sent = true;
			__tl('system', 'PageView (initial)', { pageview_event_id: this.config.pageview_event_id });
			var pageview_params = {
				event_name: 'PageView',
				event_custom: false,
				content_name: this.config.page_title || document.title || '',
				fb_pixels: this.config.pixel_ids || '',
				is_initial_pageview: true,
			};
			if (!this.config.pixel_ids) {
				pageview_params.platforms = 'google_only';
			}
			if (this.config.pageview_event_id) {
				this.log_debug('initial PageView: base code present, sending CAPI only', {
					event_id: this.config.pageview_event_id,
				});
				pageview_params.event_id_override = this.config.pageview_event_id;
				pageview_params.skip_fbq = true;
			} else {
				this.log_debug('initial PageView: base code absent, sending via runtime dispatch');
			}
			this.send_event(pageview_params);
		}
		/**
		 * Get visitor IP via Cloudflare cdn-cgi/trace.
		 */
		async get_ip() {
			var cachedIp = '';
			var _ipStart = Date.now();
			this.log_debug('get_ip(): start');
			try {
				cachedIp = sessionStorage.getItem('alvobot_ip') || '';
			} catch (_e) {
				cachedIp = '';
			}
			if (cachedIp) {
				if (!this.is_private_ip(cachedIp)) {
					this.log_debug('get_ip(): using cached public IP', { ip: cachedIp });
					__tl('geo', 'ip_resolved', { source: 'cache', ip: cachedIp, ms: Date.now() - _ipStart });
					return cachedIp;
				}
				this.log_warn('get_ip(): cached IP is private/reserved; removing', { ip: cachedIp });
				try {
					sessionStorage.removeItem('alvobot_ip');
				} catch (_e) {}
			}
			if (this.config.cf_trace_enabled) {
				this.log_debug('get_ip(): trying /cdn-cgi/trace');
				try {
					var cfResp = await this.fetch_with_timeout('/cdn-cgi/trace', {}, 2500);
					if (cfResp == null ? void 0 : cfResp.ok) {
						var cfText = await cfResp.text();
						var cfMatch = cfText.match(/ip=([^\n]+)/);
						if ((cfMatch == null ? void 0 : cfMatch[1]) && !this.is_private_ip(cfMatch[1])) {
							this.log_debug('get_ip(): resolved by Cloudflare trace', { ip: cfMatch[1] });
							__tl('geo', 'ip_resolved', {
								source: 'cloudflare',
								ip: cfMatch[1],
								ms: Date.now() - _ipStart,
							});
							try {
								sessionStorage.setItem('alvobot_ip', cfMatch[1]);
							} catch (_e) {}
							return cfMatch[1];
						}
						this.log_warn('get_ip(): Cloudflare trace had no usable IP');
					}
				} catch (e) {
					this.log_warn(
						'get_ip(): Cloudflare trace request failed',
						(e == null ? void 0 : e.message) ? e.message : e,
					);
				}
			}
			try {
				this.log_debug('get_ip(): trying api64.ipify.org');
				var ipifyResp = await this.fetch_with_timeout('https://api64.ipify.org?format=json', {}, 2500);
				if (ipifyResp == null ? void 0 : ipifyResp.ok) {
					var ipifyData = await ipifyResp.json();
					if ((ipifyData == null ? void 0 : ipifyData.ip) && !this.is_private_ip(ipifyData.ip)) {
						this.log_debug('get_ip(): resolved by ipify', { ip: ipifyData.ip });
						__tl('geo', 'ip_resolved', { source: 'ipify', ip: ipifyData.ip, ms: Date.now() - _ipStart });
						try {
							sessionStorage.setItem('alvobot_ip', ipifyData.ip);
						} catch (_e) {}
						return ipifyData.ip;
					}
				}
			} catch (e) {
				this.log_warn('get_ip(): ipify request failed', (e == null ? void 0 : e.message) ? e.message : e);
			}
			this.log_warn('get_ip(): failed to resolve a public IP (CF trace + ipify both failed)');
			__tl('geo', 'ip_failed', { ms: Date.now() - _ipStart });
			return '';
		}
		/**
		 * Fetch helper with timeout support.
		 */
		async fetch_with_timeout(url, options, timeoutMs) {
			var opts = options || {};
			var ms = timeoutMs || 3e3;
			this.log_debug('fetch_with_timeout()', { url, timeout_ms: ms });
			if (typeof AbortController === 'undefined') {
				return fetch(url, opts);
			}
			var controller = new AbortController();
			var timer = setTimeout(function () {
				controller.abort();
			}, ms);
			try {
				return await fetch(
					url,
					Object.assign({}, opts, {
						signal: controller.signal,
					}),
				);
			} finally {
				clearTimeout(timer);
			}
		}
		/**
		 * Get geolocation from localStorage cache only.
		 *
		 * Client-side ipwho.is calls were removed because the API blocks
		 * cross-origin browser requests (403 on many networks/IPv6).
		 * The server-side REST endpoint (/events/track, /leads/track) always
		 * resolves geo via server-to-server calls and returns it in the response.
		 * The resolved geo is then cached here for subsequent events.
		 */
		get_geolocation() {
			var ip = this.data.ip;
			if (!ip) {
				this.log_debug('get_geolocation(): skipped (no IP)');
				return {};
			}
			var GEO_CACHE_TTL = 24 * 60 * 60 * 1e3;
			var cacheKey = 'alvobot_geo_' + ip;
			try {
				var cached = localStorage.getItem(cacheKey);
				if (cached) {
					var parsed = JSON.parse(cached);
					if (
						(parsed == null ? void 0 : parsed.city) &&
						parsed._ts &&
						Date.now() - parsed._ts < GEO_CACHE_TTL
					) {
						this.log_debug('get_geolocation(): cache hit', parsed);
						__tl('geo', 'geo_cache_hit', { ip, city: parsed.city });
						return parsed;
					}
					localStorage.removeItem(cacheKey);
				}
			} catch (_e) {}
			this.log_debug('get_geolocation(): no cache \u2014 will be resolved server-side');
			__tl('geo', 'geo_deferred_to_server', { ip });
			return {};
		}
		/**
		 * Get or generate _fbp cookie.
		 */
		get_fbp() {
			var match = document.cookie.match(/(^|;)\s*_fbp=([^;]+)/);
			if (match) {
				this.log_debug('get_fbp(): cookie found', { fbp: match[2] });
				return match[2];
			}
			var ts = Date.now();
			var rand = Math.floor(Math.random() * 1e10);
			var fbp = 'fb.1.' + ts + '.' + rand;
			this.set_cookie('_fbp', fbp);
			this.log_debug('get_fbp(): generated new fbp', { fbp });
			return fbp;
		}
		/**
		 * Get or generate _fbc from fbclid URL param.
		 */
		get_fbc() {
			var match = document.cookie.match(/(^|;)\s*_fbc=([^;]+)/);
			if (match) {
				this.log_debug('get_fbc(): cookie found', { fbc: match[2] });
				return match[2];
			}
			var params = new URLSearchParams(window.location.search);
			var fbclid = params.get('fbclid');
			if (fbclid) {
				var fbc = 'fb.1.' + Date.now() + '.' + fbclid;
				this.set_cookie('_fbc', fbc);
				this.log_debug('get_fbc(): built from fbclid', { fbc, fbclid });
				return fbc;
			}
			this.log_debug('get_fbc(): no value resolved');
			return '';
		}
		/**
		 * Read a cookie value safely.
		 */
		get_cookie_value(name) {
			var escaped = String(name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			var match = document.cookie.match(new RegExp('(^|;)\\s*' + escaped + '=([^;]+)'));
			if (!match) {
				return '';
			}
			try {
				return decodeURIComponent(match[2]);
			} catch (_e) {
				return match[2];
			}
		}
		/**
		 * Get or create durable anonymous external ID.
		 */
		get_external_id() {
			var configured = this.config.external_id || this.get_cookie_value('_alvo_uid');
			if (configured) {
				this.set_cookie('_alvo_uid', configured, 3456e4);
				return configured;
			}
			var generated = this.generate_event_id();
			this.set_cookie('_alvo_uid', generated, 3456e4);
			return generated;
		}
		/**
		 * Parse a JSON cookie safely.
		 */
		get_json_cookie(name) {
			var value = this.get_cookie_value(name);
			if (!value) {
				return {};
			}
			try {
				return JSON.parse(value);
			} catch (_e) {
				return {};
			}
		}
		/**
		 * Build the multi-network click ID map from URL, cookies and PHP config.
		 */
		get_click_ids() {
			var configured =
				this.config.attribution &&
				this.config.attribution.click_ids &&
				!Array.isArray(this.config.attribution.click_ids)
					? this.config.attribution.click_ids
					: {};
			var fields = {
				fbclid: '_alvo_fbclid',
				gclid: '_alvo_gclid',
				gbraid: '_alvo_gbraid',
				wbraid: '_alvo_wbraid',
				gclsrc: '_alvo_gclsrc',
				dclid: '_alvo_dclid',
				gad_source: '_alvo_gad_source',
				msclkid: '_alvo_msclkid',
				ttclid: '_alvo_ttclid',
				twclid: '_alvo_twclid',
				li_fat_id: '_alvo_li_fat_id',
				sccid: '_alvo_sccid',
				epik: '_alvo_epik',
				rdt_cid: '_alvo_rdt_cid',
				tblci: '_alvo_tblci',
				ob_orig_url: '_alvo_ob_orig_url',
				qclid: '_alvo_qclid',
				ymclk: '_alvo_ymclk',
			};
			var result = {};
			for (var key in fields) {
				if (!Object.prototype.hasOwnProperty.call(fields, key)) {
					continue;
				}
				var value = this.get_click_id(key, fields[key]);
				if (!value && configured[key]) {
					value = configured[key];
				}
				if (value) {
					result[key] = value;
				}
			}
			return result;
		}
		/**
		 * Merge PHP-captured first/last touch with browser fallback context.
		 */
		get_attribution_context() {
			var configured =
				this.config.attribution && !Array.isArray(this.config.attribution) ? this.config.attribution : {};
			var context = Object.assign({}, configured);
			context.external_id = this.data.external_id || context.external_id || '';
			context.click_ids = this.data.click_ids || context.click_ids || {};
			context.utms = this.data.utms || context.utms || {};
			context.first_touch = context.first_touch || this.get_json_cookie('alvobot_attr_ft');
			context.last_touch = context.last_touch || this.get_json_cookie('alvobot_attr_lt');
			context.landing_first = context.landing_first || this.get_cookie_value('alvobot_landing_first');
			context.referrer_first = context.referrer_first || this.get_cookie_value('alvobot_referrer_first');
			context.first_seen = context.first_seen || this.get_cookie_value('alvobot_first_seen');
			context.session_id = context.session_id || this.get_cookie_value('alvobot_session_id');
			context.visit_count = context.visit_count || this.get_cookie_value('alvobot_visit_count') || '';
			return context;
		}
		/**
		 * Edge/CDN context is optional. Empty values are normal on non-Cloudflare hosts.
		 */
		get_edge_context() {
			var configured =
				this.config.attribution && this.config.attribution.edge && !Array.isArray(this.config.attribution.edge)
					? this.config.attribution.edge
					: {};
			return configured || {};
		}
		/**
		 * Capture and persist Google click identifiers from URL or first-party cookie.
		 */
		get_click_id(paramName, cookieName) {
			var params = new URLSearchParams(window.location.search);
			var value = params.get(paramName);
			if (value) {
				this.set_cookie(cookieName, value, 7776e3);
				this.log_debug('get_click_id(): captured from URL', { key: paramName, value });
				return value;
			}
			value = this.get_cookie_value(cookieName);
			if (value) {
				this.log_debug('get_click_id(): cookie found', { key: paramName, value });
				return value;
			}
			this.log_debug('get_click_id(): no value resolved', { key: paramName });
			return '';
		}
		/**
		 * Backward-compatible wrapper for existing gclid call sites.
		 */
		get_gclid() {
			return this.get_click_id('gclid', '_alvo_gclid');
		}
		/**
		 * Normalize currency codes to ISO-4217-ish three-letter uppercase format.
		 */
		normalize_currency_code(currency) {
			if (!currency) {
				return '';
			}
			var normalized = String(currency).trim().toUpperCase();
			return /^[A-Z]{3}$/.test(normalized) ? normalized : '';
		}
		/**
		 * Resolve the currency to use for browser-side Google events.
		 */
		resolve_google_currency(params, custom_data) {
			var _a, _b, _c;
			var candidates = [
				(params == null ? void 0 : params.currency) ? params.currency : '',
				(custom_data == null ? void 0 : custom_data.currency) ? custom_data.currency : '',
				((_a = this.config) == null ? void 0 : _a.site_currency) ? this.config.site_currency : '',
				((_c = (_b = this.data) == null ? void 0 : _b.geolocation) == null ? void 0 : _c.currency)
					? this.data.geolocation.currency
					: '',
				'BRL',
			];
			for (var i = 0; i < candidates.length; i++) {
				var normalized = this.normalize_currency_code(candidates[i]);
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
				var parsed = parseFloat(candidates[i]);
				if (!Number.isNaN(parsed) && Number.isFinite(parsed)) {
					return parsed;
				}
			}
			return null;
		}
		/**
		 * Build shared browser-side Google event params.
		 */
		build_google_event_params(params, tracker2, event_name, custom_data, is_ads_conversion) {
			var googleParams = {};
			var sendId = this.get_google_ads_send_id(tracker2);
			if (sendId && tracker2 && tracker2.type !== 'external') {
				googleParams.send_to = sendId;
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
			var numericValue = this.resolve_google_value(params, custom_data);
			if (null !== numericValue) {
				googleParams.value = numericValue;
				googleParams.currency = this.resolve_google_currency(params, custom_data);
			}
			if (is_ads_conversion) {
				googleParams.transport_type = 'beacon';
				googleParams.event_timeout = 2e3;
				googleParams.event_callback = function () {};
			}
			if (event_name === 'purchase' && !googleParams.transaction_id) {
				googleParams.transaction_id = params.event_id_override || '';
			}
			return googleParams;
		}
		/**
		 * Map Meta standard event names to GA4 recommended event names.
		 */
		map_to_ga4_event(meta_event) {
			var map = {
				PageView: 'page_view',
				ViewContent: 'view_item',
				Lead: 'generate_lead',
				CompleteRegistration: 'sign_up',
				AddToCart: 'add_to_cart',
				AddPaymentInfo: 'add_payment_info',
				Purchase: 'purchase',
				InitiateCheckout: 'begin_checkout',
				AddToWishlist: 'add_to_wishlist',
				Search: 'search',
				Contact: 'contact',
				Schedule: 'schedule',
			};
			return map[meta_event] || meta_event;
		}
		/**
		 * Parse UTM parameters from URL.
		 */
		get_utms() {
			var params = new URLSearchParams(window.location.search);
			var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content', 'src', 'sck'];
			var utms = {};
			for (var i = 0; i < keys.length; i++) {
				var val = params.get(keys[i]);
				if (val) {
					utms[keys[i]] = val;
				}
			}
			this.log_debug('get_utms()', utms);
			return utms;
		}
		/**
		 * Check if current visitor is a bot.
		 */
		is_bot() {
			var ua = navigator.userAgent;
			var bots = ['bot', 'Bot', 'crawl', 'spider', 'HTTrack', 'Lighthouse', 'GTmetrix', 'Page Speed'];
			for (var i = 0; i < bots.length; i++) {
				if (ua.indexOf(bots[i]) !== -1) {
					this.log_warn('is_bot(): matched signature', { signature: bots[i], user_agent: ua });
					return true;
				}
			}
			this.log_debug('is_bot(): false');
			return false;
		}
		/**
		 * Returns true when the IP is private, loopback, or reserved.
		 */
		is_private_ip(ip) {
			var value = (ip || '').trim().toLowerCase();
			if (!value) {
				return true;
			}
			if (value === '::1' || value === '::' || value === 'localhost') {
				return true;
			}
			if (value.indexOf('::ffff:') === 0) {
				value = value.slice(7);
			}
			var isV4 = /^(\d{1,3})(\.\d{1,3}){3}$/.test(value);
			if (isV4) {
				var octets = value.split('.').map(Number);
				if (
					octets.some(function (n) {
						return n < 0 || n > 255;
					})
				) {
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
			if (value.indexOf('fe80:') === 0 || value.indexOf('fc') === 0 || value.indexOf('fd') === 0) {
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
				this.log_debug('generate_event_id(): crypto.randomUUID', { event_id: uuid });
				return uuid;
			}
			var fallbackUuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
				var r = (Math.random() * 16) | 0;
				var v = c === 'x' ? r : (r & 3) | 8;
				return v.toString(16);
			});
			this.log_debug('generate_event_id(): fallback UUID', { event_id: fallbackUuid });
			return fallbackUuid;
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
			var _sendStart = Date.now();
			if (!this.tracking_enabled) {
				this.log_warn('send_event() skipped: tracking disabled', { params });
				return;
			}
			var event_id = params.event_id_override || this.generate_event_id();
			var event_name = params.event_name || 'PageView';
			var self = this;
			var metaBrowserAllowed = this.refresh_meta_pixel_state();
			var googleBrowserAllowed = this.refresh_google_tag_state();
			var custom_data = {
				content_name: params.content_name || '',
			};
			if (this.config.content_type) {
				custom_data.content_type = this.config.content_type;
			}
			if (this.config.content_category) {
				custom_data.content_category = this.config.content_category;
			}
			var defaultCurrency = this.resolve_google_currency(params, custom_data);
			if (defaultCurrency) {
				custom_data.currency = defaultCurrency;
			}
			if (params.custom_data_extra && typeof params.custom_data_extra === 'object') {
				for (var extraKey in params.custom_data_extra) {
					if (Object.prototype.hasOwnProperty.call(params.custom_data_extra, extraKey)) {
						var extraValue = params.custom_data_extra[extraKey];
						if (extraValue !== '' && extraValue !== null && typeof extraValue !== 'undefined') {
							custom_data[extraKey] = extraValue;
						}
					}
				}
			}
			this.log_debug('send_event(): prepared', {
				event_id,
				event_name,
				skip_fbq: !!params.skip_fbq,
				skip_google_ads: !!params.skip_google_ads,
				meta_browser_allowed: metaBrowserAllowed,
				google_browser_allowed: googleBrowserAllowed,
				params,
				custom_data,
			});
			var selected_ids = params.fb_pixels
				? params.fb_pixels
						.split(',')
						.map(function (s) {
							return s.trim();
						})
						.filter(Boolean)
				: [];
			var filter_active = selected_ids.length > 0;
			var platforms = params.platforms || 'all';
			if (filter_active && !params.platforms) {
				var hasMeta = selected_ids.some(function (id) {
					return /^\d{15,16}$/.test(id);
				});
				var hasGoogle = selected_ids.some(function (id) {
					return /^(G-|AW-)/.test(id) || id === 'sitekit_gtag';
				});
				if (hasMeta && !hasGoogle) {
					platforms = 'meta_only';
				} else if (hasGoogle && !hasMeta) {
					platforms = 'google_only';
				}
			}
			if (!params.skip_fbq && metaBrowserAllowed && window.fbq && platforms !== 'google_only') {
				if (filter_active) {
					var fbq_single_method = params.event_custom ? 'trackSingleCustom' : 'trackSingle';
					selected_ids.forEach(function (pid) {
						if (/^\d{15,16}$/.test(pid)) {
							self.log_debug('send_event(): fbq trackSingle', { pixel: pid, event: event_name });
							window.fbq(fbq_single_method, pid, event_name, custom_data, { eventID: event_id });
						}
					});
				} else {
					var fbq_method = params.event_custom ? 'trackCustom' : 'track';
					this.log_debug('send_event(): fbq dispatch', {
						method: fbq_method,
						event_name,
						event_id,
					});
					window.fbq(fbq_method, event_name, custom_data, { eventID: event_id });
				}
			}
			if (window.gtag && googleBrowserAllowed && platforms !== 'meta_only') {
				var trackers = self.get_google_trackers();
				var hasManagedGoogleTarget = trackers.some(function (tracker2) {
					if (tracker2.type === 'external') {
						return false;
					}
					if (filter_active && selected_ids.indexOf(tracker2.tracker_id) === -1) {
						return false;
					}
					return true;
				});
				trackers.forEach(function (tracker2) {
					if (filter_active && selected_ids.indexOf(tracker2.tracker_id) === -1) {
						return;
					}
					if (params.skip_google_ads && tracker2.type === 'google_ads') {
						return;
					}
					if (tracker2.type === 'external') {
						if (hasManagedGoogleTarget) {
							return;
						}
						var ext_event_name = params.event_custom ? event_name : self.map_to_ga4_event(event_name);
						if (params.is_initial_pageview && ext_event_name === 'page_view') {
							return;
						}
						var ext_params = self.build_google_event_params(
							params,
							null,
							ext_event_name,
							custom_data,
							false,
						);
						window.gtag('event', ext_event_name, ext_params);
						self.log_debug('send_event(): gtag external dispatch (no send_to)', { event: ext_event_name });
						return;
					}
					if (tracker2.type === 'ga4') {
						var ga_event_name = params.event_custom ? event_name : self.map_to_ga4_event(event_name);
						var ga_params = self.build_google_event_params(
							params,
							tracker2,
							ga_event_name,
							custom_data,
							false,
						);
						window.gtag('event', ga_event_name, ga_params);
						self.log_debug('send_event(): gtag GA4 dispatch', {
							tracker: tracker2.tracker_id,
							event: ga_event_name,
						});
					}
					if (tracker2.type === 'google_ads') {
						var labels_map = params.gads_labels_map || {};
						var gads_label =
							labels_map[tracker2.tracker_id] ||
							params.gads_conversion_label ||
							tracker2.conversion_label;
						if (gads_label) {
							var gads_params = self.build_google_event_params(
								params,
								tracker2,
								'conversion',
								custom_data,
								true,
							);
							var adsSendId = self.get_google_ads_send_id(tracker2);
							if (!adsSendId) {
								self.log_debug('send_event(): Google Ads tag_id missing', {
									tracker: tracker2.tracker_id,
								});
								return;
							}
							gads_params.send_to = adsSendId + '/' + gads_label;
							window.gtag('event', 'conversion', gads_params);
							self.log_debug('send_event(): gtag Ads conversion', {
								tracker: tracker2.tracker_id,
								tag_id: adsSendId,
								label: gads_label,
							});
						}
					}
				});
			}
			__tl(platforms === 'meta_only' ? 'meta' : platforms === 'google_only' ? 'google' : 'event', event_name, {
				event_id,
				platforms,
			});
			var _fetchStart = Date.now();
			try {
				var eventPayload = {
					event_id,
					event_name,
					event_url: window.location.href.replace(/#google_vignette$/, ''),
					page_url: window.location.href.replace(/#google_vignette$/, ''),
					page_title: document.title,
					page_id: this.config.page_id,
					referrer: document.referrer || '',
					external_id: this.data.external_id || '',
					fbp: this.data.fbp,
					fbc: this.data.fbc,
					ip: this.data.ip,
					browser_ip: this.data.ip,
					user_agent: this.data.user_agent,
					geo: this.data.geolocation,
					utms: this.data.utms,
					click_ids: this.data.click_ids || {},
					attribution: this.data.attribution || {},
					edge_context: this.data.edge_context || {},
					lead_id: this.data.lead_id || '',
					user_data_hashed:
						this.config.user_data_hashed && !Array.isArray(this.config.user_data_hashed)
							? this.config.user_data_hashed
							: {},
					custom_data,
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
				this.log_debug('send_event(): POST /events/track payload', eventPayload);
				fetch(this.config.api_event, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-Alvobot-Nonce': this.config.nonce || '',
					},
					body: JSON.stringify(eventPayload),
					keepalive: true,
				})
					.then(function (resp) {
						self.log_debug('send_event(): /events/track response headers', {
							ok: !!(resp == null ? void 0 : resp.ok),
							status: resp ? resp.status : 0,
						});
						if (!(resp == null ? void 0 : resp.ok)) {
							return null;
						}
						return resp.json().catch(function () {
							return null;
						});
					})
					.then(function (result) {
						var _a, _b;
						if (!result) {
							self.log_warn('send_event(): empty/non-OK response body from /events/track');
							return;
						}
						self.log_debug('send_event(): /events/track response body', result);
						__tl('server', 'response: ' + event_name, {
							ms: Date.now() - _fetchStart,
							resolved_ip: result.resolved_ip,
							resolved_geo: result.resolved_geo,
							capi_response: result.capi_response,
						});
						if (result.resolved_ip) {
							var currentIsPublic = self.data.ip && !self.is_private_ip(self.data.ip);
							var resolvedIsPublic = !self.is_private_ip(result.resolved_ip);
							if (resolvedIsPublic && !currentIsPublic) {
								self.data.ip = result.resolved_ip;
								self.log_debug('send_event(): updated in-memory IP from server', {
									resolved_ip: result.resolved_ip,
								});
								try {
									sessionStorage.setItem('alvobot_ip', result.resolved_ip);
								} catch (_e) {}
							}
						}
						if (
							((_a = result.resolved_geo) == null ? void 0 : _a.city) &&
							!((_b = self.data.geolocation) == null ? void 0 : _b.city)
						) {
							self.data.geolocation = result.resolved_geo;
							self.log_debug('send_event(): updated in-memory geo from server', result.resolved_geo);
							if (self.data.ip) {
								try {
									localStorage.setItem(
										'alvobot_geo_' + self.data.ip,
										JSON.stringify(Object.assign({}, result.resolved_geo, { _ts: Date.now() })),
									);
								} catch (_e) {}
							}
						}
					})
					.catch(function (err) {
						self.log_error(
							'send_event(): fetch failed',
							(err == null ? void 0 : err.message) ? err.message : err,
						);
					});
			} catch (e) {
				this.log_error(
					'send_event(): unexpected error before fetch',
					(e == null ? void 0 : e.message) ? e.message : e,
				);
			}
		}
		/**
		 * Monitor forms for lead capture.
		 */
		monitor_forms() {
			var self = this;
			this.log_debug('monitor_forms(): starting DOM scan loop');
			function scanForms() {
				document.querySelectorAll('form').forEach(function (form) {
					if (form.classList.contains('alvobot_tracked')) {
						return;
					}
					form.classList.add('alvobot_tracked');
					self.log_debug('monitor_forms(): form instrumented', {
						action: form.getAttribute('action') || '',
						id: form.getAttribute('id') || '',
						name: form.getAttribute('name') || '',
					});
					form.addEventListener('submit', function () {
						self.capture_form_data(form);
					});
					form.querySelectorAll('input[type="email"], input[name*="email"], input[name*="Email"]').forEach(
						function (input) {
							input.addEventListener('change', function () {
								if (input.value && input.value.indexOf('@') !== -1) {
									self.capture_form_data(form);
								}
							});
						},
					);
				});
			}
			scanForms();
			setInterval(scanForms, 5e3);
		}
		/**
		 * Extract lead data from a form and send to server.
		 */
		capture_form_data(form) {
			var data = {};
			var inputs = form.querySelectorAll('input, select, textarea');
			for (var i = 0; i < inputs.length; i++) {
				var input = inputs[i];
				var name = (input.name || '').toLowerCase();
				var type = (input.type || '').toLowerCase();
				var val = input.value;
				if (!val) {
					continue;
				}
				if (type === 'email' || name.indexOf('email') !== -1 || name.indexOf('e-mail') !== -1) {
					if (val.indexOf('@') !== -1) {
						data.email = val;
					}
				} else if (
					type === 'tel' ||
					name.indexOf('phone') !== -1 ||
					name.indexOf('tel') !== -1 ||
					name.indexOf('fone') !== -1 ||
					name.indexOf('celular') !== -1
				) {
					data.phone = val;
				} else if (name === 'name' || name === 'nome' || name === 'full_name' || name === 'fullname') {
					data.name = val;
				} else if (name === 'fname' || name === 'first_name' || name === 'primeiro_nome') {
					data.first_name = val;
				} else if (name === 'lname' || name === 'last_name' || name === 'sobrenome') {
					data.last_name = val;
				}
			}
			if (data.email) {
				this.log_debug('capture_form_data(): lead candidate found', data);
				this.send_lead_data(data);
			} else {
				this.log_debug('capture_form_data(): skipped, no email found');
			}
		}
		/**
		 * Send lead data to the WordPress REST API.
		 */
		async send_lead_data(formData) {
			var _a, _b;
			var lead_id = this.data.lead_id || this.generate_event_id();
			var payload = {
				lead_id,
				email: formData.email || '',
				name: formData.name || '',
				first_name: formData.first_name || '',
				last_name: formData.last_name || '',
				phone: formData.phone || '',
				external_id: this.data.external_id || '',
				fbp: this.data.fbp,
				fbc: this.data.fbc,
				ip: this.data.ip,
				browser_ip: this.data.ip,
				user_agent: this.data.user_agent,
				geo: this.data.geolocation,
				utms: this.data.utms,
				click_ids: this.data.click_ids || {},
				attribution: this.data.attribution || {},
				edge_context: this.data.edge_context || {},
				src: this.data.utms ? this.data.utms.src : '',
				gclid: this.data.gclid || '',
				gbraid: this.data.gbraid || '',
				wbraid: this.data.wbraid || '',
				dclid: this.data.dclid || '',
				ga_client_id: this.data.ga_client_id || '',
				sck: this.data.utms ? this.data.utms.sck : '',
			};
			this.log_debug('send_lead_data(): POST /leads/track payload', payload);
			try {
				var resp = await fetch(this.config.api_lead, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-Alvobot-Nonce': this.config.nonce || '',
					},
					body: JSON.stringify(payload),
					keepalive: true,
				});
				this.log_debug('send_lead_data(): /leads/track response headers', {
					ok: !!(resp == null ? void 0 : resp.ok),
					status: resp ? resp.status : 0,
				});
				if (!(resp == null ? void 0 : resp.ok)) {
					this.log_warn('send_lead_data(): non-OK response', { status: resp ? resp.status : 0 });
					return;
				}
				var result = await resp.json();
				this.log_debug('send_lead_data(): /leads/track response body', result);
				if ((result == null ? void 0 : result.success) && result.lead_id) {
					this.data.lead_id = result.lead_id;
					this.set_cookie('alvobot_lead_id', result.lead_id);
					this.log_debug('send_lead_data(): lead_id updated', { lead_id: result.lead_id });
				}
				if (result == null ? void 0 : result.resolved_ip) {
					var currentIsPublic = this.data.ip && !this.is_private_ip(this.data.ip);
					var resolvedIsPublic = !this.is_private_ip(result.resolved_ip);
					if (resolvedIsPublic && !currentIsPublic) {
						this.data.ip = result.resolved_ip;
						this.log_debug('send_lead_data(): updated in-memory IP from server', {
							resolved_ip: result.resolved_ip,
						});
						try {
							sessionStorage.setItem('alvobot_ip', result.resolved_ip);
						} catch (_e) {}
					}
				}
				if (
					((_a = result == null ? void 0 : result.resolved_geo) == null ? void 0 : _a.city) &&
					!((_b = this.data.geolocation) == null ? void 0 : _b.city)
				) {
					this.data.geolocation = result.resolved_geo;
					this.log_debug('send_lead_data(): updated in-memory geo from server', result.resolved_geo);
					if (this.data.ip) {
						try {
							localStorage.setItem(
								'alvobot_geo_' + this.data.ip,
								JSON.stringify(Object.assign({}, result.resolved_geo, { _ts: Date.now() })),
							);
						} catch (_e2) {}
					}
				}
			} catch (e) {
				this.log_error('send_lead_data(): request failed', (e == null ? void 0 : e.message) ? e.message : e);
			}
		}
		/**
		 * Set a cookie with standard tracking defaults.
		 */
		set_cookie(name, value, max_age_seconds) {
			var expires = /* @__PURE__ */ new Date();
			var ttl =
				typeof max_age_seconds !== 'undefined' && max_age_seconds !== null
					? max_age_seconds
					: 180 * 24 * 60 * 60;
			expires.setTime(expires.getTime() + ttl * 1e3);
			var cookie =
				name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
			if (window.location.protocol === 'https:') {
				cookie += ';Secure';
			}
			// biome-ignore lint/suspicious/noDocumentCookie: The tracking client must maintain first-party attribution cookies.
			document.cookie = cookie;
			this.log_debug('set_cookie()', { name, value });
		}
	}
	var tracker = new AlvoBotPixelTracking();
	window.alvobot_pixel = tracker;
	if (window.alvobot_pixel_config) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				tracker.start(window.alvobot_pixel_config);
			});
		} else {
			tracker.start(window.alvobot_pixel_config);
		}
	}
	var _P = '%c[AlvoBot Debug]';
	var _I = 'background:#fbbf24;color:#0E100D;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _M = 'background:#1877F2;color:#fff;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _G = 'background:#4285F4;color:#fff;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _S = 'background:#12B76A;color:#fff;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _A = 'background:#F63D68;color:#fff;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _GE = 'background:#EAB308;color:#000;padding:2px 6px;border-radius:4px;font-weight:bold';
	var _SY = 'background:#6B7280;color:#fff;padding:2px 6px;border-radius:4px;font-weight:bold';
	function _installInterceptors() {
		if (window.__alvobot_debug_installed) {
			return;
		}
		window.__alvobot_debug_installed = true;
		if (typeof window.fbq === 'function' && !window.fbq.__alvobot_debug) {
			var origFbq = window.fbq;
			window.fbq = function () {
				var args = Array.prototype.slice.call(arguments);
				var action = args[0];
				if (
					action === 'track' ||
					action === 'trackCustom' ||
					action === 'trackSingle' ||
					action === 'trackSingleCustom'
				) {
					console.group(_P + ' %c META fbq("' + action + '", "' + args[1] + '")', _I, _M);
					console.log('Event:', args[1]);
					if (args[2]) {
						console.log('Params:', args[2]);
					}
					if (args[3]) {
						console.log('Options (eventID):', args[3]);
					}
					console.groupEnd();
					__tl('meta', 'fbq.' + action + ':' + args[1], { params: args[2], options: args[3] });
				} else if (action === 'init') {
					console.log(_P + ' %c META fbq("init", "' + args[1] + '")', _I, _M, 'userData:', args[2] || {});
					__tl('meta', 'fbq.init:' + args[1], { userData_keys: args[2] ? Object.keys(args[2]) : [] });
				}
				return origFbq.apply(this, args);
			};
			window.fbq.__alvobot_debug = true;
			for (var key in origFbq) {
				if (Object.prototype.hasOwnProperty.call(origFbq, key)) {
					window.fbq[key] = origFbq[key];
				}
			}
		}
		if (typeof window.gtag === 'function' && !window.gtag.__alvobot_debug) {
			var origGtag = window.gtag;
			window.gtag = function () {
				var args = Array.prototype.slice.call(arguments);
				if (args[0] === 'event') {
					var isConv = args[1] === 'conversion';
					console.group(
						_P + ' %c GOOGLE gtag("event", "' + args[1] + '")' + (isConv ? ' CONVERSION' : ''),
						_I,
						isConv ? _A : _G,
					);
					if (args[2]) {
						console.log('Params:', args[2]);
						if (args[2].send_to) {
							console.log('send_to:', args[2].send_to);
						}
						if (args[2].value !== void 0) {
							console.log('value:', args[2].value, args[2].currency || '');
						}
					}
					console.groupEnd();
					__tl('google', 'gtag.' + args[1], {
						send_to: args[2] ? args[2].send_to : void 0,
						conversion: isConv,
					});
				} else if (args[0] === 'config') {
					console.log(_P + ' %c GOOGLE gtag("config", "' + args[1] + '")', _I, _G, args[2] || '');
					__tl('google', 'gtag.config:' + args[1], {});
				}
				return origGtag.apply(this, args);
			};
			window.gtag.__alvobot_debug = true;
		}
		if (typeof window.fetch === 'function' && !window.fetch.__alvobot_debug) {
			var origFetch = window.fetch;
			window.fetch = function (url, opts) {
				var args = Array.prototype.slice.call(arguments, 2);
				var urlStr = typeof url === 'string' ? url : (url == null ? void 0 : url.url) ? url.url : '';
				if (urlStr.indexOf('alvobot-pro/v1/pixel-tracking') !== -1) {
					var endpoint = urlStr.indexOf('/leads/') !== -1 ? 'LEAD' : 'EVENT';
					var payload = null;
					var _t0 = Date.now();
					try {
						payload = (opts == null ? void 0 : opts.body) ? JSON.parse(opts.body) : null;
					} catch (_e) {}
					console.group(_P + ' %c SERVER ' + endpoint + ' \u2192 ' + urlStr.split('?')[0], _I, _S);
					if (payload) {
						console.log('event_name:', payload.event_name, '| event_id:', payload.event_id);
						console.log('fbp:', payload.fbp, '| fbc:', payload.fbc);
						console.log('ip:', payload.ip, '| geo:', payload.geo);
						console.log('user_data_hashed:', payload.user_data_hashed);
						console.log('pixel_ids:', payload.pixel_ids);
						console.log('gclid:', payload.gclid, '| ga_client_id:', payload.ga_client_id);
						console.log(
							'gads_conversion_label:',
							payload.gads_conversion_label,
							'| gads_labels_map:',
							payload.gads_labels_map,
						);
						console.log('Full payload:', payload);
					}
					console.groupEnd();
					return origFetch.apply(this, [url, opts].concat(args)).then(function (resp) {
						var cloned = resp.clone();
						cloned
							.json()
							.then(function (body) {
								var ms = Date.now() - _t0;
								console.group(
									_P + ' %c SERVER RESPONSE \u2190 ' + endpoint + ' (' + ms + 'ms)',
									_I,
									_S,
								);
								console.log('HTTP:', resp.status, '| Body:', body);
								if (body.resolved_ip) {
									console.log('Resolved IP:', body.resolved_ip);
								}
								if (body.resolved_geo) {
									console.log('Resolved Geo:', body.resolved_geo);
								}
								if (body.capi_response) {
									console.log('CAPI Response:', body.capi_response);
								}
								console.groupEnd();
							})
							.catch(function () {});
						return resp;
					});
				}
				return origFetch.apply(this, [url, opts].concat(args));
			};
			window.fetch.__alvobot_debug = true;
		}
		document.addEventListener('alvobot:ad_event', function (e) {
			console.group(_P + ' %c AD EVENT: ' + (e.detail ? e.detail.event_name : '?'), _I, _A);
			console.log('Detail:', e.detail);
			console.groupEnd();
		});
		window.__alvobot_ad_tracker_debug = true;
	}
	window.alvobot_debug = function () {
		// biome-ignore lint/suspicious/noDocumentCookie: Debug mode is intentionally controlled through a short-lived first-party cookie.
		document.cookie =
			'alvobot_debug=1;path=/;max-age=3600;SameSite=Lax' +
			(window.location.protocol === 'https:' ? ';Secure' : '');
		var t = window.alvobot_pixel;
		if (t) {
			t.debug_enabled = true;
			t.debug_prefix = '[AlvoBot Debug]';
		}
		_installInterceptors();
		var config = window.alvobot_pixel_config || {};
		console.group(_P + ' State Dump', _I);
		console.log(
			'%cPlugin v' +
				(config.plugin_version || '?') +
				(config.test_mode ? ' | TEST MODE (' + config.test_event_code + ')' : ' | Live'),
			'font-weight:bold;font-size:13px',
		);
		if (t) {
			console.log('Initialized:', t.initialized, '| Tracking:', t.tracking_enabled);
			console.log('Meta allowed:', t.meta_browser_allowed, '| Google allowed:', t.google_browser_allowed);
			console.log('Tracker data:', JSON.parse(JSON.stringify(t.data)));
		}
		console.groupCollapsed('Config (full)');
		console.log(JSON.parse(JSON.stringify(config)));
		console.groupEnd();
		var fbpMatch = document.cookie.match(/(^|;)\s*_fbp=([^;]+)/);
		var fbcMatch = document.cookie.match(/(^|;)\s*_fbc=([^;]+)/);
		console.log('_fbp:', fbpMatch ? fbpMatch[2] : 'not set', '| _fbc:', fbcMatch ? fbcMatch[2] : 'not set');
		if (typeof window.fbq !== 'undefined' && typeof window.fbq.getState === 'function') {
			var pxState = window.fbq.getState();
			console.log(
				'fbq pixels:',
				pxState.pixels.map(function (p) {
					return { id: p.id, userData: p.userData, eventCount: p.eventCount };
				}),
			);
		}
		console.log('user_data_hashed:', config.user_data_hashed);
		console.log('pageview_event_id:', config.pageview_event_id);
		console.log('Google trackers:', config.google_trackers || []);
		console.log('site_currency:', config.site_currency);
		console.log('ad_conversion_triggers:', config.ad_conversion_triggers);
		if (config.cf_headers && Object.keys(config.cf_headers).length > 0) {
			console.log('%cCloudflare headers:', 'color:#f38020;font-weight:bold', config.cf_headers);
		}
		if (config.debug_conversion_rules && config.debug_conversion_rules.length > 0) {
			console.groupCollapsed(
				'Conversion Rules (' + config.debug_conversion_rules.length + ' active on this page)',
			);
			console.table(
				config.debug_conversion_rules.map(function (r) {
					return {
						title: r.title,
						event: r.event_name,
						trigger: r.trigger + (r.trigger_value ? ':' + r.trigger_value : ''),
						platforms: r.platforms || 'all',
						gads_label: r.gads_conversion_label || '-',
						gads_labels_map: r.gads_labels_map ? JSON.stringify(r.gads_labels_map) : '-',
						value: r.gads_conversion_value || '-',
					};
				}),
			);
			console.groupEnd();
		}
		console.groupEnd();
		if (__timeline.length > 0) {
			console.group(_P + ' Event Timeline (' + __timeline.length + ' events captured)', _I);
			var t0 = __timeline[0].ts;
			console.table(
				__timeline.map(function (e) {
					return {
						'+ms': e.ts - t0,
						time: e.iso.split('T')[1],
						type: e.type,
						event: e.name,
						detail: JSON.stringify(e.detail).substring(0, 100),
					};
				}),
			);
			console.groupEnd();
		}
		console.log(
			'\n' +
				_P +
				' Debug mode ACTIVE (cookie set for 1 hour).\n  Meta Pixel (fbq) \u2192 blue | Google (gtag) \u2192 blue/red\n  Server API (fetch) \u2192 green | Ad events \u2192 red\n  Geo/IP \u2192 yellow | System \u2192 gray\n\n  alvobot_debug_off()      \u2014 disable debug\n  alvobot_debug_timeline() \u2014 show event timeline\n',
			_I,
		);
		return 'AlvoBot Debug ON';
	};
	window.alvobot_debug_off = function () {
		// biome-ignore lint/suspicious/noDocumentCookie: Debug mode is intentionally controlled through a short-lived first-party cookie.
		document.cookie =
			'alvobot_debug=1;path=/;max-age=0;SameSite=Lax' + (window.location.protocol === 'https:' ? ';Secure' : '');
		var t = window.alvobot_pixel;
		if (t) {
			t.debug_enabled = false;
		}
		console.log(_P + ' Debug mode OFF. Reload to fully deactivate interceptors.', _I);
		return 'AlvoBot Debug OFF';
	};
	window.alvobot_debug_timeline = function () {
		if (!__timeline.length) {
			console.log(_P + ' No events in timeline.', _I);
			return;
		}
		var t0 = __timeline[0].ts;
		var cssMap = { meta: _M, google: _G, server: _S, ad: _A, geo: _GE, system: _SY };
		console.group(_P + ' Event Timeline (' + __timeline.length + ' events)', _I);
		__timeline.forEach(function (e) {
			var css = cssMap[e.type] || _SY;
			console.log(
				_P + ' %c +' + (e.ts - t0) + 'ms [' + e.type + '] ' + e.name,
				_I,
				css,
				Object.keys(e.detail).length > 0 ? e.detail : '',
			);
		});
		console.groupEnd();
	};
})();
