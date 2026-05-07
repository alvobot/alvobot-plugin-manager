(function () {
	var _a;
	var debug = !!((_a = window.alvobot_pixel_config) == null ? void 0 : _a.debug_enabled);
	var LOG_PREFIX = '[AlvoBot AdTracker]';
	Object.defineProperty(window, '__alvobot_ad_tracker_debug', {
		get: function () {
			return debug;
		},
		set: function (v) {
			debug = !!v;
		},
		configurable: true,
	});
	function log() {
		var args = Array.prototype.slice.call(arguments);
		var _a2;
		if (!debug || !((_a2 = window.console) == null ? void 0 : _a2.log)) {
			return;
		}
		args.unshift(LOG_PREFIX);
		window.console.log.apply(window.console, args);
	}
	function tl(name, detail) {
		if (typeof window.__alvobot_timeline_push === 'function') {
			window.__alvobot_timeline_push('ad', name, detail || {});
		}
	}
	var _eventCooldowns = {};
	function isRateLimited(eventName, key, cooldownMs) {
		var id = eventName + ':' + (key || 'global');
		var now = Date.now();
		var lastFired = _eventCooldowns[id] || 0;
		if (now - lastFired < cooldownMs) {
			log('Rate limited:', id, '(' + (now - lastFired) + 'ms < ' + cooldownMs + 'ms cooldown)');
			tl('rate_limited', { event: eventName, key, elapsed: now - lastFired, cooldown: cooldownMs });
			return true;
		}
		_eventCooldowns[id] = now;
		return false;
	}
	var COOLDOWN = {
		ad_impression: 3e4,
		// 30s per slot — same ad can't re-impress that fast
		ad_click: 5e3,
		// 5s per iframe — prevents double-click spam
		ad_vignette_open: 6e4,
		// 60s global — Google limits vignettes to 1 per minute
		ad_vignette_click: 6e4,
		// 60s global — can't click vignette twice in 1 minute
	};
	function generateUUID() {
		if (typeof window.crypto !== 'undefined' && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = (Math.random() * 16) | 0;
			return (c === 'x' ? r : (r & 3) | 8).toString(16);
		});
	}
	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}
	function getTracker() {
		return window.alvobot_pixel || null;
	}
	function isMetaBrowserAllowed() {
		var tracker = getTracker();
		if (tracker && typeof tracker.refresh_meta_pixel_state === 'function') {
			return tracker.refresh_meta_pixel_state();
		}
		return typeof window.fbq === 'function';
	}
	function isGoogleBrowserAllowed() {
		var tracker = getTracker();
		if (tracker && typeof tracker.refresh_google_tag_state === 'function') {
			return tracker.refresh_google_tag_state();
		}
		return true;
	}
	var META_EVENT_NAMES = {
		ad_impression: 'AdImpression',
		ad_click: 'AdClick',
		ad_vignette_open: 'AdVignetteOpen',
		ad_vignette_click: 'AdVignetteClick',
	};
	function sendToMeta(ga4EventName, params, eventId) {
		if (!isMetaBrowserAllowed() || typeof window.fbq !== 'function') {
			log('fbq indispon\xEDvel para', ga4EventName);
			return;
		}
		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;
		window.fbq('trackCustom', metaName, params, { eventID: eventId });
		log('Meta enviado:', metaName, '(eventID:', eventId, ')');
	}
	function sendToGA4(eventName, params) {
		if (!isGoogleBrowserAllowed() || typeof window.gtag !== 'function') {
			log('gtag indispon\xEDvel para', eventName);
			return;
		}
		var payload = Object.assign({ transport_type: 'beacon' }, params || {});
		window.gtag('event', eventName, payload);
		log('GA4 enviado:', eventName);
	}
	function sendToRestAPI(ga4EventName, adParams, eventId) {
		var config = window.alvobot_pixel_config;
		if (!(config == null ? void 0 : config.api_event) || !config.pixel_ids) {
			return;
		}
		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;
		var cleanUrl = window.location.href.replace(/#google_vignette$/, '');
		var tracker = getTracker();
		var trackerData = (tracker == null ? void 0 : tracker.data) ? tracker.data : {};
		var payload = {
			event_id: eventId,
			event_name: metaName,
			event_url: cleanUrl,
			page_url: cleanUrl,
			page_title: document.title || '',
			page_id: config.page_id || '0',
			fbp: getCookie('_fbp'),
			fbc: getCookie('_fbc'),
			gclid: trackerData.gclid || getCookie('_alvo_gclid'),
			gbraid: trackerData.gbraid || getCookie('_alvo_gbraid'),
			wbraid: trackerData.wbraid || getCookie('_alvo_wbraid'),
			dclid: trackerData.dclid || getCookie('_alvo_dclid'),
			ga_client_id: trackerData.ga_client_id || '',
			ip: trackerData.ip || '',
			browser_ip: trackerData.ip || '',
			geo: trackerData.geolocation || {},
			user_agent: navigator.userAgent || '',
			user_data_hashed: config.user_data_hashed || {},
			pixel_ids: config.pixel_ids || '',
			custom_data: {
				ad_position: adParams.ad_position || '',
				ad_slot_id: adParams.ad_slot_id || '',
			},
		};
		var headers = { 'Content-Type': 'application/json' };
		if (config.nonce) {
			headers['X-Alvobot-Nonce'] = config.nonce;
		}
		try {
			fetch(config.api_event, {
				method: 'POST',
				headers,
				body: JSON.stringify(payload),
				keepalive: true,
			})
				.then(function (response) {
					log('REST API resposta:', response.status, metaName, eventId);
				})
				.catch(function (err) {
					log('REST API erro:', metaName, err.message);
				});
		} catch (e) {
			log('REST API exce\xE7\xE3o:', metaName, e.message);
		}
	}
	function isAdEventHandledByConversionRule(eventName) {
		var cfg = window.alvobot_pixel_config;
		if (!(cfg == null ? void 0 : cfg.ad_conversions_active)) {
			return false;
		}
		var triggers = cfg.ad_conversion_triggers || [];
		return triggers.indexOf(eventName) !== -1;
	}
	function dispatchAdEvent(eventName, adPosition, adSlotId) {
		var cooldownMs = COOLDOWN[eventName] || 0;
		if (cooldownMs > 0) {
			var cooldownKey =
				eventName === 'ad_vignette_open' || eventName === 'ad_vignette_click'
					? 'global'
					: adSlotId || adPosition || 'unknown';
			if (isRateLimited(eventName, cooldownKey, cooldownMs)) {
				return;
			}
		}
		if (eventName === 'ad_impression' && vignetteOpened) {
			log('ad_impression suppressed: vignette is open (overlay ad, not banner)');
			tl('impression_suppressed_vignette', { ad_slot_id: adSlotId, ad_position: adPosition });
			return;
		}
		var eventId = generateUUID();
		var cleanUrl = window.location.href.replace(/#google_vignette$/, '');
		var handledByRule = isAdEventHandledByConversionRule(eventName);
		var adParams = {
			ad_position: adPosition,
			ad_slot_id: adSlotId || '',
		};
		var fullParams = {
			ad_position: adPosition,
			ad_slot_id: adSlotId || '',
			page_url: cleanUrl,
			timestamp: /* @__PURE__ */ new Date().toISOString(),
		};
		log('dispatchAdEvent:', eventName, adPosition, '(eventId:', eventId, ')');
		tl(eventName, { ad_position: adPosition, ad_slot_id: adSlotId || '', event_id: eventId });
		try {
			document.dispatchEvent(
				new CustomEvent('alvobot:ad_event', {
					detail: {
						event_name: eventName,
						ad_position: adPosition,
						ad_slot_id: adSlotId || '',
						event_id: eventId,
					},
				}),
			);
		} catch (e) {
			log('CustomEvent dispatch failed:', e.message);
		}
		if (!handledByRule) {
			sendToMeta(eventName, fullParams, eventId);
			sendToGA4(eventName, fullParams);
		}
		if (!handledByRule) {
			sendToRestAPI(eventName, adParams, eventId);
		}
	}
	function positionFromAdIdentifier(identifier) {
		if (!identifier) {
			return 'unknown';
		}
		if (identifier.indexOf('interstitial') !== -1) {
			return 'interstitial';
		}
		if (identifier.indexOf('_content_1') !== -1) {
			return 'content_1';
		}
		if (identifier.indexOf('_content_2') !== -1) {
			return 'content_2';
		}
		if (identifier.indexOf('_top') !== -1) {
			return 'top';
		}
		return 'unknown';
	}
	function slotIdFromIframeId(iframeId) {
		if (!iframeId) {
			return '';
		}
		var match = iframeId.match(/^google_ads_iframe_(\/[\w/.-]+?)_\d+$/);
		return match ? match[1] : '';
	}
	var vignetteOpened = false;
	var vignetteOpenTimestamp = 0;
	var vignetteSlotId = '';
	var interstitialFilled = false;
	var interstitialFilledAt = 0;
	var INTERSTITIAL_SIGNAL_TTL_MS = 45e3;
	var _vignetteCloseCheckInterval = null;
	function checkVignetteClosure() {
		if (!vignetteOpened) {
			return;
		}
		var bodyCleared = !document.body || document.body.getAttribute('aria-hidden') !== 'true';
		var domCleared = !hasInterstitialIframeInDom();
		if (bodyCleared && domCleared) {
			vignetteOpened = false;
			vignetteOpenTimestamp = 0;
			interstitialFilled = false;
			interstitialFilledAt = 0;
			vignetteSlotId = '';
			if (_vignetteCloseCheckInterval) {
				clearInterval(_vignetteCloseCheckInterval);
				_vignetteCloseCheckInterval = null;
			}
			cleanVignetteUrlMarker();
			log('Vinheta fechada \u2014 estado resetado, URL limpa, impress\xF5es reativadas');
			tl('vignette_closed', { body_cleared: bodyCleared, dom_cleared: domCleared });
		}
	}
	function cleanVignetteUrlMarker() {
		var _a2;
		var href = window.location.href;
		if (href.indexOf('google_vignette') === -1) {
			return;
		}
		try {
			var url = new URL(href);
			var changed = false;
			if (url.hash === '#google_vignette') {
				url.hash = '';
				changed = true;
			}
			if (url.searchParams.has('google_vignette')) {
				url.searchParams.delete('google_vignette');
				changed = true;
			}
			if (changed && ((_a2 = window.history) == null ? void 0 : _a2.replaceState)) {
				var cleanUrl = url.toString().replace(/[?#]$/, '');
				window.history.replaceState(window.history.state, '', cleanUrl);
				log('URL limpa de google_vignette:', cleanUrl);
			}
		} catch (_e) {
			if (window.location.hash === '#google_vignette' && window.history && window.history.replaceState) {
				var clean = href.replace(/#google_vignette$/, '');
				window.history.replaceState(window.history.state, '', clean);
			}
		}
	}
	function markVignetteAsOpen(source) {
		if (vignetteOpened) {
			return;
		}
		vignetteOpened = true;
		vignetteOpenTimestamp = Date.now();
		if (!_vignetteCloseCheckInterval) {
			_vignetteCloseCheckInterval = setInterval(checkVignetteClosure, 500);
		}
		var slotPath = vignetteSlotId || '';
		var signals = {
			source,
			url_hash: hasGoogleVignetteMarker(window.location.href),
			dom_iframe: hasInterstitialIframeInDom(),
			body_aria_hidden: !!(document.body && document.body.getAttribute('aria-hidden') === 'true'),
			recent_fill: hasRecentInterstitialFillSignal(),
			vignetteSlotId: slotPath,
		};
		log('Vinheta aberta (' + source + ') signals:', signals);
		tl('vignette_open', signals);
		dispatchAdEvent('ad_vignette_open', 'interstitial', slotPath);
	}
	function hasGoogleVignetteMarker(url) {
		var candidate = url || window.location.href || '';
		if (candidate.indexOf('#google_vignette') !== -1) {
			return true;
		}
		return /(?:\?|&)google_vignette(?:=|&|$)/.test(candidate);
	}
	function hasInterstitialIframeInDom() {
		return !!document.querySelector('iframe[id*="google_ads_iframe"][id*="interstitial"]');
	}
	function hasRecentInterstitialFillSignal() {
		if (!interstitialFilled || !interstitialFilledAt) {
			return false;
		}
		if (Date.now() - interstitialFilledAt > INTERSTITIAL_SIGNAL_TTL_MS) {
			interstitialFilled = false;
			return false;
		}
		return true;
	}
	function checkVignetteSignals(source) {
		if (vignetteOpened) {
			return false;
		}
		var hasUrlSignal = hasGoogleVignetteMarker(window.location.href);
		var hasDomSignal = hasInterstitialIframeInDom();
		var hasBodySignal = !!(document.body && document.body.getAttribute('aria-hidden') === 'true');
		var hasFillSignal = hasRecentInterstitialFillSignal();
		if (hasUrlSignal && (hasFillSignal || hasDomSignal || hasBodySignal)) {
			markVignetteAsOpen(source + ': url marker + interstitial context');
			return true;
		}
		if (hasBodySignal && (hasFillSignal || hasDomSignal)) {
			markVignetteAsOpen(source + ': body aria-hidden + interstitial context');
			return true;
		}
		return false;
	}
	function installHistoryHooks() {
		if (!window.history || window.alvobot_vignette_history_hooked) {
			return;
		}
		window.alvobot_vignette_history_hooked = true;
		var originalPushState = window.history.pushState;
		var originalReplaceState = window.history.replaceState;
		if (typeof originalPushState === 'function') {
			window.history.pushState = function () {
				var args = Array.prototype.slice.call(arguments);
				var ret = originalPushState.apply(window.history, args);
				checkVignetteSignals('history.pushState');
				return ret;
			};
		}
		if (typeof originalReplaceState === 'function') {
			window.history.replaceState = function () {
				var args = Array.prototype.slice.call(arguments);
				var ret = originalReplaceState.apply(window.history, args);
				checkVignetteSignals('history.replaceState');
				return ret;
			};
		}
	}
	function checkVignetteHash() {
		checkVignetteSignals('url change');
	}
	function watchVignetteDom() {
		if (!('MutationObserver' in window)) {
			return;
		}
		if (checkVignetteSignals('dom watcher bootstrap')) {
			return;
		}
		if (hasInterstitialIframeInDom()) {
			markVignetteAsOpen('iframe found on load');
			return;
		}
		var obs = new MutationObserver(function (mutations) {
			if (vignetteOpened) {
				obs.disconnect();
				return;
			}
			if (checkVignetteSignals('mutation pre-check')) {
				obs.disconnect();
				return;
			}
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (m.type === 'attributes' && m.attributeName === 'aria-hidden') {
					var t = m.target;
					var tid = t.id || '';
					if (
						t === document.body &&
						t.getAttribute('aria-hidden') === 'true' &&
						hasRecentInterstitialFillSignal()
					) {
						markVignetteAsOpen('body aria-hidden=true');
						obs.disconnect();
						return;
					}
					if (
						t !== document.body &&
						t !== document.documentElement &&
						tid.indexOf('interstitial') !== -1 &&
						t.getAttribute('aria-hidden') === 'false'
					) {
						markVignetteAsOpen('aria-hidden change: ' + tid);
						obs.disconnect();
						return;
					}
				}
				if (m.type === 'childList') {
					for (var j = 0; j < m.addedNodes.length; j++) {
						var n = m.addedNodes[j];
						var nid = n.nodeType === 1 && n.id ? String(n.id) : '';
						if (
							n.nodeType === 1 &&
							n.tagName === 'IFRAME' &&
							nid.indexOf('google_ads_iframe') !== -1 &&
							nid.indexOf('interstitial') !== -1
						) {
							markVignetteAsOpen('iframe inserted: ' + nid);
							obs.disconnect();
							return;
						}
						if (
							n.nodeType === 1 &&
							nid.indexOf('interstitial') !== -1 &&
							n.getAttribute('aria-hidden') === 'false'
						) {
							markVignetteAsOpen('interstitial container visible: ' + nid);
							obs.disconnect();
							return;
						}
						if (n.nodeType === 1) {
							var nested = n.querySelector('iframe[id*="google_ads_iframe"][id*="interstitial"]');
							if (nested) {
								markVignetteAsOpen('nested iframe: ' + nested.id);
								obs.disconnect();
								return;
							}
						}
					}
				}
			}
		});
		if (!document.body) {
			log('document.body n\xE3o dispon\xEDvel; MutationObserver de vinheta adiado');
			return;
		}
		obs.observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['aria-hidden'],
		});
		setTimeout(function () {
			obs.disconnect();
		}, 3e4);
		log('MutationObserver de vinheta configurado (timeout: 30s)');
	}
	var impressionsFired = {};
	function setupGptListeners() {
		if (typeof window.googletag === 'undefined' || !window.googletag.cmd) {
			log('googletag n\xE3o dispon\xEDvel; rastreamento de impress\xE3o inativo');
			return;
		}
		window.googletag.cmd.push(function () {
			if (window.alvobot_gpt_listeners_added) {
				return;
			}
			window.alvobot_gpt_listeners_added = true;
			window.googletag.pubads().addEventListener('impressionViewable', function (event) {
				var slot = event.slot;
				var elementId = slot.getSlotElementId();
				var unitPath = slot.getAdUnitPath();
				var position = positionFromAdIdentifier(unitPath);
				if (position === 'interstitial') {
					return;
				}
				if (impressionsFired[elementId]) {
					log('impressionViewable: j\xE1 disparado para', elementId);
					return;
				}
				impressionsFired[elementId] = true;
				log('impressionViewable:', position, unitPath, '(elementId:', elementId, ')');
				dispatchAdEvent('ad_impression', position, unitPath);
			});
			window.googletag.pubads().addEventListener('slotRenderEnded', function (event) {
				var slotInfo = {
					elementId: event.slot.getSlotElementId(),
					adUnitPath: event.slot.getAdUnitPath(),
					isEmpty: event.isEmpty,
					size: event.size,
					advertiserId: event.advertiserId,
					campaignId: event.campaignId,
					creativeId: event.creativeId,
					lineItemId: event.lineItemId,
				};
				log('slotRenderEnded:', slotInfo);
				tl('gpt_slotRenderEnded', slotInfo);
				if (!event.isEmpty && event.slot.getAdUnitPath().indexOf('interstitial') !== -1) {
					interstitialFilled = true;
					interstitialFilledAt = Date.now();
					vignetteSlotId = event.slot.getAdUnitPath();
					log('Interstitial preenchido \u2014 slot:', vignetteSlotId, '\u2014 aguardando abertura visual');
					checkVignetteSignals('slotRenderEnded interstitial');
				}
			});
			log('GPT listeners registrados (impressionViewable + slotRenderEnded)');
		});
	}
	var VIGNETTE_CLICK_GRACE_MS = 1e3;
	var VIGNETTE_CLICK_CONFIRM_MS = 2e3;
	var clicksFired = {};
	var pendingVignetteClick = null;
	function isAdIframe(el) {
		if (!el || el.tagName !== 'IFRAME') {
			return false;
		}
		var id = el.id || '';
		if (id.indexOf('google_ads_iframe') !== -1) {
			return true;
		}
		if (id.indexOf('aswift_') === 0) {
			return true;
		}
		if (el.getAttribute('data-google-container-id') !== null) {
			return true;
		}
		if (el.getAttribute('data-is-safeframe') === 'true') {
			return true;
		}
		return false;
	}
	function deferVignetteClick(position, slotId, iframeId) {
		if (pendingVignetteClick) {
			log('Vignette click j\xE1 pendente \u2014 ignorando blur adicional');
			return;
		}
		log(
			'Vignette click detectado \u2014 aguardando confirma\xE7\xE3o de navega\xE7\xE3o (' +
				VIGNETTE_CLICK_CONFIRM_MS +
				'ms)',
		);
		var resolved = false;
		var timeoutId = null;
		var dismissObs = null;
		function confirm(source) {
			if (resolved) {
				return;
			}
			resolved = true;
			cleanup();
			if (clicksFired[iframeId]) {
				log('Vignette click confirmado mas j\xE1 disparado para:', iframeId);
				return;
			}
			clicksFired[iframeId] = true;
			log('Vignette click CONFIRMADO:', source);
			tl('vignette_click_confirmed', { source, position, slotId, iframeId });
			dispatchAdEvent('ad_vignette_click', position, slotId);
			setTimeout(function () {
				delete clicksFired[iframeId];
			}, 3e3);
		}
		function cancel(source) {
			if (resolved) {
				return;
			}
			resolved = true;
			cleanup();
			log('Vignette click DESCARTADO:', source);
			tl('vignette_click_cancelled', { source, position, iframeId });
			setTimeout(function () {
				if (typeof window.focus === 'function') {
					window.focus();
				}
			}, 500);
		}
		function cleanup() {
			pendingVignetteClick = null;
			if (timeoutId) {
				clearTimeout(timeoutId);
			}
			document.removeEventListener('visibilitychange', onVisibilityChange);
			window.removeEventListener('beforeunload', onBeforeUnload);
			window.removeEventListener('hashchange', onHashPopstate);
			window.removeEventListener('popstate', onHashPopstate);
			window.removeEventListener('pageshow', onPageshow);
			if (dismissObs) {
				dismissObs.disconnect();
			}
		}
		function onVisibilityChange() {
			if (document.hidden) {
				confirm('page hidden (nova aba ou navega\xE7\xE3o)');
			}
		}
		function onBeforeUnload() {
			confirm('beforeunload (navega\xE7\xE3o na mesma aba)');
		}
		function onHashPopstate() {
			if (!hasGoogleVignetteMarker(window.location.href)) {
				cancel('vinheta fechou (#google_vignette removido da URL)');
			}
		}
		if ('MutationObserver' in window && document.body) {
			dismissObs = new MutationObserver(function () {
				var val = document.body ? document.body.getAttribute('aria-hidden') : null;
				if (val !== 'true') {
					cancel('vinheta fechou (body aria-hidden=' + (val || 'removed') + ')');
				}
			});
			dismissObs.observe(document.body, { attributes: true, attributeFilter: ['aria-hidden'] });
		}
		function onPageshow(e) {
			if (e.persisted) {
				confirm('pageshow (back/forward navigation via bfcache)');
			}
		}
		document.addEventListener('visibilitychange', onVisibilityChange);
		window.addEventListener('beforeunload', onBeforeUnload);
		window.addEventListener('hashchange', onHashPopstate);
		window.addEventListener('popstate', onHashPopstate);
		window.addEventListener('pageshow', onPageshow);
		pendingVignetteClick = { confirm, cancel };
		timeoutId = setTimeout(function () {
			cancel('timeout \u2014 nenhuma navega\xE7\xE3o detectada em ' + VIGNETTE_CLICK_CONFIRM_MS + 'ms');
		}, VIGNETTE_CLICK_CONFIRM_MS);
	}
	function setupClickTracking() {
		window.addEventListener('blur', function () {
			setTimeout(function () {
				var activeEl = document.activeElement;
				if (!isAdIframe(activeEl)) {
					return;
				}
				var iframeId = activeEl.id || '';
				var position = positionFromAdIdentifier(iframeId);
				var slotId = slotIdFromIframeId(iframeId);
				var eventName = 'ad_click';
				var isVignetteContext = position === 'interstitial' || iframeId.indexOf('aswift_') === 0;
				if (isVignetteContext) {
					var vigPosition = position === 'interstitial' ? position : 'interstitial';
					var vigSlot = slotId || vignetteSlotId || '';
					tl('blur_vignette_context', {
						iframeId,
						position,
						vignetteOpened,
					});
					checkVignetteSignals('blur pre-check');
					if (!vignetteOpened) {
						markVignetteAsOpen('blur fallback: interstitial iframe got focus');
					}
					var elapsed = Date.now() - vignetteOpenTimestamp;
					if (elapsed < VIGNETTE_CLICK_GRACE_MS) {
						log(
							'Blur ignorado: auto-foco da abertura (' + elapsed + 'ms < grace period) \u2014',
							'restaurando foco em 1s',
						);
						tl('blur_grace_period_suppressed', {
							elapsed,
							threshold: VIGNETTE_CLICK_GRACE_MS,
							iframeId,
						});
						setTimeout(function () {
							if (typeof window.focus === 'function') {
								window.focus();
							}
						}, 1e3);
						return;
					}
					tl('blur_deferred_click', { elapsed, iframeId, vigPosition });
					deferVignetteClick(vigPosition, vigSlot, iframeId);
					return;
				}
				if (clicksFired[iframeId]) {
					log('Clique j\xE1 registrado para:', iframeId);
					return;
				}
				clicksFired[iframeId] = true;
				log('Clique detectado (blur/focus):', eventName, position, iframeId);
				dispatchAdEvent(eventName, position, slotId);
				setTimeout(function () {
					if (typeof window.focus === 'function') {
						window.focus();
					}
					delete clicksFired[iframeId];
				}, 3e3);
			}, 0);
		});
		log('Rastreamento de cliques configurado (ad_click + ad_vignette_click)');
	}
	var adsenseImpressionsFired = {};
	function setupAdsenseImpressionFallback() {
		if (!('IntersectionObserver' in window)) {
			log('IntersectionObserver indispon\xEDvel; fallback de impress\xE3o AdSense inativo');
			return;
		}
		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					var el = entry.target;
					var elId = el.id || '';
					if (adsenseImpressionsFired[elId]) {
						return;
					}
					adsenseImpressionsFired[elId] = true;
					observer.unobserve(el);
					var position = positionFromAdIdentifier(elId);
					log('AdSense impression (IntersectionObserver):', position, elId);
					dispatchAdEvent('ad_impression', position, elId);
				});
			},
			{ threshold: 0.5 },
			// ≥50% visible
		);
		function observeAdsenseFrames() {
			var iframes = document.querySelectorAll('iframe[id^="aswift_"]');
			for (var i = 0; i < iframes.length; i++) {
				var iframe = iframes[i];
				if (!adsenseImpressionsFired[iframe.id] && iframe.offsetParent !== null) {
					observer.observe(iframe);
				}
			}
		}
		observeAdsenseFrames();
		var scanCount = 0;
		var scanInterval = setInterval(function () {
			observeAdsenseFrames();
			scanCount++;
			if (scanCount >= 20) {
				clearInterval(scanInterval);
			}
		}, 1500);
		log('AdSense impression fallback configurado (IntersectionObserver)');
	}
	function init() {
		log('Inicializando AlvoBot Ad Tracker');
		installHistoryHooks();
		window.addEventListener('hashchange', checkVignetteHash);
		window.addEventListener('popstate', function () {
			checkVignetteHash();
			for (var key in _eventCooldowns) {
				if (key.indexOf(':global') === -1) {
					delete _eventCooldowns[key];
				}
			}
		});
		window.addEventListener('pageshow', function () {
			checkVignetteSignals('pageshow');
		});
		watchVignetteDom();
		checkVignetteSignals('init');
		setupGptListeners();
		setupAdsenseImpressionFallback();
		setupClickTracking();
		log('AlvoBot Ad Tracker inicializado');
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
