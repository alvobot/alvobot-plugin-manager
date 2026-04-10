/**
 * Pixel Tracking Module - Admin JS
 *
 * Tab-aware dynamic behavior for settings page and conversions CRUD.
 */
(function ($) {
	'use strict';

	var config    = window.alvobot_pixel_tracking || {};
	var extra     = window.alvobot_pixel_tracking_extra || {};
	var activeTab = extra.active_tab || 'pixels';
	var debugEnabled = !! (extra.debug_enabled || config.debug_enabled);
	var debugPrefix  = '[AlvoBot Pixel][ADMIN]';

	function cloneForDebug(value) {
		try {
			return JSON.parse( JSON.stringify( value ) );
		} catch (e) {
			return value;
		}
	}

	function debugLog(message, payload) {
		if ( ! debugEnabled || ! window.console || ! window.console.log) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.log( debugPrefix + ' ' + message );
			return;
		}
		window.console.log( debugPrefix + ' ' + message, cloneForDebug( payload ) );
	}

	function debugWarn(message, payload) {
		if ( ! debugEnabled || ! window.console || ! window.console.warn) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.warn( debugPrefix + ' ' + message );
			return;
		}
		window.console.warn( debugPrefix + ' ' + message, cloneForDebug( payload ) );
	}

	function debugError(message, payload) {
		if ( ! debugEnabled || ! window.console || ! window.console.error) {
			return;
		}
		if (typeof payload === 'undefined') {
			window.console.error( debugPrefix + ' ' + message );
			return;
		}
		window.console.error( debugPrefix + ' ' + message, cloneForDebug( payload ) );
	}

	debugLog(
		'admin bootstrap',
		{
			active_tab: activeTab,
			config: config,
			extra: extra,
		}
	);

	// Logs all AJAX traffic on this module page when debug is enabled.
	if (debugEnabled) {
		$( document ).on(
			'ajaxSend.alvobotPixelDebug',
			function (_event, _xhr, settings) {
				var url = settings && settings.url ? String( settings.url ) : '';
				if (url.indexOf( 'admin-ajax.php' ) === -1 && url.indexOf( '/pixel-tracking/' ) === -1) {
					return;
				}
				debugLog(
					'ajaxSend',
					{
						url: url,
						method: settings.type || settings.method || 'GET',
						data: settings.data || null,
					}
				);
			}
		);

		$( document ).on(
			'ajaxComplete.alvobotPixelDebug',
			function (_event, xhr, settings) {
				var url = settings && settings.url ? String( settings.url ) : '';
				if (url.indexOf( 'admin-ajax.php' ) === -1 && url.indexOf( '/pixel-tracking/' ) === -1) {
					return;
				}
				debugLog(
					'ajaxComplete',
					{
						url: url,
						method: settings.type || settings.method || 'GET',
						status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
						response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring( 0, 3000 ) : null,
					}
				);
			}
		);
	}

	// Prevent double-submit on all module forms (race condition guard).
	$( '.alvobot-module-form' ).on(
		'submit',
		function () {
			var $btn = $( this ).find( 'button[type="submit"]' );
			if ($btn.prop( 'disabled' )) {
				return false;
			}
			$btn.prop( 'disabled', true ).css( 'opacity', '0.6' );
		}
	);

	// ========================
	// Pixels Tab
	// ========================
	if (activeTab === 'pixels') {
		debugLog( 'init tab: pixels' );
		initPixelsTab();
	}

	// ========================
	// Conversions Tab
	// ========================
	if (activeTab === 'conversions') {
		debugLog( 'init tab: conversions' );
		initConversionsTab();
	}

	// ========================
	// Configuracoes Tab
	// ========================
	if (activeTab === 'settings') {
		debugLog( 'init tab: settings' );
		initConfiguracoesTab();
	}

	// ========================
	// Events Tab
	// ========================
	if (activeTab === 'events') {
		debugLog( 'init tab: events' );
		initEventsTab();
	}

	// ========================
	// Status Tab
	// ========================
	if (activeTab === 'status') {
		debugLog( 'init tab: status' );
		initStatusTab();
	}

	// ================================================================
	// PIXELS TAB
	// ================================================================
	function initPixelsTab() {
		// ── Platform icon SVGs (inline for reliability) ──
		var PLATFORM_ICONS = {
			meta_pixel: '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><path fill="#1877F2" d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>',
			ga4: '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><path fill="#E37400" d="M22.84 2.02v7.97a2.14 2.14 0 0 1-4.28 0V4.3H12.9a2.14 2.14 0 0 1 0-4.28h7.8c1.18 0 2.14.9 2.14 2Z"/><path fill="#F9AB00" d="M5.44 22c-1.9 0-3.44-1.5-3.44-3.36s1.54-3.36 3.44-3.36 3.44 1.5 3.44 3.36S7.34 22 5.44 22Z"/><path fill="#E37400" d="M12 22a2.14 2.14 0 0 1-2.14-2.14V9.73a2.14 2.14 0 0 1 4.28 0v10.13c0 1.18-.96 2.14-2.14 2.14Z"/></svg>',
			google_ads: '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:6px;"><circle cx="6" cy="18" r="4" fill="#FBBC04"/><path fill="#4285F4" d="M21.2 7.2 12.6 22H7.4L16 7.2h5.2Z"/><path fill="#34A853" d="M16 7.2 7.4 22H2.8l8.6-14.8H16Z"/></svg>',
		};

		var PLATFORM_LABELS = {
			meta_pixel: 'Meta Pixel',
			ga4: 'Google Analytics',
			google_ads: 'Google Ads',
			external: 'Google (Tag existente)',
		};

		var PLATFORM_BADGES = {
			meta_pixel: '<span class="alvobot-badge alvobot-badge-info" style="gap:4px;">' + PLATFORM_ICONS.meta_pixel + 'Meta Pixel</span>',
			ga4: '<span class="alvobot-badge alvobot-badge-warning" style="gap:4px;">' + PLATFORM_ICONS.ga4 + 'GA4</span>',
			google_ads: '<span class="alvobot-badge alvobot-badge-success" style="gap:4px;">' + PLATFORM_ICONS.google_ads + 'Google Ads</span>',
			external: '<span class="alvobot-badge alvobot-badge-neutral" style="gap:4px;">' + PLATFORM_ICONS.ga4 + 'Tag existente</span>',
		};

		var pixelsData = [];

		// Parse initial pixels from hidden field
		try {
			pixelsData = JSON.parse( $( '#pixels_json' ).val() || '[]' );
			debugLog( 'pixels tab: parsed pixels_json', { count: pixelsData.length, pixels: pixelsData } );
		} catch (e) {
			pixelsData = [];
			debugError( 'pixels tab: failed to parse pixels_json', e && e.message ? e.message : e );
		}

		// Mode toggle: show/hide AlvoBot vs Manual sections
		$( 'input[name="mode"]' ).on(
			'change',
				function () {
					var mode = $( this ).val();
					debugLog( 'pixels tab: mode changed', { mode: mode } );
					$( '.alvobot-pixel-mode-section' ).hide();
				$( '#alvobot-pixel-mode-' + mode ).show();
			}
		);

		// AlvoBot Mode: Fetch pixels button
		$( '#alvobot-fetch-pixels-btn' ).on(
			'click',
			function () {
				var $btn     = $( this );
				var $loading = $( '#alvobot-pixel-list-loading' );
				var $error   = $( '#alvobot-pixel-list-error' );
				var $list    = $( '#alvobot-pixel-list' );

				$btn.prop( 'disabled', true );
				$loading.show();
				$error.hide();
				$list.hide();

				$.ajax(
					{
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_fetch_pixels',
							nonce: config.nonce,
						},
							success: function (response) {
								debugLog( 'pixels tab: fetch pixels response', response );
								$loading.hide();
							$btn.prop( 'disabled', false );

							if (response.success && response.data.pixels) {
								renderAlvobotPixelList( response.data.pixels );
								$list.show();
							} else {
								$error.text( response.data || 'Erro ao buscar pixels.' ).show();
							}
						},
							error: function () {
								debugError( 'pixels tab: fetch pixels ajax error' );
								$loading.hide();
							$btn.prop( 'disabled', false );
							$error.text( 'Erro de conexao. Tente novamente.' ).show();
						},
					}
				);
			}
		);

		// Render AlvoBot pixel list with select/update buttons
			function renderAlvobotPixelList(pixels) {
				debugLog( 'pixels tab: render AlvoBot list', { count: pixels ? pixels.length : 0, pixels: pixels } );
				var $list = $( '#alvobot-pixel-list' );
			$list.empty();

			if ( ! pixels.length) {
				$list.html(
					'<p class="alvobot-description">Nenhum pixel encontrado. Conecte um pixel no AlvoBot App.</p>'
				);
				return;
			}

			var html =
			'<table class="alvobot-table" style="width:100%"><thead><tr><th>Pixel</th><th>Conexao</th><th></th></tr></thead><tbody>';
			for (var i = 0; i < pixels.length; i++) {
				var p       = pixels[i];
				var existing = null;
				for (var j = 0; j < pixelsData.length; j++) {
					if (pixelsData[j].pixel_id === p.pixel_id) {
						existing = pixelsData[j];
						break;
					}
				}
				html += '<tr>';
				html +=
				'<td><code>' +
				escHtml( p.pixel_id ) +
				'</code> ' +
				escHtml( p.pixel_name || '' ) +
				'</td>';
				html += '<td>' + escHtml( p.connection_name || '' ) + '</td>';
				html += '<td>';
				if (existing) {
					// Already added — show update button if token changed or expired.
					var tokenChanged = existing.token_expired || (p.access_token && p.access_token !== existing.api_token);
					if (tokenChanged) {
						html +=
						'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-update-alvobot-pixel" data-pixel=\'' +
						escAttr( JSON.stringify( p ) ) +
						"'>Atualizar Token</button>";
					} else {
						html +=
						'<span class="alvobot-badge alvobot-badge-success">Adicionado</span>';
					}
				} else {
					html +=
					'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-select-alvobot-pixel" data-pixel=\'' +
					escAttr( JSON.stringify( p ) ) +
					"'>Selecionar</button>";
				}
				html += '</td></tr>';
			}
			html += '</tbody></table>';
			$list.html( html );

			if (window.lucide) {
				window.lucide.createIcons();
			}
		}

		// Update AlvoBot pixel token (expired or changed)
		$( document ).on(
			'click',
			'.alvobot-update-alvobot-pixel',
				function () {
					var pixel = JSON.parse( $( this ).attr( 'data-pixel' ) );
					debugLog( 'pixels tab: update token clicked', pixel );
				for (var i = 0; i < pixelsData.length; i++) {
					if (pixelsData[i].pixel_id === pixel.pixel_id) {
						pixelsData[i].api_token      = pixel.access_token || '';
						pixelsData[i].token_expired   = false;
						pixelsData[i].label           = pixel.pixel_name || pixelsData[i].label;
						pixelsData[i].connection_id   = pixel.connection_id || pixelsData[i].connection_id;
						break;
					}
				}
				updatePixelsHiddenField();
				renderConfiguredPixels();
				// Re-render AlvoBot list to reflect update
				$( '#alvobot-fetch-pixels-btn' ).trigger( 'click' );
			}
		);

		// Select AlvoBot pixel → add to configured list
		$( document ).on(
			'click',
			'.alvobot-select-alvobot-pixel',
				function () {
					var pixel = JSON.parse( $( this ).attr( 'data-pixel' ) );
					debugLog( 'pixels tab: select pixel clicked', pixel );
				pixelsData.push(
					{
						pixel_id: pixel.pixel_id,
						api_token: pixel.access_token || '',
						source: 'alvobot',
						connection_id: pixel.connection_id || '',
						label: pixel.pixel_name || '',
					}
				);
				updatePixelsHiddenField();
				renderConfiguredPixels();
				// Re-render AlvoBot list to show "Adicionado"
				$( '#alvobot-fetch-pixels-btn' ).trigger( 'click' );
			}
		);

		// Manual Mode: Add pixel button
		$( '#alvobot-add-manual-pixel-btn' ).on(
			'click',
				function () {
					var pixelId  = $( '#manual_pixel_id' ).val().trim();
					var apiToken = $( '#manual_api_token' ).val().trim();
					var label    = $( '#manual_pixel_label' ).val().trim();
					debugLog(
						'pixels tab: add manual pixel clicked',
						{
							pixel_id: pixelId,
							has_api_token: !! apiToken,
							label: label,
						}
					);

				if ( ! /^\d{15,16}$/.test( pixelId )) {
					alert( 'Pixel ID deve conter 15-16 digitos numericos.' );
					return;
				}

				// Check for duplicate
				var exists = pixelsData.some(
					function (p) {
						return p.pixel_id === pixelId;
					}
				);
				if (exists) {
					alert( 'Este Pixel ID ja esta configurado.' );
					return;
				}

				pixelsData.push(
					{
						pixel_id: pixelId,
						api_token: apiToken,
						source: 'manual',
						connection_id: '',
						label: label,
					}
				);

				updatePixelsHiddenField();
				renderConfiguredPixels();

				// Clear inputs
				$( '#manual_pixel_id' ).val( '' );
				$( '#manual_api_token' ).val( '' );
				$( '#manual_pixel_label' ).val( '' );
			}
		);

		// Remove pixel button
		$( document ).on(
			'click',
			'.alvobot-remove-pixel-btn',
			function () {
				var idx = parseInt( $( this ).data( 'index' ), 10 );
				if ( ! isNaN( idx ) && idx >= 0 && idx < pixelsData.length) {
					pixelsData.splice( idx, 1 );
					updatePixelsHiddenField();
					renderConfiguredPixels();
				}
			}
		);

		// Re-render the configured pixels table
		function renderConfiguredPixels() {
			var $container = $( '#alvobot-configured-pixels' );

			if ( ! pixelsData.length) {
				$container.html(
					'<div class="alvobot-empty-state alvobot-empty-state-compact"><p>Nenhum pixel configurado.</p></div>'
				);
				return;
			}

			var html =
			'<table class="alvobot-table" style="width:100%"><thead><tr><th>Pixel ID</th><th>Nome</th><th>Origem</th><th>Status</th><th></th></tr></thead><tbody>';
			for (var i = 0; i < pixelsData.length; i++) {
				var p = pixelsData[i];
				html += '<tr>';
				html += '<td><code>' + escHtml( p.pixel_id ) + '</code></td>';
				html += '<td>' + escHtml( p.label || '-' ) + '</td>';
				html += '<td>';
				if (p.source === 'alvobot') {
					html += '<span class="alvobot-badge alvobot-badge-info">AlvoBot</span>';
				} else {
					html += '<span class="alvobot-badge alvobot-badge-neutral">Manual</span>';
				}
				html += '</td>';
				html += '<td>';
				if (p.token_expired) {
						html +=
						'<span class="alvobot-badge alvobot-badge-error">Token Expirado</span>';
				} else if (p.api_token) {
					html +=
					'<span class="alvobot-badge alvobot-badge-success">CAPI Ativo</span>';
				} else {
					html +=
					'<span class="alvobot-badge alvobot-badge-warning">Sem Token</span>';
				}
				html += '</td>';
				html += '<td>';
				// Renew token button for AlvoBot-sourced expired pixels.
				if (p.token_expired && p.source === 'alvobot') {
					html +=
					'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-refresh-pixel-btn" data-pixel-id="' +
					escAttr( p.pixel_id ) + '" data-index="' + i +
					'" style="margin-right:4px;"><i data-lucide="refresh-cw" class="alvobot-icon"></i> Renovar</button>';
				}
				html +=
				'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-pixel-btn" data-index="' +
				i +
				'"><i data-lucide="trash-2" class="alvobot-icon"></i></button></td>';
				html += '</tr>';
			}
			html += '</tbody></table>';
			$container.html( html );

			// Reinitialize Lucide icons
			if (window.lucide) {
				window.lucide.createIcons();
			}
		}

		// Refresh expired pixel token via AJAX
		$( document ).on(
			'click',
			'.alvobot-refresh-pixel-btn',
			function () {
				var $btn    = $( this );
				var pixelId = $btn.data( 'pixel-id' );
				var idx     = parseInt( $btn.data( 'index' ), 10 );

				$btn.prop( 'disabled', true ).text( 'Renovando...' );

				$.ajax(
					{
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_refresh_token',
							nonce: config.nonce,
							pixel_id: pixelId,
						},
						success: function (response) {
							if (response.success && response.data) {
								// Update local pixelsData with fresh token.
								if ( ! isNaN( idx ) && idx >= 0 && idx < pixelsData.length) {
									pixelsData[idx].api_token     = response.data.api_token || '';
									pixelsData[idx].token_expired = false;
									if (response.data.label) {
										pixelsData[idx].label = response.data.label;
									}
								}
								updatePixelsHiddenField();
								renderConfiguredPixels();
							} else {
								alert( response.data || 'Erro ao renovar token.' );
								$btn.prop( 'disabled', false ).text( 'Renovar' );
							}
						},
						error: function () {
							alert( 'Erro de conexao ao renovar token.' );
							$btn.prop( 'disabled', false ).text( 'Renovar' );
						},
					}
				);
			}
		);

		// Sync pixels data to hidden field for form submission
		function updatePixelsHiddenField() {
			$( '#pixels_json' ).val( JSON.stringify( pixelsData ) );
		}

		// ── Google Trackers Data ──────────────────────────────────────
		var googleTrackersData = [];
		try {
			googleTrackersData = JSON.parse( $( '#google_trackers_json' ).val() || '[]' );
		} catch (e) {
			googleTrackersData = [];
		}

		// ── Unified Add Pixel/Tracker Flow ──────────────────────────
		var currentPlatform = '';

		// Step 1: Platform change — show/hide relevant fields
		$( '#add_pixel_platform' ).on( 'change', function () {
			currentPlatform = $( this ).val();
			var $source      = $( '#add-pixel-source' );
			var $alvobotFetch = $( '#add-pixel-alvobot-fetch' );
			var $manualFields = $( '#add-pixel-manual-fields' );
			var $addBtn       = $( '#alvobot-add-pixel-btn' );

			// Hide all dynamic sections first
			$source.hide();
			$alvobotFetch.hide();
			$manualFields.hide();
			$( '#add-pixel-manual-fields .alvobot-form-field' ).hide();
			$addBtn.hide();

			if ( ! currentPlatform) { return; }

			if (currentPlatform === 'google_sitekit') {
				// No fields needed — add virtual external tracker directly
				var alreadyHasExternal = googleTrackersData.some( function (gt) { return gt.type === 'external'; } );
				if (alreadyHasExternal) {
					alert( 'Google Tag existente ja esta configurado.' );
					$( '#add_pixel_platform' ).val( '' );
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
				$( '#add_pixel_platform' ).val( '' );
				return;
			}

			// All other platforms support AlvoBot + Manual source
			if (currentPlatform) {
				$source.show();
				$( 'input[name="add_pixel_source"][value="alvobot"]' ).prop( 'checked', true ).trigger( 'change' );
			}
		});

		// Step 2: Source change — AlvoBot vs Manual (all platforms)
		$( document ).on( 'change', 'input[name="add_pixel_source"]', function () {
			var source = $( this ).val();
			var $alvobotFetch       = $( '#add-pixel-alvobot-fetch' );
			var $googleAlvobotFetch = $( '#add-google-alvobot-fetch' );
			var $manualFields       = $( '#add-pixel-manual-fields' );
			var $addBtn             = $( '#alvobot-add-pixel-btn' );

			$alvobotFetch.hide();
			$googleAlvobotFetch.hide();
			$manualFields.hide();
			$( '#add-pixel-manual-fields .alvobot-form-field' ).hide();
			$addBtn.hide();

			if (source === 'alvobot') {
				if (currentPlatform === 'meta_pixel') {
					$alvobotFetch.show();
				} else {
					$googleAlvobotFetch.show();
				}
				} else {
					$manualFields.show();
					if (currentPlatform === 'meta_pixel') {
						$( '#add-field-meta-id, #add-field-meta-token, #add-field-label' ).show();
					} else if (currentPlatform === 'ga4') {
						$( '#add-field-ga4-id, #add-field-label' ).show();
					} else if (currentPlatform === 'google_ads') {
						$( '#add-field-gads-id, #add-field-gads-label, #add-field-label' ).show();
					}
					$addBtn.show();
				}
		});

		// Step 3: Add button — validate and add pixel/tracker
		$( '#alvobot-add-pixel-btn' ).on( 'click', function () {
			var label = $( '#manual_pixel_label' ).val().trim();

			if (currentPlatform === 'meta_pixel') {
				var pixelId = $( '#manual_pixel_id' ).val().trim();
				var token   = $( '#manual_api_token' ).val().trim();
				if ( ! /^\d{15,16}$/.test( pixelId )) {
					alert( 'Pixel ID deve ter 15-16 digitos numericos.' );
					return;
				}
				// Duplicate check
				for (var i = 0; i < pixelsData.length; i++) {
					if (pixelsData[i].pixel_id === pixelId) {
						alert( 'Este Pixel ID ja esta configurado.' );
						return;
					}
				}
				pixelsData.push( { pixel_id: pixelId, api_token: token, source: 'manual', connection_id: '', label: label, token_expired: false } );
				updatePixelsHiddenField();
			} else if (currentPlatform === 'ga4') {
				var gaId = $( '#manual_ga4_id' ).val().trim();
				if ( ! /^G-[A-Z0-9]{7,12}$/.test( gaId )) {
					alert( 'Formato invalido. Use G-XXXXXXXXXX (letras maiusculas e numeros).' );
					return;
				}
				for (var j = 0; j < googleTrackersData.length; j++) {
					if (googleTrackersData[j].tracker_id === gaId) {
						alert( 'Este Tracker ID ja esta configurado.' );
						return;
					}
				}
				googleTrackersData.push( { tracker_id: gaId, type: 'ga4', label: label, conversion_label: '' } );
				updateGoogleTrackersHiddenField();
				} else if (currentPlatform === 'google_ads') {
					var adsId = $( '#manual_gads_id' ).val().trim();
					var adsLabel = $( '#manual_gads_conv_label' ).val().trim();
					if ( ! /^AW-\d{7,12}$/.test( adsId )) {
						alert( 'Formato invalido. Use AW-XXXXXXXXX (numeros).' );
						return;
				}
				for (var k = 0; k < googleTrackersData.length; k++) {
						if (googleTrackersData[k].tracker_id === adsId) {
							alert( 'Este Tracker ID ja esta configurado.' );
							return;
						}
					}
					googleTrackersData.push( { tracker_id: adsId, type: 'google_ads', label: label, conversion_label: adsLabel } );
					updateGoogleTrackersHiddenField();
				}

				// Reset form
				$( '#manual_pixel_id, #manual_api_token, #manual_ga4_id, #manual_gads_id, #manual_gads_conv_label, #manual_pixel_label' ).val( '' );
				$( '#add_pixel_platform' ).val( '' ).trigger( 'change' );
				renderUnifiedTable();
			});

		// ── Remove handlers ──────────────────────────────────────────
		$( document ).on( 'click', '.alvobot-remove-meta-pixel', function () {
			var idx = parseInt( $( this ).attr( 'data-index' ), 10 );
			if ( ! isNaN( idx ) && idx >= 0 && idx < pixelsData.length) {
				pixelsData.splice( idx, 1 );
				updatePixelsHiddenField();
				renderUnifiedTable();
			}
		});

		$( document ).on( 'click', '.alvobot-remove-google-tracker', function () {
			var idx = parseInt( $( this ).attr( 'data-index' ), 10 );
			if ( ! isNaN( idx ) && idx >= 0 && idx < googleTrackersData.length) {
				googleTrackersData.splice( idx, 1 );
				updateGoogleTrackersHiddenField();
				renderUnifiedTable();
			}
		});

		// ── AlvoBot Google Trackers Fetch ─────────────────────────────
		$( '#alvobot-fetch-google-btn' ).on( 'click', function () {
			var $btn     = $( this );
			var $loading = $( '#alvobot-google-list-loading' );
			var $error   = $( '#alvobot-google-list-error' );
			var $list    = $( '#alvobot-google-list' );

			$btn.prop( 'disabled', true );
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
					$btn.prop( 'disabled', false );

					if (response.success && response.data.trackers) {
						var trackers = response.data.trackers;
						// Filter by current platform selection
						var filtered = trackers.filter( function (t) {
							if (currentPlatform === 'ga4') return t.type === 'ga4';
							if (currentPlatform === 'google_ads') return t.type === 'google_ads';
							return true;
						});

						if ( ! filtered.length) {
							$error.text( 'Nenhum tracker ' + (currentPlatform === 'ga4' ? 'GA4' : 'Google Ads') + ' encontrado no AlvoBot.' ).show();
							return;
						}

						var html = '';
						for (var i = 0; i < filtered.length; i++) {
							var t = filtered[i];
							var already = googleTrackersData.some( function (gt) { return gt.tracker_id === t.tracker_id; } );
							var badge = t.type === 'ga4'
								? '<span class="alvobot-badge alvobot-badge-warning">GA4</span>'
								: '<span class="alvobot-badge alvobot-badge-success">Google Ads</span>';
							html += '<div style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:4px;">';
							html += badge + ' <code>' + escHtml( t.tracker_id ) + '</code>';
							if (t.label) html += ' — ' + escHtml( t.label );
							if (already) {
								html += ' <span class="alvobot-badge alvobot-badge-neutral" style="margin-left:auto;">Ja adicionado</span>';
							} else {
								html += ' <button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary alvobot-select-google-tracker" style="margin-left:auto;" ' +
									'data-tracker=\'' + escAttr( JSON.stringify( t ) ) + '\'>Selecionar</button>';
							}
							html += '</div>';
						}
						$list.html( html ).show();
					} else {
						$error.text( response.data || 'Erro ao buscar trackers.' ).show();
					}
				},
				error: function () {
					$loading.hide();
					$btn.prop( 'disabled', false );
					$error.text( 'Erro de conexao.' ).show();
				},
			});
		});

		// Handle selecting a Google tracker from AlvoBot list (GA4 or Google Ads — both add directly)
		$( document ).on( 'click', '.alvobot-select-google-tracker', function () {
			var t = JSON.parse( $( this ).attr( 'data-tracker' ) );
			var already = googleTrackersData.some( function (gt) { return gt.tracker_id === t.tracker_id; } );
			if (already) { return; }

			googleTrackersData.push({
				tracker_id: t.tracker_id,
				type: t.type,
				label: t.label || '',
				conversion_label: '',
				connection_id: t.connection_id || '',
			});
			updateGoogleTrackersHiddenField();
			renderUnifiedTable();
			$( this ).replaceWith( '<span class="alvobot-badge alvobot-badge-neutral" style="margin-left:auto;">Ja adicionado</span>' );

			// Suggest creating default conversions for Google Ads trackers with AlvoBot connection
			if (t.type === 'google_ads' && t.connection_id) {
				showConversionSuggestionBanner( t );
			}
		});

		// ── Conversion Suggestion Banner (shown after adding a Google Ads tracker) ──
		function showConversionSuggestionBanner( tracker ) {
			// Remove any existing banner
			$( '#alvobot-conv-suggestion-banner' ).remove();

			var allSuggestions = [
				{ name: 'Ad Impression', desc: 'Viu anuncio' },
				{ name: 'Ad Click', desc: 'Clicou anuncio' },
				{ name: 'Vignette View', desc: 'Viu vinheta' },
				{ name: 'Vignette Click', desc: 'Clicou vinheta' },
			];

			// Check which conversion rules already exist in the plugin
			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: { action: 'alvobot_pixel_tracking_get_conversions', nonce: config.nonce },
				success: function (response) {
					var existingRules = (response.success && response.data && response.data.conversions) ? response.data.conversions : [];
					var normalizeConvName = function (n) { return n.toLowerCase().replace(/[\s_-]+/g, ''); };
					var existingRuleNames = existingRules.map( function (r) { return normalizeConvName( r.name ); } );

					// Check which are missing
					var missing = [];
					var configured = [];
					for (var i = 0; i < allSuggestions.length; i++) {
						if (existingRuleNames.indexOf( normalizeConvName( allSuggestions[i].name ) ) !== -1) {
							configured.push( allSuggestions[i] );
						} else {
							missing.push( allSuggestions[i] );
						}
					}

					// If all are configured, don't show the banner
					if ( ! missing.length) { return; }

					var html = '<div id="alvobot-conv-suggestion-banner" style="margin-top:16px;padding:12px 16px;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb;">';
					html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">';
					html += '<i data-lucide="sparkles" class="alvobot-icon" style="width:18px;height:18px;color:#f59e0b;"></i>';
					html += '<strong>' + missing.length + ' conversao(oes) faltando para ' + escHtml( tracker.label || tracker.tracker_id ) + '</strong>';
					html += '<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary" id="alvobot-create-all-suggested" data-tracker=\'' + escAttr( JSON.stringify( tracker ) ) + '\' style="margin-left:auto;">';
					html += '<i data-lucide="zap" class="alvobot-icon"></i> Criar faltantes</button>';
					html += '<button type="button" style="background:none;border:none;cursor:pointer;padding:4px;margin-left:8px;" onclick="this.closest(\'#alvobot-conv-suggestion-banner\').remove();">';
					html += '<i data-lucide="x" class="alvobot-icon" style="width:16px;height:16px;color:#666;"></i></button>';
					html += '</div>';
					html += '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
					for (var j = 0; j < allSuggestions.length; j++) {
						var sg = allSuggestions[j];
						var isMissing = missing.some( function (m) { return m.name === sg.name; } );
						if (isMissing) {
							html += '<span style="font-size:12px;padding:3px 8px;background:#fff;border:1px solid #f59e0b;border-radius:4px;color:#92400e;">' + escHtml( sg.name ) + '</span>';
						} else {
							html += '<span style="font-size:12px;padding:3px 8px;background:#d1fae5;border:1px solid #10b981;border-radius:4px;color:#065f46;">&#10003; ' + escHtml( sg.name ) + '</span>';
						}
					}
					html += '</div></div>';

					$( '#alvobot-configured-all-pixels' ).closest( '.alvobot-card-content' ).append( html );
					if (window.lucide) { window.lucide.createIcons(); }
				},
			});
		}

		// Handle "Criar todas" button — save settings first, then create via AJAX (avoids PHP timeout)
		$( document ).on( 'click', '#alvobot-create-all-suggested', function () {
			var $btn       = $( this );
			var tracker    = JSON.parse( $btn.attr( 'data-tracker' ) );
			var trackerId  = tracker.tracker_id;
			var $banner    = $( '#alvobot-conv-suggestion-banner' );

			var suggestions = [
				{ name: 'Ad Impression', category: 'PAGE_VIEW', trigger: 'ad_impression', event: 'ViewContent' },
				{ name: 'Ad Click', category: 'DEFAULT', trigger: 'ad_click', event: 'Lead' },
				{ name: 'Vignette View', category: 'PAGE_VIEW', trigger: 'ad_vignette_open', event: 'ViewContent' },
				{ name: 'Vignette Click', category: 'DEFAULT', trigger: 'ad_vignette_click', event: 'Lead' },
				{ name: 'Quiz Lead', category: 'LEAD', trigger: 'form_submit', event: 'Lead', css_selector: '.qbv2__lead-form' },
			];

			$btn.prop( 'disabled', true ).html( '<span class="spinner is-active" style="float:none;margin:0;"></span> Verificando existentes...' );

			// Step 1: Fetch BOTH existing Google Ads conversion actions AND existing plugin rules
			var normalizeConvName = function (n) { return n.toLowerCase().replace(/[\s_-]+/g, ''); };

			$.when(
				$.ajax({ url: config.ajaxurl, method: 'POST', data: {
					action: 'alvobot_pixel_tracking_fetch_conversion_actions',
					nonce: config.nonce,
					connection_id: tracker.connection_id,
					customer_id: trackerId.replace( 'AW-', '' ),
				}}),
				$.ajax({ url: config.ajaxurl, method: 'POST', data: {
					action: 'alvobot_pixel_tracking_get_conversions',
					nonce: config.nonce,
				}})
			).done( function (gadsResp, rulesResp) {
					var gadsData = gadsResp[0] || {};
					var rulesData = rulesResp[0] || {};

					// Google Ads existing conversion actions
					var existingActions = (gadsData.success && gadsData.data && gadsData.data.conversion_actions) ? gadsData.data.conversion_actions : [];
					var existingGadsNames = existingActions.map( function (a) { return normalizeConvName( a.name ); } );
					var existingLabels = {};
					for (var e = 0; e < existingActions.length; e++) {
						if (existingActions[e].conversion_label) {
							existingLabels[normalizeConvName( existingActions[e].name )] = existingActions[e].conversion_label;
						}
					}

					// Plugin existing conversion rules
					var existingRules = (rulesData.success && rulesData.data && rulesData.data.conversions) ? rulesData.data.conversions : [];
					var existingRuleNames = existingRules.map( function (r) { return normalizeConvName( r.name ); } );

					// Categorize each suggestion
					var toCreate = [];       // Not in Google Ads AND not in plugin rules
					var toSaveRule = [];     // In Google Ads but NOT in plugin rules
					var alreadyDone = [];    // In both — skip entirely
					for (var s = 0; s < suggestions.length; s++) {
						var normalized = normalizeConvName( suggestions[s].name );
						var inGads  = existingGadsNames.indexOf( normalized ) !== -1;
						var inRules = existingRuleNames.indexOf( normalized ) !== -1;

						if (inRules) {
							alreadyDone.push( suggestions[s] ); // Skip — already fully configured
						} else if (inGads) {
							suggestions[s].existingLabel = existingLabels[normalized] || '';
							toSaveRule.push( suggestions[s] ); // Only save the rule
						} else {
							toCreate.push( suggestions[s] ); // Create in Google Ads + save rule
						}
					}

					if ( ! toCreate.length && ! toSaveRule.length) {
						$banner.html( '<p class="alvobot-description" style="color:#16a34a;padding:12px;">Todas as conversoes ja estao configuradas!</p>' );
						return;
					}

					var total = toCreate.length + toSaveRule.length;
					var created = 0;
					var errors = [];

					// Step 2: Create conversion rules for already-existing ones (just save the rule, no API call)
					function saveExistingRules( idx ) {
						if (idx >= toSaveRule.length) {
							// Step 3: Create missing ones in Google Ads
							createMissing( 0 );
							return;
						}
						var sg = toSaveRule[idx];
						var label = sg.existingLabel || '';
						$btn.html( '<span class="spinner is-active" style="float:none;margin:0;"></span> Configurando ' + (created + 1) + '/' + total + ' (' + sg.name + ')...' );

						var labelsMap = {};
						if (label) { labelsMap[trackerId] = label; }

						$.ajax({
							url: config.ajaxurl,
							method: 'POST',
							data: {
								action: 'alvobot_pixel_tracking_save_conversion',
								nonce: config.nonce,
								conversion_id: 0,
								name: sg.name,
								event_type: sg.event,
								trigger_type: sg.trigger,
								display_on: 'all',
								content_name: sg.name,
								pixel_ids: trackerId,
								gads_conversion_label: label,
								gads_labels_map: JSON.stringify( labelsMap ),
								gads_conversion_value: '',
							},
							complete: function () { created++; saveExistingRules( idx + 1 ); },
						});
					}

					// Step 3: Create missing ones in Google Ads + save rule
					function createMissing( idx ) {
						if (idx >= toCreate.length) {
							// All done
							var msg = created + ' conversoes configuradas!';
							if (errors.length) { msg += ' (' + errors.length + ' erros: ' + errors.join( ', ' ) + ')'; }
							$banner.html( '<p class="alvobot-description" style="color:#16a34a;padding:12px;"><i data-lucide="check-circle" class="alvobot-icon" style="width:16px;height:16px;"></i> ' + escHtml( msg ) + ' Va para a aba <strong>Conversoes</strong> para ver.</p>' );
							if (window.lucide) { window.lucide.createIcons(); }

							return;
						}

						var sg = toCreate[idx];
						$btn.html( '<span class="spinner is-active" style="float:none;margin:0;"></span> Criando ' + (created + 1) + '/' + total + ' (' + sg.name + ')...' );

						$.ajax({
							url: config.ajaxurl,
							method: 'POST',
							data: {
								action: 'alvobot_pixel_tracking_create_conversion_action',
								nonce: config.nonce,
								connection_id: tracker.connection_id,
								customer_id: trackerId.replace( 'AW-', '' ),
								name: sg.name,
								category: sg.category,
							},
							success: function (response) {
								if (response.success && response.data) {
									var label = response.data.conversion_label || '';
									var labelsMap = {};
									if (label) { labelsMap[trackerId] = label; }
									// Save the conversion rule
									$.ajax({
										url: config.ajaxurl,
										method: 'POST',
										data: {
											action: 'alvobot_pixel_tracking_save_conversion',
											nonce: config.nonce,
											conversion_id: 0,
											name: sg.name,
											event_type: sg.event,
											trigger_type: sg.trigger,
											display_on: 'all',
											content_name: sg.name,
											pixel_ids: trackerId,
											css_selector: sg.css_selector || '',
											gads_conversion_label: label,
											gads_labels_map: JSON.stringify( labelsMap ),
											gads_conversion_value: '',
										},
										complete: function () { created++; createMissing( idx + 1 ); },
									});
									return;
								} else {
									errors.push( sg.name );
								}
								created++;
								createMissing( idx + 1 );
							},
							error: function () {
								errors.push( sg.name );
								created++;
								createMissing( idx + 1 );
							},
						});
					}

					// Start the chain
					saveExistingRules( 0 );
				},
			).fail( function () {
				$btn.prop( 'disabled', false ).html( '<i data-lucide="zap" class="alvobot-icon"></i> Criar faltantes' );
				alert( 'Erro ao verificar conversoes existentes.' );
			});
		});

		// ── Check existing trackers for missing suggested conversions ──
		function checkExistingSuggestions() {
			for (var i = 0; i < googleTrackersData.length; i++) {
				var gt = googleTrackersData[i];
				if (gt.type === 'google_ads' && gt.connection_id) {
					showConversionSuggestionBanner( gt );
					break; // Show one at a time
				}
			}
		}

		// ── Unified Table Render ─────────────────────────────────────
		function renderUnifiedTable() {
			var $container = $( '#alvobot-configured-all-pixels' );
			var total      = pixelsData.length + googleTrackersData.length;

			if ( ! total) {
				$container.html(
					'<div class="alvobot-empty-state alvobot-empty-state-compact">' +
					'<i data-lucide="radio" class="alvobot-icon" style="width:32px;height:32px;opacity:0.3;"></i>' +
					'<p>Nenhum pixel ou tracker configurado.</p>' +
					'<p class="alvobot-description">Use o seletor acima para adicionar.</p></div>'
				);
				return;
			}

			var html = '<table class="alvobot-table" style="width:100%;"><thead><tr>' +
				'<th>Plataforma</th><th>ID</th><th>Nome</th><th>Status</th><th></th>' +
				'</tr></thead><tbody>';

			// Meta pixels
			for (var i = 0; i < pixelsData.length; i++) {
				var p = pixelsData[i];
				var statusBadge = '';
				if (p.token_expired) {
					statusBadge = '<span class="alvobot-badge alvobot-badge-error">Token Expirado</span>';
				} else if (p.api_token) {
					statusBadge = '<span class="alvobot-badge alvobot-badge-success">CAPI Ativo</span>';
				} else {
					statusBadge = '<span class="alvobot-badge alvobot-badge-warning">Sem Token</span>';
				}
				html += '<tr>';
				html += '<td>' + PLATFORM_BADGES.meta_pixel + '</td>';
				html += '<td><code>' + escHtml( p.pixel_id ) + '</code></td>';
				html += '<td>' + escHtml( p.label || '-' ) + '</td>';
				html += '<td>' + statusBadge + '</td>';
				html += '<td><button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-meta-pixel" data-index="' + i + '">' +
					'<i data-lucide="trash-2" class="alvobot-icon"></i></button></td>';
				html += '</tr>';
			}

			// Google trackers
			for (var j = 0; j < googleTrackersData.length; j++) {
				var t = googleTrackersData[j];
				var platBadge = PLATFORM_BADGES[t.type] || PLATFORM_BADGES.external;
				var extraInfo = '';
				if (t.type === 'external') {
					extraInfo = '<span class="alvobot-badge alvobot-badge-neutral">Detectado na pagina</span>';
				} else {
					extraInfo = '<span class="alvobot-badge alvobot-badge-success">Ativo</span>';
				}
				html += '<tr>';
				html += '<td>' + platBadge + '</td>';
				html += '<td>' + (t.type === 'external' ? '<em>Auto-detectado</em>' : '<code>' + escHtml( t.tracker_id ) + '</code>') + '</td>';
				html += '<td>' + escHtml( t.label || '-' ) + '</td>';
				html += '<td>' + extraInfo + '</td>';
				html += '<td><button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-google-tracker" data-index="' + j + '">' +
					'<i data-lucide="trash-2" class="alvobot-icon"></i></button></td>';
				html += '</tr>';
			}
			html += '</tbody></table>';
			$container.html( html );
			if (window.lucide) { window.lucide.createIcons(); }
		}

		function updateGoogleTrackersHiddenField() {
			$( '#google_trackers_json' ).val( JSON.stringify( googleTrackersData ) );
		}

		// Initial render of unified table
		renderUnifiedTable();
		checkExistingSuggestions();
	}

	// ================================================================
	// CONVERSIONS TAB
	// ================================================================
	function initConversionsTab() {
		// Platform icons for pixel selector (must be defined before buildPixelSelector call)
		var CONV_ICONS = {
			meta: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><path fill="#1877F2" d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>',
			ga4: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><path fill="#E37400" d="M22.84 2.02v7.97a2.14 2.14 0 0 1-4.28 0V4.3H12.9a2.14 2.14 0 0 1 0-4.28h7.8c1.18 0 2.14.9 2.14 2Z"/><path fill="#F9AB00" d="M5.44 22c-1.9 0-3.44-1.5-3.44-3.36s1.54-3.36 3.44-3.36 3.44 1.5 3.44 3.36S7.34 22 5.44 22Z"/><path fill="#E37400" d="M12 22a2.14 2.14 0 0 1-2.14-2.14V9.73a2.14 2.14 0 0 1 4.28 0v10.13c0 1.18-.96 2.14-2.14 2.14Z"/></svg>',
			gads: '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;"><circle cx="6" cy="18" r="4" fill="#FBBC04"/><path fill="#4285F4" d="M21.2 7.2 12.6 22H7.4L16 7.2h5.2Z"/><path fill="#34A853" d="M16 7.2 7.4 22H2.8l8.6-14.8H16Z"/></svg>',
		};

		buildPixelSelector();
		loadConversions();

		// Build pixel/tracker selector checkboxes from configured pixels + trackers
		function buildPixelSelector() {
			var $container = $( '#conv-pixel-selector' );
			if ( ! $container.length) { return; }

			var html = '';

			// Meta Pixels
			var pixelLabels = extra.pixel_labels || {};
			var metaIds     = Object.keys( pixelLabels );
			if (metaIds.length) {
				for (var i = 0; i < metaIds.length; i++) {
					var pid   = metaIds[i];
					var pname = pixelLabels[pid] || '';
					html += '<label class="alvobot-checkbox-label" style="display:flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:4px;cursor:pointer;">' +
						'<input type="checkbox" class="conv-pixel-checkbox" value="' + escAttr( pid ) + '"> ' +
						CONV_ICONS.meta + ' <span>' + escHtml( pid ) + (pname ? ' — ' + escHtml( pname ) : '') + '</span></label>';
				}
			}

			// Google Trackers
			var trackers = extra.google_trackers || [];
			if (trackers.length) {
				for (var j = 0; j < trackers.length; j++) {
					var t = trackers[j];
					var icon, typeName, displayId;
					if (t.type === 'external') {
						icon      = CONV_ICONS.ga4;
						typeName  = 'Tag existente';
						displayId = 'Site Kit / GTM';
					} else if (t.type === 'ga4') {
						icon      = CONV_ICONS.ga4;
						typeName  = 'GA4';
						displayId = t.tracker_id;
					} else {
						icon      = CONV_ICONS.gads;
						typeName  = 'Google Ads';
						displayId = t.tracker_id;
					}
					html += '<label class="alvobot-checkbox-label" style="display:flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:4px;cursor:pointer;">' +
						'<input type="checkbox" class="conv-pixel-checkbox" value="' + escAttr( t.tracker_id ) + '"> ' +
						icon + ' <span>' + escHtml( displayId ) + ' — ' + escHtml( typeName ) +
						(t.label && t.type !== 'external' ? ' (' + escHtml( t.label ) + ')' : '') + '</span></label>';
				}
			}

			if ( ! html) {
				html = '<p class="alvobot-description">Nenhum pixel ou tracker configurado. Adicione na aba Pixels.</p>';
			}

			$container.html( html );

			// Update hidden field, visibility, and per-tracker label fields when checkboxes change
			$container.on( 'change', '.conv-pixel-checkbox', function () {
				var selected = [];
				$container.find( '.conv-pixel-checkbox:checked' ).each( function () {
					selected.push( $( this ).val() );
				});
				$( '#conv_pixel_ids' ).val( selected.join( ',' ) );
				updateGadsLabelsUI( selected );
			});
		}

		/**
		 * Render per-tracker Google Ads label fields based on selected checkboxes.
		 * Each selected Google Ads tracker gets its own label input + Buscar button.
		 */
		function updateGadsLabelsUI( selected ) {
			var trackers       = extra.google_trackers || [];
			var hasAdsSelected = selected.some( function (id) { return /^AW-/.test( id ); } );
			var hasAdsConfigured = trackers.some( function (t) { return t.type === 'google_ads'; } );
			var showGadsFields = hasAdsSelected || ( ! selected.length && hasAdsConfigured );

			if (showGadsFields) {
				$( '#conv-gads-fields' ).addClass( 'visible' );
			} else {
				$( '#conv-gads-fields' ).removeClass( 'visible' );
				return;
			}

			// Build list of Google Ads trackers to show label fields for
			var adsTrackers = [];
			for (var i = 0; i < trackers.length; i++) {
				if (trackers[i].type !== 'google_ads') { continue; }
				// Show if explicitly selected OR nothing selected (= all)
				if ( ! selected.length || selected.indexOf( trackers[i].tracker_id ) !== -1) {
					adsTrackers.push( trackers[i] );
				}
			}

			// Read current labels map from hidden field
			var currentMap = {};
			try { currentMap = JSON.parse( $( '#conv_gads_labels_map' ).val() || '{}' ); } catch (e) { /* ignore */ }

			var $container = $( '#conv-gads-labels-container' );
			var html = '';

			for (var j = 0; j < adsTrackers.length; j++) {
				var at       = adsTrackers[j];
				var savedLabel = currentMap[at.tracker_id] || '';
				html += '<div class="alvobot-gads-label-row" data-tracker-id="' + escAttr( at.tracker_id ) + '" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:6px;">';
				html += CONV_ICONS.gads + ' ';
				html += '<span style="min-width:160px;font-weight:500;">' + escHtml( at.tracker_id ) + '</span>';
				if (at.label) {
					html += '<span style="color:#666;font-size:12px;">' + escHtml( at.label ) + '</span>';
				}
				html += '<input type="text" class="alvobot-input alvobot-gads-label-input" data-tracker-id="' + escAttr( at.tracker_id ) + '" value="' + escAttr( savedLabel ) + '" placeholder="Conversion Label" style="flex:1;max-width:200px;">';
				if (at.connection_id) {
					html += '<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-gads-fetch-label-btn" data-tracker=\'' + escAttr( JSON.stringify( at ) ) + '\' title="Buscar labels disponiveis">';
					html += '<i data-lucide="search" class="alvobot-icon"></i> Buscar';
					html += '</button>';
				}
				// Link to create conversion in this specific Google Ads account
				var customerId = at.tracker_id.replace( 'AW-', '' );
				html += '<a href="https://ads.google.com/aw/conversions/new?customerId=' + encodeURIComponent( customerId ) + '" target="_blank" rel="noopener noreferrer" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" title="Criar conversao nesta conta" style="white-space:nowrap;">';
				html += '<i data-lucide="external-link" class="alvobot-icon"></i> Criar';
				html += '</a>';
				html += '</div>';
			}

			if ( ! html) {
				html = '<p class="alvobot-description">Selecione pelo menos um tracker Google Ads acima.</p>';
			}

			$container.html( html );
			if (window.lucide) { window.lucide.createIcons(); }

			// Sync label inputs → hidden map field
			$container.off( 'input', '.alvobot-gads-label-input' ).on( 'input', '.alvobot-gads-label-input', function () {
				syncGadsLabelsMap();
			});
		}

		/**
		 * Read all per-tracker label inputs and sync to hidden field.
		 */
		function syncGadsLabelsMap() {
			var map = {};
			$( '#conv-gads-labels-container .alvobot-gads-label-input' ).each( function () {
				var tid = $( this ).attr( 'data-tracker-id' );
				var val = $( this ).val().trim();
				if (tid && val) {
					map[tid] = val;
				}
			});
			$( '#conv_gads_labels_map' ).val( JSON.stringify( map ) );
			// Also set legacy field with first label for backward compat
			var firstLabel = '';
			for (var k in map) {
				if (map.hasOwnProperty( k )) { firstLabel = map[k]; break; }
			}
			$( '#conv_gads_conversion_label' ).val( firstLabel );
		}

		// Load conversions
		function loadConversions() {
			var $table = $( '#alvobot-conversions-tbody' );
			debugLog( 'loadConversions: table found?', { found: $table.length, ajaxurl: config.ajaxurl, hasNonce: !! config.nonce } );
			if ( ! $table.length) {
				debugError( 'loadConversions: #alvobot-conversions-tbody not found in DOM' );
				return;
			}

			$.ajax(
				{
					url: config.ajaxurl,
					method: 'POST',
					data: {
						action: 'alvobot_pixel_tracking_get_conversions',
						nonce: config.nonce,
					},
					success: function (response) {
						if (response.success) {
							renderConversionsTable( response.data.conversions );
						} else {
							$table.html( '<tr><td colspan="7" class="alvobot-empty-state"><p>Erro ao carregar: ' + escHtml( response.data || 'Resposta invalida' ) + '</p></td></tr>' );
						}
					},
					error: function (xhr, status, err) {
						debugError( 'loadConversions ajax error', { status: status, error: err } );
						$table.html( '<tr><td colspan="7" class="alvobot-empty-state"><p>Erro de conexao ao carregar conversoes.</p></td></tr>' );
					},
				}
			);
		}

		// Render conversions into table body
		function renderConversionsTable(conversions) {
			var $tbody = $( '#alvobot-conversions-tbody' );
			$tbody.empty();

			if ( ! conversions || ! conversions.length) {
				$tbody.html(
					'<tr><td colspan="7" class="alvobot-empty-state"><p>Nenhuma conversao configurada.</p></td></tr>'
				);
				updateBulkBar();
				return;
			}

			for (var i = 0; i < conversions.length; i++) {
				var c            = conversions[i];
				var isActive     = c.status === 'publish';
				var eventLabel   =
				c.event_type === 'CustomEvent' ? c.event_custom_name : c.event_type;
				var triggerLabel = getTriggerLabel( c.trigger_type );
				var platformLabel = getPlatformLabel( c.platforms );

				var html = '<tr data-id="' + c.id + '">';
				html    += '<td><input type="checkbox" class="alvobot-conv-select" data-id="' + c.id + '"></td>';
				html    += '<td>' + escHtml( c.name ) + '</td>';
				html    += '<td>' + escHtml( eventLabel ) + '</td>';
				html    += '<td>' + escHtml( triggerLabel ) + '</td>';
				html    += '<td>' + platformLabel + '</td>';
				html    +=
				'<td><label class="alvobot-toggle"><input type="checkbox" class="alvobot-toggle-conversion" data-id="' +
				c.id +
				'" ' +
				(isActive ? 'checked' : '') +
				'><span class="alvobot-toggle-slider"></span></label></td>';
				html    += '<td>';
				html    +=
				'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-edit-conversion" data-conversion=\'' +
				escAttr( JSON.stringify( c ) ) +
				"'>Editar</button> ";
				html +=
				'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-delete-conversion" data-id="' +
				c.id +
				'">Excluir</button>';
				html    += '</td>';
				html    += '</tr>';
				$tbody.append( html );
			}
			$( '#alvobot-conv-select-all' ).prop( 'checked', false );
			updateBulkBar();
		}

		// Get platform badge HTML
		function getPlatformLabel(platform) {
			var labels = {
				all: '<span class="alvobot-badge alvobot-badge-info">Todas</span>',
				meta_only: '<span class="alvobot-badge alvobot-badge-neutral">Meta</span>',
				google_only: '<span class="alvobot-badge alvobot-badge-success">Google</span>',
			};
			return labels[platform] || labels.all;
		}

		// Get human-readable trigger label
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

		// Show new conversion form
		$( document ).on(
			'click',
			'#alvobot-new-conversion-btn',
			function () {
				resetConversionForm();
				$( '#alvobot-conversion-form' ).show();
				$( '#alvobot-conversion-form-title' ).text( 'Nova Conversao' );
			}
		);

		// Edit conversion
		$( document ).on(
			'click',
			'.alvobot-edit-conversion',
			function () {
				var c = JSON.parse( $( this ).attr( 'data-conversion' ) );
				resetConversionForm();
				fillConversionForm( c );
				$( '#alvobot-conversion-form' ).show();
				$( '#alvobot-conversion-form-title' ).text( 'Editar Conversao' );
			}
		);

		// Fill form with conversion data
			function fillConversionForm(c) {
			$( '#conv_id' ).val( c.id );
			$( '#conv_name' ).val( c.name );
			$( '#conv_event_type' ).val( c.event_type ).trigger( 'change' );
			$( '#conv_event_custom_name' ).val( c.event_custom_name );
				$( '#conv_trigger_type' ).val( c.trigger_type ).trigger( 'change' );
				$( '#conv_trigger_value' ).val( c.trigger_value );
				$( '#conv_display_on' ).val( c.display_on ).trigger( 'change' );
					var pageIds = Array.isArray( c.page_ids )
						? c.page_ids
						: (typeof c.page_ids === 'string' && c.page_ids ? c.page_ids.split( ',' ) : []);
				$( '#conv_page_ids' ).val( pageIds.join( ',' ) );
				$( '#conv_page_ids_legacy' ).val( pageIds.join( ', ' ) );
				$( '#conv_page_paths' ).val( c.page_paths );
				$( '#conv_css_selector' ).val( c.css_selector );
				$( '#conv_content_name' ).val( c.content_name );
			$( '#conv_gads_conversion_value' ).val( c.gads_conversion_value || '' );

			// Load per-tracker labels map (new format) or migrate from legacy single label
			var labelsMap = {};
			if (c.gads_labels_map) {
				try { labelsMap = JSON.parse( c.gads_labels_map ); } catch (e) { /* ignore */ }
			}
			// Migrate legacy single label to all selected Google Ads trackers
			if ( ! Object.keys( labelsMap ).length && c.gads_conversion_label) {
				var selectedAds = (c.pixel_ids || '').split( ',' ).filter( function (id) { return /^AW-/.test( id.trim() ); } );
				for (var k = 0; k < selectedAds.length; k++) {
					labelsMap[selectedAds[k].trim()] = c.gads_conversion_label;
				}
			}
			$( '#conv_gads_labels_map' ).val( JSON.stringify( labelsMap ) );
			$( '#conv_gads_conversion_label' ).val( c.gads_conversion_label || '' );

			// Populate pixel selector checkboxes
			var selectedIds = (c.pixel_ids || '').split( ',' ).map( function (s) { return s.trim(); } ).filter( Boolean );
			$( '#conv-pixel-selector .conv-pixel-checkbox' ).each( function () {
				$( this ).prop( 'checked', selectedIds.indexOf( $( this ).val() ) !== -1 );
			});
			$( '#conv_pixel_ids' ).val( c.pixel_ids || '' );

			// Trigger visibility update for Google Ads fields (fix: .prop() doesn't fire change)
			updateGadsLabelsUI( selectedIds );
		}

		// Reset conversion form
			function resetConversionForm() {
			$( '#conv_id' ).val( '0' );
			$( '#conv_name' ).val( '' );
			$( '#conv_event_type' ).val( 'PageView' ).trigger( 'change' );
			$( '#conv_event_custom_name' ).val( '' );
				$( '#conv_trigger_type' ).val( 'page_load' ).trigger( 'change' );
				$( '#conv_trigger_value' ).val( '' );
				$( '#conv_display_on' ).val( 'all' ).trigger( 'change' );
				$( '#conv_page_ids' ).val( '' );
				$( '#conv_page_ids_legacy' ).val( '' );
				$( '#conv_page_paths' ).val( '' );
				$( '#conv_css_selector' ).val( '' );
				$( '#conv_content_name' ).val( '' );
			$( '#conv_gads_conversion_label' ).val( '' );
			$( '#conv_gads_labels_map' ).val( '{}' );
			$( '#conv_gads_conversion_value' ).val( '' );
			$( '#conv-gads-labels-container' ).empty();
			$( '#conv-gads-fields' ).removeClass( 'visible' );
			$( '#conv-pixel-selector .conv-pixel-checkbox' ).prop( 'checked', false );
			$( '#conv_pixel_ids' ).val( '' );
		}

		// Conditional field visibility for conversion form
		$( document ).on(
			'change',
			'#conv_event_type',
			function () {
				if ($( this ).val() === 'CustomEvent') {
					$( '#conv-custom-name-field' ).addClass( 'visible' );
				} else {
					$( '#conv-custom-name-field' ).removeClass( 'visible' );
				}
			}
		);

		$( document ).on(
			'change',
			'#conv_trigger_type',
			function () {
				var val = $( this ).val();
				if (val === 'page_time' || val === 'scroll') {
					$( '#conv-trigger-value-field' ).addClass( 'visible' );
				} else {
					$( '#conv-trigger-value-field' ).removeClass( 'visible' );
				}
				if (val === 'form_submit' || val === 'click' || val === 'view_element') {
					$( '#conv-selector-field' ).addClass( 'visible' );
				} else {
					$( '#conv-selector-field' ).removeClass( 'visible' );
				}

				// For ad-event triggers, show reminder that empty selection = all platforms.
				var isAdTrigger = ['ad_impression', 'ad_click', 'ad_vignette_open', 'ad_vignette_click'].indexOf( val ) !== -1;
				$( '#conv-ad-all-notice' ).toggle( isAdTrigger );
			}
		);

			$( document ).on(
				'change',
				'#conv_display_on',
				function () {
					if ($( this ).val() === 'path') {
						$( '#conv-page-paths-field' ).addClass( 'visible' );
					} else {
						$( '#conv-page-paths-field' ).removeClass( 'visible' );
					}
					if ($( this ).val() === 'specific') {
						$( '#conv-page-ids-legacy-field' ).addClass( 'visible' );
					} else {
						$( '#conv-page-ids-legacy-field' ).removeClass( 'visible' );
					}
				}
			);

		// ── Bulk Selection & Delete ──────────────────────────────────

		// Select all checkbox
		$( document ).on( 'change', '#alvobot-conv-select-all', function () {
			var checked = $( this ).prop( 'checked' );
			$( '.alvobot-conv-select' ).prop( 'checked', checked );
			updateBulkBar();
		});

		// Individual checkbox
		$( document ).on( 'change', '.alvobot-conv-select', function () {
			var total   = $( '.alvobot-conv-select' ).length;
			var checked = $( '.alvobot-conv-select:checked' ).length;
			$( '#alvobot-conv-select-all' ).prop( 'checked', total > 0 && checked === total );
			updateBulkBar();
		});

		// Update bulk action bar visibility and count
		function updateBulkBar() {
			var count = $( '.alvobot-conv-select:checked' ).length;
			var $bar  = $( '#alvobot-conversions-bulk-bar' );
			if (count > 0) {
				$( '#alvobot-bulk-count' ).text( count );
				$bar.css( 'display', 'flex' );
			} else {
				$bar.hide();
			}
		}

		// Bulk delete
		$( document ).on( 'click', '#alvobot-bulk-delete-btn', function () {
			var ids = [];
			$( '.alvobot-conv-select:checked' ).each( function () {
				ids.push( $( this ).attr( 'data-id' ) );
			});

			if ( ! ids.length) { return; }

			if ( ! confirm( 'Excluir ' + ids.length + ' conversao(oes)? Esta acao nao pode ser desfeita.' ) ) {
				return;
			}

			var $btn = $( this );
			$btn.prop( 'disabled', true );

			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_bulk_delete_conversions',
					nonce: config.nonce,
					ids: ids.join( ',' ),
				},
				success: function (response) {
					$btn.prop( 'disabled', false );
					if (response.success) {
						loadConversions();
					} else {
						alert( response.data || 'Erro ao excluir.' );
					}
				},
				error: function () {
					$btn.prop( 'disabled', false );
					alert( 'Erro de conexao.' );
				},
			});
		});

		// Cancel conversion form
		$( document ).on(
			'click',
			'#alvobot-cancel-conversion-btn',
			function () {
				$( '#alvobot-conversion-form' ).hide();
				resetConversionForm();
			}
		);

		// Per-tracker Buscar button handler (each tracker row has its own button)
		$( document ).on( 'click', '.alvobot-gads-fetch-label-btn', function () {
			var $btn       = $( this );
			var adsTracker = JSON.parse( $btn.attr( 'data-tracker' ) );
			var $row       = $btn.closest( '.alvobot-gads-label-row' );
			var trackerId  = $row.attr( 'data-tracker-id' );

			// Create or reuse a picker div (sibling after the row, not child)
			var $picker = $row.next( '.alvobot-gads-row-picker' );
			if ( ! $picker.length) {
				$picker = $( '<div class="alvobot-gads-row-picker" style="margin-top:6px;width:100%;"></div>' );
				$row.after( $picker );
			}

			$btn.prop( 'disabled', true );
			$picker.html( '<span class="spinner is-active" style="float:none;"></span> Buscando conversoes...' ).show();

			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_fetch_conversion_actions',
					nonce: config.nonce,
					connection_id: adsTracker.connection_id,
					customer_id: adsTracker.tracker_id.replace( 'AW-', '' ),
				},
				success: function (response) {
					$btn.prop( 'disabled', false );
					if (response.success && response.data.conversion_actions) {
						var actions = response.data.conversion_actions;
						var html    = '';
						for (var k = 0; k < actions.length; k++) {
							var ca = actions[k];
							if ( ! ca.conversion_label) { continue; }
							html += '<div style="display:flex;align-items:center;gap:8px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:4px;margin-bottom:3px;cursor:pointer;" class="alvobot-ca-pick-item" data-label="' + escAttr( ca.conversion_label ) + '" data-tracker-id="' + escAttr( trackerId ) + '">';
							html += '<i data-lucide="target" class="alvobot-icon" style="width:12px;height:12px;"></i> ';
							html += '<span style="font-size:13px;">' + escHtml( ca.name ) + '</span>';
							if (ca.status && ca.status !== 'ENABLED') {
								html += '<span style="font-size:10px;padding:1px 4px;border-radius:3px;background:#fef3c7;color:#92400e;">' + escHtml( ca.status ) + '</span>';
							}
							html += '<code style="margin-left:auto;font-size:11px;">' + escHtml( ca.conversion_label ) + '</code>';
							html += '</div>';
						}
						if ( ! html) {
							html = '<p class="alvobot-description" style="margin-bottom:8px;">Nenhuma conversao encontrada nesta conta.</p>';
						}
						// Suggested conversions (only show ones that don't already exist)
						var normalizeConvName = function (n) { return n.toLowerCase().replace(/[\s_-]+/g, ''); };
						var existingNames = actions.map( function (a) { return normalizeConvName( a.name ); } );
						var suggestions = [
							{ name: 'Ad Impression', category: 'PAGE_VIEW', desc: 'Visitante viu um anuncio', trigger: 'ad_impression', event: 'ViewContent' },
							{ name: 'Ad Click', category: 'DEFAULT', desc: 'Visitante clicou num anuncio', trigger: 'ad_click', event: 'Lead' },
							{ name: 'Vignette View', category: 'PAGE_VIEW', desc: 'Visitante viu a vinheta', trigger: 'ad_vignette_open', event: 'ViewContent' },
							{ name: 'Vignette Click', category: 'DEFAULT', desc: 'Visitante clicou na vinheta', trigger: 'ad_vignette_click', event: 'Lead' },
							{ name: 'Quiz Lead', category: 'LEAD', desc: 'Visitante preencheu lead no quiz', trigger: 'form_submit', event: 'Lead' },
						];
						var missingSuggestions = suggestions.filter( function (s) { return existingNames.indexOf( normalizeConvName( s.name ) ) === -1; } );

						if (missingSuggestions.length) {
							html += '<div style="margin-top:10px;padding:8px 10px;border:1px dashed #f59e0b;border-radius:6px;background:#fffbeb;">';
							html += '<p class="alvobot-description" style="font-weight:600;margin-bottom:6px;"><i data-lucide="sparkles" class="alvobot-icon" style="width:14px;height:14px;"></i> Conversoes sugeridas</p>';
							for (var s = 0; s < missingSuggestions.length; s++) {
								var sg = missingSuggestions[s];
								html += '<div style="display:flex;align-items:center;gap:8px;padding:5px 8px;border:1px solid #e2e8f0;border-radius:4px;margin-bottom:4px;background:#fff;">';
								html += '<span style="font-size:13px;font-weight:500;">' + escHtml( sg.name ) + '</span>';
								html += '<span style="font-size:11px;color:#666;">' + escHtml( sg.desc ) + '</span>';
								html += '<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary alvobot-ca-create-btn" style="margin-left:auto;" data-tracker=\'' + escAttr( JSON.stringify( adsTracker ) ) + '\' data-tracker-id="' + escAttr( trackerId ) + '" data-prefill-name="' + escAttr( sg.name ) + '" data-prefill-category="' + escAttr( sg.category ) + '" data-prefill-trigger="' + escAttr( sg.trigger ) + '" data-prefill-event="' + escAttr( sg.event ) + '">';
								html += '<i data-lucide="plus" class="alvobot-icon"></i> Criar</button>';
								html += '</div>';
							}
							html += '</div>';
						}

						// Custom create form
						html += '<div class="alvobot-ca-create-form" style="margin-top:8px;padding:8px 10px;border:1px dashed #94a3b8;border-radius:6px;background:#f8fafc;">';
						html += '<p class="alvobot-description" style="font-weight:600;margin-bottom:6px;"><i data-lucide="plus-circle" class="alvobot-icon" style="width:14px;height:14px;"></i> Criar conversao personalizada</p>';
						html += '<div style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">';
						html += '<div><label style="font-size:11px;font-weight:500;">Nome</label><input type="text" class="alvobot-input alvobot-ca-create-name" placeholder="Ex: Purchase, Lead" style="width:180px;"></div>';
						html += '<div><label style="font-size:11px;font-weight:500;">Categoria</label><select class="alvobot-input alvobot-ca-create-category" style="width:140px;">';
						html += '<option value="DEFAULT" selected>Default</option><option value="PURCHASE">Purchase</option><option value="LEAD">Lead</option><option value="PAGE_VIEW">Page View</option>';
						html += '<option value="SIGNUP">Signup</option><option value="ADD_TO_CART">Add to Cart</option><option value="BEGIN_CHECKOUT">Begin Checkout</option>';
						html += '<option value="SUBSCRIBE_PAID">Subscribe</option><option value="CONTACT">Contact</option></select></div>';
						html += '<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline alvobot-ca-create-btn" data-tracker=\'' + escAttr( JSON.stringify( adsTracker ) ) + '\' data-tracker-id="' + escAttr( trackerId ) + '">';
						html += '<i data-lucide="check" class="alvobot-icon"></i> Criar</button>';
						html += '</div></div>';
						$picker.html( html );
						if (window.lucide) { window.lucide.createIcons(); }
					} else {
						$picker.html( '<p class="alvobot-description" style="color:#e53e3e;">' + escHtml( response.data || 'Erro ao buscar.' ) + '</p>' );
					}
				},
				error: function () {
					$btn.prop( 'disabled', false );
					$picker.html( '<p class="alvobot-description" style="color:#e53e3e;">Erro de conexao.</p>' );
				},
			});
		});

		// Click on a ConversionAction to fill the label field for the specific tracker
		$( document ).on( 'click', '.alvobot-ca-pick-item', function () {
			var label     = $( this ).attr( 'data-label' );
			var trackerId = $( this ).attr( 'data-tracker-id' );

			// Fill the specific tracker's input
			$( '.alvobot-gads-label-input[data-tracker-id="' + trackerId + '"]' ).val( label );
			// Hide picker
			$( this ).closest( '.alvobot-gads-row-picker' ).hide();
			// Sync hidden map
			syncGadsLabelsMap();
		});

		// Create a new ConversionAction in Google Ads (works for both suggested and custom)
		$( document ).on( 'click', '.alvobot-ca-create-btn', function () {
			var $btn       = $( this );
			var adsTracker = JSON.parse( $btn.attr( 'data-tracker' ) );
			var trackerId  = $btn.attr( 'data-tracker-id' );

			// Prefill from suggestion buttons (data-prefill-*) or read from form inputs
			var name     = $btn.attr( 'data-prefill-name' ) || '';
			var category = $btn.attr( 'data-prefill-category' ) || '';
			if ( ! name) {
				var $form = $btn.closest( '.alvobot-ca-create-form' );
				name     = $form.find( '.alvobot-ca-create-name' ).val().trim();
				category = $form.find( '.alvobot-ca-create-category' ).val();
			}

			if ( ! name) {
				alert( 'Digite o nome da conversao.' );
				return;
			}

			// Capture prefill data for auto-save after creation
			var prefillData = {
				trigger: $btn.attr( 'data-prefill-trigger' ) || '',
				event:   $btn.attr( 'data-prefill-event' ) || '',
			};

			$btn.prop( 'disabled', true ).text( 'Criando...' );

			$.ajax({
				url: config.ajaxurl,
				method: 'POST',
				data: {
					action: 'alvobot_pixel_tracking_create_conversion_action',
					nonce: config.nonce,
					connection_id: adsTracker.connection_id,
					customer_id: adsTracker.tracker_id.replace( 'AW-', '' ),
					name: name,
					category: category,
				},
				success: function (response) {
					$btn.prop( 'disabled', false ).html( '<i data-lucide="check" class="alvobot-icon"></i> Criar' );
					if (response.success && response.data) {
						var label = response.data.conversion_label || '';
						if (label) {
							$( '.alvobot-gads-label-input[data-tracker-id="' + trackerId + '"]' ).val( label );
							syncGadsLabelsMap();
						}

						// If suggestion with prefill data, auto-fill the conversion form and save
						if (prefillData.trigger && prefillData.event) {
							// Disable all other create buttons to prevent race condition
							$( '.alvobot-ca-create-btn' ).prop( 'disabled', true );
							// Set pixel_ids to the specific tracker
							$( '#conv_pixel_ids' ).val( trackerId );
							$( '#conv-pixel-selector .conv-pixel-checkbox' ).each( function () {
								$( this ).prop( 'checked', $( this ).val() === trackerId );
							});
							$( '#conv_name' ).val( name );
							$( '#conv_event_type' ).val( prefillData.event ).trigger( 'change' );
							$( '#conv_trigger_type' ).val( prefillData.trigger ).trigger( 'change' );
							$( '#conv_display_on' ).val( 'all' ).trigger( 'change' );
							$( '#conv_content_name' ).val( name );
							$( '#conv_id' ).val( '0' ); // New conversion
							$btn.closest( '.alvobot-gads-row-picker' ).html(
								'<p class="alvobot-description" style="color:#16a34a;">' + escHtml( name ) + ' criada e configurada!' + (label ? ' Label: <code>' + escHtml( label ) + '</code>' : '') + '</p>'
							);
							// Auto-save the conversion rule (delay for form fields to update)
							setTimeout( function () { $( '#alvobot-save-conversion-btn' ).trigger( 'click' ); }, 300 );
						} else {
							$btn.closest( '.alvobot-gads-row-picker' ).html(
								label
									? '<p class="alvobot-description" style="color:#16a34a;">Conversao "' + escHtml( name ) + '" criada! Label: <code>' + escHtml( label ) + '</code></p>'
									: '<p class="alvobot-description" style="color:#f59e0b;">Conversao criada, mas label nao disponivel. Clique em Buscar novamente.</p>'
							);
						}
					} else {
						alert( response.data || 'Erro ao criar conversao.' );
					}
					if (window.lucide) { window.lucide.createIcons(); }
				},
				error: function () {
					$btn.prop( 'disabled', false ).html( '<i data-lucide="check" class="alvobot-icon"></i> Criar' );
					alert( 'Erro de conexao.' );
				},
			});
		});

		// Save conversion
		$( document ).on(
			'click',
			'#alvobot-save-conversion-btn',
				function () {
					var $btn = $( this );
					if ($btn.prop( 'disabled' )) { return; } // Guard against race condition with auto-save
					$btn.prop( 'disabled', true );
					var pageIdsRaw = $( '#conv_page_ids' ).val();
					var pageIds = pageIdsRaw
						? pageIdsRaw.split( ',' ).map( function (value) { return parseInt( value, 10 ); } ).filter( function (value) { return ! isNaN( value ) && value > 0; } )
						: [];

						$.ajax(
							{
								url: config.ajaxurl,
							method: 'POST',
							data: {
							action: 'alvobot_pixel_tracking_save_conversion',
							nonce: config.nonce,
							conversion_id: $( '#conv_id' ).val(),
							name: $( '#conv_name' ).val(),
							event_type: $( '#conv_event_type' ).val(),
								event_custom_name: $( '#conv_event_custom_name' ).val(),
								trigger_type: $( '#conv_trigger_type' ).val(),
								trigger_value: $( '#conv_trigger_value' ).val(),
								display_on: $( '#conv_display_on' ).val(),
								page_ids: pageIds,
								page_paths: $( '#conv_page_paths' ).val(),
								css_selector: $( '#conv_css_selector' ).val(),
							content_name: $( '#conv_content_name' ).val(),
							pixel_ids: $( '#conv_pixel_ids' ).val(),
							gads_conversion_label: $( '#conv_gads_conversion_label' ).val(),
							gads_labels_map: $( '#conv_gads_labels_map' ).val(),
							gads_conversion_value: $( '#conv_gads_conversion_value' ).val(),
						},
						success: function (response) {
							$btn.prop( 'disabled', false );
							if (response.success) {
								$( '#alvobot-conversion-form' ).hide();
								resetConversionForm();
								loadConversions();
							} else {
								alert( response.data || 'Erro ao salvar.' );
							}
						},
						error: function () {
							$btn.prop( 'disabled', false );
							alert( 'Erro de conexao.' );
						},
					}
				);
			}
		);

		// Toggle conversion active/inactive
		$( document ).on(
			'change',
			'.alvobot-toggle-conversion',
			function () {
				var $toggle = $( this );
				var id      = $toggle.data( 'id' );
				var active  = $toggle.is( ':checked' ) ? '1' : '0';

				$toggle.prop( 'disabled', true );

				$.ajax(
					{
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_toggle_conversion',
							nonce: config.nonce,
							conversion_id: id,
							active: active,
						},
						success: function (response) {
							$toggle.prop( 'disabled', false );
							if ( ! response.success) {
								// Revert on failure
								$toggle.prop( 'checked', active !== '1' );
								alert( response.data || 'Erro ao alterar status.' );
							}
						},
						error: function () {
							$toggle.prop( 'disabled', false );
							// Revert on network failure
							$toggle.prop( 'checked', active !== '1' );
							alert( 'Erro de conexao ao alterar status.' );
						},
					}
				);
			}
		);

		// Delete conversion
		$( document ).on(
			'click',
			'.alvobot-delete-conversion',
			function () {
				if ( ! confirm( 'Tem certeza que deseja excluir esta conversao?' )) {
					return;
				}

				var id   = $( this ).data( 'id' );
				var $row = $( this ).closest( 'tr' );

				$.ajax(
					{
						url: config.ajaxurl,
						method: 'POST',
						data: {
							action: 'alvobot_pixel_tracking_delete_conversion',
							nonce: config.nonce,
							conversion_id: id,
						},
						success: function (response) {
							if (response.success) {
								$row.fadeOut(
									function () {
										$( this ).remove();
										loadConversions();
									}
								);
							}
						},
					}
				);
			}
		);
	}

	// ================================================================
	// CONFIGURACOES TAB
	// ================================================================
	function initConfiguracoesTab() {
		debugLog( 'settings tab: init' );
		// Test mode: show/hide test event code field
		$( 'input[name="test_mode"]' ).on(
			'change',
			function () {
				debugLog( 'settings tab: test_mode changed', { enabled: $( this ).is( ':checked' ) } );
				if ($( this ).is( ':checked' )) {
					$( '#test-code-field' ).show();
				} else {
					$( '#test-code-field' ).hide();
				}
			}
		);
	}

	// ================================================================
	// EVENTS TAB
	// ================================================================
	function initEventsTab() {
		debugLog( 'events tab: init' );
		var currentPage   = 1;
		var perPage       = 25;
		var totalEvents   = 0;
		var currentOffset = 0;
		var pixelLabelsById = getConfiguredPixelLabelMap();
		var selectedEventIds = {};

		loadEvents();
		loadEventStats();
		updateBulkActionsState();
		debugLog( 'events tab: initial load triggered' );

		// Filter button
		$( '#events-filter-btn' ).on(
			'click',
			function () {
				debugLog(
					'events tab: filter apply clicked',
					{
						status: $( '#filter-status' ).val(),
						event_name: $( '#filter-event-name' ).val(),
					}
				);
				currentPage   = 1;
				currentOffset = 0;
				clearSelection();
				loadEvents();
			}
		);

		// Clear filters
		$( '#events-clear-filters-btn' ).on(
			'click',
			function () {
				debugLog( 'events tab: clear filters clicked' );
				$( '#filter-status' ).val( '' );
				$( '#filter-event-name' ).val( '' );
				currentPage   = 1;
				currentOffset = 0;
				clearSelection();
				loadEvents();
			}
		);

		// Pagination
		$( '#events-prev-btn' ).on(
			'click',
			function () {
				if (currentPage > 1) {
					currentPage--;
					currentOffset = (currentPage - 1) * perPage;
					loadEvents();
				}
			}
		);

		$( '#events-next-btn' ).on(
			'click',
			function () {
				var maxPage = Math.ceil( totalEvents / perPage );
				if (currentPage < maxPage) {
					currentPage++;
					currentOffset = (currentPage - 1) * perPage;
					loadEvents();
				}
			}
		);

		$( '#events-select-all' ).on(
			'change',
			function () {
				var checked = $( this ).is( ':checked' );
				debugLog( 'events tab: select-all changed', { checked: checked } );
				$( '.alvobot-events-row-check' ).each(
					function () {
						var eventId = String( $( this ).data( 'event-id' ) || '' );
						$( this ).prop( 'checked', checked );
						setSelected( eventId, checked );
					}
				);
				updateBulkActionsState();
			}
		);

		$( document )
		.off( 'change.alvobotEventRowCheck', '.alvobot-events-row-check' )
		.on(
			'change.alvobotEventRowCheck',
			'.alvobot-events-row-check',
				function () {
					var eventId = String( $( this ).data( 'event-id' ) || '' );
					setSelected( eventId, $( this ).is( ':checked' ) );
					debugLog( 'events tab: row selection changed', { event_id: eventId, checked: $( this ).is( ':checked' ) } );
					updateBulkActionsState();
				}
			);

		$( '#events-bulk-clear-btn' ).on(
			'click',
			function () {
				debugLog( 'events tab: bulk clear clicked' );
				clearSelection();
				$( '.alvobot-events-row-check' ).prop( 'checked', false );
				updateBulkActionsState();
			}
		);

		$( '#events-bulk-apply-btn' ).on(
			'click',
			function () {
				var action = String( $( '#events-bulk-action' ).val() || '' );
				var eventIds = Object.keys( selectedEventIds );
				debugLog( 'events tab: bulk apply clicked', { action: action, event_ids: eventIds } );

				if ( ! action || ! eventIds.length) {
					return;
				}

				var confirmMessage =
					action === 'delete'
						? 'Excluir permanentemente ' + eventIds.length + ' evento(s) selecionado(s)?'
						: 'Reenviar ' + eventIds.length + ' evento(s) selecionado(s) para a Meta?';
				if ( ! confirm( confirmMessage )) {
					return;
				}

				var $btn = $( this );
				$btn.prop( 'disabled', true );

				restPost(
					'events/bulk',
					{
						action: action,
						event_ids: eventIds,
					},
						function (result) {
							debugLog( 'events tab: bulk apply success', result );
							$btn.prop( 'disabled', false );
						clearSelection();
						$( '#events-bulk-action' ).val( '' );
						loadEvents();
						loadEventStats();

						var processed = result && typeof result.processed === 'number' ? result.processed : 0;
						var failed    = result && typeof result.failed_count === 'number' ? result.failed_count : 0;
						if (failed > 0) {
							alert( 'Acao concluida com falhas. Processados: ' + processed + ' | Falhas: ' + failed );
						}
					},
					function (error) {
						$btn.prop( 'disabled', false );
						alert( 'Erro na acao em massa: ' + error );
					}
				);
			}
		);

			function loadEvents() {
				var $tbody = $( '#alvobot-events-tbody' );
				$tbody.html( '<tr><td colspan="9" class="alvobot-events-loading"><span class="alvobot-skeleton" style="width:120px;"></span></td></tr>' );

			var params = 'limit=' + perPage + '&offset=' + currentOffset;
			var status = $( '#filter-status' ).val();
			if (status) {
				params += '&status=' + encodeURIComponent( status );
			}
			var eventName = $( '#filter-event-name' ).val();
			if (eventName) {
				params += '&event_name=' + encodeURIComponent( eventName );
			}
			debugLog( 'events tab: loadEvents request', { params: params, page: currentPage, offset: currentOffset } );

				restGet(
					'events?' + params,
					function (data, meta) {
						debugLog( 'events tab: loadEvents success', { events_count: data ? data.length : 0, meta: meta } );
						totalEvents = meta ? meta.total : 0;
						renderEventsTable( data );
						updatePagination();
					},
					function (error) {
						debugError( 'events tab: loadEvents error', error );
						totalEvents = 0;
						$( '#alvobot-events-pagination' ).hide();
						$tbody.html(
							'<tr><td colspan="9" class="alvobot-empty-state"><p>' +
							escHtml( error || 'Falha ao carregar eventos.' ) +
							'</p><small>Atualize a pagina e tente novamente.</small></td></tr>'
						);
						updateBulkActionsState();
					}
				);
			}

		function loadEventStats() {
				debugLog( 'events tab: loadEventStats request' );
				restGet(
					'events/stats',
					function (data) {
						debugLog( 'events tab: loadEventStats success', data );
						var pending = data.pixel_pending || 0;
						var sent    = data.pixel_sent || 0;
					var error   = data.pixel_error || 0;
					var total   = pending + sent + error;
					$( '#events-stat-total' ).find( 'strong' ).text( formatNumber( total ) );
						$( '#events-stat-pending' ).text( formatNumber( pending ) );
						$( '#events-stat-sent' ).text( formatNumber( sent ) );
						$( '#events-stat-error' ).text( formatNumber( error ) );
					},
					function () {
						debugError( 'events tab: loadEventStats error' );
						$( '#events-stat-total' ).find( 'strong' ).text( '-' );
						$( '#events-stat-pending' ).text( '-' );
						$( '#events-stat-sent' ).text( '-' );
						$( '#events-stat-error' ).text( '-' );
					}
				);
			}

		function renderEventsTable(events) {
			debugLog( 'events tab: renderEventsTable', { count: events ? events.length : 0 } );
			var $tbody = $( '#alvobot-events-tbody' );
			$tbody.empty();

			if ( ! events || ! events.length) {
				$tbody.html( '<tr><td colspan="9" class="alvobot-empty-state"><p>Nenhum evento encontrado.</p></td></tr>' );
				updateBulkActionsState();
				return;
			}

			for (var i = 0; i < events.length; i++) {
				var e    = events[i];
				var html = '<tr data-event-id="' + escAttr( e.event_id ) + '">';
				var eventId = String( e.event_id || '' );
				var isSelected = !! selectedEventIds[eventId];

				// Selection checkbox
				html += '<td class="alvobot-events-col-check">';
				html += '<input type="checkbox" class="alvobot-events-row-check" data-event-id="' + escAttr( eventId ) + '"' + (isSelected ? ' checked' : '') + '>';
				html += '</td>';

				// Event name + ID
				html += '<td>';
				html += '<div class="alvobot-events-cell-event">';
				html += '<strong>' + escHtml( e.event_name ) + '</strong>';
				html += '<code class="alvobot-events-uuid">' + escHtml( (e.event_id || '').substring( 0, 8 ) ) + '...</code>';
				html += '</div>';
				html += '</td>';

				// Status badge
					html += '<td class="alvobot-events-col-status">' + getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids ) + '</td>';

				// Page URL + title
				html += '<td class="alvobot-events-col-page">';
				html += '<div class="alvobot-events-cell-page">';
				if (e.page_title) {
					html += '<span class="alvobot-events-page-title">' + escHtml( truncate( e.page_title, 30 ) ) + '</span>';
				}
				if (e.page_url) {
					html += '<span class="alvobot-events-page-url">' + escHtml( truncateUrl( e.page_url, 35 ) ) + '</span>';
				}
				html += '</div>';
				html += '</td>';

				// IP / Location
				html += '<td>';
				html += '<div class="alvobot-events-cell-ip">';
				if (e.ip) {
					html += '<code>' + escHtml( e.ip ) + '</code>';
				}
				if (e.geo_city || e.geo_country) {
					html += '<span class="alvobot-events-geo">';
					html += escHtml( [e.geo_city, e.geo_country].filter( Boolean ).join( ', ' ) );
					html += '</span>';
				}
				html += '</div>';
				html += '</td>';

				// Identifiers (fbp, fbc)
				html += '<td>';
				html += '<div class="alvobot-events-cell-ids">';
				if (e.fbp) {
					html += '<span class="alvobot-events-id-tag">fbp</span>';
				}
				if (e.fbc) {
					html += '<span class="alvobot-events-id-tag alvobot-events-id-tag-fbc">fbc</span>';
				}
				if ( ! e.fbp && ! e.fbc) {
					html += '<span class="alvobot-events-no-data">-</span>';
				}
				html += '</div>';
				html += '</td>';

				// Pixels
				html += '<td class="alvobot-events-col-pixels">';
				html += '<div class="alvobot-events-cell-pixels">';
				if (e.pixel_ids) {
					var pIds = e.pixel_ids.split( ',' );
					for (var j = 0; j < pIds.length; j++) {
						var pid = pIds[j].trim();
						if (pid) {
							html += formatPixelTag( pid );
						}
					}
				} else {
					html += '<span class="alvobot-events-no-data">-</span>';
				}
				html += '</div>';
				html += '</td>';

				// Date
				html += '<td>';
				html += '<div class="alvobot-events-cell-date">';
				html += '<span>' + escHtml( formatDate( e.created_at ) ) + '</span>';
				if (e.sent_at) {
					var sentTs = parseInt( e.sent_at, 10 );
					if (sentTs > 0) {
						html += '<span class="alvobot-events-sent-at">Enviado: ' + escHtml( formatTimestamp( sentTs ) ) + '</span>';
					}
				}
				html += '</div>';
				html += '</td>';

				// Actions (icon buttons)
				html += '<td class="alvobot-events-col-actions">';
				html += '<div class="alvobot-events-actions-inline">';
				html += '<button type="button" class="alvobot-events-action-btn" data-action="view" data-event-id="' + escAttr( e.event_id ) + '" data-tooltip="Detalhes" title="Detalhes" aria-label="Detalhes">';
				html += '<i data-lucide="eye" class="alvobot-icon"></i>';
				html += '</button>';
				html += '<button type="button" class="alvobot-events-action-btn" data-action="logs" data-event-id="' + escAttr( e.event_id ) + '" data-tooltip="Logs CAPI" title="Logs CAPI" aria-label="Logs CAPI">';
				html += '<i data-lucide="file-text" class="alvobot-icon"></i>';
				html += '</button>';
				html += '<button type="button" class="alvobot-events-action-btn" data-action="resend" data-event-id="' + escAttr( e.event_id ) + '" data-tooltip="Reenviar" title="Reenviar" aria-label="Reenviar">';
				html += '<i data-lucide="refresh-cw" class="alvobot-icon"></i>';
				html += '</button>';
				html += '<button type="button" class="alvobot-events-action-btn alvobot-events-action-btn-danger" data-action="delete" data-event-id="' + escAttr( e.event_id ) + '" data-tooltip="Excluir" title="Excluir" aria-label="Excluir">';
				html += '<i data-lucide="trash-2" class="alvobot-icon"></i>';
				html += '</button>';
				html += '</div>';
				html += '</td>';

				html += '</tr>';
				$tbody.append( html );
			}

			// Init Lucide icons
			if (window.lucide) {
				window.lucide.createIcons();
			}

			updateBulkActionsState();
		}

			function getStatusBadge(status, error, retryCount, dispatchChannel, pixelIds) {
				var map = {
					pixel_pending: { cls: 'alvobot-badge-warning', label: 'Pendente' },
					pixel_sent:    { cls: 'alvobot-badge-success', label: 'Enviado' },
					pixel_error:   { cls: 'alvobot-badge-error', label: 'Erro' },
				};
				// Google Ads-only events: show "Via Browser" instead of "Pendente"
				var pids = String( pixelIds || '' ).trim();
				if (status === 'pixel_pending' && /^AW-/.test( pids ) && ! /^\d{15,16}/.test( pids )) {
					map.pixel_pending = { cls: 'alvobot-badge-success', label: 'Via Navegador' };
				}
				var info = map[status] || { cls: 'alvobot-badge-neutral', label: status };
				var html = '<span class="alvobot-badge ' + info.cls + '">' + info.label + '</span>';
				if (status === 'pixel_sent') {
					if (dispatchChannel === 'realtime') {
						html += '<span class="alvobot-events-dispatch-tag alvobot-events-dispatch-realtime">Tempo real</span>';
					} else if (dispatchChannel === 'queue') {
						html += '<span class="alvobot-events-dispatch-tag alvobot-events-dispatch-queue">Fila</span>';
					}
				}
				if (retryCount > 0 && status === 'pixel_pending') {
					html += '<span class="alvobot-events-retry-tag">retry ' + retryCount + '/3</span>';
				}
				if (error && status === 'pixel_error') {
					html += '<span class="alvobot-events-error-hint" title="' + escAttr( error ) + '">!</span>';
				}
				return html;
			}

		function getConfiguredPixelLabelMap() {
			var map = getLocalizedPixelLabelMap();
			var raw = $( '#pixels_json' ).val();
			if ( ! raw) {
				return map;
			}

			try {
				var pixels = JSON.parse( raw );
				if ( ! Array.isArray( pixels )) {
					return map;
				}

				for (var i = 0; i < pixels.length; i++) {
					var pixel = pixels[i] || {};
					var id    = String( pixel.pixel_id || '' ).trim();
					var label = String( pixel.label || '' ).trim();
					if (id) {
						map[id] = label;
					}
				}
			} catch (e) {
				// Ignore malformed JSON and keep empty map.
			}

			return map;
		}

		function getLocalizedPixelLabelMap() {
			var map = {};
			var source = extra && typeof extra.pixel_labels === 'object' ? extra.pixel_labels : {};
			var keys = Object.keys( source );
			for (var i = 0; i < keys.length; i++) {
				var id = String( keys[i] || '' ).trim();
				if ( ! id) {
					continue;
				}
				map[id] = String( source[id] || '' ).trim();
			}
			return map;
		}

		function formatPixelTag(pixelId) {
			var id    = String( pixelId || '' ).trim();
			var label = id ? (pixelLabelsById[id] || '') : '';
			var text  = label ? (label + ' (' + id + ')') : id;
			return '<code class="alvobot-events-pixel-tag">' + escHtml( text ) + '</code> ';
		}

		function formatPixelList(pixelIds) {
			if ( ! pixelIds) {
				return '<span class="alvobot-events-missing">Nenhum</span>';
			}

			var list = String( pixelIds ).split( ',' );
			var html = '';
			for (var i = 0; i < list.length; i++) {
				var id = String( list[i] || '' ).trim();
				if (id) {
					html += formatPixelTag( id );
				}
			}

			return html || '<span class="alvobot-events-missing">Nenhum</span>';
		}

		function setSelected(eventId, selected) {
			if ( ! eventId) {
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
			var selectedCount = Object.keys( selectedEventIds ).length;
			var action        = String( $( '#events-bulk-action' ).val() || '' );
			var canApply      = selectedCount > 0 && !! action;

			$( '#events-selected-count' ).text( selectedCount );
			$( '#events-bulk-clear-btn' ).prop( 'disabled', selectedCount === 0 );
			$( '#events-bulk-apply-btn' ).prop( 'disabled', ! canApply );

			// Show/hide bulk bar based on selection
			var $bulkBar = $( '#events-bulk-bar' );
			if (selectedCount > 0) {
				$bulkBar.addClass( 'is-visible' );
			} else {
				$bulkBar.removeClass( 'is-visible' );
				$( '#events-bulk-action' ).val( '' );
			}

			var $visibleChecks = $( '.alvobot-events-row-check' );
			if ( ! $visibleChecks.length) {
				$( '#events-select-all' ).prop( 'checked', false );
				return;
			}

			var checkedVisible = $visibleChecks.filter( ':checked' ).length;
			$( '#events-select-all' ).prop( 'checked', checkedVisible > 0 && checkedVisible === $visibleChecks.length );
		}

		$( '#events-bulk-action' ).on(
			'change',
			function () {
				updateBulkActionsState();
			}
		);

			function updatePagination() {
				var maxPage = Math.ceil( totalEvents / perPage );
				if (maxPage <= 1) {
					$( '#alvobot-events-pagination' ).hide();
					return;
				}
				$( '#alvobot-events-pagination' ).show();
				$( '#events-pagination-info' ).text( 'Pagina ' + currentPage + ' de ' + maxPage + ' (' + formatNumber( totalEvents ) + ' eventos)' );
				$( '#events-prev-btn' ).prop( 'disabled', currentPage <= 1 );
				$( '#events-next-btn' ).prop( 'disabled', currentPage >= maxPage );
			}

			// ---- Direct icon actions ----
			$( document )
			.off( 'click.alvobotEventsIconAction', '.alvobot-events-action-btn' )
			.on(
				'click.alvobotEventsIconAction',
				'.alvobot-events-action-btn',
				function (e) {
					e.preventDefault();
					e.stopImmediatePropagation();

					var $btn    = $( this );
					var action  = String( $btn.data( 'action' ) || '' );
					var eventId = String( $btn.data( 'event-id' ) || '' );
					debugLog( 'events tab: inline action clicked', { action: action, event_id: eventId } );

					switch (action) {
						case 'view':
							openEventModal( eventId, 'details' );
							break;
						case 'logs':
							openEventModal( eventId, 'logs' );
							break;
						case 'resend':
							resendEvent( eventId );
							break;
						case 'delete':
							deleteEvent( eventId );
							break;
					}
				}
			);

		// ---- Modal ----
			function openEventModal(eventId, view) {
				debugLog( 'events tab: openEventModal', { event_id: eventId, view: view } );
				var $modal = $( '#alvobot-event-modal' );
			var $body  = $( '#alvobot-modal-body' );
			var $title = $( '#alvobot-modal-title' );

			$title.text( view === 'logs' ? 'Logs CAPI' : 'Detalhes do Evento' );
			$body.html( '<div class="alvobot-events-loading"><span class="alvobot-skeleton" style="width:200px;"></span></div>' );
			$modal.show();

			restGet(
					'events/' + encodeURIComponent( eventId ),
					function (data) {
						debugLog( 'events tab: openEventModal data loaded', { event_id: eventId, view: view, data: data } );
						if (view === 'logs') {
						renderLogsView( data, $body );
					} else {
						renderDetailsView( data, $body );
					}
					if (window.lucide) {
						window.lucide.createIcons();
					}
				}
			);
		}

		function renderDetailsView(e, $container) {
			var sections = [];

			// Event Info
			sections.push( buildSection(
				'Informacoes do Evento',
				[
					['Evento', e.event_name],
					['Event ID', '<code>' + escHtml( e.event_id ) + '</code>'],
						['Status', getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids )],
					['Event Time', e.event_time ? formatTimestamp( parseInt( e.event_time, 10 ) ) : '-'],
					['Criado em', e.created_at || '-'],
					['Enviado em', e.sent_at ? formatTimestamp( parseInt( e.sent_at, 10 ) ) : '-'],
				]
			) );

			// Page Info
			sections.push( buildSection(
				'Pagina',
				[
					['Titulo', e.page_title || '-'],
					['URL', e.page_url ? '<a href="' + escAttr( e.page_url ) + '" target="_blank">' + escHtml( e.page_url ) + '</a>' : '-'],
					['Post ID', e.page_id || '-'],
					['Referrer', e.referrer || '-'],
				]
			) );

			// User Data
			sections.push( buildSection(
				'Dados do Usuario (enviados a Meta)',
				[
					['IP', e.ip ? '<code>' + escHtml( e.ip ) + '</code>' : '-'],
					['Browser IP', e.browser_ip ? '<code>' + escHtml( e.browser_ip ) + '</code>' : '-'],
					['User Agent', e.user_agent ? '<span class="alvobot-events-ua">' + escHtml( e.user_agent ) + '</span>' : '-'],
					['fbp', e.fbp ? '<code>' + escHtml( e.fbp ) + '</code>' : '<span class="alvobot-events-missing">Ausente</span>'],
					['fbc', e.fbc ? '<code>' + escHtml( e.fbc ) + '</code>' : '<span class="alvobot-events-missing">Ausente</span>'],
					['Lead ID', e.lead_id || '-'],
					['WP Email (hash)', e.wp_em || '-'],
					['WP Nome (hash)', e.wp_fn || '-'],
					['WP Sobrenome (hash)', e.wp_ln || '-'],
					['WP External ID (hash)', e.wp_external_id || '-'],
				]
			) );

			// Geo Data
			sections.push( buildSection(
				'Geolocalizacao',
				[
					['Cidade', e.geo_city || '<span class="alvobot-events-missing">Ausente</span>'],
					['Estado', e.geo_state || '<span class="alvobot-events-missing">Ausente</span>'],
					['Pais', e.geo_country || '-'],
					['Codigo Pais', e.geo_country_code || '<span class="alvobot-events-missing">Ausente</span>'],
					['CEP', e.geo_zipcode || '<span class="alvobot-events-missing">Ausente</span>'],
					['Timezone', e.geo_timezone || '-'],
				]
			) );

			// UTM Params
			sections.push( buildSection(
				'Parametros UTM',
				[
					['utm_source', e.utm_source || '-'],
					['utm_medium', e.utm_medium || '-'],
					['utm_campaign', e.utm_campaign || '-'],
					['utm_content', e.utm_content || '-'],
					['utm_term', e.utm_term || '-'],
				]
			) );

			// Delivery Info — context-aware for Google Ads vs Meta
			var pixelIdStr = String( e.pixel_ids || '' );
			var isGoogleAdsOnly = /^AW-/.test( pixelIdStr.trim() ) && ! /^\d{15,16}/.test( pixelIdStr.trim() );
			if (isGoogleAdsOnly) {
				sections.push( buildSection(
					'Entrega',
					[
						['Plataforma', formatPixelList( e.pixel_ids )],
						['Canal', '<span class="alvobot-badge alvobot-badge-success">Navegador (gtag.js)</span>'],
						['Servidor (CAPI)', '<span class="alvobot-events-missing">N/A — Google Ads usa envio pelo navegador</span>'],
					]
				) );
			} else {
				sections.push( buildSection(
					'Entrega CAPI',
					[
						['Pixels Alvo', formatPixelList( e.pixel_ids )],
						['Pixels Entregues', formatPixelList( e.fb_pixel_ids )],
						['Canal de Envio', (e.dispatch_channel === 'realtime' ? 'Tempo real' : (e.dispatch_channel === 'queue' ? 'Fila' : '-'))],
						['Tentativas', e.retry_count + '/3'],
						['Erro', e.error ? '<span class="alvobot-events-error-text">' + escHtml( e.error ) + '</span>' : '-'],
					]
				) );
			}

			// Custom Data
			if (e.custom_data && typeof e.custom_data === 'object' && Object.keys( e.custom_data ).length > 0) {
				sections.push(
					'<div class="alvobot-modal-section">' +
					'<h4>Custom Data</h4>' +
					'<pre class="alvobot-events-json">' + escHtml( JSON.stringify( e.custom_data, null, 2 ) ) + '</pre>' +
					'</div>'
				);
			}

			$container.html( sections.join( '' ) );
		}

		function renderLogsView(e, $container) {
			var html = '';

			// Status overview
			html += '<div class="alvobot-modal-section">';
			html += '<h4>Status da Entrega</h4>';
			html += '<div class="alvobot-events-detail-grid">';
				html += '<div class="alvobot-events-detail-row"><span>Status</span><span>' + getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel, e.pixel_ids ) + '</span></div>';
				html += '<div class="alvobot-events-detail-row"><span>Canal de Envio</span><span>' + escHtml( e.dispatch_channel === 'realtime' ? 'Tempo real' : (e.dispatch_channel === 'queue' ? 'Fila' : '-' ) ) + '</span></div>';
				html += '<div class="alvobot-events-detail-row"><span>Tentativas</span><span>' + e.retry_count + '/3</span></div>';
			html += '<div class="alvobot-events-detail-row"><span>Pixels Entregues</span><span>' + escHtml( e.fb_pixel_ids || 'Nenhum' ) + '</span></div>';
			if (e.error) {
				html += '<div class="alvobot-events-detail-row"><span>Erro</span><span class="alvobot-events-error-text">' + escHtml( e.error ) + '</span></div>';
			}
			html += '</div>';
			html += '</div>';

			// Request/Response payload log
			if (e.request_payload && Array.isArray( e.request_payload ) && e.request_payload.length > 0) {
				for (var i = 0; i < e.request_payload.length; i++) {
					var entry = e.request_payload[i];
					html += '<div class="alvobot-modal-section">';
					html += '<h4>Tentativa #' + (i + 1) + (entry.success ? ' <span class="alvobot-badge alvobot-badge-success">OK</span>' : ' <span class="alvobot-badge alvobot-badge-error">Falha</span>') + '</h4>';

					// Request
					html += '<div class="alvobot-events-log-block">';
					html += '<div class="alvobot-events-log-label">Request (enviado a Meta)</div>';
					html += '<pre class="alvobot-events-json">' + escHtml( JSON.stringify( entry.request, null, 2 ) ) + '</pre>';
					html += '</div>';

					// Response
					html += '<div class="alvobot-events-log-block">';
					html += '<div class="alvobot-events-log-label">Response (da Meta)</div>';
					html += '<pre class="alvobot-events-json">' + escHtml( JSON.stringify( entry.response, null, 2 ) ) + '</pre>';
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

			// Raw response_payload (last response)
			if (e.response_payload && typeof e.response_payload === 'object' && Object.keys( e.response_payload ).length > 0) {
				html += '<div class="alvobot-modal-section">';
				html += '<h4>Ultima Resposta (raw)</h4>';
				html += '<pre class="alvobot-events-json">' + escHtml( JSON.stringify( e.response_payload, null, 2 ) ) + '</pre>';
				html += '</div>';
			}

			$container.html( html );
		}

		function buildSection(title, rows) {
			var html = '<div class="alvobot-modal-section">';
			html += '<h4>' + escHtml( title ) + '</h4>';
			html += '<div class="alvobot-events-detail-grid">';
			for (var i = 0; i < rows.length; i++) {
				html += '<div class="alvobot-events-detail-row">';
				html += '<span>' + escHtml( rows[i][0] ) + '</span>';
				html += '<span>' + rows[i][1] + '</span>';
				html += '</div>';
			}
			html += '</div>';
			html += '</div>';
			return html;
		}

		// Close modal
		$( '#alvobot-modal-close, .alvobot-modal-backdrop' ).on(
			'click',
			function () {
				$( '#alvobot-event-modal' ).hide();
			}
		);

		$( document ).on(
			'keydown',
			function (e) {
				if (e.key === 'Escape') {
					$( '#alvobot-event-modal' ).hide();
				}
			}
		);

		// ---- Resend event ----
			function resendEvent(eventId) {
				debugLog( 'events tab: resend requested', { event_id: eventId } );
				if ( ! confirm( 'Reenviar este evento para a Meta? O status sera resetado para Pendente.' )) {
					debugWarn( 'events tab: resend canceled by user', { event_id: eventId } );
					return;
				}

			restPost(
				'actions/resend-event/' + encodeURIComponent( eventId ),
				{},
					function () {
						debugLog( 'events tab: resend success', { event_id: eventId } );
						setSelected( String( eventId || '' ), false );
					updateBulkActionsState();
					loadEvents();
					loadEventStats();
				},
					function (error) {
						debugError( 'events tab: resend failed', { event_id: eventId, error: error } );
						alert( 'Erro ao reenviar: ' + error );
				}
			);
		}

		// ---- Delete event ----
			function deleteEvent(eventId) {
				debugLog( 'events tab: delete requested', { event_id: eventId } );
				if ( ! confirm( 'Excluir este evento permanentemente?' )) {
					debugWarn( 'events tab: delete canceled by user', { event_id: eventId } );
					return;
				}

			restDelete(
				'events/' + encodeURIComponent( eventId ),
					function () {
						debugLog( 'events tab: delete success', { event_id: eventId } );
						setSelected( String( eventId || '' ), false );
					$( 'tr[data-event-id="' + eventId + '"]' ).fadeOut(
						function () {
							$( this ).remove();
							updateBulkActionsState();
						}
					);
					loadEventStats();
				},
					function (error) {
						debugError( 'events tab: delete failed', { event_id: eventId, error: error } );
						alert( 'Erro ao excluir: ' + error );
				}
			);
		}

		// ---- Helpers ----
		function truncate(str, max) {
			if ( ! str) {
				return '';
			}
			return str.length > max ? str.substring( 0, max ) + '...' : str;
		}

		function truncateUrl(url, max) {
			if ( ! url) {
				return '';
			}
			try {
				var u = new URL( url );
				var path = u.pathname + u.search;
				return path.length > max ? path.substring( 0, max ) + '...' : path;
			} catch (e) {
				return truncate( url, max );
			}
		}

		function formatDate(dateStr) {
			if ( ! dateStr) {
				return '-';
			}
			try {
				var d = new Date( dateStr );
				return d.toLocaleDateString( 'pt-BR' ) + ' ' + d.toLocaleTimeString( 'pt-BR', { hour: '2-digit', minute: '2-digit' } );
			} catch (e) {
				return dateStr;
			}
		}

		function formatTimestamp(ts) {
			if ( ! ts || ts <= 0) {
				return '-';
			}
			try {
				var d = new Date( ts * 1000 );
				return d.toLocaleDateString( 'pt-BR' ) + ' ' + d.toLocaleTimeString( 'pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' } );
			} catch (e) {
				return String( ts );
			}
		}

		function formatNumber(num) {
			return Number( num ).toLocaleString( 'pt-BR' );
		}
	}

	// ================================================================
	// STATUS TAB
	// ================================================================
	function initStatusTab() {
		debugLog( 'status tab: init' );
		loadStatusData();
		initQuickActions();

		function loadStatusData() {
			debugLog( 'status tab: loadStatusData request' );
			// Fetch event stats
			restGet(
				'events/stats',
				function (data) {
					debugLog( 'status tab: events/stats success', data );
					$( '#stat-pending' ).text( formatNumber( data.pixel_pending || 0 ) );
					$( '#stat-sent' ).text( formatNumber( data.pixel_sent || 0 ) );
					$( '#stat-error' ).text( formatNumber( data.pixel_error || 0 ) );
				}
			);

			// Fetch lead stats
			restGet(
				'leads/stats',
				function (data) {
					debugLog( 'status tab: leads/stats success', data );
					$( '#stat-leads' ).text( formatNumber( data.total || 0 ) );
				}
			);
		}

		function initQuickActions() {
			$( document )
			.off( 'click.alvobotStatusAction', '.alvobot-action-btn' )
			.on(
				'click.alvobotStatusAction',
				'.alvobot-action-btn',
				function () {
					var $btn   = $( this );
					var action = $btn.data( 'action' );
					debugLog( 'status tab: quick action clicked', { action: action } );
					if ( ! action) {
						return;
					}

					var actionMap = {
						'send-pending': 'actions/send-pending',
						'test-pixel': 'actions/test-pixel',
						cleanup: 'actions/cleanup',
						'refresh-pixels': 'actions/refresh-pixels',
					};

					var endpoint = actionMap[action];
					if ( ! endpoint) {
						debugWarn( 'status tab: unknown quick action', { action: action } );
						return;
					}
					$btn.prop( 'disabled', true );

					restPost(
						endpoint,
						{},
						function (data) {
							debugLog( 'status tab: quick action success', { action: action, endpoint: endpoint, data: data } );
							$btn.prop( 'disabled', false );
							showActionFeedback( data.message || 'Acao executada com sucesso.', 'success' );
							// Refresh stats after action
							loadStatusData();
						},
						function (error) {
							debugError( 'status tab: quick action error', { action: action, endpoint: endpoint, error: error } );
							$btn.prop( 'disabled', false );
							showActionFeedback(
								error || 'Erro ao executar acao.',
								'error'
							);
						}
					);
				}
			);
		}

		function showActionFeedback(message, type) {
			var $el = $( '#alvobot-action-feedback' );
			$el
			.removeClass( 'feedback-success feedback-error' )
			.addClass( 'feedback-' + type )
			.text( message )
			.show();

			setTimeout(
				function () {
					$el.fadeOut();
				},
				5000
			);
		}

		function formatNumber(num) {
			return Number( num ).toLocaleString( 'pt-BR' );
		}
	}

	// ================================================================
	// REST Helpers
	// ================================================================
	function restGet(endpoint, onSuccess, onError) {
		debugLog( 'restGet request', { endpoint: endpoint } );
		$.ajax(
			{
				url: extra.rest_url + endpoint,
				method: 'GET',
				beforeSend: function (xhr) {
					xhr.setRequestHeader( 'X-WP-Nonce', extra.rest_nonce );
				},
				success: function (response) {
					debugLog( 'restGet success', { endpoint: endpoint, response: response } );
					if (response.data) {
						onSuccess( response.data, response.meta || null );
					} else if (response.success && response.data) {
						onSuccess( response.data, response.meta || null );
					} else {
						onSuccess( response, null );
					}
				},
				error: function (xhr) {
					var msg = getRestErrorMessage( xhr );
					debugError(
						'restGet error',
						{
							endpoint: endpoint,
							status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
							message: msg,
							response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring( 0, 3000 ) : null,
						}
					);
					if (onError) {
						onError( msg );
					}
				},
			}
		);
	}

	function restPost(endpoint, data, onSuccess, onError) {
		debugLog( 'restPost request', { endpoint: endpoint, payload: data } );
		$.ajax(
			{
				url: extra.rest_url + endpoint,
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify( data ),
				beforeSend: function (xhr) {
					xhr.setRequestHeader( 'X-WP-Nonce', extra.rest_nonce );
				},
				success: function (response) {
					debugLog( 'restPost success', { endpoint: endpoint, response: response } );
					if (response.data) {
						onSuccess( response.data );
					} else {
						onSuccess( response );
					}
				},
				error: function (xhr) {
					var msg = getRestErrorMessage( xhr );
					debugError(
						'restPost error',
						{
							endpoint: endpoint,
							status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
							message: msg,
							response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring( 0, 3000 ) : null,
						}
					);
					if (onError) {
						onError( msg );
					}
				},
			}
		);
	}

	function restDelete(endpoint, onSuccess, onError) {
		debugLog( 'restDelete request', { endpoint: endpoint } );
		$.ajax(
			{
				url: extra.rest_url + endpoint,
				method: 'DELETE',
				beforeSend: function (xhr) {
					xhr.setRequestHeader( 'X-WP-Nonce', extra.rest_nonce );
				},
				success: function (response) {
					debugLog( 'restDelete success', { endpoint: endpoint, response: response } );
					if (onSuccess) {
						onSuccess( response.data || response );
					}
				},
				error: function (xhr) {
					var msg = getRestErrorMessage( xhr );
					debugError(
						'restDelete error',
						{
							endpoint: endpoint,
							status: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null,
							message: msg,
							response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring( 0, 3000 ) : null,
						}
					);
					if (onError) {
						onError( msg );
					}
				},
			}
		);
	}

	function getRestErrorMessage(xhr) {
		var msg = 'Erro de conexao.';
		try {
			var body = JSON.parse( xhr.responseText );
			msg      = body.message || body.error || msg;
			if (body.code === 'rest_cookie_invalid_nonce') {
				msg = 'Sessao expirada (nonce invalido). Atualize a pagina do admin.';
			}
			debugWarn( 'getRestErrorMessage(): parsed body', body );
		} catch (e) {
			debugWarn(
				'getRestErrorMessage(): failed to parse response body',
				{
					error: e && e.message ? e.message : e,
					response: xhr && typeof xhr.responseText === 'string' ? xhr.responseText.substring( 0, 1500 ) : null,
				}
			);
		}
		return msg;
	}

	// ================================================================
	// Shared Helpers
	// ================================================================
	function escHtml(str) {
		if ( ! str) {
			return '';
		}
		return String( str )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
	}

	function escAttr(str) {
		if ( ! str) {
			return '';
		}
		return String( str )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /'/g, '&#39;' )
		.replace( /"/g, '&quot;' );
	}
})( jQuery );
