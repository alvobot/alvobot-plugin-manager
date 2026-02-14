jQuery(document).ready(function ($) {
	// Debounce helper
	var debounceTimer = null;
	function debouncedPreview() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(updatePreview, 300);
	}

	// Initialize color pickers
	$('.color-picker').wpColorPicker({
		change: function () {
			debouncedPreview();
		},
	});

	// Event handlers para inputs
	$('#blog_name, #font_choice').on('change input', function () {
		debouncedPreview();
	});

	// =========================================================================
	// Lucide Icon Grid - Build from CDN
	// =========================================================================

	var allIconNames = [];
	var currentSearch = '';
	var selectedIconName = '';
	var selectedIconSvg = '';

	/**
	 * Convert PascalCase icon name to kebab-case for display
	 */
	function toKebab(str) {
		return str.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
	}

	/**
	 * Build SVG string from Lucide icon data array
	 */
	function buildIconSvg(iconName, attrs) {
		attrs = attrs || {};
		var iconData = lucide.icons[iconName];
		if (!iconData) return null;

		// iconData is [attrs, children] where children are [elementName, elAttrs] tuples
		var iconAttrs = iconData[0] || {};
		var iconChildren = iconData[1] || [];

		// If old format (array of tuples directly), handle it
		if (Array.isArray(iconData[0]) && typeof iconData[0][0] === 'string') {
			iconChildren = iconData;
			iconAttrs = {};
		}

		var svgAttrs = {
			xmlns: 'http://www.w3.org/2000/svg',
			width: attrs.width || 24,
			height: attrs.height || 24,
			viewBox: '0 0 24 24',
			fill: 'none',
			stroke: attrs.stroke || 'currentColor',
			'stroke-width': attrs['stroke-width'] || 2,
			'stroke-linecap': 'round',
			'stroke-linejoin': 'round',
		};

		var svgAttrsStr = '';
		for (var k in svgAttrs) {
			svgAttrsStr += ' ' + k + '="' + svgAttrs[k] + '"';
		}

		var children = '';
		for (var i = 0; i < iconChildren.length; i++) {
			var element = iconChildren[i];
			if (Array.isArray(element) && element.length >= 2) {
				var tag = element[0];
				var elAttrs = element[1] || {};
				var attrStr = '';
				for (var a in elAttrs) {
					attrStr += ' ' + a + '="' + elAttrs[a] + '"';
				}
				children += '<' + tag + attrStr + '/>';
			}
		}

		return '<svg' + svgAttrsStr + '>' + children + '</svg>';
	}

	/**
	 * Initialize icon grid from Lucide CDN data
	 */
	function initIconGrid() {
		if (typeof lucide === 'undefined' || !lucide.icons) {
			$('#icon-grid').html(
				'<p style="grid-column:1/-1;text-align:center;color:#c00;">Erro ao carregar ícones Lucide.</p>',
			);
			return;
		}

		// Collect all icon names
		allIconNames = Object.keys(lucide.icons).sort();

		var gridEl = document.getElementById('icon-grid');
		var html = '';

		for (var i = 0; i < allIconNames.length; i++) {
			var name = allIconNames[i];
			var kebab = toKebab(name);
			var svg = buildIconSvg(name);
			if (svg) {
				html +=
					'<div class="icon-option" data-name="' +
					name +
					'" data-kebab="' +
					kebab +
					'">' +
					'<input type="radio" name="icon_choice" id="icon_' +
					name +
					'" value="' +
					name +
					'">' +
					'<label for="icon_' +
					name +
					'">' +
					'<div class="icon-preview">' +
					svg +
					'</div>' +
					'<span class="icon-name">' +
					kebab +
					'</span>' +
					'</label></div>';
			}
		}

		gridEl.innerHTML = html;

		// Enable search
		var searchEl = document.getElementById('icon-search');
		searchEl.disabled = false;
		searchEl.placeholder = 'Buscar entre ' + allIconNames.length + ' ícones...';

		// Bind events on dynamically created elements
		bindIconEvents();

		// Select first icon by default
		var firstRadio = $('input[name="icon_choice"]').first();
		if (firstRadio.length) {
			firstRadio.prop('checked', true);
			firstRadio.closest('.icon-option').find('label').trigger('click');
		}
	}

	/**
	 * Bind click events to icon grid items
	 */
	function bindIconEvents() {
		$('#icon-grid').on('click', '.icon-option', function () {
			var $this = $(this);
			var $radio = $this.find('input[type="radio"]');
			$radio.prop('checked', true);

			selectedIconName = $this.data('name');
			selectedIconSvg = buildIconSvg(selectedIconName);

			// Store SVG in hidden field
			$('#selected_icon_svg').val(selectedIconSvg);

			updatePreview();
		});
	}

	/**
	 * Filter icons by search term
	 */
	function filterIcons() {
		var searchTerm = currentSearch.toLowerCase().trim();
		var found = false;

		$('#icon-grid .icon-option').each(function () {
			var $icon = $(this);
			var kebab = ($icon.data('kebab') || '').toLowerCase();
			var matches = !searchTerm || kebab.indexOf(searchTerm) !== -1;
			$icon.toggle(matches);
			if (matches) found = true;
		});

		$('.no-icons-found').toggle(!found);
	}

	// Search handler
	$('#icon-search').on('input', function () {
		currentSearch = $(this).val();
		filterIcons();
	});

	// Initialize the grid
	initIconGrid();

	// =========================================================================
	// Preview & Save (AJAX)
	// =========================================================================

	/**
	 * Get current icon SVG content for AJAX calls
	 */
	function getIconSvg() {
		if (selectedIconSvg) return selectedIconSvg;
		// Fallback: try to get from hidden field
		return $('#selected_icon_svg').val() || '';
	}

	/**
	 * Unified preview update function
	 */
	function updatePreview() {
		var iconSvg = getIconSvg();
		if (!iconSvg) return;

		var formData = new FormData();
		formData.append('action', 'generate_logo');
		formData.append('_wpnonce', logoGeneratorParams.nonce);
		formData.append('blog_name', $('#blog_name').val());
		formData.append('font_color', $('#font_color').wpColorPicker('color'));
		formData.append('background_color', $('#background_color').wpColorPicker('color'));
		formData.append('icon_svg', iconSvg);
		formData.append('font_choice', $('#font_choice').val());

		// Atualiza preview do logo
		$.ajax({
			url: logoGeneratorParams.ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				if (response.success) {
					$('#logo-preview-content').html(response.data);
					updateFavicon();
				} else {
					showNotice('error', 'Erro ao gerar o logo: ' + (response.data || 'Erro desconhecido'));
				}
			},
			error: function (xhr, status, error) {
				showNotice('error', 'Erro ao comunicar com o servidor: ' + error);
			},
		});
	}

	/**
	 * Update favicon preview
	 */
	function updateFavicon() {
		var iconSvg = getIconSvg();
		if (!iconSvg) return;

		var formData = new FormData();
		formData.append('action', 'generate_favicon');
		formData.append('_wpnonce', logoGeneratorParams.nonce);
		formData.append('icon_svg', iconSvg);
		formData.append('font_color', $('#font_color').wpColorPicker('color'));
		formData.append('background_color', $('#background_color').wpColorPicker('color'));

		$.ajax({
			url: logoGeneratorParams.ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				if (response.success) {
					$('#favicon-preview-content').html(response.data);
				} else {
					showNotice('error', 'Erro ao gerar o favicon: ' + (response.data || 'Erro desconhecido'));
				}
			},
			error: function (xhr, status, error) {
				showNotice('error', 'Erro ao comunicar com o servidor: ' + error);
			},
		});
	}

	// =========================================================================
	// Form Submission (Save)
	// =========================================================================

	$('#logo-generator-form').on('submit', function (e) {
		e.preventDefault();

		var $button = $('input[type="submit"], button[type="submit"]', this);
		var originalText = $button.val() || $button.text();

		var iconSvg = getIconSvg();
		if (!iconSvg) {
			showNotice('error', 'Por favor, selecione um ícone primeiro.');
			return;
		}

		$button.prop('disabled', true).val('Salvando...').text('Salvando...');

		// Save logo if checked
		var saveLogo = $('#set_as_logo').prop('checked')
			? new Promise(function (resolve, reject) {
					var logoData = new FormData();
					logoData.append('action', 'save_logo');
					logoData.append('_wpnonce', logoGeneratorParams.nonce);
					logoData.append('blog_name', $('#blog_name').val());
					logoData.append('font_color', $('#font_color').wpColorPicker('color'));
					logoData.append('background_color', $('#background_color').wpColorPicker('color'));
					logoData.append('icon_svg', iconSvg);
					logoData.append('font_choice', $('#font_choice').val());
					logoData.append('set_as_logo', '1');

					$.ajax({
						url: logoGeneratorParams.ajaxurl,
						type: 'POST',
						data: logoData,
						processData: false,
						contentType: false,
					}).then(resolve, reject);
				})
			: Promise.resolve(null);

		// Save favicon if checked
		var saveFavicon = $('#set_as_favicon').prop('checked')
			? new Promise(function (resolve, reject) {
					var faviconData = new FormData();
					faviconData.append('action', 'save_favicon');
					faviconData.append('_wpnonce', logoGeneratorParams.nonce);
					faviconData.append('icon_svg', iconSvg);
					faviconData.append('font_color', $('#font_color').wpColorPicker('color'));
					faviconData.append('background_color', $('#background_color').wpColorPicker('color'));

					$.ajax({
						url: logoGeneratorParams.ajaxurl,
						type: 'POST',
						data: faviconData,
						processData: false,
						contentType: false,
					}).then(resolve, reject);
				})
			: Promise.resolve(null);

		Promise.all([saveLogo, saveFavicon])
			.then(function (results) {
				var logoResponse = results[0];
				var faviconResponse = results[1];
				var messages = [];

				if (logoResponse && logoResponse.success) {
					messages.push(logoResponse.data.message);
					if (logoResponse.data.logo) {
						var customLogoImg = $('.custom-logo');
						if (customLogoImg.length) {
							customLogoImg.attr('src', logoResponse.data.logo.url + '?t=' + new Date().getTime());
						}
					}
				}

				if (faviconResponse && faviconResponse.success) {
					messages.push(faviconResponse.data.message);
					if (faviconResponse.data.favicon) {
						var faviconUrl = faviconResponse.data.favicon.url + '?t=' + new Date().getTime();
						$('link[rel*="icon"]').each(function () {
							$(this).attr('href', faviconUrl);
						});
						if (!$('link[rel="icon"]').length) {
							$('head').append('<link rel="icon" type="image/svg+xml" href="' + faviconUrl + '">');
						}
						if (!$('link[rel="apple-touch-icon"]').length) {
							$('head').append('<link rel="apple-touch-icon" href="' + faviconUrl + '">');
						} else {
							$('link[rel="apple-touch-icon"]').attr('href', faviconUrl);
						}
					}
				}

				if (messages.length > 0) {
					showNotice('success', messages.join(' '));
				}
			})
			.catch(function (error) {
				showNotice('error', 'Erro ao salvar: ' + (error.message || 'Erro desconhecido'));
			})
			.finally(function () {
				$button.prop('disabled', false).val(originalText).text(originalText);
			});
	});

	// =========================================================================
	// Notifications
	// =========================================================================

	function showNotice(type, message) {
		$('.notice').remove();
		var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
		$('.alvobot-pro-header').after(notice);
		notice.append(
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Fechar</span></button>',
		);
		notice.find('.notice-dismiss').on('click', function () {
			notice.fadeOut(function () {
				$(this).remove();
			});
		});
	}
});
