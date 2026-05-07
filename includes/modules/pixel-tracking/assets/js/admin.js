(function ($) {
	var config = window.alvobot_pixel_tracking || {};
	var extra = window.alvobot_pixel_tracking_extra || {};
	if (Array.isArray(extra.pixel_labels)) {
		extra.pixel_labels = {};
	}
	if (!Array.isArray(extra.google_trackers)) {
		extra.google_trackers = [];
	}
	var activeTab = extra.active_tab || 'pixels';
	var debugEnabled = !!(extra.debug_enabled || config.debug_enabled);
	var debugPrefix = '[AlvoBot Pixel][ADMIN]';
	var ARBITRAGE_CONVERSION_PRESETS = [
		{
			name: 'Page View',
			category: 'PAGE_VIEW',
			primary_for_goal: false,
			desc: 'Visitante carregou a pagina (secundaria)',
			trigger: 'page_load',
			event_type: 'PageView',
			event_custom_name: '',
			default_value: '0.01',
			aliases: ['Pageview', 'Page Impression', 'Impressao de Pagina'],
		},
		{
			name: 'Ad Impression',
			category: 'PAGE_VIEW',
			primary_for_goal: false,
			desc: 'Visitante viu um anuncio (secundaria)',
			trigger: 'ad_impression',
			event_type: 'CustomEvent',
			event_custom_name: 'AdImpression',
			default_value: '0.01',
		},
		{
			name: 'Ad Click',
			category: 'PURCHASE',
			primary_for_goal: true,
			desc: 'Visitante clicou num anuncio (principal)',
			trigger: 'ad_click',
			event_type: 'CustomEvent',
			event_custom_name: 'AdClick',
			default_value: '0.20',
		},
		{
			name: 'Vignette View',
			category: 'PAGE_VIEW',
			primary_for_goal: false,
			desc: 'Visitante viu a vinheta (secundaria)',
			trigger: 'ad_vignette_open',
			event_type: 'CustomEvent',
			event_custom_name: 'AdVignetteOpen',
			default_value: '0.03',
		},
		{
			name: 'Vignette Click',
			category: 'PURCHASE',
			primary_for_goal: true,
			desc: 'Visitante clicou na vinheta (principal)',
			trigger: 'ad_vignette_click',
			event_type: 'CustomEvent',
			event_custom_name: 'AdVignetteClick',
			default_value: '0.30',
		},
	];
	function getArbitrageConversionPresets(includeQuizLead) {
		var presets = ARBITRAGE_CONVERSION_PRESETS.map(function (preset) {
			return $.extend({}, preset);
		});
		if (includeQuizLead) {
			presets.push({
				name: 'Quiz Lead',
				category: 'LEAD',
				desc: 'Visitante preencheu lead no quiz',
				trigger: 'form_submit',
				event_type: 'Lead',
				event_custom_name: '',
				css_selector: '.qbv2__lead-form',
				default_value: '',
			});
		}
		return presets;
	}
	function getAdRuntimeEventName(trigger) {
		var map = {
			ad_impression: 'AdImpression',
			ad_click: 'AdClick',
			ad_vignette_open: 'AdVignetteOpen',
			ad_vignette_click: 'AdVignetteClick',
		};
		return map[trigger] || '';
	}
	function getArbitragePresetByTrigger(trigger) {
		for (var i = 0; i < ARBITRAGE_CONVERSION_PRESETS.length; i++) {
			if (ARBITRAGE_CONVERSION_PRESETS[i].trigger === trigger) {
				return ARBITRAGE_CONVERSION_PRESETS[i];
			}
		}
		return null;
	}
	function applyArbitrageTriggerDefaults(trigger, forceEventName) {
		var runtimeName = getAdRuntimeEventName(trigger);
		if (!runtimeName) {
			return false;
		}
		var preset = getArbitragePresetByTrigger(trigger);
		var currentEventType = $('#conv_event_type').val();
		var currentCustomName = $('#conv_event_custom_name').val();
		var shouldSetEventName =
			forceEventName ||
			!currentCustomName ||
			currentEventType === 'Lead' ||
			currentEventType === 'ViewContent' ||
			currentEventType === 'PageView';
		if (shouldSetEventName) {
			$('#conv_event_type').val('CustomEvent').trigger('change');
			$('#conv_event_custom_name').val(runtimeName);
		}
		if (preset) {
			if (!$('#conv_content_name').val()) {
				$('#conv_content_name').val(preset.name);
			}
			if (preset.default_value && !$('#conv_gads_conversion_value').val()) {
				$('#conv_gads_conversion_value').val(preset.default_value);
			}
		}
		return true;
	}
	function getConversionActionId(action) {
		if (!action || typeof action !== 'object') {
			return '';
		}
		var id = action.conversion_action_id || action.id || action.conversionActionId || '';
		if (id) {
			return String(id);
		}
		var resourceName = action.resource_name || action.resourceName || '';
		var match = String(resourceName).match(/conversionActions\/([^/]+)$/);
		return match ? match[1] : '';
	}
	function normalizeGoogleAdsId(value) {
		var raw = String(value || '').trim();
		if (/^AW-\d{6,15}$/.test(raw)) {
			return raw;
		}
		if (/^\d{6,15}$/.test(raw)) {
			return 'AW-' + raw;
		}
		return '';
	}
	function extractGoogleAdsIdFromText(text) {
		var match = String(text || '').match(/AW-\d{6,15}/);
		return match ? match[0] : '';
	}
	function getGoogleAdsCustomerId(tracker) {
		if (!tracker || typeof tracker !== 'object') {
			return '';
		}
		var candidates = [
			tracker.customer_id,
			tracker.customerId,
			tracker.account_customer_id,
			tracker.accountCustomerId,
			tracker.account_id,
			tracker.accountId,
			tracker.conversion_customer_id,
			tracker.conversionCustomerId,
			tracker.google_ads_customer_id,
		];
		for (var i = 0; i < candidates.length; i++) {
			var value = String(candidates[i] || '').replace(/\D/g, '');
			if (value) {
				return value;
			}
		}
		if (tracker.tracker_id && /^AW-\d{6,15}$/.test(tracker.tracker_id)) {
			return tracker.tracker_id.replace('AW-', '');
		}
		return '';
	}
	function getGoogleAdsTagId(tracker) {
		if (!tracker || typeof tracker !== 'object') {
			return '';
		}
		var candidates = [
			tracker.tag_id,
			tracker.tagId,
			tracker.google_tag_id,
			tracker.googleTagId,
			tracker.conversion_id,
			tracker.conversionId,
			tracker.google_ads_id,
			tracker.ads_id,
		];
		for (var i = 0; i < candidates.length; i++) {
			var normalized = normalizeGoogleAdsId(candidates[i]);
			if (normalized) {
				return normalized;
			}
		}
		var trackerIdTag = normalizeGoogleAdsId(tracker.tracker_id);
		if (trackerIdTag) {
			return trackerIdTag;
		}
		return '';
	}
	function _getGoogleAdsSendId(tracker) {
		return getGoogleAdsTagId(tracker);
	}
	function getConversionActionTagId(action) {
		if (!action || typeof action !== 'object') {
			return '';
		}
		var candidates = [
			action.tag_id,
			action.tagId,
			action.google_tag_id,
			action.googleTagId,
			action.conversion_id,
			action.conversionId,
			action.google_ads_id,
			action.ads_id,
		];
		for (var i = 0; i < candidates.length; i++) {
			var normalized = normalizeGoogleAdsId(candidates[i]);
			if (normalized) {
				return normalized;
			}
		}
		if ($.isArray(action.tag_snippets)) {
			for (var j = 0; j < action.tag_snippets.length; j++) {
				var fromSnippet = extractGoogleAdsIdFromText(JSON.stringify(action.tag_snippets[j] || {}));
				if (fromSnippet) {
					return fromSnippet;
				}
			}
		}
		return extractGoogleAdsIdFromText(JSON.stringify(action));
	}
	function syncGoogleTrackerRuntimeIds(trackerId, updates) {
		var trackers = [];
		var changed = false;
		var $hidden = $('#google_trackers_json');
		if ($hidden.length) {
			try {
				trackers = JSON.parse($hidden.val() || '[]');
			} catch (_e) {
				trackers = [];
			}
		} else if ($.isArray(extra.google_trackers)) {
			trackers = extra.google_trackers;
		}
		for (var i = 0; i < trackers.length; i++) {
			if (trackers[i].tracker_id !== trackerId) {
				continue;
			}
			if (updates.customer_id && trackers[i].customer_id !== updates.customer_id) {
				trackers[i].customer_id = updates.customer_id;
				changed = true;
			}
			if (updates.tag_id && trackers[i].tag_id !== updates.tag_id) {
				trackers[i].tag_id = updates.tag_id;
				changed = true;
			}
		}
		if (changed) {
			extra.google_trackers = trackers;
			if ($hidden.length) {
				$hidden.val(JSON.stringify(trackers));
			}
			persistGoogleTrackerRuntimeIds(trackerId, updates);
		}
	}
	function persistGoogleTrackerRuntimeIds(trackerId, updates) {
		updates = updates || {};
		if (!trackerId || !config.ajaxurl || !config.nonce) {
			return;
		}
		if (!updates.customer_id && !updates.tag_id) {
			return;
		}
		$.ajax({
			url: config.ajaxurl,
			method: 'POST',
			timeout: 1e4,
			data: {
				action: 'alvobot_pixel_tracking_update_google_tracker_runtime_ids',
				nonce: config.nonce,
				tracker_id: trackerId,
				customer_id: updates.customer_id || '',
				tag_id: updates.tag_id || '',
			},
		}).fail(function (xhr, status) {
			debugError('persist google tracker runtime ids failed', {
				tracker_id: trackerId,
				status,
				http: xhr == null ? void 0 : xhr.status,
			});
		});
	}
	function normalizeConversionName(name) {
		return String(name || '')
			.toLowerCase()
			.replace(/[\s_-]+/g, '');
	}
	function getPresetNameVariants(preset) {
		var names = [preset.name];
		if ($.isArray(preset.aliases)) {
			names = names.concat(preset.aliases);
		}
		return names.map(normalizeConversionName);
	}
	function hasPresetName(nameList, preset) {
		var variants = getPresetNameVariants(preset);
		for (var i = 0; i < variants.length; i++) {
			if (nameList.indexOf(variants[i]) !== -1) {
				return true;
			}
		}
		return false;
	}
	function getExistingLabelForPreset(labelMap, preset) {
		var variants = getPresetNameVariants(preset);
		for (var i = 0; i < variants.length; i++) {
			if (labelMap[variants[i]]) {
				return labelMap[variants[i]];
			}
		}
		return '';
	}
	function cloneForDebug(value) {
		try {
			return JSON.parse(JSON.stringify(value));
		} catch (_e) {
			return value;
		}
	}
	function debugLog(message, payload) {
		var _a;
		if (!debugEnabled || !((_a = window.console) == null ? void 0 : _a.log)) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.log(debugPrefix + ' ' + message);
			return;
		}
		window.console.log(debugPrefix + ' ' + message, cloneForDebug(payload));
	}
	function debugWarn(message, payload) {
		var _a;
		if (!debugEnabled || !((_a = window.console) == null ? void 0 : _a.warn)) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.warn(debugPrefix + ' ' + message);
			return;
		}
		window.console.warn(debugPrefix + ' ' + message, cloneForDebug(payload));
	}
	function debugError(message, payload) {
		var _a;
		if (!debugEnabled || !((_a = window.console) == null ? void 0 : _a.error)) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.error(debugPrefix + ' ' + message);
			return;
		}
		window.console.error(debugPrefix + ' ' + message, cloneForDebug(payload));
	}
	function parseJsonAttr($el, attrName) {
		var raw = $el.attr(attrName) || '{}';
		try {
			return JSON.parse(raw);
		} catch (e) {
			debugError('failed to parse ' + attrName, {
				error: (e == null ? void 0 : e.message) ? e.message : e,
			});
			return null;
		}
	}
	function triggerOnceWhenVisible($btn, flagName) {
		var queuedFlagName = flagName + 'Queued';
		if (!$btn.length || $btn.data(flagName) || $btn.data(queuedFlagName)) {
			return;
		}
		$btn.data(queuedFlagName, true);
		window.setTimeout(function () {
			$btn.removeData(queuedFlagName);
			if (!$btn.length || $btn.data(flagName)) {
				return;
			}
			if (!$btn.is(':visible') || $btn.prop('disabled')) {
				return;
			}
			$btn.data(flagName, true);
			$btn.trigger('click');
		}, 50);
	}
	function resetAutoFetchFlag($btn, flagName) {
		if (!$btn.length) {
			return;
		}
		$btn.removeData(flagName);
		$btn.removeData(flagName + 'Queued');
	}
	debugLog('admin bootstrap', {
		active_tab: activeTab,
		config,
		extra,
	});
	if (debugEnabled) {
		$(document).on('ajaxSend.alvobotPixelDebug', function (_event, _xhr, settings) {
			var url = (settings == null ? void 0 : settings.url) ? String(settings.url) : '';
			if (url.indexOf('admin-ajax.php') === -1 && url.indexOf('/pixel-tracking/') === -1) {
				return;
			}
			debugLog('ajaxSend', {
				url,
				method: settings.type || settings.method || 'GET',
				data: settings.data || null,
			});
		});
		$(document).on('ajaxComplete.alvobotPixelDebug', function (_event, xhr, settings) {
			var url = (settings == null ? void 0 : settings.url) ? String(settings.url) : '';
			if (url.indexOf('admin-ajax.php') === -1 && url.indexOf('/pixel-tracking/') === -1) {
				return;
			}
			debugLog('ajaxComplete', {
				url,
				method: settings.type || settings.method || 'GET',
				status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
				response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring(0, 3e3) : null,
			});
		});
	}
	$('.alvobot-module-form').on('submit', function () {
		var $btn = $(this).find('button[type="submit"]');
		if (!$btn.length) {
			return true;
		}
		if ($btn.prop('disabled')) {
			return false;
		}
		$btn.prop('disabled', true).css('opacity', '0.6');
		window.setTimeout(function () {
			$btn.prop('disabled', false).css('opacity', '');
		}, 30000);
	});
	if (activeTab === 'pixels') {
		debugLog('init tab: pixels');
		initPixelsTab();
	}
	if (activeTab === 'conversions') {
		debugLog('init tab: conversions');
		initConversionsTab();
	}
	if (activeTab === 'settings') {
		debugLog('init tab: settings');
		initConfiguracoesTab();
	}
	if (activeTab === 'events') {
		debugLog('init tab: events');
		initEventsTab();
	}
	if (activeTab === 'status') {
		debugLog('init tab: status');
		initStatusTab();
	}
	function initPixelsTab() {
		var PLATFORM_ICONS = {
			meta_pixel:
				'<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><path fill="#1877F2" d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>',
			ga4: '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><path fill="#E37400" d="M22.84 2.02v7.97a2.14 2.14 0 0 1-4.28 0V4.3H12.9a2.14 2.14 0 0 1 0-4.28h7.8c1.18 0 2.14.9 2.14 2Z"/><path fill="#F9AB00" d="M5.44 22c-1.9 0-3.44-1.5-3.44-3.36s1.54-3.36 3.44-3.36 3.44 1.5 3.44 3.36S7.34 22 5.44 22Z"/><path fill="#E37400" d="M12 22a2.14 2.14 0 0 1-2.14-2.14V9.73a2.14 2.14 0 0 1 4.28 0v10.13c0 1.18-.96 2.14-2.14 2.14Z"/></svg>',
			google_ads:
				'<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><circle cx="6" cy="18" r="4" fill="#FBBC04"/><path fill="#4285F4" d="M21.2 7.2 12.6 22H7.4L16 7.2h5.2Z"/><path fill="#34A853" d="M16 7.2 7.4 22H2.8l8.6-14.8H16Z"/></svg>',
		};
		var _PLATFORM_LABELS = {
			meta_pixel: 'Meta Pixel',
			ga4: 'Google Analytics',
			google_ads: 'Google Ads',
			external: 'Google (Tag existente)',
		};
		var PLATFORM_BADGES = {
			meta_pixel:
				'<span class="alvobot-badge alvobot-badge-info" style="gap:4px;">' +
				PLATFORM_ICONS.meta_pixel +
				'Meta Pixel</span>',
			ga4:
				'<span class="alvobot-badge alvobot-badge-warning" style="gap:4px;">' +
				PLATFORM_ICONS.ga4 +
				'GA4</span>',
			google_ads:
				'<span class="alvobot-badge alvobot-badge-success" style="gap:4px;">' +
				PLATFORM_ICONS.google_ads +
				'Google Ads</span>',
			external:
				'<span class="alvobot-badge alvobot-badge-neutral" style="gap:4px;">' +
				PLATFORM_ICONS.ga4 +
				'Tag existente</span>',
		};
		var pixelsData = [];
		try {
			pixelsData = JSON.parse($('#pixels_json').val() || '[]');
			debugLog('pixels tab: parsed pixels_json', { count: pixelsData.length, pixels: pixelsData });
		} catch (e) {
			pixelsData = [];
			debugError('pixels tab: failed to parse pixels_json', (e == null ? void 0 : e.message) ? e.message : e);
		}
		$('input[name="mode"]').on('change', function () {
			var mode = $(this).val();
			debugLog('pixels tab: mode changed', { mode });
			$('.alvobot-pixel-mode-section').hide();
			$('#alvobot-pixel-mode-' + mode).show();
		});
		$('#alvobot-fetch-pixels-btn').on('click', function () {
			var $btn = $(this);
			var $loading = $('#alvobot-pixel-list-loading');
			var $error = $('#alvobot-pixel-list-error');
			var $list = $('#alvobot-pixel-list');
			$btn.prop('disabled', true);
			$loading.show();
			$error.hide();
			$list.hide();
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_fetch_pixels',
					nonce: config.nonce,
				},
				success: function (response) {
					debugLog('pixels tab: fetch pixels response', response);
					$loading.hide();
					$btn.prop('disabled', false);
					if (response.success && response.data.pixels) {
						renderAlvobotPixelList(response.data.pixels);
						$list.show();
					} else {
						$error.text(response.data || 'Erro ao buscar pixels.').show();
					}
				},
				error: function () {
					debugError('pixels tab: fetch pixels ajax error');
					$loading.hide();
					$btn.prop('disabled', false);
					$error.text('Erro de conexao. Tente novamente.').show();
				},
			});
		});
		function renderAlvobotPixelList(pixels) {
			debugLog('pixels tab: render AlvoBot list', { count: pixels ? pixels.length : 0, pixels });
			var $list = $('#alvobot-pixel-list');
			if (!$list.length) {
				debugWarn('pixels tab: #alvobot-pixel-list not found');
				return;
			}
			$list.empty();
			if (!pixels.length) {
				$list.html(
					'<p class="alvobot-description">Nenhum pixel encontrado. Conecte um pixel no AlvoBot App.</p>',
				);
				return;
			}
			var html =
				'<table class="alvobot-table" style="width:100%"><thead><tr><th>Pixel</th><th>Conexao</th><th></th></tr></thead><tbody>';
			for (var i = 0; i < pixels.length; i++) {
				var p = pixels[i];
				var existing = null;
				for (var j = 0; j < pixelsData.length; j++) {
					if (pixelsData[j].pixel_id === p.pixel_id) {
						existing = pixelsData[j];
						break;
					}
				}
				html += '<tr>';
				html += '<td><code>' + escHtml(p.pixel_id) + '</code> ' + escHtml(p.pixel_name || '') + '</td>';
				html += '<td>' + escHtml(p.connection_name || '') + '</td>';
				html += '<td>';
				if (existing) {
					var tokenChanged = existing.token_expired && (p.has_api_token || p.token_ref);
					if (tokenChanged) {
						html +=
							`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-update-alvobot-pixel" data-pixel='` +
							escAttr(JSON.stringify(p)) +
							"'>Atualizar Token</button>";
					} else {
						html += '<span class="alvobot-badge alvobot-badge-success">Adicionado</span>';
					}
				} else {
					html +=
						`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-select-alvobot-pixel" data-pixel='` +
						escAttr(JSON.stringify(p)) +
						"'>Selecionar</button>";
				}
				html += '</td></tr>';
			}
			html += '</tbody></table>';
			$list.html(html);
			if (window.lucide) {
				window.lucide.createIcons();
			}
		}
		$(document).on('click', '.alvobot-update-alvobot-pixel', function () {
			var pixel = parseJsonAttr($(this), 'data-pixel');
			if (!pixel) {
				return;
			}
			debugLog('pixels tab: update token clicked', pixel);
			for (var i = 0; i < pixelsData.length; i++) {
				if (pixelsData[i].pixel_id === pixel.pixel_id) {
					pixelsData[i].token_ref = pixel.token_ref || '';
					pixelsData[i].has_api_token =
						!!pixel.token_ref || !!pixel.has_api_token || !!pixelsData[i].has_api_token;
					pixelsData[i].token_expired = false;
					pixelsData[i].label = pixel.pixel_name || pixelsData[i].label;
					pixelsData[i].connection_id = pixel.connection_id || pixelsData[i].connection_id;
					break;
				}
			}
			updatePixelsHiddenField();
			renderUnifiedTable();
			$('#alvobot-fetch-pixels-btn').trigger('click');
		});
		$(document).on('click', '.alvobot-select-alvobot-pixel', function () {
			var pixel = parseJsonAttr($(this), 'data-pixel');
			if (!pixel) {
				return;
			}
			debugLog('pixels tab: select pixel clicked', pixel);
			pixelsData.push({
				pixel_id: pixel.pixel_id,
				token_ref: pixel.token_ref || '',
				has_api_token: !!pixel.token_ref || !!pixel.has_api_token,
				source: 'alvobot',
				connection_id: pixel.connection_id || '',
				label: pixel.pixel_name || '',
			});
			updatePixelsHiddenField();
			renderUnifiedTable();
			$('#alvobot-fetch-pixels-btn').trigger('click');
		});
		$(document).on('click', '.alvobot-refresh-pixel-btn', function () {
			var $btn = $(this);
			var pixelId = $btn.data('pixel-id');
			var idx = parseInt($btn.data('index'), 10);
			$btn.prop('disabled', true).text('Renovando...');
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_refresh_token',
					nonce: config.nonce,
					pixel_id: pixelId,
				},
				success: function (response) {
					if (response.success && response.data) {
						if (!Number.isNaN(idx) && idx >= 0 && idx < pixelsData.length) {
							pixelsData[idx].has_api_token = true;
							pixelsData[idx].token_expired = false;
							if (response.data.label) {
								pixelsData[idx].label = response.data.label;
							}
						}
						updatePixelsHiddenField();
						renderUnifiedTable();
					} else {
						alert(response.data || 'Erro ao renovar token.');
						$btn.prop('disabled', false).text('Renovar');
					}
				},
				error: function () {
					alert('Erro de conexao ao renovar token.');
					$btn.prop('disabled', false).text('Renovar');
				},
			});
		});
		function updatePixelsHiddenField() {
			$('#pixels_json').val(
				JSON.stringify(
					pixelsData.map(function (pixel) {
						var clean = $.extend({}, pixel);
						delete clean.api_token;
						delete clean.access_token;
						return clean;
					}),
				),
			);
		}
		var googleTrackersData = [];
		try {
			googleTrackersData = JSON.parse($('#google_trackers_json').val() || '[]');
		} catch (_e) {
			googleTrackersData = [];
		}
		function resetAlvobotFetchLists() {
			$('#alvobot-pixel-list-loading, #alvobot-pixel-list-error, #alvobot-pixel-list').hide();
			$('#alvobot-google-list-loading, #alvobot-google-list-error, #alvobot-google-list').hide();
			$('#alvobot-gads-conversion-actions, #alvobot-gads-ca-loading, #alvobot-gads-ca-error').hide();
			$('#alvobot-gads-ca-list').empty();
			resetAutoFetchFlag($('#alvobot-fetch-pixels-btn'), 'alvobotAutoFetched_' + currentPlatform);
			resetAutoFetchFlag($('#alvobot-fetch-google-btn'), 'alvobotAutoFetched_' + currentPlatform);
		}
		function syncGoogleTrackerRuntimeIds2(trackerId, updates) {
			var changed = false;
			for (var i = 0; i < googleTrackersData.length; i++) {
				if (googleTrackersData[i].tracker_id !== trackerId) {
					continue;
				}
				if (updates.customer_id && googleTrackersData[i].customer_id !== updates.customer_id) {
					googleTrackersData[i].customer_id = updates.customer_id;
					changed = true;
				}
				if (updates.tag_id && googleTrackersData[i].tag_id !== updates.tag_id) {
					googleTrackersData[i].tag_id = updates.tag_id;
					changed = true;
				}
			}
			if (changed) {
				updateGoogleTrackersHiddenField();
				persistGoogleTrackerRuntimeIds(trackerId, updates);
			}
		}
		var currentPlatform = '';
		$('#add_pixel_platform').on('change', function () {
			currentPlatform = $(this).val();
			var $source = $('#add-pixel-source');
			var $alvobotFetch = $('#add-pixel-alvobot-fetch');
			var $googleAlvobotFetch = $('#add-google-alvobot-fetch');
			var $manualFields = $('#add-pixel-manual-fields');
			var $addBtn = $('#alvobot-add-pixel-btn');
			$source.hide();
			$alvobotFetch.hide();
			$googleAlvobotFetch.hide();
			$manualFields.hide();
			resetAlvobotFetchLists();
			$('#add-pixel-manual-fields .alvobot-form-field').hide();
			$addBtn.hide();
			if (!currentPlatform) {
				return;
			}
			if (currentPlatform === 'google_sitekit') {
				var alreadyHasExternal = googleTrackersData.some(function (gt) {
					return gt.type === 'external';
				});
				if (alreadyHasExternal) {
					alert('Google Tag existente ja esta configurado.');
					$('#add_pixel_platform').val('');
					return;
				}
				googleTrackersData.push({
					tracker_id: 'sitekit_gtag',
					type: 'external',
					label: 'Google Tag existente (Site Kit / GTM)',
					conversion_label: '',
				});
				updateGoogleTrackersHiddenField();
				renderUnifiedTable();
				$('#add_pixel_platform').val('');
				return;
			}
			if (currentPlatform) {
				$source.show();
				$('input[name="add_pixel_source"][value="alvobot"]').prop('checked', true).trigger('change');
			}
		});
		$(document).on('change', 'input[name="add_pixel_source"]', function () {
			var source = $(this).val();
			var $alvobotFetch = $('#add-pixel-alvobot-fetch');
			var $googleAlvobotFetch = $('#add-google-alvobot-fetch');
			var $manualFields = $('#add-pixel-manual-fields');
			var $addBtn = $('#alvobot-add-pixel-btn');
			$alvobotFetch.hide();
			$googleAlvobotFetch.hide();
			$manualFields.hide();
			$('#add-pixel-manual-fields .alvobot-form-field').hide();
			$addBtn.hide();
			if (!currentPlatform) {
				resetAlvobotFetchLists();
				return;
			}
			if (source === 'alvobot') {
				resetAlvobotFetchLists();
				if (currentPlatform === 'meta_pixel') {
					$alvobotFetch.show();
					triggerOnceWhenVisible($('#alvobot-fetch-pixels-btn'), 'alvobotAutoFetched_' + currentPlatform);
				} else {
					$googleAlvobotFetch.show();
					triggerOnceWhenVisible($('#alvobot-fetch-google-btn'), 'alvobotAutoFetched_' + currentPlatform);
				}
			} else {
				resetAlvobotFetchLists();
				$manualFields.show();
				if (currentPlatform === 'meta_pixel') {
					$('#add-field-meta-id, #add-field-meta-token, #add-field-label').show();
				} else if (currentPlatform === 'ga4') {
					$('#add-field-ga4-id, #add-field-label').show();
				} else if (currentPlatform === 'google_ads') {
					$('#add-field-gads-id, #add-field-gads-label, #add-field-label').show();
				}
				$addBtn.show();
			}
		});
		$('#alvobot-add-pixel-btn').on('click', function () {
			var label = $('#manual_pixel_label').val().trim();
			var $addBtn = $(this);
			var finishAdd = function () {
				$(
					'#manual_pixel_id, #manual_api_token, #manual_ga4_id, #manual_gads_id, #manual_gads_conv_label, #manual_pixel_label',
				).val('');
				$('#add_pixel_platform').val('').trigger('change');
				renderUnifiedTable();
			};
			if (currentPlatform === 'meta_pixel') {
				var pixelId = $('#manual_pixel_id').val().trim();
				var token = $('#manual_api_token').val().trim();
				if (!/^\d{15,16}$/.test(pixelId)) {
					alert('Pixel ID deve ter 15-16 digitos numericos.');
					return;
				}
				for (var i = 0; i < pixelsData.length; i++) {
					if (pixelsData[i].pixel_id === pixelId) {
						alert('Este Pixel ID ja esta configurado.');
						return;
					}
				}
				var addMetaPixel = function (tokenRef) {
					pixelsData.push({
						pixel_id: pixelId,
						token_ref: tokenRef || '',
						has_api_token: !!tokenRef,
						source: 'manual',
						connection_id: '',
						label,
						token_expired: false,
					});
					updatePixelsHiddenField();
					finishAdd();
				};
				if (token) {
					$addBtn.prop('disabled', true).text('Adicionando...');
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_store_pixel_token_ref',
							nonce: config.nonce,
							pixel_id: pixelId,
							api_token: token,
						},
					})
						.done(function (response) {
							if (response.success && response.data && response.data.token_ref) {
								addMetaPixel(response.data.token_ref);
								return;
							}
							alert((response && response.data) || 'Erro ao preparar token CAPI.');
						})
						.fail(function () {
							alert('Erro de conexao ao preparar token CAPI.');
						})
						.always(function () {
							$addBtn.prop('disabled', false).text('Adicionar');
						});
					return;
				}
				addMetaPixel('');
				return;
			} else if (currentPlatform === 'ga4') {
				var gaId = $('#manual_ga4_id').val().trim();
				if (!/^G-[A-Z0-9]{7,12}$/.test(gaId)) {
					alert('Formato invalido. Use G-XXXXXXXXXX (letras maiusculas e numeros).');
					return;
				}
				for (var j = 0; j < googleTrackersData.length; j++) {
					if (googleTrackersData[j].tracker_id === gaId) {
						alert('Este Tracker ID ja esta configurado.');
						return;
					}
				}
				googleTrackersData.push({ tracker_id: gaId, type: 'ga4', label, conversion_label: '' });
				updateGoogleTrackersHiddenField();
			} else if (currentPlatform === 'google_ads') {
				var adsId = $('#manual_gads_id').val().trim();
				var adsLabel = $('#manual_gads_conv_label').val().trim();
				adsId = normalizeGoogleAdsId(adsId);
				if (!adsId) {
					alert('Formato invalido. Use AW-XXXXXXXXX (numeros).');
					return;
				}
				for (var k = 0; k < googleTrackersData.length; k++) {
					if (googleTrackersData[k].tracker_id === adsId) {
						alert('Este Tracker ID ja esta configurado.');
						return;
					}
				}
				googleTrackersData.push({
					tracker_id: adsId,
					type: 'google_ads',
					label,
					conversion_label: adsLabel,
					tag_id: adsId,
					customer_id: '',
				});
				updateGoogleTrackersHiddenField();
			}
			finishAdd();
		});
		$(document).on('click', '.alvobot-remove-meta-pixel', function () {
			var idx = parseInt($(this).attr('data-index'), 10);
			if (!Number.isNaN(idx) && idx >= 0 && idx < pixelsData.length) {
				pixelsData.splice(idx, 1);
				updatePixelsHiddenField();
				renderUnifiedTable();
			}
		});
		$(document).on('click', '.alvobot-remove-google-tracker', function () {
			var idx = parseInt($(this).attr('data-index'), 10);
			if (!Number.isNaN(idx) && idx >= 0 && idx < googleTrackersData.length) {
				googleTrackersData.splice(idx, 1);
				updateGoogleTrackersHiddenField();
				renderUnifiedTable();
			}
		});
		$('#alvobot-fetch-google-btn').on('click', function () {
			var $btn = $(this);
			var $loading = $('#alvobot-google-list-loading');
			var $error = $('#alvobot-google-list-error');
			var $list = $('#alvobot-google-list');
			$btn.prop('disabled', true);
			$loading.show();
			$error.hide();
			$list.hide();
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_fetch_google_trackers',
					nonce: config.nonce,
				},
				success: function (response) {
					$loading.hide();
					$btn.prop('disabled', false);
					if (response.success && response.data.trackers) {
						var trackers = response.data.trackers;
						var filtered = trackers.filter(function (t2) {
							if (currentPlatform === 'ga4') return t2.type === 'ga4';
							if (currentPlatform === 'google_ads') return t2.type === 'google_ads';
							return true;
						});
						if (!filtered.length) {
							$error
								.text(
									'Nenhum tracker ' +
										(currentPlatform === 'ga4' ? 'GA4' : 'Google Ads') +
										' encontrado no AlvoBot.',
								)
								.show();
							return;
						}
						var html = '';
						for (var i = 0; i < filtered.length; i++) {
							var t = filtered[i];
							var already = googleTrackersData.some(function (gt) {
								return gt.tracker_id === t.tracker_id;
							});
							var tagId = getGoogleAdsTagId(t);
							var badge =
								t.type === 'ga4'
									? '<span class="alvobot-badge alvobot-badge-warning">GA4</span>'
									: '<span class="alvobot-badge alvobot-badge-success">Google Ads</span>';
							html += '<div class="alvobot-tracker-row-item">';
							html += badge + ' <code>' + escHtml(t.tracker_id) + '</code>';
							if (tagId && tagId !== t.tracker_id)
								html +=
									' <span class="alvobot-text-muted-xs">Tag: <code>' +
									escHtml(tagId) +
									'</code></span>';
							if (t.label) html += ' \u2014 ' + escHtml(t.label);
							if (already) {
								html +=
									' <span class="alvobot-badge alvobot-badge-neutral" style="margin-left:auto;">Ja adicionado</span>';
							} else {
								html +=
									` <button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary alvobot-select-google-tracker" style="margin-left:auto;" data-tracker='` +
									escAttr(JSON.stringify(t)) +
									"'>Selecionar</button>";
							}
							html += '</div>';
						}
						$list.html(html).show();
					} else {
						$error.text(response.data || 'Erro ao buscar trackers.').show();
					}
				},
				error: function () {
					$loading.hide();
					$btn.prop('disabled', false);
					$error.text('Erro de conexao.').show();
				},
			});
		});
		$(document).on('click', '.alvobot-select-google-tracker', function () {
			var t = parseJsonAttr($(this), 'data-tracker');
			if (!t) {
				return;
			}
			var already = googleTrackersData.some(function (gt) {
				return gt.tracker_id === t.tracker_id;
			});
			if (already) {
				return;
			}
			googleTrackersData.push({
				tracker_id: t.tracker_id,
				type: t.type,
				label: t.label || '',
				conversion_label: '',
				connection_id: t.connection_id || '',
				customer_id: getGoogleAdsCustomerId(t),
				tag_id: getGoogleAdsTagId(t),
			});
			updateGoogleTrackersHiddenField();
			renderUnifiedTable();
			$(this).replaceWith(
				'<span class="alvobot-badge alvobot-badge-neutral" style="margin-left:auto;">Ja adicionado</span>',
			);
			if (t.type === 'google_ads' && t.connection_id) {
				var connectedAds = googleTrackersData.filter(function (gt) {
					return gt.type === 'google_ads' && gt.connection_id;
				});
				if (connectedAds.length === 1) {
					showConversionSuggestionBanner(t);
				} else {
					$('#alvobot-conv-suggestion-banner').remove();
				}
			}
		});
		function showConversionSuggestionBanner(tracker) {
			$('#alvobot-conv-suggestion-banner').remove();
			var connectedAds = googleTrackersData.filter(function (gt) {
				return gt.type === 'google_ads' && gt.connection_id;
			});
			if (connectedAds.length !== 1) {
				return;
			}
			var allSuggestions = getArbitrageConversionPresets(false);
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: { action: 'alvobot_pixel_tracking_get_conversions', nonce: config.nonce },
				success: function (response) {
					var _a;
					var existingRules =
						response.success && ((_a = response.data) == null ? void 0 : _a.conversions)
							? response.data.conversions
							: [];
					var existingRuleNames = existingRules.map(function (r) {
						return normalizeConversionName(r.name);
					});
					var missing = [];
					var configured = [];
					for (var i = 0; i < allSuggestions.length; i++) {
						if (hasPresetName(existingRuleNames, allSuggestions[i])) {
							configured.push(allSuggestions[i]);
						} else {
							missing.push(allSuggestions[i]);
						}
					}
					if (!missing.length) {
						return;
					}
					var html = '<div id="alvobot-conv-suggestion-banner" class="alvobot-suggestion-banner">';
					html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">';
					html +=
						'<i data-lucide="sparkles" class="alvobot-icon alvobot-icon-warning" style="width:18px;height:18px;"></i>';
					html +=
						'<strong>' +
						missing.length +
						' conversao(oes) faltando para ' +
						escHtml(tracker.label || tracker.tracker_id) +
						'</strong>';
					html +=
						`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary" id="alvobot-create-all-suggested" data-tracker='` +
						escAttr(JSON.stringify(tracker)) +
						`' style="margin-left:auto;">`;
					html += '<i data-lucide="zap" class="alvobot-icon"></i> Criar faltantes</button>';
					html += `<button type="button" style="background:none;border:none;cursor:pointer;padding:4px;margin-left:8px;" onclick="this.closest('#alvobot-conv-suggestion-banner').remove();">`;
					html +=
						'<i data-lucide="x" class="alvobot-icon alvobot-icon-muted" style="width:16px;height:16px;"></i></button>';
					html += '</div>';
					html += '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
					for (var j = 0; j < allSuggestions.length; j++) {
						var sg = allSuggestions[j];
						var isMissing = missing.some(function (m) {
							return m.name === sg.name;
						});
						if (isMissing) {
							html += '<span class="alvobot-suggestion-tag-missing">' + escHtml(sg.name) + '</span>';
						} else {
							html +=
								'<span class="alvobot-suggestion-tag-done">&#10003; ' + escHtml(sg.name) + '</span>';
						}
					}
					html += '</div></div>';
					$('#alvobot-configured-all-pixels').closest('.alvobot-card-content').append(html);
					if (window.lucide) {
						window.lucide.createIcons();
					}
				},
			});
		}
		$(document).on('click', '#alvobot-create-all-suggested', function () {
			var $btn = $(this);
			var tracker = parseJsonAttr($btn, 'data-tracker');
			if (!tracker) {
				return;
			}
			var trackerId = tracker.tracker_id;
			var customerId = getGoogleAdsCustomerId(tracker);
			var $banner = $('#alvobot-conv-suggestion-banner');
			var $formSaveBtn = $('.alvobot-module-form button[type="submit"]');
			var originalSaveTitle = $formSaveBtn.attr('title') || '';
			$formSaveBtn.prop('disabled', true).attr('title', 'Aguarde: criando conversoes em lote...');
			function releaseFormSave() {
				$formSaveBtn.prop('disabled', false).attr('title', originalSaveTitle);
			}
			if (!customerId) {
				alert('Nao foi possivel identificar o Customer ID da conta Google Ads.');
				releaseFormSave();
				return;
			}
			var suggestions = getArbitrageConversionPresets(false);
			$btn.prop('disabled', true).html(
				'<span class="spinner is-active" style="float:none;margin:0;"></span> Verificando existentes...',
			);
			$.when(
				$.ajax({
					url: config.ajaxurl,
					method: 'POST',
					data: {
						action: 'alvobot_pixel_tracking_fetch_conversion_actions',
						nonce: config.nonce,
						connection_id: tracker.connection_id,
						customer_id: customerId,
					},
				}),
				$.ajax({
					url: config.ajaxurl,
					method: 'POST',
					data: {
						action: 'alvobot_pixel_tracking_get_conversions',
						nonce: config.nonce,
					},
				}),
			)
				.done(function (gadsResp, rulesResp) {
					var _a, _b;
					var gadsData = gadsResp[0] || {};
					var rulesData = rulesResp[0] || {};
					var existingActions =
						gadsData.success && ((_a = gadsData.data) == null ? void 0 : _a.conversion_actions)
							? gadsData.data.conversion_actions
							: [];
					var actionTagId = '';
					var existingGadsNames = existingActions.map(function (a) {
						return normalizeConversionName(a.name);
					});
					var existingLabels = {};
					for (var e = 0; e < existingActions.length; e++) {
						if (!actionTagId) {
							actionTagId = getConversionActionTagId(existingActions[e]);
						}
						if (existingActions[e].conversion_label) {
							existingLabels[normalizeConversionName(existingActions[e].name)] =
								existingActions[e].conversion_label;
						}
					}
					if (actionTagId) {
						tracker.tag_id = actionTagId;
						tracker.customer_id = customerId;
						syncGoogleTrackerRuntimeIds2(trackerId, { customer_id: customerId, tag_id: actionTagId });
					}
					var existingRules =
						rulesData.success && ((_b = rulesData.data) == null ? void 0 : _b.conversions)
							? rulesData.data.conversions
							: [];
					var existingRuleNames = existingRules.map(function (r) {
						return normalizeConversionName(r.name);
					});
					var toCreate = [];
					var toSaveRule = [];
					var alreadyDone = [];
					for (var s = 0; s < suggestions.length; s++) {
						var inGads = hasPresetName(existingGadsNames, suggestions[s]);
						var inRules = hasPresetName(existingRuleNames, suggestions[s]);
						if (inRules) {
							alreadyDone.push(suggestions[s]);
						} else if (inGads) {
							suggestions[s].existingLabel = getExistingLabelForPreset(existingLabels, suggestions[s]);
							toSaveRule.push(suggestions[s]);
						} else {
							toCreate.push(suggestions[s]);
						}
					}
					if (!toCreate.length && !toSaveRule.length) {
						$banner.html(
							'<p class="alvobot-description" class="alvobot-text-success" style="padding:12px;">Todas as conversoes ja estao configuradas!</p>',
						);
						releaseFormSave();
						return;
					}
					var total = toCreate.length + toSaveRule.length;
					var created = 0;
					var errors = [];
					function saveExistingRules(idx) {
						if (idx >= toSaveRule.length) {
							createMissing(0);
							return;
						}
						var sg = toSaveRule[idx];
						var label = sg.existingLabel || '';
						$btn.html(
							'<span class="spinner is-active" style="float:none;margin:0;"></span> Configurando ' +
								(created + 1) +
								'/' +
								total +
								' (' +
								sg.name +
								')...',
						);
						var labelsMap = {};
						if (label) {
							labelsMap[trackerId] = label;
						}
						$.ajax({
							url: config.ajaxurl,
							method: 'POST',
							data: {
								action: 'alvobot_pixel_tracking_save_conversion',
								nonce: config.nonce,
								conversion_id: 0,
								name: sg.name,
								event_type: sg.event_type,
								event_custom_name: sg.event_custom_name || '',
								trigger_type: sg.trigger,
								display_on: 'all',
								content_name: sg.name,
								pixel_ids: trackerId,
								gads_conversion_label: label,
								gads_labels_map: JSON.stringify(labelsMap),
								gads_conversion_value: sg.default_value || '',
							},
							complete: function () {
								created++;
								saveExistingRules(idx + 1);
							},
						});
					}
					function createMissing(idx) {
						if (idx >= toCreate.length) {
							var msg = created + ' conversoes configuradas!';
							if (errors.length) {
								msg += ' (' + errors.length + ' erros: ' + errors.join(', ') + ')';
							}
							$banner.html(
								'<p class="alvobot-description" class="alvobot-text-success" style="padding:12px;"><i data-lucide="check-circle" class="alvobot-icon" style="width:16px;height:16px;"></i> ' +
									escHtml(msg) +
									' Va para a aba <strong>Conversoes</strong> para ver.</p>',
							);
							if (window.lucide) {
								window.lucide.createIcons();
							}
							releaseFormSave();
							return;
						}
						var sg = toCreate[idx];
						$btn.html(
							'<span class="spinner is-active" style="float:none;margin:0;"></span> Criando ' +
								(created + 1) +
								'/' +
								total +
								' (' +
								sg.name +
								')...',
						);
						$.ajax({
							url: config.ajaxurl,
							method: 'POST',
							data: {
								action: 'alvobot_pixel_tracking_create_conversion_action',
								nonce: config.nonce,
								connection_id: tracker.connection_id,
								customer_id: customerId,
								name: sg.name,
								category: sg.category,
								primary_for_goal:
									sg.primary_for_goal === void 0 ? '1' : sg.primary_for_goal ? '1' : '0',
								default_value: sg.default_value || 0,
								currency: 'BRL',
							},
							success: function (response) {
								if (response.success && response.data) {
									var label = response.data.conversion_label || '';
									var createdTagId = getConversionActionTagId(response.data);
									if (createdTagId) {
										tracker.tag_id = createdTagId;
										tracker.customer_id = customerId;
										syncGoogleTrackerRuntimeIds2(trackerId, {
											customer_id: customerId,
											tag_id: createdTagId,
										});
									}
									var labelsMap = {};
									if (label) {
										labelsMap[trackerId] = label;
									}
									$.ajax({
										url: config.ajaxurl,
										method: 'POST',
										data: {
											action: 'alvobot_pixel_tracking_save_conversion',
											nonce: config.nonce,
											conversion_id: 0,
											name: sg.name,
											event_type: sg.event_type,
											event_custom_name: sg.event_custom_name || '',
											trigger_type: sg.trigger,
											display_on: 'all',
											content_name: sg.name,
											pixel_ids: trackerId,
											css_selector: sg.css_selector || '',
											gads_conversion_label: label,
											gads_labels_map: JSON.stringify(labelsMap),
											gads_conversion_value: sg.default_value || '',
										},
										complete: function () {
											created++;
											createMissing(idx + 1);
										},
									});
									return;
								} else {
									errors.push(sg.name);
								}
								created++;
								createMissing(idx + 1);
							},
							error: function () {
								errors.push(sg.name);
								created++;
								createMissing(idx + 1);
							},
						});
					}
					saveExistingRules(0);
				})
				.fail(function () {
					$btn.prop('disabled', false).html('<i data-lucide="zap" class="alvobot-icon"></i> Criar faltantes');
					alert('Erro ao verificar conversoes existentes.');
					releaseFormSave();
				});
		});
		function checkExistingSuggestions() {
			var connectedAds = googleTrackersData.filter(function (gt) {
				return gt.type === 'google_ads' && gt.connection_id;
			});
			if (connectedAds.length === 1) {
				showConversionSuggestionBanner(connectedAds[0]);
			} else {
				$('#alvobot-conv-suggestion-banner').remove();
			}
		}
		function renderUnifiedTable() {
			var $container = $('#alvobot-configured-all-pixels');
			var total = pixelsData.length + googleTrackersData.length;
			var connectedAdsCount = googleTrackersData.filter(function (gt) {
				return gt.type === 'google_ads' && gt.connection_id;
			}).length;
			if (connectedAdsCount > 1) {
				$('#alvobot-conv-suggestion-banner').remove();
			}
			if (!total) {
				$container.html(
					'<div class="alvobot-empty-state alvobot-empty-state-compact"><i data-lucide="radio" class="alvobot-icon" style="width:32px;height:32px;opacity:0.3;"></i><p>Nenhum pixel ou tracker configurado.</p><p class="alvobot-description">Use o seletor acima para adicionar.</p></div>',
				);
				return;
			}
			var html =
				'<table class="alvobot-table" style="width:100%;"><thead><tr><th>Plataforma</th><th>ID</th><th>Nome</th><th>Status</th><th></th></tr></thead><tbody>';
			for (var i = 0; i < pixelsData.length; i++) {
				var p = pixelsData[i];
				var statusBadge = '';
				if (p.token_expired) {
					statusBadge = '<span class="alvobot-badge alvobot-badge-error">Token Expirado</span>';
				} else if (p.api_token || p.has_api_token) {
					statusBadge = '<span class="alvobot-badge alvobot-badge-success">CAPI Ativo</span>';
				} else {
					statusBadge = '<span class="alvobot-badge alvobot-badge-warning">Sem Token</span>';
				}
				html += '<tr>';
				html += '<td>' + PLATFORM_BADGES.meta_pixel + '</td>';
				html += '<td><code>' + escHtml(p.pixel_id) + '</code></td>';
				html += '<td>' + escHtml(p.label || '-') + '</td>';
				html += '<td>' + statusBadge + '</td>';
				html += '<td>';
				if (p.token_expired && p.source === 'alvobot') {
					html +=
						'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-refresh-pixel-btn" data-pixel-id="' +
						escAttr(p.pixel_id) +
						'" data-index="' +
						i +
						'" style="margin-right:4px;"><i data-lucide="refresh-cw" class="alvobot-icon"></i> Renovar</button>';
				}
				html +=
					'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-meta-pixel" data-index="' +
					i +
					'"><i data-lucide="trash-2" class="alvobot-icon"></i></button></td>';
				html += '</tr>';
			}
			for (var j = 0; j < googleTrackersData.length; j++) {
				var t = googleTrackersData[j];
				var platBadge = PLATFORM_BADGES[t.type] || PLATFORM_BADGES.external;
				var extraInfo = '';
				var idCell =
					t.type === 'external' ? '<em>Auto-detectado</em>' : '<code>' + escHtml(t.tracker_id) + '</code>';
				if (t.type === 'google_ads') {
					var sendId = getGoogleAdsTagId(t);
					var customerId = getGoogleAdsCustomerId(t);
					if (sendId && sendId !== t.tracker_id) {
						idCell =
							'<code>' +
							escHtml(sendId) +
							'</code><div class="alvobot-text-muted-xs" style="margin-top:2px;">Conta: ' +
							escHtml(customerId || t.tracker_id.replace('AW-', '')) +
							'</div>';
					} else if (!sendId && t.connection_id) {
						idCell =
							'<code>' +
							escHtml(t.tracker_id) +
							'</code><div class="alvobot-text-warning-dark" style="font-size:11px;margin-top:2px;">Tag ID pendente</div>';
					}
					if (t.connection_id) {
						idCell +=
							'<div style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
						idCell +=
							'<input type="text" class="alvobot-input alvobot-gads-tag-id-input" data-index="' +
							j +
							'" value="' +
							escAttr(sendId || '') +
							'" placeholder="AW-17854527745" style="width:170px;min-height:30px;padding:4px 8px;font-size:12px;">';
						idCell += '<span class="alvobot-text-muted-xs">Google Tag ID</span>';
						idCell += '</div>';
					}
				}
				if (t.type === 'external') {
					extraInfo = '<span class="alvobot-badge alvobot-badge-neutral">Detectado na pagina</span>';
				} else {
					extraInfo = '<span class="alvobot-badge alvobot-badge-success">Ativo</span>';
				}
				html += '<tr>';
				html += '<td>' + platBadge + '</td>';
				html += '<td>' + idCell + '</td>';
				html += '<td>' + escHtml(t.label || '-') + '</td>';
				html += '<td>' + extraInfo + '</td>';
				html +=
					'<td><button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-google-tracker" data-index="' +
					j +
					'"><i data-lucide="trash-2" class="alvobot-icon"></i></button></td>';
				html += '</tr>';
			}
			html += '</tbody></table>';
			var bulkEligible = googleTrackersData.filter(function (t2) {
				return t2.type === 'google_ads' && t2.connection_id;
			});
			if (bulkEligible.length) {
				html +=
					'<div class="alvobot-bulk-create-actions" style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
				html +=
					'<button type="button" id="alvobot-create-conversions-all-trackers" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary">';
				html +=
					'<i data-lucide="zap" class="alvobot-icon"></i> ' +
					(bulkEligible.length > 1
						? 'Verificar/criar conversoes padrao em todas as contas Google Ads (' +
							bulkEligible.length +
							')'
						: 'Verificar/criar conversoes padrao');
				html += '</button>';
				html +=
					'<span class="alvobot-description alvobot-text-muted-xs">' +
					(bulkEligible.length > 1
						? 'Acao unica para todas as contas selecionadas. Page View, Ad Impression, Ad Click, Vignette View e Vignette Click.'
						: 'Page View, Ad Impression, Ad Click, Vignette View e Vignette Click.') +
					'</span>';
				html += '</div>';
			}
			$container.html(html);
			if (window.lucide) {
				window.lucide.createIcons();
			}
		}
		function updateGoogleTrackersHiddenField() {
			$('#google_trackers_json').val(JSON.stringify(googleTrackersData));
		}
		$(document).on('input', '.alvobot-gads-tag-id-input', function () {
			var index = parseInt($(this).attr('data-index'), 10);
			if (Number.isNaN(index) || !googleTrackersData[index]) {
				return;
			}
			var tagId = normalizeGoogleAdsId($(this).val());
			googleTrackersData[index].tag_id = tagId;
			updateGoogleTrackersHiddenField();
		});
		function runCreateConversionsForTracker(tracker, opts) {
			opts = opts || {};
			var onProgress = typeof opts.onProgress === 'function' ? opts.onProgress : function () {};
			var trackerId = tracker.tracker_id;
			var customerId = getGoogleAdsCustomerId(tracker);
			var suggestions = getArbitrageConversionPresets(false);
			return new Promise(function (resolve) {
				var summary = { tracker, created: 0, skipped: 0, errors: [] };
				if (!customerId) {
					summary.errors.push((tracker.label || trackerId) + ': sem customer_id');
					resolve(summary);
					return;
				}
				onProgress('verificando conversoes existentes...');
				$.when(
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 3e4,
						data: {
							action: 'alvobot_pixel_tracking_fetch_conversion_actions',
							nonce: config.nonce,
							connection_id: tracker.connection_id,
							customer_id: customerId,
						},
					}),
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 15e3,
						data: {
							action: 'alvobot_pixel_tracking_get_conversions',
							nonce: config.nonce,
						},
					}),
				)
					.done(function (gadsResp, rulesResp) {
						var _a, _b;
						var gadsData = gadsResp[0] || {};
						var rulesData = rulesResp[0] || {};
						var existingActions =
							gadsData.success && ((_a = gadsData.data) == null ? void 0 : _a.conversion_actions)
								? gadsData.data.conversion_actions
								: [];
						var actionByName = {};
						for (var e = 0; e < existingActions.length; e++) {
							var nname = normalizeConversionName(existingActions[e].name);
							actionByName[nname] = existingActions[e];
						}
						var existingActiveNames = [];
						var existingArchivedNames = [];
						var existingLabels = {};
						for (var na in actionByName) {
							if (!Object.prototype.hasOwnProperty.call(actionByName, na)) {
								continue;
							}
							var act = actionByName[na];
							if (act.status === 'REMOVED' || act.status === 'HIDDEN') {
								existingArchivedNames.push(na);
							} else {
								existingActiveNames.push(na);
							}
							if (act.conversion_label) {
								existingLabels[na] = act.conversion_label;
							}
						}
						var existingRules =
							rulesData.success && ((_b = rulesData.data) == null ? void 0 : _b.conversions)
								? rulesData.data.conversions
								: [];
						var existingRuleNames = existingRules.map(function (r) {
							return normalizeConversionName(r.name);
						});
						function findRuleForPreset(preset2) {
							var variants = getPresetNameVariants(preset2);
							for (var k = 0; k < existingRules.length; k++) {
								if (variants.indexOf(normalizeConversionName(existingRules[k].name)) !== -1) {
									return existingRules[k];
								}
							}
							return null;
						}
						function findActionForPreset(preset2) {
							var variants = getPresetNameVariants(preset2);
							for (var k = 0; k < variants.length; k++) {
								if (actionByName[variants[k]]) {
									return actionByName[variants[k]];
								}
							}
							return null;
						}
						var toCreate = [];
						var toSaveRule = [];
						var toMerge = [];
						var blockedByArchived = [];
						for (var s = 0; s < suggestions.length; s++) {
							var preset = suggestions[s];
							if (hasPresetName(existingRuleNames, preset)) {
								var existingRule = findRuleForPreset(preset);
								if (existingRule) {
									var currentIds = String(existingRule.pixel_ids || '')
										.split(',')
										.map(function (s2) {
											return s2.trim();
										})
										.filter(Boolean);
									if (currentIds.indexOf(trackerId) === -1) {
										toMerge.push({ rule: existingRule, preset, currentIds });
										continue;
									}
								}
								continue;
							}
							if (hasPresetName(existingArchivedNames, preset)) {
								blockedByArchived.push(preset);
								continue;
							}
							if (hasPresetName(existingActiveNames, preset)) {
								preset.existingLabel = getExistingLabelForPreset(existingLabels, preset);
								toSaveRule.push(preset);
							} else {
								toCreate.push(preset);
							}
						}
						var total = toCreate.length + toSaveRule.length + toMerge.length + blockedByArchived.length;
						var saveIdx = 0;
						var mergeIdx = 0;
						var renameIdx = 0;
						function renameArchivedPhase() {
							if (renameIdx >= blockedByArchived.length) {
								return mergeRulesPhase();
							}
							var sg = blockedByArchived[renameIdx];
							var act2 = findActionForPreset(sg);
							if (!act2) {
								summary.errors.push(
									(tracker.label || trackerId) +
										' / ' +
										sg.name +
										' (arquivada \u2014 referencia interna nao encontrada)',
								);
								renameIdx++;
								renameArchivedPhase();
								return;
							}
							onProgress(
								'liberando nome ' +
									(summary.created + summary.errors.length + 1) +
									'/' +
									total +
									' (' +
									sg.name +
									')',
							);
							var dateStr = /* @__PURE__ */ new Date().toISOString().slice(0, 10);
							var suffix =
								' [arquivada ' +
								dateStr +
								' #' +
								(act2.id || Math.random().toString(36).slice(2, 7)) +
								']';
							var origName = act2.name || sg.name;
							var newName = (origName + suffix).slice(0, 100);
							$.ajax({
								url: config.ajaxurl,
								method: 'POST',
								timeout: 3e4,
								data: {
									action: 'alvobot_pixel_tracking_update_conversion_action',
									nonce: config.nonce,
									connection_id: tracker.connection_id,
									customer_id: customerId,
									conversion_action_id: act2.id,
									name: newName,
								},
							})
								.done(function (resp) {
									if (resp == null ? void 0 : resp.success) {
										toCreate.push(sg);
										debugLog('rename archived: success', {
											id: act2.id,
											oldName: origName,
											newName,
											preset: sg.name,
										});
									} else {
										summary.errors.push(
											(tracker.label || trackerId) +
												' / ' +
												sg.name +
												' (arquivada \u2014 falha ao liberar nome: ' +
												((resp == null ? void 0 : resp.data) || 'desconhecido') +
												')',
										);
									}
								})
								.fail(function (xhr, status) {
									summary.errors.push(
										(tracker.label || trackerId) +
											' / ' +
											sg.name +
											' (arquivada \u2014 ' +
											(status === 'timeout' ? 'timeout' : 'rede') +
											' ao liberar nome)',
									);
								})
								.always(function () {
									renameIdx++;
									renameArchivedPhase();
								});
						}
						if (!toCreate.length && !toSaveRule.length && !toMerge.length && !blockedByArchived.length) {
							summary.skipped = suggestions.length;
							resolve(summary);
							return;
						}
						function mergeRulesPhase() {
							if (mergeIdx >= toMerge.length) {
								return saveRulesPhase();
							}
							var item = toMerge[mergeIdx];
							var sg = item.preset;
							onProgress(
								'mesclando ' +
									(summary.created + summary.errors.length + 1) +
									'/' +
									total +
									' (' +
									sg.name +
									')',
							);
							var label = getExistingLabelForPreset(existingLabels, sg);
							var newIds = item.currentIds.slice();
							newIds.push(trackerId);
							var labelsMap = {};
							if (item.rule.gads_labels_map) {
								try {
									labelsMap = JSON.parse(item.rule.gads_labels_map) || {};
								} catch (_e) {
									labelsMap = {};
								}
							}
							if (label) {
								labelsMap[trackerId] = label;
							}
							$.ajax({
								url: config.ajaxurl,
								method: 'POST',
								timeout: 15e3,
								data: {
									action: 'alvobot_pixel_tracking_save_conversion',
									nonce: config.nonce,
									conversion_id: item.rule.id,
									name: item.rule.name,
									event_type: item.rule.event_type || sg.event_type,
									event_custom_name: item.rule.event_custom_name || sg.event_custom_name || '',
									trigger_type: item.rule.trigger_type || sg.trigger,
									display_on: item.rule.display_on || 'all',
									content_name: item.rule.content_name || sg.name,
									pixel_ids: newIds.join(','),
									gads_conversion_label: item.rule.gads_conversion_label || label,
									gads_labels_map: JSON.stringify(labelsMap),
									gads_conversion_value: item.rule.gads_conversion_value || sg.default_value || '',
								},
							})
								.done(function (r) {
									if (r == null ? void 0 : r.success) {
										summary.created++;
									} else {
										summary.errors.push(
											(tracker.label || trackerId) + ' / ' + sg.name + ' (merge)',
										);
									}
								})
								.fail(function () {
									summary.errors.push(
										(tracker.label || trackerId) + ' / ' + sg.name + ' (merge rede)',
									);
								})
								.always(function () {
									mergeIdx++;
									mergeRulesPhase();
								});
						}
						function saveRulesPhase() {
							if (saveIdx >= toSaveRule.length) {
								return createPhase(0);
							}
							var sg = toSaveRule[saveIdx];
							onProgress(
								'configurando ' +
									(summary.created + summary.errors.length + 1) +
									'/' +
									total +
									' (' +
									sg.name +
									')',
							);
							var labelsMap = {};
							if (sg.existingLabel) {
								labelsMap[trackerId] = sg.existingLabel;
							}
							$.ajax({
								url: config.ajaxurl,
								method: 'POST',
								timeout: 15e3,
								data: {
									action: 'alvobot_pixel_tracking_save_conversion',
									nonce: config.nonce,
									conversion_id: 0,
									name: sg.name,
									event_type: sg.event_type,
									event_custom_name: sg.event_custom_name || '',
									trigger_type: sg.trigger,
									display_on: 'all',
									content_name: sg.name,
									pixel_ids: trackerId,
									gads_conversion_label: sg.existingLabel || '',
									gads_labels_map: JSON.stringify(labelsMap),
									gads_conversion_value: sg.default_value || '',
								},
							})
								.done(function (r) {
									if (r == null ? void 0 : r.success) {
										summary.created++;
									} else {
										summary.errors.push((tracker.label || trackerId) + ' / ' + sg.name);
									}
								})
								.fail(function () {
									summary.errors.push((tracker.label || trackerId) + ' / ' + sg.name + ' (rede)');
								})
								.always(function () {
									saveIdx++;
									saveRulesPhase();
								});
						}
						function createPhase(idx) {
							if (idx >= toCreate.length) {
								resolve(summary);
								return;
							}
							var sg = toCreate[idx];
							onProgress(
								'criando ' +
									(summary.created + summary.errors.length + 1) +
									'/' +
									total +
									' (' +
									sg.name +
									')',
							);
							$.ajax({
								url: config.ajaxurl,
								method: 'POST',
								timeout: 3e4,
								data: {
									action: 'alvobot_pixel_tracking_create_conversion_action',
									nonce: config.nonce,
									connection_id: tracker.connection_id,
									customer_id: customerId,
									name: sg.name,
									category: sg.category,
									primary_for_goal:
										sg.primary_for_goal === void 0 ? '1' : sg.primary_for_goal ? '1' : '0',
									default_value: sg.default_value || 0,
									currency: 'BRL',
								},
							})
								.done(function (response) {
									if (!(response == null ? void 0 : response.success) || !response.data) {
										summary.errors.push((tracker.label || trackerId) + ' / ' + sg.name);
										createPhase(idx + 1);
										return;
									}
									var label = response.data.conversion_label || '';
									var createdTagId = getConversionActionTagId(response.data);
									if (createdTagId) {
										tracker.tag_id = createdTagId;
										tracker.customer_id = customerId;
										syncGoogleTrackerRuntimeIds2(trackerId, {
											customer_id: customerId,
											tag_id: createdTagId,
										});
									}
									var labelsMap = {};
									if (label) {
										labelsMap[trackerId] = label;
									}
									$.ajax({
										url: config.ajaxurl,
										method: 'POST',
										timeout: 15e3,
										data: {
											action: 'alvobot_pixel_tracking_save_conversion',
											nonce: config.nonce,
											conversion_id: 0,
											name: sg.name,
											event_type: sg.event_type,
											event_custom_name: sg.event_custom_name || '',
											trigger_type: sg.trigger,
											display_on: 'all',
											content_name: sg.name,
											pixel_ids: trackerId,
											css_selector: sg.css_selector || '',
											gads_conversion_label: label,
											gads_labels_map: JSON.stringify(labelsMap),
											gads_conversion_value: sg.default_value || '',
										},
									})
										.done(function (r) {
											if (r == null ? void 0 : r.success) {
												summary.created++;
											} else {
												summary.errors.push(
													(tracker.label || trackerId) + ' / ' + sg.name + ' (save local)',
												);
											}
										})
										.fail(function () {
											summary.errors.push(
												(tracker.label || trackerId) + ' / ' + sg.name + ' (save local rede)',
											);
										})
										.always(function () {
											createPhase(idx + 1);
										});
								})
								.fail(function (xhr, status) {
									summary.errors.push(
										(tracker.label || trackerId) +
											' / ' +
											sg.name +
											' (' +
											(status === 'timeout' ? 'timeout' : 'rede') +
											')',
									);
									createPhase(idx + 1);
								});
						}
						renameArchivedPhase();
					})
					.fail(function () {
						summary.errors.push((tracker.label || trackerId) + ': falha ao listar conversoes existentes');
						resolve(summary);
					});
			});
		}
		var bulkAllInFlight = false;
		$(document).on('click', '#alvobot-create-conversions-all-trackers', function () {
			if (bulkAllInFlight) {
				debugLog('bulk-all: ignored re-click while in-flight');
				return;
			}
			var $btn = $(this);
			var trackers = googleTrackersData.filter(function (t) {
				return t.type === 'google_ads' && t.connection_id;
			});
			if (!trackers.length) {
				alert('Nenhuma conta Google Ads conectada via AlvoBot foi encontrada.');
				return;
			}
			alvobotModal
				.confirm({
					title: 'Criar conversoes em todas as contas',
					message:
						'Vamos verificar e criar as conversoes padrao em ' +
						trackers.length +
						' conta(s) Google Ads. Isso pode levar alguns minutos. Deseja continuar?',
					confirmText: 'Continuar',
					cancelText: 'Cancelar',
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					var $formSaveBtn = $('.alvobot-module-form button[type="submit"]');
					var origTitle = $formSaveBtn.attr('title') || '';
					$formSaveBtn.prop('disabled', true).attr('title', 'Aguarde: criando conversoes em massa...');
					var originalBtnHtml = $btn.data('original-html');
					if (!originalBtnHtml) {
						originalBtnHtml = $btn.html();
						$btn.data('original-html', originalBtnHtml);
					}
					$btn.attr('aria-busy', 'true')
						.css('opacity', '0.7')
						.html(
							'<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Iniciando...',
						);
					bulkAllInFlight = true;
					var totalCreated = 0;
					var totalSkipped = 0;
					var allErrors = [];
					var concurrency = 3;
					var inFlight = 0;
					var nextToStart = 0;
					function release() {
						bulkAllInFlight = false;
						$formSaveBtn.prop('disabled', false).attr('title', origTitle);
						$btn.removeAttr('aria-busy').css('opacity', '').html(originalBtnHtml);
						if (window.lucide) {
							window.lucide.createIcons();
						}
					}
					function finishAll() {
						release();
						var msg =
							'Concluido. ' +
							totalCreated +
							' conversao(oes) configurada(s) em ' +
							trackers.length +
							' conta(s).';
						if (totalSkipped) {
							msg += ' ' + totalSkipped + ' conta(s) ja estavam totalmente configurada(s).';
						}
						if (allErrors.length) {
							var preview = allErrors.slice(0, 6).join('\n - ');
							msg += '\n\n' + allErrors.length + ' erro(s):\n - ' + preview;
							if (allErrors.length > 6) {
								msg += '\n... e mais ' + (allErrors.length - 6) + '.';
							}
						}
						alvobotModal.alert({ title: 'Concluido', message: msg });
						if (typeof window.alvobotPixelReloadConversions === 'function') {
							try {
								window.alvobotPixelReloadConversions();
							} catch (_e) {}
						}
					}
					function startNext() {
						while (inFlight < concurrency && nextToStart < trackers.length) {
							var i = nextToStart++;
							inFlight++;
							$btn.html(
								'<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Processando ' +
									(i + 1) +
									'/' +
									trackers.length +
									' contas...',
							);
							(function (t) {
								runCreateConversionsForTracker(t, {
									onProgress: function (text) {
										$btn.html(
											'<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Processando ' +
												nextToStart +
												'/' +
												trackers.length +
												' contas: ' +
												escHtml(text),
										);
									},
								}).then(
									function (result) {
										var _a, _b;
										totalCreated += result.created || 0;
										if (result.skipped && !((_a = result.errors) == null ? void 0 : _a.length)) {
											totalSkipped++;
										}
										if ((_b = result.errors) == null ? void 0 : _b.length) {
											allErrors = allErrors.concat(result.errors);
										}
										inFlight--;
										if (inFlight === 0 && nextToStart >= trackers.length) {
											finishAll();
										} else {
											startNext();
										}
									},
									function () {
										inFlight--;
										if (inFlight === 0 && nextToStart >= trackers.length) {
											finishAll();
										} else {
											startNext();
										}
									},
								);
							})(trackers[i]);
						}
					}
					startNext();
				});
		});
		renderUnifiedTable();
		checkExistingSuggestions();
	}
	function initConversionsTab() {
		var CONV_ICONS = {
			meta: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><path fill="#1877F2" d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>',
			ga4: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><path fill="#E37400" d="M22.84 2.02v7.97a2.14 2.14 0 0 1-4.28 0V4.3H12.9a2.14 2.14 0 0 1 0-4.28h7.8c1.18 0 2.14.9 2.14 2Z"/><path fill="#F9AB00" d="M5.44 22c-1.9 0-3.44-1.5-3.44-3.36s1.54-3.36 3.44-3.36 3.44 1.5 3.44 3.36S7.34 22 5.44 22Z"/><path fill="#E37400" d="M12 22a2.14 2.14 0 0 1-2.14-2.14V9.73a2.14 2.14 0 0 1 4.28 0v10.13c0 1.18-.96 2.14-2.14 2.14Z"/></svg>',
			gads: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><circle cx="6" cy="18" r="4" fill="#FBBC04"/><path fill="#4285F4" d="M21.2 7.2 12.6 22H7.4L16 7.2h5.2Z"/><path fill="#34A853" d="M16 7.2 7.4 22H2.8l8.6-14.8H16Z"/></svg>',
		};
		buildPixelSelector();
		loadConversions();
		function buildPixelSelector() {
			var $container = $('#conv-pixel-selector');
			if (!$container.length) {
				return;
			}
			var html = '';
			var pixelLabels = extra.pixel_labels || {};
			var metaIds = Object.keys(pixelLabels);
			if (metaIds.length) {
				for (var i = 0; i < metaIds.length; i++) {
					var pid = metaIds[i];
					var pname = pixelLabels[pid] || '';
					html +=
						'<label class="alvobot-checkbox-label alvobot-item-row"><input type="checkbox" class="conv-pixel-checkbox" value="' +
						escAttr(pid) +
						'"> ' +
						CONV_ICONS.meta +
						' <span>' +
						escHtml(pid) +
						(pname ? ' \u2014 ' + escHtml(pname) : '') +
						'</span></label>';
				}
			}
			var trackers = extra.google_trackers || [];
			if (trackers.length) {
				for (var j = 0; j < trackers.length; j++) {
					var t = trackers[j];
					var icon, typeName, displayId;
					if (t.type === 'external') {
						icon = CONV_ICONS.ga4;
						typeName = 'Tag existente';
						displayId = 'Site Kit / GTM';
					} else if (t.type === 'ga4') {
						icon = CONV_ICONS.ga4;
						typeName = 'GA4';
						displayId = t.tracker_id;
					} else {
						icon = CONV_ICONS.gads;
						typeName = 'Google Ads';
						displayId = t.tracker_id;
					}
					html +=
						'<label class="alvobot-checkbox-label alvobot-item-row"><input type="checkbox" class="conv-pixel-checkbox" value="' +
						escAttr(t.tracker_id) +
						'"> ' +
						icon +
						' <span>' +
						escHtml(displayId) +
						' \u2014 ' +
						escHtml(typeName) +
						(t.label && t.type !== 'external' ? ' (' + escHtml(t.label) + ')' : '') +
						'</span></label>';
				}
			}
			if (!html) {
				html =
					'<p class="alvobot-description">Nenhum pixel ou tracker configurado. Adicione na aba Pixels.</p>';
			}
			$container.html(html);
			$container.on('change', '.conv-pixel-checkbox', function () {
				var selected = [];
				$container.find('.conv-pixel-checkbox:checked').each(function () {
					selected.push($(this).val());
				});
				$('#conv_pixel_ids').val(selected.join(','));
				updateGadsLabelsUI(selected);
				var selectedCount = selected.length;
				var announceMsg =
					selectedCount === 0
						? 'Nenhum pixel selecionado \u2014 todos serao usados'
						: selectedCount === 1
							? '1 pixel selecionado'
							: selectedCount + ' pixels selecionados';
				$('#conv-pixel-selection-announce').text(announceMsg);
			});
		}
		function updateGadsLabelsUI(selected) {
			var trackers = extra.google_trackers || [];
			var hasAdsSelected = selected.some(function (id) {
				return /^AW-/.test(id);
			});
			var hasAdsConfigured = trackers.some(function (t) {
				return t.type === 'google_ads';
			});
			var showGadsFields = hasAdsSelected || (!selected.length && hasAdsConfigured);
			if (showGadsFields) {
				$('#conv-gads-fields').addClass('visible');
			} else {
				$('#conv-gads-fields').removeClass('visible');
				return;
			}
			var adsTrackers = [];
			for (var i = 0; i < trackers.length; i++) {
				if (trackers[i].type !== 'google_ads') {
					continue;
				}
				if (!selected.length || selected.indexOf(trackers[i].tracker_id) !== -1) {
					adsTrackers.push(trackers[i]);
				}
			}
			var currentMap = {};
			try {
				currentMap = JSON.parse($('#conv_gads_labels_map').val() || '{}');
			} catch (_e) {}
			var $container = $('#conv-gads-labels-container');
			var html = '';
			for (var j = 0; j < adsTrackers.length; j++) {
				var at = adsTrackers[j];
				var savedLabel = currentMap[at.tracker_id] || '';
				var tagId = getGoogleAdsTagId(at);
				var customerId = getGoogleAdsCustomerId(at);
				html +=
					'<div class="alvobot-gads-label-row alvobot-item-row" data-tracker-id="' +
					escAttr(at.tracker_id) +
					'">';
				html += CONV_ICONS.gads + ' ';
				html += '<span style="min-width:160px;font-weight:500;">' + escHtml(tagId || at.tracker_id) + '</span>';
				if (at.label) {
					html += '<span class="alvobot-text-muted-sm">' + escHtml(at.label) + '</span>';
				}
				if (customerId && tagId && tagId !== at.tracker_id) {
					html += '<span class="alvobot-text-muted-xs">Conta: ' + escHtml(customerId) + '</span>';
				}
				if (!tagId && at.connection_id) {
					html +=
						'<span class="alvobot-text-warning-dark alvobot-text-muted-xs">Tag ID pendente: use Buscar</span>';
				}
				html +=
					'<input type="text" class="alvobot-input alvobot-gads-label-input" data-tracker-id="' +
					escAttr(at.tracker_id) +
					'" value="' +
					escAttr(savedLabel) +
					'" placeholder="Conversion Label" style="flex:1;max-width:200px;">';
				if (at.connection_id) {
					html +=
						`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-gads-fetch-label-btn" data-tracker='` +
						escAttr(JSON.stringify(at)) +
						`' title="Buscar labels disponiveis">`;
					html += '<i data-lucide="search" class="alvobot-icon"></i> Buscar';
					html += '</button>';
				}
				html +=
					'<a href="https://ads.google.com/aw/conversions/new?customerId=' +
					encodeURIComponent(customerId || at.tracker_id.replace('AW-', '')) +
					'" target="_blank" rel="noopener noreferrer" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" title="Criar conversao nesta conta" style="white-space:nowrap;">';
				html += '<i data-lucide="external-link" class="alvobot-icon"></i> Criar';
				html += '</a>';
				html += '</div>';
			}
			if (!html) {
				html = '<p class="alvobot-description">Selecione pelo menos um tracker Google Ads acima.</p>';
			}
			$container.html(html);
			if (window.lucide) {
				window.lucide.createIcons();
			}
			$container.off('input', '.alvobot-gads-label-input').on('input', '.alvobot-gads-label-input', function () {
				syncGadsLabelsMap();
			});
		}
		function syncGadsLabelsMap() {
			var map = {};
			$('#conv-gads-labels-container .alvobot-gads-label-input').each(function () {
				var tid = $(this).attr('data-tracker-id');
				var val = $(this).val().trim();
				if (tid && val) {
					map[tid] = val;
				}
			});
			$('#conv_gads_labels_map').val(JSON.stringify(map));
			var firstLabel = '';
			for (var k in map) {
				if (Object.prototype.hasOwnProperty.call(map, k)) {
					firstLabel = map[k];
					break;
				}
			}
			$('#conv_gads_conversion_label').val(firstLabel);
		}
		function loadConversions() {
			var $table = $('#alvobot-conversions-tbody');
			debugLog('loadConversions: table found?', {
				found: $table.length,
				ajaxurl: config.ajaxurl,
				hasNonce: !!config.nonce,
			});
			if (!$table.length) {
				debugError('loadConversions: #alvobot-conversions-tbody not found in DOM');
				return;
			}
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_get_conversions',
					nonce: config.nonce,
				},
				success: function (response) {
					if (response.success) {
						renderConversionsTable(response.data.conversions);
					} else {
						$table.html(
							'<tr><td colspan="7" class="alvobot-empty-state"><p>Erro ao carregar: ' +
								escHtml(response.data || 'Resposta invalida') +
								'</p></td></tr>',
						);
					}
				},
				error: function (xhr, status, err) {
					debugError('loadConversions ajax error', { status, error: err });
					$table.html(
						'<tr><td colspan="7" class="alvobot-empty-state"><p>Erro de conexao ao carregar conversoes.</p></td></tr>',
					);
				},
			});
		}
		function renderConversionsTable(conversions) {
			var $tbody = $('#alvobot-conversions-tbody');
			$tbody.empty();
			if (!(conversions == null ? void 0 : conversions.length)) {
				$tbody.html(
					'<tr><td colspan="7" class="alvobot-empty-state"><p>Nenhuma conversao configurada.</p></td></tr>',
				);
				updateBulkBar();
				return;
			}
			for (var i = 0; i < conversions.length; i++) {
				var c = conversions[i];
				var isActive = c.status === 'publish';
				var eventLabel = getDisplayedConversionEventName(c);
				var triggerLabel = getTriggerLabel(c.trigger_type);
				var platformLabel = getPlatformLabel(c.platforms);
				var syncSummary = getConversionSyncSummary(c);
				var html = '<tr data-id="' + c.id + '">';
				html += '<td><input type="checkbox" class="alvobot-conv-select" data-id="' + c.id + '"></td>';
				html += '<td><div style="font-weight:500;">' + escHtml(c.name) + '</div>' + syncSummary + '</td>';
				html += '<td>' + escHtml(eventLabel) + '</td>';
				html += '<td>' + escHtml(triggerLabel) + '</td>';
				html += '<td>' + platformLabel + '</td>';
				html +=
					'<td><label class="alvobot-toggle"><input type="checkbox" class="alvobot-toggle-conversion" data-id="' +
					c.id +
					'" ' +
					(isActive ? 'checked' : '') +
					'><span class="alvobot-toggle-slider"></span></label></td>';
				var convAttr = escAttr(JSON.stringify(c));
				html += '<td><div class="alvobot-row-actions">';
				html += '<div class="alvobot-row-actions-inline">';
				html +=
					`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-edit-conversion" data-conversion='` +
					convAttr +
					`' title="Editar nome, gatilho e selecao de pixels"><i data-lucide="pencil" class="alvobot-icon"></i> Editar</button>`;
				html +=
					'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-unlink-conversion" data-id="' +
					c.id +
					'" title="Apaga apenas a regra local. Conversao no Google Ads fica intacta \u2014 voce pode reativar via Criar em todas."><i data-lucide="unlink" class="alvobot-icon"></i> Desvincular</button>';
				html +=
					'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-delete-conversion-full" data-id="' +
					c.id +
					'" title="Apaga a regra local E arquiva a conversao no Google Ads. Irreversivel via API."><i data-lucide="trash-2" class="alvobot-icon"></i> Excluir</button>';
				html += '</div>';
				html += '<div class="alvobot-row-actions-kebab">';
				html +=
					'<button type="button" class="alvobot-kebab-trigger" aria-haspopup="menu" aria-expanded="false" aria-label="Acoes da conversao">';
				html += '<i data-lucide="more-vertical" class="alvobot-icon"></i>';
				html += '</button>';
				html += '<div class="alvobot-kebab-menu" role="menu" hidden>';
				html +=
					`<button type="button" role="menuitem" class="alvobot-kebab-item alvobot-edit-conversion" data-conversion='` +
					convAttr +
					`'><i data-lucide="pencil" class="alvobot-icon"></i> Editar</button>`;
				html +=
					'<button type="button" role="menuitem" class="alvobot-kebab-item alvobot-unlink-conversion" data-id="' +
					c.id +
					'"><i data-lucide="unlink" class="alvobot-icon"></i> Desvincular</button>';
				html += '<div class="alvobot-kebab-divider" role="separator"></div>';
				html +=
					'<button type="button" role="menuitem" class="alvobot-kebab-item alvobot-kebab-item-danger alvobot-delete-conversion-full" data-id="' +
					c.id +
					'"><i data-lucide="trash-2" class="alvobot-icon"></i> Excluir (com Google Ads)</button>';
				html += '</div></div>';
				html += '</div></td>';
				html += '</tr>';
				$tbody.append(html);
			}
			$('#alvobot-conv-select-all').prop('checked', false);
			updateBulkBar();
		}
		function getPlatformLabel(platform) {
			var labels = {
				all: '<span class="alvobot-badge alvobot-badge-info">Todas</span>',
				meta_only: '<span class="alvobot-badge alvobot-badge-neutral">Meta</span>',
				google_only: '<span class="alvobot-badge alvobot-badge-success">Google</span>',
			};
			return labels[platform] || labels.all;
		}
		function getTriggerLabel(type) {
			var labels = {
				page_load: 'Carregamento da Pagina',
				page_time: 'Tempo na Pagina',
				form_submit: 'Envio de Formulario',
				click: 'Clique',
				scroll: 'Scroll',
				view_element: 'Visualizacao de Elemento',
				ad_impression: 'Impressao de Anuncio',
				ad_click: 'Clique em Anuncio',
				ad_vignette_open: 'Abertura de Vinheta',
				ad_vignette_click: 'Clique em Vinheta',
			};
			return labels[type] || type;
		}
		function getDisplayedConversionEventName(c) {
			var runtimeName = getAdRuntimeEventName(c.trigger_type);
			if (runtimeName) {
				return runtimeName;
			}
			return c.event_type === 'CustomEvent' ? c.event_custom_name : c.event_type;
		}
		function getConversionSyncSummary(c) {
			var pixelIds = (c.pixel_ids || '').split(',').map(function (id) {
				return id.trim();
			});
			var hasGoogleTracker = pixelIds.some(function (id) {
				return /^AW-/.test(id);
			});
			var isGoogleTarget = c.platforms === 'google_only' || c.platforms === 'all' || hasGoogleTracker;
			if (!isGoogleTarget) {
				return '';
			}
			var labelsMap = {};
			if (c.gads_labels_map) {
				try {
					labelsMap = JSON.parse(c.gads_labels_map);
				} catch (_e) {
					labelsMap = {};
				}
			}
			var labels = Object.keys(labelsMap).filter(function (trackerId) {
				return !!labelsMap[trackerId];
			});
			var value = c.gads_conversion_value ? String(c.gads_conversion_value) : '';
			if (!labels.length && c.gads_conversion_label) {
				labels.push('legacy');
			}
			if (!labels.length) {
				return '<div class="alvobot-text-warning-dark alvobot-text-muted-xs" style="margin-top:3px;">Google Ads: sem label configurado</div>';
			}
			return (
				'<div class="alvobot-text-success-dark alvobot-text-muted-xs" style="margin-top:3px;">Google Ads: ' +
				labels.length +
				' label(s)' +
				(value ? ' \xB7 valor ' + escHtml(value) : ' \xB7 sem valor') +
				'</div>'
			);
		}
		$(document).on('click', '#alvobot-new-conversion-btn', function () {
			resetConversionForm();
			$('#alvobot-conversion-form').show();
			$('#alvobot-conversion-form-title').text('Nova Conversao');
		});
		$(document).on('click', '.alvobot-edit-conversion', function () {
			var c = parseJsonAttr($(this), 'data-conversion');
			if (!c) {
				return;
			}
			resetConversionForm();
			fillConversionForm(c);
			$('#alvobot-conversion-form').show();
			$('#alvobot-conversion-form-title').text('Editar Conversao');
		});
		function fillConversionForm(c) {
			$('#conv_id').val(c.id);
			$('#conv_name').val(c.name);
			$('#conv_event_type').val(c.event_type).trigger('change');
			$('#conv_event_custom_name').val(c.event_custom_name);
			$('#conv_trigger_type').val(c.trigger_type).trigger('change');
			$('#conv_trigger_value').val(c.trigger_value);
			$('#conv_display_on').val(c.display_on).trigger('change');
			var pageIds = Array.isArray(c.page_ids)
				? c.page_ids
				: typeof c.page_ids === 'string' && c.page_ids
					? c.page_ids.split(',')
					: [];
			$('#conv_page_ids').val(pageIds.join(','));
			$('#conv_page_ids_legacy').val(pageIds.join(', '));
			$('#conv_page_paths').val(c.page_paths);
			$('#conv_css_selector').val(c.css_selector);
			$('#conv_content_name').val(c.content_name);
			$('#conv_gads_conversion_value').val(c.gads_conversion_value || '');
			applyArbitrageTriggerDefaults(c.trigger_type, true);
			var labelsMap = {};
			if (c.gads_labels_map) {
				try {
					labelsMap = JSON.parse(c.gads_labels_map);
				} catch (_e) {}
			}
			if (!Object.keys(labelsMap).length && c.gads_conversion_label) {
				var selectedAds = (c.pixel_ids || '').split(',').filter(function (id) {
					return /^AW-/.test(id.trim());
				});
				for (var k = 0; k < selectedAds.length; k++) {
					labelsMap[selectedAds[k].trim()] = c.gads_conversion_label;
				}
			}
			$('#conv_gads_labels_map').val(JSON.stringify(labelsMap));
			$('#conv_gads_conversion_label').val(c.gads_conversion_label || '');
			var selectedIds = (c.pixel_ids || '')
				.split(',')
				.map(function (s) {
					return s.trim();
				})
				.filter(Boolean);
			$('#conv-pixel-selector .conv-pixel-checkbox').each(function () {
				$(this).prop('checked', selectedIds.indexOf($(this).val()) !== -1);
			});
			$('#conv_pixel_ids').val(c.pixel_ids || '');
			updateGadsLabelsUI(selectedIds);
		}
		function resetConversionForm() {
			$('#conv_id').val('0');
			$('#conv_name').val('');
			$('#conv_event_type').val('PageView').trigger('change');
			$('#conv_event_custom_name').val('');
			$('#conv_trigger_type').val('page_load').trigger('change');
			$('#conv_trigger_value').val('');
			$('#conv_display_on').val('all').trigger('change');
			$('#conv_page_ids').val('');
			$('#conv_page_ids_legacy').val('');
			$('#conv_page_paths').val('');
			$('#conv_css_selector').val('');
			$('#conv_content_name').val('');
			$('#conv_gads_conversion_label').val('');
			$('#conv_gads_labels_map').val('{}');
			$('#conv_gads_conversion_value').val('');
			$('#conv-gads-labels-container').empty();
			$('#conv-gads-fields').removeClass('visible');
			$('#conv-pixel-selector .conv-pixel-checkbox').prop('checked', false);
			$('#conv_pixel_ids').val('');
		}
		var openKebabRefs = null;
		function positionKebabMenu($trigger, $menu) {
			var rect = $trigger[0].getBoundingClientRect();
			var menuWidth = Math.max(200, $menu.outerWidth() || 200);
			var menuHeight = $menu.outerHeight() || 0;
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var adminBarH = 0;
			var adminBar = document.getElementById('wpadminbar');
			if (adminBar && adminBar.offsetParent !== null) {
				adminBarH = adminBar.getBoundingClientRect().height || 0;
			}
			var top = rect.bottom + 4;
			var left = Math.max(8, rect.right - menuWidth);
			if (menuHeight && top + menuHeight > vh - 8) {
				top = rect.top - menuHeight - 4;
			}
			top = Math.max(adminBarH + 8, top);
			if (menuHeight && top + menuHeight > vh - 8) {
				top = vh - menuHeight - 8;
			}
			if (left + menuWidth > vw - 8) {
				left = vw - menuWidth - 8;
			}
			$menu.css({ position: 'fixed', top: top + 'px', left: left + 'px', right: 'auto' });
		}
		function closeAllKebabs() {
			$('.alvobot-kebab-menu').attr('hidden', true).css({ position: '', top: '', left: '', right: '' });
			$('.alvobot-kebab-trigger').attr('aria-expanded', 'false');
			openKebabRefs = null;
		}
		function repositionOpenKebab() {
			if ((openKebabRefs == null ? void 0 : openKebabRefs.$trigger.length) && openKebabRefs.$menu.length) {
				var rect = openKebabRefs.$trigger[0].getBoundingClientRect();
				if (rect.bottom < 0 || rect.top > window.innerHeight) {
					closeAllKebabs();
					return;
				}
				positionKebabMenu(openKebabRefs.$trigger, openKebabRefs.$menu);
			}
		}
		$(document).on('click', '.alvobot-kebab-trigger', function (e) {
			e.stopPropagation();
			var $trigger = $(this);
			var $menu = $trigger.siblings('.alvobot-kebab-menu');
			var isOpen = !$menu.attr('hidden');
			closeAllKebabs();
			if (!isOpen) {
				$menu.removeAttr('hidden');
				positionKebabMenu($trigger, $menu);
				$trigger.attr('aria-expanded', 'true');
				openKebabRefs = { $trigger, $menu };
			}
		});
		$(document).on('click', '.alvobot-kebab-item', function () {
			setTimeout(closeAllKebabs, 0);
		});
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.alvobot-row-actions-kebab, .alvobot-kebab-menu').length) {
				closeAllKebabs();
			}
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				closeAllKebabs();
			}
		});
		$(window).on('scroll resize', function () {
			if (openKebabRefs) {
				repositionOpenKebab();
			}
		});
		document.addEventListener(
			'scroll',
			function () {
				if (openKebabRefs) {
					repositionOpenKebab();
				}
			},
			true,
		);
		$(document).on('change', '#conv_event_type', function () {
			if ($(this).val() === 'CustomEvent') {
				$('#conv-custom-name-field').addClass('visible');
			} else {
				$('#conv-custom-name-field').removeClass('visible');
			}
		});
		$(document).on('change', '#conv_trigger_type', function () {
			var val = $(this).val();
			if (val === 'page_time' || val === 'scroll') {
				$('#conv-trigger-value-field').addClass('visible');
			} else {
				$('#conv-trigger-value-field').removeClass('visible');
			}
			if (val === 'form_submit' || val === 'click' || val === 'view_element') {
				$('#conv-selector-field').addClass('visible');
			} else {
				$('#conv-selector-field').removeClass('visible');
			}
			var isAdTrigger =
				['ad_impression', 'ad_click', 'ad_vignette_open', 'ad_vignette_click'].indexOf(val) !== -1;
			$('#conv-ad-all-notice').toggle(isAdTrigger);
			applyArbitrageTriggerDefaults(val, false);
		});
		$(document).on('change', '#conv_display_on', function () {
			if ($(this).val() === 'path') {
				$('#conv-page-paths-field').addClass('visible');
			} else {
				$('#conv-page-paths-field').removeClass('visible');
			}
			if ($(this).val() === 'specific') {
				$('#conv-page-ids-legacy-field').addClass('visible');
			} else {
				$('#conv-page-ids-legacy-field').removeClass('visible');
			}
		});
		$(document).on('change', '#alvobot-conv-select-all', function () {
			var checked = $(this).prop('checked');
			$('.alvobot-conv-select').prop('checked', checked);
			updateBulkBar();
		});
		$(document).on('change', '.alvobot-conv-select', function () {
			var total = $('.alvobot-conv-select').length;
			var checked = $('.alvobot-conv-select:checked').length;
			$('#alvobot-conv-select-all').prop('checked', total > 0 && checked === total);
			updateBulkBar();
		});
		function updateBulkBar() {
			var count = $('.alvobot-conv-select:checked').length;
			var $bar = $('#alvobot-conversions-bulk-bar');
			if (count > 0) {
				$('#alvobot-bulk-count').text(count);
				$bar.css('display', 'flex');
			} else {
				$bar.hide();
			}
		}
		function selectedConversionIds() {
			var ids = [];
			$('.alvobot-conv-select:checked').each(function () {
				ids.push($(this).attr('data-id'));
			});
			return ids;
		}
		$(document).on('click', '#alvobot-bulk-unlink-btn', function () {
			var ids = selectedConversionIds();
			if (!ids.length) {
				return;
			}
			var $btn = $(this);
			alvobotModal
				.confirm({
					title: 'Desvincular selecionados',
					message:
						'Desvincular ' +
						ids.length +
						' conversao(oes) do plugin?\n\nAs regras locais serao apagadas, mas as conversoes no Google Ads continuarao existindo (e podem ser recriadas com "Criar em todas").',
					confirmText: 'Desvincular',
					cancelText: 'Cancelar',
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					$btn.prop('disabled', true);
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 2e4,
						data: {
							action: 'alvobot_pixel_tracking_bulk_delete_conversions',
							nonce: config.nonce,
							ids: ids.join(','),
						},
					})
						.done(function (response) {
							if (response == null ? void 0 : response.success) {
								loadConversions();
							} else {
								alert((response == null ? void 0 : response.data) || 'Erro ao desvincular.');
							}
						})
						.fail(function (xhr, status) {
							alert('Erro de conexao ao desvincular' + (status === 'timeout' ? ' (timeout)' : '') + '.');
						})
						.always(function () {
							$btn.prop('disabled', false);
						});
				});
		});
		$(document).on('click', '#alvobot-bulk-delete-btn', function () {
			var ids = selectedConversionIds();
			if (!ids.length) {
				return;
			}
			var $btn = $(this);
			alvobotModal
				.confirm({
					title: 'Excluir selecionados',
					message:
						'Excluir ' +
						ids.length +
						' conversao(oes) COMPLETAMENTE?\n\n  1. Regras locais serao apagadas\n  2. Conversoes serao arquivadas no Google Ads (todas as contas vinculadas)\n\nA acao no Google Ads e IRREVERSIVEL via API.\n\nSe voce quer apenas desvincular do plugin (mantendo o historico no Google Ads), use "Desvincular Selecionados".',
					confirmText: 'Excluir tudo',
					cancelText: 'Cancelar',
					danger: true,
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					$btn.prop('disabled', true).html(
						'<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Excluindo...',
					);
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 3e5,
						data: {
							action: 'alvobot_pixel_tracking_bulk_delete_conversions_full',
							nonce: config.nonce,
							ids: ids.join(','),
						},
					})
						.done(function (response) {
							var _a;
							if (response == null ? void 0 : response.success) {
								var d = response.data || {};
								var summary = 'Concluido. ' + (d.local_deleted || 0) + ' regra(s) local(is) apagada(s)';
								if (d.gads_archived) {
									summary += ', ' + d.gads_archived + ' conversao(oes) arquivada(s) no Google Ads';
								}
								if ((_a = d.gads_errors) == null ? void 0 : _a.length) {
									summary +=
										'.\n\n' +
										d.gads_errors.length +
										' erro(s) ao arquivar no Google Ads:\n - ' +
										d.gads_errors.slice(0, 6).join('\n - ');
									if (d.gads_errors.length > 6) {
										summary += '\n... e mais ' + (d.gads_errors.length - 6) + '.';
									}
								} else {
									summary += '.';
								}
								alvobotModal.alert({ title: 'Concluido', message: summary });
								loadConversions();
							} else {
								alert((response == null ? void 0 : response.data) || 'Erro ao excluir.');
							}
						})
						.fail(function (xhr, status) {
							alert('Erro de conexao ao excluir' + (status === 'timeout' ? ' (timeout)' : '') + '.');
						})
						.always(function () {
							$btn.prop('disabled', false).html(
								'<i data-lucide="trash-2" class="alvobot-icon"></i> Excluir Selecionados (com Google Ads)',
							);
							if (window.lucide) {
								window.lucide.createIcons();
							}
						});
				});
		});
		$(document).on('click', '#alvobot-cancel-conversion-btn', function () {
			$('#alvobot-conversion-form').hide();
			resetConversionForm();
		});
		$(document).on('click', '.alvobot-gads-fetch-label-btn', function () {
			var $btn = $(this);
			var adsTracker = parseJsonAttr($btn, 'data-tracker');
			if (!adsTracker) {
				return;
			}
			var $row = $btn.closest('.alvobot-gads-label-row');
			var trackerId = $row.attr('data-tracker-id');
			var customerId = getGoogleAdsCustomerId(adsTracker);
			if (!customerId) {
				alert('Nao foi possivel identificar o Customer ID da conta Google Ads.');
				return;
			}
			var $picker = $row.next('.alvobot-gads-row-picker');
			if (!$picker.length) {
				$picker = $('<div class="alvobot-gads-row-picker" style="margin-top:6px;width:100%;"></div>');
				$row.after($picker);
			}
			$btn.prop('disabled', true);
			$picker.html('<span class="spinner is-active" style="float:none;"></span> Buscando conversoes...').show();
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_fetch_conversion_actions',
					nonce: config.nonce,
					connection_id: adsTracker.connection_id,
					customer_id: customerId,
				},
				success: function (response) {
					$btn.prop('disabled', false);
					if (response.success && response.data.conversion_actions) {
						var actions = response.data.conversion_actions;
						var html = '';
						var actionTagId = '';
						for (var k = 0; k < actions.length; k++) {
							var ca = actions[k];
							if (!ca.conversion_label) {
								continue;
							}
							if (!actionTagId) {
								actionTagId = getConversionActionTagId(ca);
							}
							var actionId = getConversionActionId(ca);
							html +=
								'<div class="alvobot-ca-pick-item alvobot-item-row-sm" data-label="' +
								escAttr(ca.conversion_label) +
								'" data-tracker-id="' +
								escAttr(trackerId) +
								'">';
							html +=
								'<i data-lucide="target" class="alvobot-icon" style="width:12px;height:12px;"></i> ';
							html += '<span style="font-size:13px;">' + escHtml(ca.name) + '</span>';
							if (ca.status && ca.status !== 'ENABLED') {
								html += '<span class="alvobot-status-tag-warning">' + escHtml(ca.status) + '</span>';
							}
							html +=
								'<code style="margin-left:auto;font-size:11px;">' +
								escHtml(ca.conversion_label) +
								'</code>';
							if (actionId && ca.status !== 'REMOVED') {
								html +=
									`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-gads-remove-action-btn" data-tracker='` +
									escAttr(JSON.stringify(adsTracker)) +
									`' data-action-id="` +
									escAttr(actionId) +
									'" data-action-name="' +
									escAttr(ca.name || ca.conversion_label) +
									'" title="Arquivar no Google Ads">';
								html += '<i data-lucide="archive" class="alvobot-icon"></i> Arquivar';
								html += '</button>';
							}
							html += '</div>';
						}
						if (actionTagId) {
							adsTracker.tag_id = actionTagId;
							adsTracker.customer_id = customerId;
							syncGoogleTrackerRuntimeIds(trackerId, { customer_id: customerId, tag_id: actionTagId });
						}
						if (!html) {
							html =
								'<p class="alvobot-description" style="margin-bottom:8px;">Nenhuma conversao encontrada nesta conta.</p>';
						}
						var existingNames = actions.map(function (a) {
							return normalizeConversionName(a.name);
						});
						var suggestions = getArbitrageConversionPresets(true);
						var missingSuggestions = suggestions.filter(function (s2) {
							return !hasPresetName(existingNames, s2);
						});
						if (missingSuggestions.length) {
							html += '<div class="alvobot-suggestion-panel" style="margin-top:10px;">';
							html +=
								'<p class="alvobot-description" style="font-weight:600;margin-bottom:6px;"><i data-lucide="sparkles" class="alvobot-icon" style="width:14px;height:14px;"></i> Conversoes sugeridas</p>';
							for (var s = 0; s < missingSuggestions.length; s++) {
								var sg = missingSuggestions[s];
								html +=
									'<div class="alvobot-item-row-sm alvobot-item-row-white" style="margin-bottom:4px;">';
								html += '<span style="font-size:13px;font-weight:500;">' + escHtml(sg.name) + '</span>';
								html += '<span class="alvobot-text-muted-xs">' + escHtml(sg.desc) + '</span>';
								html +=
									`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary alvobot-ca-create-btn" style="margin-left:auto;" data-tracker='` +
									escAttr(JSON.stringify(adsTracker)) +
									`' data-tracker-id="` +
									escAttr(trackerId) +
									'" data-prefill-name="' +
									escAttr(sg.name) +
									'" data-prefill-category="' +
									escAttr(sg.category) +
									'" data-prefill-primary-for-goal="' +
									escAttr(sg.primary_for_goal === false ? '0' : '1') +
									'" data-prefill-trigger="' +
									escAttr(sg.trigger) +
									'" data-prefill-event-type="' +
									escAttr(sg.event_type) +
									'" data-prefill-event-custom-name="' +
									escAttr(sg.event_custom_name || '') +
									'" data-prefill-value="' +
									escAttr(sg.default_value || '') +
									'">';
								html += '<i data-lucide="plus" class="alvobot-icon"></i> Criar</button>';
								html += '</div>';
							}
							html += '</div>';
						}
						html +=
							'<div class="alvobot-ca-create-form alvobot-create-form-panel" style="margin-top:8px;">';
						html +=
							'<p class="alvobot-description" style="font-weight:600;margin-bottom:6px;"><i data-lucide="plus-circle" class="alvobot-icon" style="width:14px;height:14px;"></i> Criar conversao personalizada</p>';
						html += '<div style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">';
						html +=
							'<div><label style="font-size:11px;font-weight:500;">Nome</label><input type="text" class="alvobot-input alvobot-ca-create-name" placeholder="Ex: Purchase, Lead" style="width:180px;"></div>';
						html +=
							'<div><label style="font-size:11px;font-weight:500;">Categoria</label><select class="alvobot-input alvobot-ca-create-category" style="width:140px;">';
						html +=
							'<option value="DEFAULT" selected>Default</option><option value="PURCHASE">Purchase</option><option value="LEAD">Lead</option><option value="PAGE_VIEW">Page View</option>';
						html +=
							'<option value="SIGNUP">Signup</option><option value="ADD_TO_CART">Add to Cart</option><option value="BEGIN_CHECKOUT">Begin Checkout</option>';
						html +=
							'<option value="SUBSCRIBE_PAID">Subscribe</option><option value="CONTACT">Contact</option></select></div>';
						html +=
							`<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-ca-create-btn" data-tracker='` +
							escAttr(JSON.stringify(adsTracker)) +
							`' data-tracker-id="` +
							escAttr(trackerId) +
							'">';
						html += '<i data-lucide="check" class="alvobot-icon"></i> Criar</button>';
						html += '</div></div>';
						$picker.html(html);
						if (window.lucide) {
							window.lucide.createIcons();
						}
					} else {
						$picker.html(
							'<p class="alvobot-description" class="alvobot-text-error">' +
								escHtml(response.data || 'Erro ao buscar.') +
								'</p>',
						);
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$picker.html('<p class="alvobot-description" class="alvobot-text-error">Erro de conexao.</p>');
				},
			});
		});
		$(document).on('click', '.alvobot-ca-pick-item', function () {
			var label = $(this).attr('data-label');
			var trackerId = $(this).attr('data-tracker-id');
			$('.alvobot-gads-label-input[data-tracker-id="' + trackerId + '"]').val(label);
			$(this).closest('.alvobot-gads-row-picker').hide();
			syncGadsLabelsMap();
		});
		$(document).on('click', '.alvobot-gads-remove-action-btn', function (ev) {
			ev.preventDefault();
			ev.stopPropagation();
			var $btn = $(this);
			var adsTracker = parseJsonAttr($btn, 'data-tracker');
			if (!adsTracker) {
				return;
			}
			var actionId = $btn.attr('data-action-id');
			var actionName = $btn.attr('data-action-name') || actionId;
			var customerId = getGoogleAdsCustomerId(adsTracker);
			if (!actionId) {
				alert('Nao foi possivel identificar esta conversao no Google Ads.');
				return;
			}
			if (!customerId) {
				alert('Nao foi possivel identificar o Customer ID da conta Google Ads.');
				return;
			}
			alvobotModal
				.confirm({
					title: 'Arquivar conversao',
					message:
						'Arquivar "' +
						actionName +
						'" no Google Ads? Esta acao e irreversivel via API (apenas via UI do Google Ads). A regra local do AlvoBot nao sera apagada automaticamente.',
					confirmText: 'Arquivar',
					cancelText: 'Cancelar',
					danger: true,
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					$btn.prop('disabled', true).text('Arquivando...');
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_delete_conversion_action',
							nonce: config.nonce,
							connection_id: adsTracker.connection_id,
							customer_id: customerId,
							conversion_action_id: actionId,
						},
						success: function (response) {
							if (response.success) {
								$btn.closest('.alvobot-ca-pick-item').fadeOut(150, function () {
									$(this).remove();
								});
								return;
							}
							$btn.prop('disabled', false).html(
								'<i data-lucide="archive" class="alvobot-icon"></i> Arquivar',
							);
							alert(response.data || 'Erro ao arquivar no Google Ads.');
							if (window.lucide) {
								window.lucide.createIcons();
							}
						},
						error: function () {
							$btn.prop('disabled', false).html(
								'<i data-lucide="archive" class="alvobot-icon"></i> Arquivar',
							);
							alert('Erro de conexao ao arquivar no Google Ads.');
							if (window.lucide) {
								window.lucide.createIcons();
							}
						},
					});
				});
		});
		$(document).on('click', '.alvobot-ca-create-btn', function () {
			var $btn = $(this);
			var adsTracker = parseJsonAttr($btn, 'data-tracker');
			if (!adsTracker) {
				return;
			}
			var trackerId = $btn.attr('data-tracker-id');
			var customerId = getGoogleAdsCustomerId(adsTracker);
			var name = $btn.attr('data-prefill-name') || '';
			var category = $btn.attr('data-prefill-category') || '';
			if (!name) {
				var $form = $btn.closest('.alvobot-ca-create-form');
				name = $form.find('.alvobot-ca-create-name').val().trim();
				category = $form.find('.alvobot-ca-create-category').val();
			}
			if (!name) {
				alert('Digite o nome da conversao.');
				return;
			}
			if (adsTracker.connection_id && !customerId) {
				alert('Nao foi possivel identificar o Customer ID da conta Google Ads.');
				return;
			}
			var prefillData = {
				trigger: $btn.attr('data-prefill-trigger') || '',
				event_type: $btn.attr('data-prefill-event-type') || '',
				event_custom_name: $btn.attr('data-prefill-event-custom-name') || '',
				value: $btn.attr('data-prefill-value') || '',
				primary_for_goal: $btn.attr('data-prefill-primary-for-goal'),
			};
			$btn.prop('disabled', true).text('Criando...');
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_create_conversion_action',
					nonce: config.nonce,
					connection_id: adsTracker.connection_id,
					customer_id: customerId,
					name,
					category,
					// Default '1' (primary) when prefill data is missing — preserves prior behaviour
					// for the manual-form path while letting suggestion buttons opt-in to '0'.
					primary_for_goal: prefillData.primary_for_goal === void 0 ? '1' : prefillData.primary_for_goal,
					default_value: prefillData.value || 0,
					currency: 'BRL',
				},
				success: function (response) {
					$btn.prop('disabled', false).html('<i data-lucide="check" class="alvobot-icon"></i> Criar');
					if (response.success && response.data) {
						var label = response.data.conversion_label || '';
						var createdTagId = getConversionActionTagId(response.data);
						if (createdTagId) {
							adsTracker.tag_id = createdTagId;
							adsTracker.customer_id = customerId;
							syncGoogleTrackerRuntimeIds(trackerId, { customer_id: customerId, tag_id: createdTagId });
						}
						if (label) {
							$('.alvobot-gads-label-input[data-tracker-id="' + trackerId + '"]').val(label);
							syncGadsLabelsMap();
						}
						if (prefillData.trigger && prefillData.event_type) {
							var labelsMap = {};
							if (label) {
								labelsMap[trackerId] = label;
							}
							$.ajax({
								url: config.ajaxurl,
								method: 'POST',
								timeout: 2e4,
								data: {
									action: 'alvobot_pixel_tracking_save_conversion',
									nonce: config.nonce,
									conversion_id: 0,
									name,
									event_type: prefillData.event_type,
									event_custom_name: prefillData.event_custom_name || '',
									trigger_type: prefillData.trigger,
									display_on: 'all',
									content_name: name,
									pixel_ids: trackerId,
									gads_conversion_label: label,
									gads_labels_map: JSON.stringify(labelsMap),
									gads_conversion_value: prefillData.value || '',
								},
							})
								.done(function (saveResponse) {
									if (!(saveResponse == null ? void 0 : saveResponse.success)) {
										alert(
											(saveResponse == null ? void 0 : saveResponse.data) ||
												'Conversao criada no Google Ads mas falhou ao salvar a regra local.',
										);
									}
								})
								.fail(function (xhr, status) {
									alert(
										'Conversao criada no Google Ads mas a conexao falhou ao salvar a regra local' +
											(status === 'timeout' ? ' (timeout)' : '') +
											'. Clique em Buscar novamente.',
									);
								});
							$btn.closest('.alvobot-gads-row-picker').html(
								'<p class="alvobot-description" class="alvobot-text-success">' +
									escHtml(name) +
									' criada e configurada!' +
									(label ? ' Label: <code>' + escHtml(label) + '</code>' : '') +
									'</p>',
							);
						} else {
							$btn.closest('.alvobot-gads-row-picker').html(
								label
									? '<p class="alvobot-description" class="alvobot-text-success">Conversao "' +
											escHtml(name) +
											'" criada! Label: <code>' +
											escHtml(label) +
											'</code></p>'
									: '<p class="alvobot-description" class="alvobot-text-warning">Conversao criada, mas label nao disponivel. Clique em Buscar novamente.</p>',
							);
						}
					} else {
						alert(response.data || 'Erro ao criar conversao.');
					}
					if (window.lucide) {
						window.lucide.createIcons();
					}
				},
				error: function () {
					$btn.prop('disabled', false).html('<i data-lucide="check" class="alvobot-icon"></i> Criar');
					alert('Erro de conexao.');
				},
			});
		});
		var saveConversionXhr = null;
		var saveConversionInFlight = false;
		$(document).on('click', '#alvobot-save-conversion-btn', function () {
			var $btn = $(this);
			var $form = $('#alvobot-conversion-form');
			var originalHTML = $btn.data('original-html');
			if (!originalHTML) {
				originalHTML = $btn.html();
				$btn.data('original-html', originalHTML);
			}
			if (saveConversionInFlight) {
				debugLog('save conversion: re-click while in-flight \u2014 aborting prior request and retrying');
				if (saveConversionXhr == null ? void 0 : saveConversionXhr.abort) {
					try {
						saveConversionXhr.abort();
					} catch (_e) {}
				}
			}
			if (!($('#conv_name').val() || '').trim()) {
				alert('Informe o nome da conversao.');
				$('#conv_name').trigger('focus');
				return;
			}
			saveConversionInFlight = true;
			$btn.attr('aria-busy', 'true')
				.css('opacity', '0.7')
				.html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Salvando...');
			var releaseBtn = function () {
				saveConversionInFlight = false;
				saveConversionXhr = null;
				$btn.removeAttr('aria-busy').css('opacity', '').html(originalHTML);
				if (window.lucide) {
					window.lucide.createIcons();
				}
			};
			var pageIdsRaw = $('#conv_page_ids').val();
			var pageIds = pageIdsRaw
				? pageIdsRaw
						.split(',')
						.map(function (value) {
							return parseInt(value, 10);
						})
						.filter(function (value) {
							return !Number.isNaN(value) && value > 0;
						})
				: [];
			debugLog('save conversion: submitting', {
				conversion_id: $('#conv_id').val(),
				name: $('#conv_name').val(),
				trigger_type: $('#conv_trigger_type').val(),
				event_type: $('#conv_event_type').val(),
			});
			saveConversionXhr = $.ajax({
				url: config.ajaxurl,
				method: 'POST',
				timeout: 2e4,
				data: {
					action: 'alvobot_pixel_tracking_save_conversion',
					nonce: config.nonce,
					conversion_id: $('#conv_id').val(),
					name: $('#conv_name').val(),
					event_type: $('#conv_event_type').val(),
					event_custom_name: $('#conv_event_custom_name').val(),
					trigger_type: $('#conv_trigger_type').val(),
					trigger_value: $('#conv_trigger_value').val(),
					display_on: $('#conv_display_on').val(),
					page_ids: pageIds,
					page_paths: $('#conv_page_paths').val(),
					css_selector: $('#conv_css_selector').val(),
					content_name: $('#conv_content_name').val(),
					pixel_ids: $('#conv_pixel_ids').val(),
					gads_conversion_label: $('#conv_gads_conversion_label').val(),
					gads_labels_map: $('#conv_gads_labels_map').val(),
					gads_conversion_value: $('#conv_gads_conversion_value').val(),
				},
			})
				.done(function (response) {
					if (response == null ? void 0 : response.success) {
						$form.hide();
						resetConversionForm();
						loadConversions();
					} else {
						var msg =
							(response == null ? void 0 : response.data) ||
							'Erro ao salvar (resposta invalida do servidor).';
						debugError('save conversion: server returned error', response);
						alert(msg);
					}
				})
				.fail(function (xhr, status, err) {
					var _a;
					if (status === 'abort') {
						return;
					}
					debugError('save conversion: AJAX error', {
						status,
						http: xhr == null ? void 0 : xhr.status,
						error: err,
						body: (_a = xhr == null ? void 0 : xhr.responseText) == null ? void 0 : _a.slice(0, 300),
					});
					var detail = '';
					if (xhr && xhr.status === 403) {
						detail = ' (Sessao expirada. Recarregue a pagina e tente novamente.)';
					} else if (status === 'timeout') {
						detail = ' (Tempo limite excedido. Verifique sua conexao e tente novamente.)';
					} else if (xhr && xhr.status >= 500) {
						detail = ' (Erro no servidor: HTTP ' + xhr.status + '.)';
					}
					alert('Erro ao salvar conversao.' + detail);
				})
				.always(releaseBtn);
		});
		$(document).on('change', '.alvobot-toggle-conversion', function () {
			var $toggle = $(this);
			var id = $toggle.data('id');
			var active = $toggle.is(':checked') ? '1' : '0';
			$toggle.prop('disabled', true);
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				timeout: 15e3,
				data: {
					action: 'alvobot_pixel_tracking_toggle_conversion',
					nonce: config.nonce,
					conversion_id: id,
					active,
				},
			})
				.done(function (response) {
					if (!(response == null ? void 0 : response.success)) {
						$toggle.prop('checked', active !== '1');
						alert((response == null ? void 0 : response.data) || 'Erro ao alterar status.');
					}
				})
				.fail(function (xhr, status) {
					$toggle.prop('checked', active !== '1');
					alert('Erro de conexao ao alterar status' + (status === 'timeout' ? ' (timeout)' : '') + '.');
				})
				.always(function () {
					$toggle.prop('disabled', false);
				});
		});
		$(document).on('click', '.alvobot-unlink-conversion', function () {
			var $btn = $(this);
			var id = $btn.data('id');
			var $row = $btn.closest('tr');
			var name = $row.find('td:nth-child(2)').text().trim() || 'esta conversao';
			alvobotModal
				.confirm({
					title: 'Desvincular conversao',
					message:
						'Desvincular "' +
						name +
						'" do plugin?\n\nA regra local sera apagada, mas a conversao no Google Ads continuara existindo (e pode ser recriada depois com "Criar em todas").',
					confirmText: 'Desvincular',
					cancelText: 'Cancelar',
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					$btn.prop('disabled', true);
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 15e3,
						data: {
							action: 'alvobot_pixel_tracking_delete_conversion',
							nonce: config.nonce,
							conversion_id: id,
						},
					})
						.done(function (response) {
							if (response == null ? void 0 : response.success) {
								$row.fadeOut(function () {
									$(this).remove();
									loadConversions();
								});
							} else {
								alert((response == null ? void 0 : response.data) || 'Erro ao desvincular conversao.');
							}
						})
						.fail(function (xhr, status) {
							alert('Erro de conexao ao desvincular' + (status === 'timeout' ? ' (timeout)' : '') + '.');
						})
						.always(function () {
							$btn.prop('disabled', false);
						});
				});
		});
		$(document).on('click', '.alvobot-delete-conversion-full', function () {
			var $btn = $(this);
			var id = $btn.data('id');
			var $row = $btn.closest('tr');
			var name = $row.find('td:nth-child(2)').text().trim() || 'esta conversao';
			alvobotModal
				.confirm({
					title: 'Excluir conversao',
					message:
						'Excluir "' +
						name +
						'" COMPLETAMENTE?\n\nIsso vai:\n  1. Apagar a regra local do plugin\n  2. Arquivar a conversao no Google Ads (todas as contas vinculadas)\n\nA acao no Google Ads e IRREVERSIVEL via API (so reversivel pela UI do Google Ads).\n\nSe voce quer apenas parar de usar no plugin (mantendo o historico no Google Ads), use "Desvincular".',
					confirmText: 'Excluir',
					cancelText: 'Cancelar',
					danger: true,
				})
				.then(function (ok) {
					if (!ok) {
						return;
					}
					$btn.prop('disabled', true);
					$.ajax({
						url: config.ajaxurl,
						method: 'POST',
						timeout: 6e4,
						data: {
							action: 'alvobot_pixel_tracking_delete_conversion_full',
							nonce: config.nonce,
							conversion_id: id,
						},
					})
						.done(function (response) {
							var _a;
							if (response == null ? void 0 : response.success) {
								var msg = ((_a = response.data) == null ? void 0 : _a.message) || '';
								if (msg) {
									debugLog('delete-full result', response.data);
								}
								$row.fadeOut(function () {
									$(this).remove();
									loadConversions();
								});
							} else {
								alert((response == null ? void 0 : response.data) || 'Erro ao excluir conversao.');
							}
						})
						.fail(function (xhr, status) {
							alert('Erro de conexao ao excluir' + (status === 'timeout' ? ' (timeout)' : '') + '.');
						})
						.always(function () {
							$btn.prop('disabled', false);
						});
				});
		});
	}
	function initConfiguracoesTab() {
		debugLog('settings tab: init');
		$('input[name="test_mode"]').on('change', function () {
			debugLog('settings tab: test_mode changed', { enabled: $(this).is(':checked') });
			if ($(this).is(':checked')) {
				$('#test-code-field').show();
			} else {
				$('#test-code-field').hide();
			}
		});
	}
	function initEventsTab() {
		debugLog('events tab: init');
		var currentPage = 1;
		var perPage = 25;
		var totalEvents = 0;
		var currentOffset = 0;
		var pixelLabelsById = getConfiguredPixelLabelMap();
		var selectedEventIds = {};
		loadEvents();
		loadEventStats();
		updateBulkActionsState();
		debugLog('events tab: initial load triggered');
		$('#events-filter-btn').on('click', function () {
			debugLog('events tab: filter apply clicked', {
				status: $('#filter-status').val(),
				event_name: $('#filter-event-name').val(),
			});
			currentPage = 1;
			currentOffset = 0;
			clearSelection();
			loadEvents();
		});
		$('#events-clear-filters-btn').on('click', function () {
			debugLog('events tab: clear filters clicked');
			$('#filter-status').val('');
			$('#filter-event-name').val('');
			currentPage = 1;
			currentOffset = 0;
			clearSelection();
			loadEvents();
		});
		$('#events-prev-btn').on('click', function () {
			if (currentPage > 1) {
				currentPage--;
				currentOffset = (currentPage - 1) * perPage;
				loadEvents();
			}
		});
		$('#events-next-btn').on('click', function () {
			var maxPage = Math.ceil(totalEvents / perPage);
			if (currentPage < maxPage) {
				currentPage++;
				currentOffset = (currentPage - 1) * perPage;
				loadEvents();
			}
		});
		$('#events-select-all').on('change', function () {
			var checked = $(this).is(':checked');
			debugLog('events tab: select-all changed', { checked });
			$('.alvobot-events-row-check').each(function () {
				var eventId = String($(this).data('event-id') || '');
				$(this).prop('checked', checked);
				setSelected(eventId, checked);
			});
			updateBulkActionsState();
		});
		$(document)
			.off('change.alvobotEventRowCheck', '.alvobot-events-row-check')
			.on('change.alvobotEventRowCheck', '.alvobot-events-row-check', function () {
				var eventId = String($(this).data('event-id') || '');
				setSelected(eventId, $(this).is(':checked'));
				debugLog('events tab: row selection changed', { event_id: eventId, checked: $(this).is(':checked') });
				updateBulkActionsState();
			});
		$('#events-bulk-clear-btn').on('click', function () {
			debugLog('events tab: bulk clear clicked');
			clearSelection();
			$('.alvobot-events-row-check').prop('checked', false);
			updateBulkActionsState();
		});
		$('#events-bulk-apply-btn').on('click', function () {
			var action = String($('#events-bulk-action').val() || '');
			var eventIds = Object.keys(selectedEventIds);
			debugLog('events tab: bulk apply clicked', { action, event_ids: eventIds });
			if (!action || !eventIds.length) {
				return;
			}
			var confirmMessage =
				action === 'delete'
					? 'Excluir permanentemente ' + eventIds.length + ' evento(s) selecionado(s)?'
					: 'Reenviar ' + eventIds.length + ' evento(s) selecionado(s) para a Meta?';
			if (!confirm(confirmMessage)) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			restPost(
				'events/bulk',
				{
					action,
					event_ids: eventIds,
				},
				function (result) {
					debugLog('events tab: bulk apply success', result);
					$btn.prop('disabled', false);
					clearSelection();
					$('#events-bulk-action').val('');
					loadEvents();
					loadEventStats();
					var processed = result && typeof result.processed === 'number' ? result.processed : 0;
					var failed = result && typeof result.failed_count === 'number' ? result.failed_count : 0;
					if (failed > 0) {
						alert('Acao concluida com falhas. Processados: ' + processed + ' | Falhas: ' + failed);
					}
				},
				function (error) {
					$btn.prop('disabled', false);
					alert('Erro na acao em massa: ' + error);
				},
			);
		});
		function loadEvents() {
			var $tbody = $('#alvobot-events-tbody');
			$tbody.html(
				'<tr><td colspan="9" class="alvobot-events-loading"><span class="alvobot-skeleton" style="width:120px;"></span></td></tr>',
			);
			var params = 'limit=' + perPage + '&offset=' + currentOffset;
			var status = $('#filter-status').val();
			if (status) {
				params += '&status=' + encodeURIComponent(status);
			}
			var eventName = $('#filter-event-name').val();
			if (eventName) {
				params += '&event_name=' + encodeURIComponent(eventName);
			}
			debugLog('events tab: loadEvents request', { params, page: currentPage, offset: currentOffset });
			restGet(
				'events?' + params,
				function (data, meta) {
					debugLog('events tab: loadEvents success', { events_count: data ? data.length : 0, meta });
					totalEvents = meta ? meta.total : 0;
					renderEventsTable(data);
					updatePagination();
				},
				function (error) {
					debugError('events tab: loadEvents error', error);
					totalEvents = 0;
					$('#alvobot-events-pagination').hide();
					$tbody.html(
						'<tr><td colspan="9" class="alvobot-empty-state"><p>' +
							escHtml(error || 'Falha ao carregar eventos.') +
							'</p><small>Atualize a pagina e tente novamente.</small></td></tr>',
					);
					updateBulkActionsState();
				},
			);
		}
		function loadEventStats() {
			debugLog('events tab: loadEventStats request');
			restGet(
				'events/stats',
				function (data) {
					debugLog('events tab: loadEventStats success', data);
					var pending = data.pixel_pending || 0;
					var sent = data.pixel_sent || 0;
					var error = data.pixel_error || 0;
					var total = pending + sent + error;
					$('#events-stat-total').find('strong').text(formatNumber(total));
					$('#events-stat-pending').text(formatNumber(pending));
					$('#events-stat-sent').text(formatNumber(sent));
					$('#events-stat-error').text(formatNumber(error));
				},
				function () {
					debugError('events tab: loadEventStats error');
					$('#events-stat-total').find('strong').text('-');
					$('#events-stat-pending').text('-');
					$('#events-stat-sent').text('-');
					$('#events-stat-error').text('-');
				},
			);
		}
		function renderEventsTable(events) {
			debugLog('events tab: renderEventsTable', { count: events ? events.length : 0 });
			var $tbody = $('#alvobot-events-tbody');
			$tbody.empty();
			if (!(events == null ? void 0 : events.length)) {
				$tbody.html(
					'<tr><td colspan="9" class="alvobot-empty-state"><p>Nenhum evento encontrado.</p></td></tr>',
				);
				updateBulkActionsState();
				return;
			}
			for (var i = 0; i < events.length; i++) {
				var e = events[i];
				var html = '<tr data-event-id="' + escAttr(e.event_id) + '">';
				var eventId = String(e.event_id || '');
				var isSelected = !!selectedEventIds[eventId];
				html += '<td class="alvobot-events-col-check">';
				html +=
					'<input type="checkbox" class="alvobot-events-row-check" data-event-id="' +
					escAttr(eventId) +
					'"' +
					(isSelected ? ' checked' : '') +
					'>';
				html += '</td>';
				html += '<td>';
				html += '<div class="alvobot-events-cell-event">';
				html += '<strong>' + escHtml(e.event_name) + '</strong>';
				html +=
					'<code class="alvobot-events-uuid">' + escHtml((e.event_id || '').substring(0, 8)) + '...</code>';
				html += '</div>';
				html += '</td>';
				html +=
					'<td class="alvobot-events-col-status">' +
					getStatusBadge(e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids) +
					'</td>';
				html += '<td class="alvobot-events-col-page">';
				html += '<div class="alvobot-events-cell-page">';
				if (e.page_title) {
					html +=
						'<span class="alvobot-events-page-title">' + escHtml(truncate(e.page_title, 30)) + '</span>';
				}
				if (e.page_url) {
					html += '<span class="alvobot-events-page-url">' + escHtml(truncateUrl(e.page_url, 35)) + '</span>';
				}
				html += '</div>';
				html += '</td>';
				html += '<td>';
				html += '<div class="alvobot-events-cell-ip">';
				if (e.ip) {
					html += '<code>' + escHtml(e.ip) + '</code>';
				}
				if (e.geo_city || e.geo_country) {
					html += '<span class="alvobot-events-geo">';
					html += escHtml([e.geo_city, e.geo_country].filter(Boolean).join(', '));
					html += '</span>';
				}
				html += '</div>';
				html += '</td>';
				html += '<td>';
				html += '<div class="alvobot-events-cell-ids">';
				if (e.fbp) {
					html += '<span class="alvobot-events-id-tag">fbp</span>';
				}
				if (e.fbc) {
					html += '<span class="alvobot-events-id-tag alvobot-events-id-tag-fbc">fbc</span>';
				}
				if (!e.fbp && !e.fbc) {
					html += '<span class="alvobot-events-no-data">-</span>';
				}
				html += '</div>';
				html += '</td>';
				html += '<td class="alvobot-events-col-pixels">';
				html += '<div class="alvobot-events-cell-pixels">';
				if (e.pixel_ids) {
					var pIds = e.pixel_ids.split(',');
					for (var j = 0; j < pIds.length; j++) {
						var pid = pIds[j].trim();
						if (pid) {
							html += formatPixelTag(pid);
						}
					}
				} else {
					html += '<span class="alvobot-events-no-data">-</span>';
				}
				html += '</div>';
				html += '</td>';
				html += '<td>';
				html += '<div class="alvobot-events-cell-date">';
				html += '<span>' + escHtml(formatDate(e.created_at)) + '</span>';
				if (e.sent_at) {
					var sentTs = parseInt(e.sent_at, 10);
					if (sentTs > 0) {
						html +=
							'<span class="alvobot-events-sent-at">Enviado: ' +
							escHtml(formatTimestamp(sentTs)) +
							'</span>';
					}
				}
				html += '</div>';
				html += '</td>';
				html += '<td class="alvobot-events-col-actions">';
				html += '<div class="alvobot-events-actions-inline">';
				html +=
					'<button type="button" class="alvobot-events-action-btn" data-action="view" data-event-id="' +
					escAttr(e.event_id) +
					'" data-tooltip="Detalhes" title="Detalhes" aria-label="Detalhes">';
				html += '<i data-lucide="eye" class="alvobot-icon"></i>';
				html += '</button>';
				html +=
					'<button type="button" class="alvobot-events-action-btn" data-action="logs" data-event-id="' +
					escAttr(e.event_id) +
					'" data-tooltip="Logs CAPI" title="Logs CAPI" aria-label="Logs CAPI">';
				html += '<i data-lucide="file-text" class="alvobot-icon"></i>';
				html += '</button>';
				html +=
					'<button type="button" class="alvobot-events-action-btn" data-action="resend" data-event-id="' +
					escAttr(e.event_id) +
					'" data-tooltip="Reenviar" title="Reenviar" aria-label="Reenviar">';
				html += '<i data-lucide="refresh-cw" class="alvobot-icon"></i>';
				html += '</button>';
				html +=
					'<button type="button" class="alvobot-events-action-btn alvobot-events-action-btn-danger" data-action="delete" data-event-id="' +
					escAttr(e.event_id) +
					'" data-tooltip="Excluir" title="Excluir" aria-label="Excluir">';
				html += '<i data-lucide="trash-2" class="alvobot-icon"></i>';
				html += '</button>';
				html += '</div>';
				html += '</td>';
				html += '</tr>';
				$tbody.append(html);
			}
			if (window.lucide) {
				window.lucide.createIcons();
			}
			updateBulkActionsState();
		}
		function getStatusBadge(status, error, retryCount, dispatchChannel, pixelIds) {
			var map = {
				pixel_pending: { cls: 'alvobot-badge-warning', label: 'Pendente' },
				pixel_sent: { cls: 'alvobot-badge-success', label: 'Enviado' },
				pixel_error: { cls: 'alvobot-badge-error', label: 'Erro' },
			};
			var pids = String(pixelIds || '').trim();
			if (status === 'pixel_pending' && /^AW-/.test(pids) && !/^\d{15,16}/.test(pids)) {
				map.pixel_pending = { cls: 'alvobot-badge-success', label: 'Via Navegador' };
			}
			var info = map[status] || { cls: 'alvobot-badge-neutral', label: status };
			var html = '<span class="alvobot-badge ' + info.cls + '">' + info.label + '</span>';
			if (status === 'pixel_sent') {
				if (dispatchChannel === 'realtime') {
					html +=
						'<span class="alvobot-events-dispatch-tag alvobot-events-dispatch-realtime">Tempo real</span>';
				} else if (dispatchChannel === 'queue') {
					html += '<span class="alvobot-events-dispatch-tag alvobot-events-dispatch-queue">Fila</span>';
				}
			}
			if (retryCount > 0 && status === 'pixel_pending') {
				html += '<span class="alvobot-events-retry-tag">retry ' + retryCount + '/3</span>';
			}
			if (error && status === 'pixel_error') {
				html += '<span class="alvobot-events-error-hint" title="' + escAttr(error) + '">!</span>';
			}
			return html;
		}
		function getConfiguredPixelLabelMap() {
			var map = getLocalizedPixelLabelMap();
			var raw = $('#pixels_json').val();
			if (!raw) {
				return map;
			}
			try {
				var pixels = JSON.parse(raw);
				if (!Array.isArray(pixels)) {
					return map;
				}
				for (var i = 0; i < pixels.length; i++) {
					var pixel = pixels[i] || {};
					var id = String(pixel.pixel_id || '').trim();
					var label = String(pixel.label || '').trim();
					if (id) {
						map[id] = label;
					}
				}
			} catch (_e) {}
			return map;
		}
		function getLocalizedPixelLabelMap() {
			var map = {};
			var source = extra && typeof extra.pixel_labels === 'object' ? extra.pixel_labels : {};
			var keys = Object.keys(source);
			for (var i = 0; i < keys.length; i++) {
				var id = String(keys[i] || '').trim();
				if (!id) {
					continue;
				}
				map[id] = String(source[id] || '').trim();
			}
			return map;
		}
		function formatPixelTag(pixelId) {
			var id = String(pixelId || '').trim();
			var label = id ? pixelLabelsById[id] || '' : '';
			var text = label ? label + ' (' + id + ')' : id;
			return '<code class="alvobot-events-pixel-tag">' + escHtml(text) + '</code> ';
		}
		function formatPixelList(pixelIds) {
			if (!pixelIds) {
				return '<span class="alvobot-events-missing">Nenhum</span>';
			}
			var list = String(pixelIds).split(',');
			var html = '';
			for (var i = 0; i < list.length; i++) {
				var id = String(list[i] || '').trim();
				if (id) {
					html += formatPixelTag(id);
				}
			}
			return html || '<span class="alvobot-events-missing">Nenhum</span>';
		}
		function setSelected(eventId, selected) {
			if (!eventId) {
				return;
			}
			if (selected) {
				selectedEventIds[eventId] = true;
			} else {
				delete selectedEventIds[eventId];
			}
		}
		function clearSelection() {
			selectedEventIds = {};
		}
		function updateBulkActionsState() {
			var selectedCount = Object.keys(selectedEventIds).length;
			var action = String($('#events-bulk-action').val() || '');
			var canApply = selectedCount > 0 && !!action;
			$('#events-selected-count').text(selectedCount);
			$('#events-bulk-clear-btn').prop('disabled', selectedCount === 0);
			$('#events-bulk-apply-btn').prop('disabled', !canApply);
			var $bulkBar = $('#events-bulk-bar');
			if (selectedCount > 0) {
				$bulkBar.addClass('is-visible');
			} else {
				$bulkBar.removeClass('is-visible');
				$('#events-bulk-action').val('');
			}
			var $visibleChecks = $('.alvobot-events-row-check');
			if (!$visibleChecks.length) {
				$('#events-select-all').prop('checked', false);
				return;
			}
			var checkedVisible = $visibleChecks.filter(':checked').length;
			$('#events-select-all').prop('checked', checkedVisible > 0 && checkedVisible === $visibleChecks.length);
		}
		$('#events-bulk-action').on('change', function () {
			updateBulkActionsState();
		});
		function updatePagination() {
			var maxPage = Math.ceil(totalEvents / perPage);
			if (maxPage <= 1) {
				$('#alvobot-events-pagination').hide();
				return;
			}
			$('#alvobot-events-pagination').show();
			$('#events-pagination-info').text(
				'Pagina ' + currentPage + ' de ' + maxPage + ' (' + formatNumber(totalEvents) + ' eventos)',
			);
			$('#events-prev-btn').prop('disabled', currentPage <= 1);
			$('#events-next-btn').prop('disabled', currentPage >= maxPage);
		}
		$(document)
			.off('click.alvobotEventsIconAction', '.alvobot-events-action-btn')
			.on('click.alvobotEventsIconAction', '.alvobot-events-action-btn', function (e) {
				e.preventDefault();
				e.stopImmediatePropagation();
				var $btn = $(this);
				var action = String($btn.data('action') || '');
				var eventId = String($btn.data('event-id') || '');
				debugLog('events tab: inline action clicked', { action, event_id: eventId });
				switch (action) {
					case 'view':
						openEventModal(eventId, 'details');
						break;
					case 'logs':
						openEventModal(eventId, 'logs');
						break;
					case 'resend':
						resendEvent(eventId);
						break;
					case 'delete':
						deleteEvent(eventId);
						break;
				}
			});
		function openEventModal(eventId, view) {
			debugLog('events tab: openEventModal', { event_id: eventId, view });
			var $modal = $('#alvobot-event-modal');
			var $body = $('#alvobot-modal-body');
			var $title = $('#alvobot-modal-title');
			$title.text(view === 'logs' ? 'Logs CAPI' : 'Detalhes do Evento');
			$body.html(
				'<div class="alvobot-events-loading"><span class="alvobot-skeleton" style="width:200px;"></span></div>',
			);
			$modal.css('display', 'flex');
			restGet('events/' + encodeURIComponent(eventId), function (data) {
				debugLog('events tab: openEventModal data loaded', { event_id: eventId, view, data });
				if (view === 'logs') {
					renderLogsView(data, $body);
				} else {
					renderDetailsView(data, $body);
				}
				if (window.lucide) {
					window.lucide.createIcons();
				}
			});
		}
		function renderDetailsView(e, $container) {
			var sections = [];
			sections.push(
				buildSection('Informacoes do Evento', [
					['Evento', e.event_name],
					['Event ID', safeSectionHtml('<code>' + escHtml(e.event_id) + '</code>')],
					[
						'Status',
						safeSectionHtml(
							getStatusBadge(e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids),
						),
					],
					['Event Time', e.event_time ? formatTimestamp(parseInt(e.event_time, 10)) : '-'],
					['Criado em', e.created_at || '-'],
					['Enviado em', e.sent_at ? formatTimestamp(parseInt(e.sent_at, 10)) : '-'],
				]),
			);
			sections.push(
				buildSection('Pagina', [
					['Titulo', e.page_title || '-'],
					[
						'URL',
						e.page_url
							? safeSectionHtml(
									'<a href="' +
										escAttr(e.page_url) +
										'" target="_blank">' +
										escHtml(e.page_url) +
										'</a>',
								)
							: '-',
					],
					['Post ID', e.page_id || '-'],
					['Referrer', e.referrer || '-'],
				]),
			);
			sections.push(
				buildSection('Dados do Usuario (enviados a Meta)', [
					['IP', e.ip ? safeSectionHtml('<code>' + escHtml(e.ip) + '</code>') : '-'],
					['Browser IP', e.browser_ip ? safeSectionHtml('<code>' + escHtml(e.browser_ip) + '</code>') : '-'],
					[
						'User Agent',
						e.user_agent
							? safeSectionHtml('<span class="alvobot-events-ua">' + escHtml(e.user_agent) + '</span>')
							: '-',
					],
					[
						'fbp',
						e.fbp
							? safeSectionHtml('<code>' + escHtml(e.fbp) + '</code>')
							: safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>'),
					],
					[
						'fbc',
						e.fbc
							? safeSectionHtml('<code>' + escHtml(e.fbc) + '</code>')
							: safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>'),
					],
					['Lead ID', e.lead_id || '-'],
					['WP Email (hash)', e.wp_em || '-'],
					['WP Nome (hash)', e.wp_fn || '-'],
					['WP Sobrenome (hash)', e.wp_ln || '-'],
					['WP External ID (hash)', e.wp_external_id || '-'],
				]),
			);
			sections.push(
				buildSection('Geolocalizacao', [
					['Cidade', e.geo_city || safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>')],
					['Estado', e.geo_state || safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>')],
					['Pais', e.geo_country || '-'],
					[
						'Codigo Pais',
						e.geo_country_code || safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>'),
					],
					['CEP', e.geo_zipcode || safeSectionHtml('<span class="alvobot-events-missing">Ausente</span>')],
					['Timezone', e.geo_timezone || '-'],
				]),
			);
			sections.push(
				buildSection('Parametros UTM', [
					['utm_source', e.utm_source || '-'],
					['utm_medium', e.utm_medium || '-'],
					['utm_campaign', e.utm_campaign || '-'],
					['utm_content', e.utm_content || '-'],
					['utm_term', e.utm_term || '-'],
				]),
			);
			var pixelIdStr = String(e.pixel_ids || '');
			var isGoogleAdsOnly = /^AW-/.test(pixelIdStr.trim()) && !/^\d{15,16}/.test(pixelIdStr.trim());
			if (isGoogleAdsOnly) {
				sections.push(
					buildSection('Entrega', [
						['Plataforma', safeSectionHtml(formatPixelList(e.pixel_ids))],
						[
							'Canal',
							safeSectionHtml(
								'<span class="alvobot-badge alvobot-badge-success">Navegador (gtag.js)</span>',
							),
						],
						[
							'Servidor (CAPI)',
							safeSectionHtml(
								'<span class="alvobot-events-missing">N/A \u2014 Google Ads usa envio pelo navegador</span>',
							),
						],
					]),
				);
			} else {
				sections.push(
					buildSection('Entrega CAPI', [
						['Pixels Alvo', safeSectionHtml(formatPixelList(e.pixel_ids))],
						['Pixels Entregues', safeSectionHtml(formatPixelList(e.fb_pixel_ids))],
						[
							'Canal de Envio',
							e.dispatch_channel === 'realtime'
								? 'Tempo real'
								: e.dispatch_channel === 'queue'
									? 'Fila'
									: '-',
						],
						['Tentativas', e.retry_count + '/3'],
						[
							'Erro',
							e.error
								? safeSectionHtml(
										'<span class="alvobot-events-error-text">' + escHtml(e.error) + '</span>',
									)
								: '-',
						],
					]),
				);
			}
			if (e.custom_data && typeof e.custom_data === 'object' && Object.keys(e.custom_data).length > 0) {
				sections.push(
					'<div class="alvobot-modal-section"><h4>Custom Data</h4><pre class="alvobot-events-json">' +
						escHtml(JSON.stringify(e.custom_data, null, 2)) +
						'</pre></div>',
				);
			}
			$container.html(sections.join(''));
		}
		function safeSectionHtml(html) {
			return {
				__alvobotSafeHtml: true,
				html: String(html || ''),
			};
		}
		function renderSectionValue(value) {
			if (value && typeof value === 'object' && value.__alvobotSafeHtml === true) {
				return value.html;
			}
			if (value === null || typeof value === 'undefined' || value === '') {
				return '-';
			}
			return escHtml(String(value));
		}
		function renderLogsView(e, $container) {
			var html = '';
			html += '<div class="alvobot-modal-section">';
			html += '<h4>Status da Entrega</h4>';
			html += '<div class="alvobot-events-detail-grid">';
			html +=
				'<div class="alvobot-events-detail-row"><span>Status</span><span>' +
				getStatusBadge(e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids) +
				'</span></div>';
			html +=
				'<div class="alvobot-events-detail-row"><span>Canal de Envio</span><span>' +
				escHtml(
					e.dispatch_channel === 'realtime' ? 'Tempo real' : e.dispatch_channel === 'queue' ? 'Fila' : '-',
				) +
				'</span></div>';
			html +=
				'<div class="alvobot-events-detail-row"><span>Tentativas</span><span>' +
				e.retry_count +
				'/3</span></div>';
			html +=
				'<div class="alvobot-events-detail-row"><span>Pixels Entregues</span><span>' +
				escHtml(e.fb_pixel_ids || 'Nenhum') +
				'</span></div>';
			if (e.error) {
				html +=
					'<div class="alvobot-events-detail-row"><span>Erro</span><span class="alvobot-events-error-text">' +
					escHtml(e.error) +
					'</span></div>';
			}
			html += '</div>';
			html += '</div>';
			if (e.request_payload && Array.isArray(e.request_payload) && e.request_payload.length > 0) {
				for (var i = 0; i < e.request_payload.length; i++) {
					var entry = e.request_payload[i];
					html += '<div class="alvobot-modal-section">';
					html +=
						'<h4>Tentativa #' +
						(i + 1) +
						(entry.success
							? ' <span class="alvobot-badge alvobot-badge-success">OK</span>'
							: ' <span class="alvobot-badge alvobot-badge-error">Falha</span>') +
						'</h4>';
					html += '<div class="alvobot-events-log-block">';
					html += '<div class="alvobot-events-log-label">Request (enviado a Meta)</div>';
					html +=
						'<pre class="alvobot-events-json">' +
						escHtml(JSON.stringify(entry.request, null, 2)) +
						'</pre>';
					html += '</div>';
					html += '<div class="alvobot-events-log-block">';
					html += '<div class="alvobot-events-log-label">Response (da Meta)</div>';
					html +=
						'<pre class="alvobot-events-json">' +
						escHtml(JSON.stringify(entry.response, null, 2)) +
						'</pre>';
					html += '</div>';
					html += '</div>';
				}
			} else {
				html += '<div class="alvobot-modal-section">';
				html += '<div class="alvobot-empty-state alvobot-empty-state-compact">';
				html += '<p>Nenhum log de envio disponivel.</p>';
				html += '<small>Logs sao gerados quando o evento e processado pelo CAPI.</small>';
				html += '</div>';
				html += '</div>';
			}
			if (
				e.response_payload &&
				typeof e.response_payload === 'object' &&
				Object.keys(e.response_payload).length > 0
			) {
				html += '<div class="alvobot-modal-section">';
				html += '<h4>Ultima Resposta (raw)</h4>';
				html +=
					'<pre class="alvobot-events-json">' +
					escHtml(JSON.stringify(e.response_payload, null, 2)) +
					'</pre>';
				html += '</div>';
			}
			$container.html(html);
		}
		function buildSection(title, rows) {
			var html = '<div class="alvobot-modal-section">';
			html += '<h4>' + escHtml(title) + '</h4>';
			html += '<div class="alvobot-events-detail-grid">';
			for (var i = 0; i < rows.length; i++) {
				html += '<div class="alvobot-events-detail-row">';
				html += '<span>' + escHtml(rows[i][0]) + '</span>';
				html += '<span>' + renderSectionValue(rows[i][1]) + '</span>';
				html += '</div>';
			}
			html += '</div>';
			html += '</div>';
			return html;
		}
		$('#alvobot-modal-close, .alvobot-modal-backdrop').on('click', function () {
			$('#alvobot-event-modal').hide();
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				$('#alvobot-event-modal').hide();
			}
		});
		function resendEvent(eventId) {
			debugLog('events tab: resend requested', { event_id: eventId });
			if (!confirm('Reenviar este evento para a Meta? O status sera resetado para Pendente.')) {
				debugWarn('events tab: resend canceled by user', { event_id: eventId });
				return;
			}
			restPost(
				'actions/resend-event/' + encodeURIComponent(eventId),
				{},
				function () {
					debugLog('events tab: resend success', { event_id: eventId });
					setSelected(String(eventId || ''), false);
					updateBulkActionsState();
					loadEvents();
					loadEventStats();
				},
				function (error) {
					debugError('events tab: resend failed', { event_id: eventId, error });
					alert('Erro ao reenviar: ' + error);
				},
			);
		}
		function deleteEvent(eventId) {
			debugLog('events tab: delete requested', { event_id: eventId });
			if (!confirm('Excluir este evento permanentemente?')) {
				debugWarn('events tab: delete canceled by user', { event_id: eventId });
				return;
			}
			restDelete(
				'events/' + encodeURIComponent(eventId),
				function () {
					debugLog('events tab: delete success', { event_id: eventId });
					setSelected(String(eventId || ''), false);
					$('tr[data-event-id="' + eventId + '"]').fadeOut(function () {
						$(this).remove();
						updateBulkActionsState();
					});
					loadEventStats();
				},
				function (error) {
					debugError('events tab: delete failed', { event_id: eventId, error });
					alert('Erro ao excluir: ' + error);
				},
			);
		}
		function truncate(str, max) {
			if (!str) {
				return '';
			}
			return str.length > max ? str.substring(0, max) + '...' : str;
		}
		function truncateUrl(url, max) {
			if (!url) {
				return '';
			}
			try {
				var u = new URL(url);
				var path = u.pathname + u.search;
				return path.length > max ? path.substring(0, max) + '...' : path;
			} catch (_e) {
				return truncate(url, max);
			}
		}
		function formatDate(dateStr) {
			if (!dateStr) {
				return '-';
			}
			try {
				var d = new Date(dateStr);
				return (
					d.toLocaleDateString('pt-BR') +
					' ' +
					d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
				);
			} catch (_e) {
				return dateStr;
			}
		}
		function formatTimestamp(ts) {
			if (!ts || ts <= 0) {
				return '-';
			}
			try {
				var d = new Date(ts * 1e3);
				return (
					d.toLocaleDateString('pt-BR') +
					' ' +
					d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
				);
			} catch (_e) {
				return String(ts);
			}
		}
		function formatNumber(num) {
			return Number(num).toLocaleString('pt-BR');
		}
	}
	function initStatusTab() {
		debugLog('status tab: init');
		loadStatusData();
		initQuickActions();
		function loadStatusData() {
			debugLog('status tab: loadStatusData request');
			restGet('events/stats', function (data) {
				debugLog('status tab: events/stats success', data);
				$('#stat-pending').text(formatNumber(data.pixel_pending || 0));
				$('#stat-sent').text(formatNumber(data.pixel_sent || 0));
				$('#stat-error').text(formatNumber(data.pixel_error || 0));
			});
			restGet('leads/stats', function (data) {
				debugLog('status tab: leads/stats success', data);
				$('#stat-leads').text(formatNumber(data.total || 0));
			});
		}
		function initQuickActions() {
			$(document)
				.off('click.alvobotStatusAction', '.alvobot-action-btn')
				.on('click.alvobotStatusAction', '.alvobot-action-btn', function () {
					var $btn = $(this);
					var action = $btn.data('action');
					debugLog('status tab: quick action clicked', { action });
					if (!action) {
						return;
					}
					var actionMap = {
						'send-pending': 'actions/send-pending',
						'test-pixel': 'actions/test-pixel',
						cleanup: 'actions/cleanup',
						'refresh-pixels': 'actions/refresh-pixels',
					};
					var endpoint = actionMap[action];
					if (!endpoint) {
						debugWarn('status tab: unknown quick action', { action });
						return;
					}
					var executeAction = function () {
						$btn.prop('disabled', true);
						showActionFeedback('Executando acao...', 'info');
						restPost(
							endpoint,
							{},
							function (data) {
								debugLog('status tab: quick action success', {
									action,
									endpoint,
									data,
								});
								$btn.prop('disabled', false);
								showActionFeedback(data.message || 'Acao executada com sucesso.', 'success');
								loadStatusData();
							},
							function (error) {
								debugError('status tab: quick action error', {
									action,
									endpoint,
									error,
								});
								$btn.prop('disabled', false);
								showActionFeedback(error || 'Erro ao executar acao.', 'error');
							},
						);
					};
					if (action === 'cleanup') {
						alvobotModal
							.confirm({
								title: 'Limpar dados antigos',
								message:
									'Remover eventos e leads antigos conforme a politica de retencao configurada? Esta acao altera dados armazenados no plugin.',
								confirmText: 'Limpar dados',
								cancelText: 'Cancelar',
								danger: true,
							})
							.then(function (ok) {
								if (ok) {
									executeAction();
								}
							});
						return;
					}
					executeAction();
				});
		}
		function showActionFeedback(message, type) {
			var $el = $('#alvobot-action-feedback');
			$el.removeClass('feedback-success feedback-error feedback-info')
				.addClass('feedback-' + type)
				.text(message)
				.stop(true, true)
				.show();
		}
		function formatNumber(num) {
			return Number(num).toLocaleString('pt-BR');
		}
	}
	function restGet(endpoint, onSuccess, onError) {
		debugLog('restGet request', { endpoint });
		$.ajax({
			url: extra.rest_url + endpoint,
			method: 'GET',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', extra.rest_nonce);
			},
			success: function (response) {
				debugLog('restGet success', { endpoint, response });
				if (response.data) {
					onSuccess(response.data, response.meta || null);
				} else {
					onSuccess(response, null);
				}
			},
			error: function (xhr) {
				var msg = getRestErrorMessage(xhr);
				debugError('restGet error', {
					endpoint,
					status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
					message: msg,
					response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring(0, 3e3) : null,
				});
				if (onError) {
					onError(msg);
				}
			},
		});
	}
	function restPost(endpoint, data, onSuccess, onError) {
		debugLog('restPost request', { endpoint, payload: data });
		$.ajax({
			url: extra.rest_url + endpoint,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(data),
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', extra.rest_nonce);
			},
			success: function (response) {
				debugLog('restPost success', { endpoint, response });
				if (response.data) {
					onSuccess(response.data);
				} else {
					onSuccess(response);
				}
			},
			error: function (xhr) {
				var msg = getRestErrorMessage(xhr);
				debugError('restPost error', {
					endpoint,
					status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
					message: msg,
					response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring(0, 3e3) : null,
				});
				if (onError) {
					onError(msg);
				}
			},
		});
	}
	function restDelete(endpoint, onSuccess, onError) {
		debugLog('restDelete request', { endpoint });
		$.ajax({
			url: extra.rest_url + endpoint,
			method: 'DELETE',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', extra.rest_nonce);
			},
			success: function (response) {
				debugLog('restDelete success', { endpoint, response });
				if (onSuccess) {
					onSuccess(response.data || response);
				}
			},
			error: function (xhr) {
				var msg = getRestErrorMessage(xhr);
				debugError('restDelete error', {
					endpoint,
					status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
					message: msg,
					response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring(0, 3e3) : null,
				});
				if (onError) {
					onError(msg);
				}
			},
		});
	}
	function getRestErrorMessage(xhr) {
		var msg = 'Erro de conexao.';
		try {
			var body = JSON.parse(xhr.responseText);
			msg = body.message || body.error || msg;
			if (body.code === 'rest_cookie_invalid_nonce') {
				msg = 'Sessao expirada (nonce invalido). Atualize a pagina do admin.';
			}
			debugWarn('getRestErrorMessage(): parsed body', body);
		} catch (e) {
			debugWarn('getRestErrorMessage(): failed to parse response body', {
				error: (e == null ? void 0 : e.message) ? e.message : e,
				response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring(0, 1500) : null,
			});
		}
		return msg;
	}
	function escHtml(str) {
		if (!str) {
			return '';
		}
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	function escAttr(str) {
		if (!str) {
			return '';
		}
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/'/g, '&#39;')
			.replace(/"/g, '&quot;');
	}
	var alvobotModal = /* @__PURE__ */ (function () {
		var lastTrigger = null;
		function trapFocus($modal, e) {
			if (e.key !== 'Tab') {
				return;
			}
			var focusables = $modal
				.find('button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])')
				.filter(':visible:not([disabled])');
			if (!focusables.length) {
				return;
			}
			var first = focusables[0],
				last = focusables[focusables.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
		function close($overlay) {
			$overlay.remove();
			$(document).off('keydown.alvobotModal');
			if (lastTrigger) {
				try {
					lastTrigger.focus();
				} catch (_e) {}
			}
			lastTrigger = null;
		}
		function open(opts) {
			return new $.Deferred(function (deferred) {
				lastTrigger = document.activeElement;
				var titleId = 'alvobot-modal-title-' + Date.now();
				var msgId = 'alvobot-modal-msg-' + Date.now();
				var html = '<div class="alvobot-modal-overlay" role="presentation">';
				html +=
					'<div class="alvobot-modal-dialog" role="' +
					(opts.role || 'alertdialog') +
					'" aria-modal="true" aria-labelledby="' +
					titleId +
					'" aria-describedby="' +
					msgId +
					'">';
				html += '<h3 id="' + titleId + '" class="alvobot-modal-title">' + escHtml(opts.title || '') + '</h3>';
				var msgHtml;
				if (opts.html) {
					msgHtml = String(opts.message || '');
				} else {
					msgHtml = String(opts.message || '')
						.split('\n')
						.map(function (line) {
							if (line === '') {
								return '<br>';
							}
							return '<p class="alvobot-modal-message-line">' + escHtml(line) + '</p>';
						})
						.join('');
				}
				html += '<div id="' + msgId + '" class="alvobot-modal-message">' + msgHtml + '</div>';
				html += '<div class="alvobot-modal-actions">';
				if (opts.kind === 'confirm') {
					html +=
						'<button type="button" class="alvobot-btn alvobot-btn-outline alvobot-modal-cancel">' +
						escHtml(opts.cancelText || 'Cancelar') +
						'</button>';
					html +=
						'<button type="button" class="alvobot-btn ' +
						(opts.danger ? 'alvobot-btn-danger' : 'alvobot-btn-primary') +
						' alvobot-modal-confirm">' +
						escHtml(opts.confirmText || 'OK') +
						'</button>';
				} else {
					html +=
						'<button type="button" class="alvobot-btn alvobot-btn-primary alvobot-modal-ok">' +
						escHtml(opts.okText || 'OK') +
						'</button>';
				}
				html += '</div></div></div>';
				var $overlay = $(html);
				$('body').append($overlay);
				var $primary = opts.danger
					? $overlay.find('.alvobot-modal-cancel')
					: $overlay.find('.alvobot-modal-confirm, .alvobot-modal-ok').first();
				if ($primary.length) {
					$primary.focus();
				}
				$overlay.on('click', '.alvobot-modal-confirm', function () {
					close($overlay);
					deferred.resolve(true);
				});
				$overlay.on('click', '.alvobot-modal-cancel', function () {
					close($overlay);
					deferred.resolve(false);
				});
				$overlay.on('click', '.alvobot-modal-ok', function () {
					close($overlay);
					deferred.resolve();
				});
				$overlay.on('click', '.alvobot-modal-overlay', function (e) {
					if (e.target === e.currentTarget) {
						close($overlay);
						deferred.resolve(opts.kind === 'confirm' ? false : void 0);
					}
				});
				$(document).on('keydown.alvobotModal', function (e) {
					if (e.key === 'Escape') {
						close($overlay);
						deferred.resolve(opts.kind === 'confirm' ? false : void 0);
					}
					trapFocus($overlay.find('.alvobot-modal-dialog'), e);
				});
			}).promise();
		}
		return {
			confirm: function (opts) {
				return open($.extend({ kind: 'confirm' }, opts));
			},
			alert: function (opts) {
				return open($.extend({ kind: 'alert' }, opts));
			},
		};
	})();
})(jQuery);
