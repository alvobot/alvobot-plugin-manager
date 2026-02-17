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
	}

	// ================================================================
	// CONVERSIONS TAB
	// ================================================================
	function initConversionsTab() {
		loadConversions();

		// Load conversions
		function loadConversions() {
			var $table = $( '#alvobot-conversions-tbody' );
			if ( ! $table.length) {
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
						}
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
					'<tr><td colspan="5" class="alvobot-empty-state"><p>Nenhuma conversao configurada.</p></td></tr>'
				);
				return;
			}

			for (var i = 0; i < conversions.length; i++) {
				var c            = conversions[i];
				var isActive     = c.status === 'publish';
				var eventLabel   =
				c.event_type === 'CustomEvent' ? c.event_custom_name : c.event_type;
				var triggerLabel = getTriggerLabel( c.trigger_type );

				var html = '<tr data-id="' + c.id + '">';
				html    += '<td>' + escHtml( c.name ) + '</td>';
				html    += '<td>' + escHtml( eventLabel ) + '</td>';
				html    += '<td>' + escHtml( triggerLabel ) + '</td>';
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
				html    +=
				'<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-delete-conversion" data-id="' +
				c.id +
				'">Excluir</button>';
				html    += '</td>';
				html    += '</tr>';
				$tbody.append( html );
			}
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
			$( '#conv_page_paths' ).val( c.page_paths );
			$( '#conv_css_selector' ).val( c.css_selector );
			$( '#conv_content_name' ).val( c.content_name );
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
			$( '#conv_page_paths' ).val( '' );
			$( '#conv_css_selector' ).val( '' );
			$( '#conv_content_name' ).val( '' );
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
			}
		);

		// Cancel conversion form
		$( document ).on(
			'click',
			'#alvobot-cancel-conversion-btn',
			function () {
				$( '#alvobot-conversion-form' ).hide();
				resetConversionForm();
			}
		);

		// Save conversion
		$( document ).on(
			'click',
			'#alvobot-save-conversion-btn',
			function () {
				var $btn = $( this );
				$btn.prop( 'disabled', true );

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
							page_paths: $( '#conv_page_paths' ).val(),
							css_selector: $( '#conv_css_selector' ).val(),
							content_name: $( '#conv_content_name' ).val(),
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
					html += '<td>' + getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel ) + '</td>';

				// Page URL + title
				html += '<td>';
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
				html += '<td>';
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

				// Actions (3-dot menu)
				html += '<td class="alvobot-events-col-actions">';
				html += '<div class="alvobot-events-actions-wrap">';
				html += '<button type="button" class="alvobot-events-actions-btn" data-event-id="' + escAttr( e.event_id ) + '">';
				html += '<i data-lucide="more-vertical" class="alvobot-icon"></i>';
				html += '</button>';
				html += '<div class="alvobot-events-dropdown" style="display:none;">';
				html += '<button type="button" class="alvobot-events-dropdown-item" data-action="view" data-event-id="' + escAttr( e.event_id ) + '">';
				html += '<i data-lucide="eye" class="alvobot-icon"></i> Detalhes';
				html += '</button>';
				html += '<button type="button" class="alvobot-events-dropdown-item" data-action="logs" data-event-id="' + escAttr( e.event_id ) + '">';
				html += '<i data-lucide="file-text" class="alvobot-icon"></i> Logs CAPI';
				html += '</button>';
				html += '<button type="button" class="alvobot-events-dropdown-item" data-action="resend" data-event-id="' + escAttr( e.event_id ) + '">';
				html += '<i data-lucide="refresh-cw" class="alvobot-icon"></i> Reenviar';
				html += '</button>';
				html += '<hr class="alvobot-events-dropdown-divider">';
				html += '<button type="button" class="alvobot-events-dropdown-item alvobot-events-dropdown-danger" data-action="delete" data-event-id="' + escAttr( e.event_id ) + '">';
				html += '<i data-lucide="trash-2" class="alvobot-icon"></i> Excluir';
				html += '</button>';
				html += '</div>';
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

			function getStatusBadge(status, error, retryCount, dispatchChannel) {
				var map = {
					pixel_pending: { cls: 'alvobot-badge-warning', label: 'Pendente' },
					pixel_sent:    { cls: 'alvobot-badge-success', label: 'Enviado' },
					pixel_error:   { cls: 'alvobot-badge-error', label: 'Erro' },
				};
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

			function closeEventDropdowns() {
				$( '.alvobot-events-dropdown' )
				.hide()
				.removeClass( 'is-open' )
				.css(
					{
						position: '',
						left: '',
						top: '',
						right: '',
						bottom: '',
						transform: '',
						visibility: '',
						zIndex: '',
					}
				);
			}

			function openEventDropdown($btn, $dropdown) {
				var gap   = 8;
				var rect  = $btn[0].getBoundingClientRect();

				closeEventDropdowns();

				// Temporarily render invisibly to measure and then place in viewport.
				$dropdown.css(
					{
						display: 'block',
						visibility: 'hidden',
						position: 'fixed',
						right: 'auto',
						bottom: 'auto',
						transform: 'none',
					}
				);

				var menuWidth  = $dropdown.outerWidth() || 160;
				var menuHeight = $dropdown.outerHeight() || 140;
				var left       = rect.right + gap;
				var top        = rect.top;

				// Prefer opening to the right of the actions column; fallback to left.
				if (left + menuWidth > window.innerWidth - gap) {
					left = rect.left - menuWidth - gap;
				}
				if (left < gap) {
					left = gap;
				}

				// Keep menu fully visible vertically.
				if (top + menuHeight > window.innerHeight - gap) {
					top = window.innerHeight - menuHeight - gap;
				}
				if (top < gap) {
					top = gap;
				}

				$dropdown
				.css(
					{
						left: left + 'px',
						top: top + 'px',
						visibility: 'visible',
						zIndex: 100050,
					}
				)
				.addClass( 'is-open' );
			}

			// ---- 3-dot menu + item actions (deterministic handlers) ----
			$( document )
			.off( 'click.alvobotEventsActionBtn', '.alvobot-events-actions-btn' )
			.on(
				'click.alvobotEventsActionBtn',
				'.alvobot-events-actions-btn',
				function (e) {
					e.preventDefault();
					e.stopPropagation();

					var $btn      = $( this );
					var $dropdown = $btn.siblings( '.alvobot-events-dropdown' );
					var eventId   = String( $btn.data( 'event-id' ) || '' );
					var isOpen    = $dropdown.hasClass( 'is-open' ) && $dropdown.is( ':visible' );

					debugLog( 'events tab: action button clicked', { event_id: eventId, was_open: isOpen } );

					if (isOpen) {
						closeEventDropdowns();
						debugLog( 'events tab: action menu closed', { event_id: eventId } );
						return;
					}

					openEventDropdown( $btn, $dropdown );
					debugLog( 'events tab: action menu opened', { event_id: eventId } );
				}
			);

			$( document )
			.off( 'click.alvobotEventsActionItem', '.alvobot-events-dropdown-item' )
			.on(
				'click.alvobotEventsActionItem',
				'.alvobot-events-dropdown-item',
				function (e) {
					e.preventDefault();
					e.stopPropagation();

					var $item   = $( this );
					var action  = $item.data( 'action' );
					var eventId = $item.data( 'event-id' );
					debugLog( 'events tab: dropdown action clicked', { action: action, event_id: eventId } );
					closeEventDropdowns();

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

			// Keep dropdown open when clicking inside its container.
			$( document )
			.off( 'click.alvobotEventsDropdownShell', '.alvobot-events-dropdown' )
			.on(
				'click.alvobotEventsDropdownShell',
				'.alvobot-events-dropdown',
				function (e) {
					e.stopPropagation();
				}
			);

			// Click outside closes all menus.
			$( document )
			.off( 'click.alvobotEventsOutside' )
			.on(
				'click.alvobotEventsOutside',
				function (e) {
					var $target = $( e.target );
					if ( $target.closest( '.alvobot-events-actions-wrap' ).length || $target.closest( '.alvobot-events-dropdown' ).length ) {
						return;
					}
					closeEventDropdowns();
				}
			);

			// Close floating menus when viewport changes.
			$( window )
			.off( 'scroll.alvobotEventsPosition resize.alvobotEventsPosition' )
			.on(
				'scroll.alvobotEventsPosition resize.alvobotEventsPosition',
				function () {
					closeEventDropdowns();
				}
			);

			$( document )
			.off( 'keydown.alvobotEventsEsc' )
			.on(
				'keydown.alvobotEventsEsc',
				function (e) {
					if (e.key === 'Escape') {
						closeEventDropdowns();
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
						['Status', getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel )],
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

			// Delivery Info
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
				html += '<div class="alvobot-events-detail-row"><span>Status</span><span>' + getStatusBadge( e.status, e.error, e.retry_count, e.dispatch_channel ) + '</span></div>';
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
