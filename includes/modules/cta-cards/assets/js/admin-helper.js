/**
 * AlvoBot CTA Cards Admin Helper
 * JavaScript for the modern builder interface
 */

(function ($) {
	var CTACardsBuilder = {
		selectedTemplate: null,
		formData: {},

		init: function () {
			this.initColorPickers();
			this.bindEvents();
			this.initTemplateSelection();
			this.initPreviewToggle();
		},

		initColorPickers: function () {
			// Initialize WordPress color picker
			if ($.fn.wpColorPicker) {
				$('input[type="color"]').wpColorPicker({
					change: function (event, ui) {
						// Trigger preview update when color changes
						$(this).trigger('input');
					},
				});
			}
		},

		bindEvents: function () {
			var self = this;

			// Form input changes trigger preview update
			$('#cta-generator-form input, #cta-generator-form select, #cta-generator-form textarea').on(
				'input change',
				function () {
					if ($('#generated-shortcode').is(':visible')) {
						// Auto-update shortcode if already generated
						setTimeout(function () {
							$('#generate-shortcode').trigger('click');
						}, 300);
					}
				},
			);

			// Template change updates fields and preview
			$('#gen-template').on('change', function () {
				self.updateTemplateFields($(this).val());
				self.updateTemplateDescription($(this).val());
			});

			// Copy shortcode with better feedback
			$(document).on('click', '#copy-shortcode', function () {
				var $button = $(this);
				var $textarea = $('#shortcode-result');

				$textarea.select();

				try {
					var successful = document.execCommand('copy');
					if (successful) {
						$button.addClass('button-primary').text(alvobotCTACards.copied || 'Copiado!');
						setTimeout(function () {
							$button.removeClass('button-primary').text(alvobotCTACards.copy || 'Copiar');
						}, 2000);
					}
				} catch (err) {
					console.log('Fallback: Copy failed');
				}
			});

			// Enhanced example code selection
			$('.example-code textarea').on('click', function () {
				$(this).select();

				// Visual feedback
				$(this).addClass('selected');
				setTimeout(() => {
					$(this).removeClass('selected');
				}, 1000);
			});

			// Form validation
			$('#generate-shortcode').on('click', function () {
				if (!self.validateForm()) {
					return false;
				}
			});
		},

		updateTemplateFields: function (template) {
			var html = '';
			var templates = alvobotCTACards.templates || {};

			switch (template) {
				case 'horizontal':
					html += this.generateFieldHTML('image', 'url', 'URL da Imagem:', 'https://exemplo.com/imagem.jpg');
					break;

				case 'banner':
					html += this.generateFieldHTML(
						'background',
						'url',
						'Imagem de Fundo:',
						'https://exemplo.com/background.jpg',
					);
					break;

				case 'minimal':
					html += this.generateFieldHTML('tag', 'text', 'Tag/Badge:', 'PDF, GRÁTIS, NOVO...');
					break;

				case 'simple':
					html += this.generateFieldHTML('icon', 'text', 'Ícone/Emoji:', 'dashicons-star-filled ou 🌟');
					html += '<div class="form-note">';
					html +=
						'<p><small>Use um ícone Dashicons (ex: dashicons-star-filled) ou um emoji (ex: 🌟, 🚀, ⭐)<br>';
					html +=
						'Lista de ícones: <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Dashicons</a></small></p>';
					html += '</div>';
					break;

				case 'pulse':
					html += this.generateFieldHTML('icon', 'text', 'Ícone/Emoji:', 'dashicons-controls-play ou ▶️');
					html += this.generateFieldHTML('pulse_text', 'text', 'Texto do Pulse:', 'AO VIVO');
					html += this.generateFieldHTML('pulse_color', 'color', 'Cor do Pulse:', '#ff6b6b');
					html += '<div class="form-note">';
					html += '<p><small>Use um ícone Dashicons ou emoji. Pulse indica conteúdo ao vivo.</small></p>';
					html += '</div>';
					break;

				case 'multi-button':
					html += this.generateFieldHTML('button2', 'text', 'Botão 2 (opcional):', 'Saiba Mais');
					html += this.generateFieldHTML('url2', 'url', 'URL 2:', 'https://exemplo.com/mais');
					html += this.generateFieldHTML('button3', 'text', 'Botão 3 (opcional):', 'Contato');
					html += this.generateFieldHTML('url3', 'url', 'URL 3:', 'https://exemplo.com/contato');
					html += '<div class="form-note">';
					html += '<p><small>Adicione até 3 botões diferentes para o mesmo CTA.</small></p>';
					html += '</div>';
					break;

				case 'led-border':
					html += this.generateFieldHTML('icon', 'text', 'Ícone/Emoji:', 'dashicons-star-filled ou ⚡');
					html += this.generateFieldHTML(
						'led_colors',
						'text',
						'Cores LED (separadas por vírgula):',
						'#ff0080,#00ff80,#8000ff,#ff8000',
					);
					html += this.generateFieldHTML('led_speed', 'text', 'Velocidade da animação:', '2s');
					html += '<div class="form-note">';
					html += '<p><small>Use ícones ou emojis. Cores em hexadecimal separadas por vírgula.</small></p>';
					html += '</div>';
					break;
			}

			$('#template-specific-fields').html(html);
		},

		generateFieldHTML: function (name, type, label, placeholder) {
			return (
				'<div class="form-row">' +
				'<div class="form-group full-width">' +
				'<label for="gen-' +
				name +
				'">' +
				label +
				'</label>' +
				'<input type="' +
				type +
				'" id="gen-' +
				name +
				'" name="' +
				name +
				'" placeholder="' +
				placeholder +
				'">' +
				'</div>' +
				'</div>'
			);
		},

		updateTemplateDescription: function (template) {
			var descriptions = {
				vertical: 'Layout vertical com conteúdo centralizado, ideal para destaque no meio do artigo.',
				horizontal: 'Imagem à esquerda e conteúdo à direita, perfeito para artigos relacionados.',
				minimal: 'Design limpo e simples, ideal para downloads ou links rápidos.',
				banner: 'Banner destacado com imagem de fundo, ótimo para promoções especiais.',
				simple: 'Card simples com ícone/emoji, título e link, perfeito para listas de recursos.',
				pulse: 'Template animado com indicador "ao vivo" e ícone/emoji pulsante.',
				'multi-button': 'Template com até 3 botões de ação diferentes para o mesmo CTA.',
				'led-border': 'Template com efeito LED colorido na borda e ícone/emoji com brilho.',
			};

			var $description = $('#template-description');
			if ($description.length === 0) {
				$description = $('<p id="template-description" class="description"></p>');
				$('#gen-template').parent().append($description);
			}

			$description.text(descriptions[template] || '');
		},

		validateForm: function () {
			var isValid = true;
			var errors = [];

			// Required fields validation
			var requiredFields = {
				'gen-title': 'Título é obrigatório',
				'gen-url': 'URL é obrigatória',
			};

			$.each(requiredFields, function (fieldId, message) {
				var $field = $('#' + fieldId);
				if (!$field.val().trim()) {
					errors.push(message);
					$field.addClass('error');
					isValid = false;
				} else {
					$field.removeClass('error');
				}
			});

			// URL validation
			var url = $('#gen-url').val().trim();
			if (url && !this.isValidURL(url)) {
				errors.push('URL inválida');
				$('#gen-url').addClass('error');
				isValid = false;
			}

			// Color validation
			$('input[type="color"]').each(function () {
				var color = $(this).val();
				if (color && !color.match(/^#[0-9A-F]{6}$/i)) {
					errors.push('Cor inválida: ' + $(this).prev('label').text());
					$(this).addClass('error');
					isValid = false;
				} else {
					$(this).removeClass('error');
				}
			});

			// Show errors if any
			if (!isValid) {
				this.showErrors(errors);
			} else {
				this.hideErrors();
			}

			return isValid;
		},

		isValidURL: function (string) {
			try {
				new URL(string);
				return true;
			} catch (_) {
				// Check for relative URLs
				return string.startsWith('/') || string.startsWith('#');
			}
		},

		showErrors: function (errors) {
			var $errorContainer = $('#form-errors');
			if ($errorContainer.length === 0) {
				$errorContainer = $('<div id="form-errors" class="notice notice-error"><ul></ul></div>');
				$('#cta-generator-form').prepend($errorContainer);
			}

			var errorList = '';
			$.each(errors, function (i, error) {
				errorList += '<li>' + error + '</li>';
			});

			$errorContainer.find('ul').html(errorList);
			$errorContainer.show();

			// Scroll to errors
			$('html, body').animate(
				{
					scrollTop: $errorContainer.offset().top - 100,
				},
				300,
			);
		},

		hideErrors: function () {
			$('#form-errors').hide();
			$('.error').removeClass('error');
		},

		initTooltips: function () {
			// Add tooltips to form fields
			var tooltips = {
				'gen-template': 'Escolha o layout do seu CTA card',
				'gen-title': 'Título principal que será exibido no card',
				'gen-subtitle': 'Texto secundário abaixo do título (opcional)',
				'gen-description': 'Descrição mais detalhada do seu CTA',
				'gen-button': 'Texto que aparecerá no botão de ação',
				'gen-url': 'Para onde o usuário será direcionado ao clicar',
				'gen-target': 'Escolha se abre na mesma janela ou em nova aba',
				'gen-align': 'Alinhamento do conteúdo do card',
			};

			$.each(tooltips, function (fieldId, tooltip) {
				var $field = $('#' + fieldId);
				if ($field.length) {
					$field.attr('title', tooltip);
				}
			});
		},
	};

	// Initialize when document is ready
	$(document).ready(function () {
		CTACardsAdmin.init();
	});

	// Add CSS for enhanced styling
	$('<style>')
		.prop('type', 'text/css')
		.html(
			'.form-note { margin-top: 10px; }' +
				'.form-note p { margin: 0; color: #666; }' +
				'.error { border-color: #dc3232 !important; box-shadow: 0 0 2px rgba(220, 50, 50, 0.3) !important; }' +
				'.selected { box-shadow: 0 0 5px rgba(34, 113, 177, 0.5) !important; }' +
				'#template-description { font-style: italic; color: #666; margin-top: 5px; }' +
				'.wp-color-picker-container { display: inline-block; }' +
				'.wp-picker-container .wp-color-result { margin-bottom: 0; }',
		)
		.appendTo('head');
})(jQuery);
