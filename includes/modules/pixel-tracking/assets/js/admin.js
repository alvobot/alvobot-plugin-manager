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

	// ========================
	// Pixels Tab
	// ========================
	if (activeTab === 'pixels') {
		initPixelsTab();
	}

	// ========================
	// Conversions Tab
	// ========================
	if (activeTab === 'conversoes') {
		initConversionsTab();
	}

	// ========================
	// Configuracoes Tab
	// ========================
	if (activeTab === 'configuracoes') {
		initConfiguracoesTab();
	}

	// ========================
	// Status Tab
	// ========================
	if (activeTab === 'status') {
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
		} catch (e) {
			pixelsData = [];
		}

		// Mode toggle: show/hide AlvoBot vs Manual sections
		$( 'input[name="mode"]' ).on(
			'change',
			function () {
				var mode = $( this ).val();
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
							$loading.hide();
							$btn.prop( 'disabled', false );
							$error.text( 'Erro de conexao. Tente novamente.' ).show();
						},
					}
				);
			}
		);

		// Render AlvoBot pixel list with select buttons
		function renderAlvobotPixelList(pixels) {
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
				var p            = pixels[i];
				var alreadyAdded = pixelsData.some(
					function (existing) {
						return existing.pixel_id === p.pixel_id;
					}
				);
				html            += '<tr>';
				html            +=
				'<td><code>' +
				escHtml( p.pixel_id ) +
				'</code> ' +
				escHtml( p.pixel_name || '' ) +
				'</td>';
				html            += '<td>' + escHtml( p.connection_name || '' ) + '</td>';
				html            += '<td>';
				if (alreadyAdded) {
					html +=
						'<span class="alvobot-badge alvobot-badge-success">Adicionado</span>';
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
		}

		// Select AlvoBot pixel → add to configured list
		$( document ).on(
			'click',
			'.alvobot-select-alvobot-pixel',
			function () {
				var pixel = JSON.parse( $( this ).attr( 'data-pixel' ) );
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
				html +=
				'<td><button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-pixel-btn" data-index="' +
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
				var id     = $( this ).data( 'id' );
				var active = $( this ).is( ':checked' ) ? '1' : '0';

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
		// Test mode: show/hide test event code field
		$( 'input[name="test_mode"]' ).on(
			'change',
			function () {
				if ($( this ).is( ':checked' )) {
					$( '#test-code-field' ).show();
				} else {
					$( '#test-code-field' ).hide();
				}
			}
		);
	}

	// ================================================================
	// STATUS TAB
	// ================================================================
	function initStatusTab() {
		loadStatusData();
		initQuickActions();

		function loadStatusData() {
			// Fetch event stats
			restGet(
				'events/stats',
				function (data) {
					$( '#stat-pending' ).text( formatNumber( data.pixel_pending || 0 ) );
					$( '#stat-sent' ).text( formatNumber( data.pixel_sent || 0 ) );
					$( '#stat-error' ).text( formatNumber( data.pixel_error || 0 ) );
				}
			);

			// Fetch lead stats
			restGet(
				'leads/stats',
				function (data) {
					$( '#stat-leads' ).text( formatNumber( data.total || 0 ) );
				}
			);
		}

		function initQuickActions() {
			$( document ).on(
				'click',
				'.alvobot-action-btn',
				function () {
					var $btn   = $( this );
					var action = $btn.data( 'action' );
					if ( ! action) {
						return;
					}

					$btn.prop( 'disabled', true );

					var actionMap = {
						'send-pending': 'actions/send-pending',
						'test-pixel': 'actions/test-pixel',
						cleanup: 'actions/cleanup',
						'refresh-pixels': 'actions/refresh-pixels',
					};

					var endpoint = actionMap[action];
					if ( ! endpoint) {
						return;
					}

					restPost(
						endpoint,
						{},
						function (data) {
							$btn.prop( 'disabled', false );
							showActionFeedback( data.message || 'Acao executada com sucesso.', 'success' );
							// Refresh stats after action
							loadStatusData();
						},
						function (error) {
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
	// REST Helpers (for Status tab)
	// ================================================================
	function restGet(endpoint, onSuccess, onError) {
		$.ajax(
			{
				url: extra.rest_url + endpoint,
				method: 'GET',
				beforeSend: function (xhr) {
					xhr.setRequestHeader( 'X-WP-Nonce', extra.rest_nonce );
				},
				success: function (response) {
					if (response.data) {
						onSuccess( response.data );
					} else if (response.success && response.data) {
						onSuccess( response.data );
					} else {
						onSuccess( response );
					}
				},
				error: function (xhr) {
					if (onError) {
						var msg = 'Erro de conexao.';
						try {
								var body = JSON.parse( xhr.responseText );
								msg      = body.message || msg;
						} catch (e) {
							// ignore
						}
						onError( msg );
					}
				},
			}
		);
	}

	function restPost(endpoint, data, onSuccess, onError) {
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
					if (response.data) {
						onSuccess( response.data );
					} else {
						onSuccess( response );
					}
				},
				error: function (xhr) {
					var msg = 'Erro de conexao.';
					try {
						var body = JSON.parse( xhr.responseText );
						msg      = body.message || msg;
					} catch (e) {
						// ignore
					}
					onError( msg );
				},
			}
		);
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
