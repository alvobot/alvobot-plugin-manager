jQuery(document).ready(function ($) {
	// Initialize Lucide icons
	if (typeof lucide !== 'undefined') {
		lucide.createIcons();
	}

	// Cache dos elementos DOM
	var elements = {
		preview: $('.alvobot-author-box'),
		title: $('.alvobot-author-box .author-box-title'),
		description: $('.alvobot-author-box .author-description'),
		avatar: $('.alvobot-author-box .author-avatar img, .alvobot-author-box .author-avatar .avatar'),
		titleInput: $('#title_text'),
		showDescInput: $('#show_description'),
		displayPostsInput: $('#display_on_posts'),
		displayPagesInput: $('#display_on_pages'),
	};

	// Função para criar mensagem de status
	function createStatusMessage() {
		var $statusContainer = $('.alvobot-card .alvobot-card-body');
		var $existingStatus = $statusContainer.find('.author-box-preview-status');

		if ($existingStatus.length === 0) {
			$existingStatus = $('<div class="author-box-preview-status"></div>');
			$statusContainer.prepend($existingStatus);
		}

		return $existingStatus;
	}

	// Função para atualizar o status de exibição
	function updateDisplayStatus() {
		var displayLocations = [];
		var $status = createStatusMessage();

		if (elements.displayPostsInput.is(':checked')) {
			displayLocations.push('Posts');
		}
		if (elements.displayPagesInput.is(':checked')) {
			displayLocations.push('Páginas');
		}

		if (displayLocations.length > 0) {
			$status
				.html('<strong>Author Box ativo</strong> — Exibindo em: ' + displayLocations.join(' e '))
				.removeClass('status-disabled')
				.addClass('status-enabled')
				.css({
					'margin-bottom': '16px',
					padding: '12px 16px',
					background: 'linear-gradient(135deg, #e7f5ea 0%, #d4edda 100%)',
					border: '1px solid #c3e6cb',
					'border-radius': '8px',
					color: '#155724',
					'font-size': '14px',
					'border-left': '4px solid #28a745',
				});
		} else {
			$status
				.html('<strong>Author Box desativado</strong> — Selecione onde exibir')
				.removeClass('status-enabled')
				.addClass('status-disabled')
				.css({
					'margin-bottom': '16px',
					padding: '12px 16px',
					background: 'linear-gradient(135deg, #fbeaea 0%, #f8d7da 100%)',
					border: '1px solid #f5c6cb',
					'border-radius': '8px',
					color: '#721c24',
					'font-size': '14px',
					'border-left': '4px solid #dc3545',
				});
		}
	}

	// Função para atualizar o preview em tempo real
	function updatePreview() {
		// Atualiza o título
		if (elements.titleInput.length && elements.title.length) {
			var titleText = elements.titleInput.val().trim();
			if (titleText) {
				elements.title.text(titleText).show();
			} else {
				elements.title.hide();
			}
		}

		// Atualiza a visibilidade da descrição
		if (elements.showDescInput.length && elements.description.length) {
			if (elements.showDescInput.is(':checked')) {
				elements.description.show().css('opacity', '1');
			} else {
				elements.description.hide().css('opacity', '0.5');
			}
		}

		// Atualiza o status de exibição
		updateDisplayStatus();

		// Adiciona classe de preview ativo
		elements.preview.addClass('preview-active');
	}

	// Função para adicionar efeitos visuais
	function addVisualEffects() {
		// Efeito hover nos campos de entrada
		$('input[type="text"], input[type="number"]')
			.on('focus', function () {
				$(this).closest('tr').addClass('field-focused');
			})
			.on('blur', function () {
				$(this).closest('tr').removeClass('field-focused');
			});

		// Efeito nos checkboxes
		$('input[type="checkbox"]').on('change', function () {
			var $label = $(this).closest('label');
			if ($(this).is(':checked')) {
				$label.addClass('checkbox-checked');
			} else {
				$label.removeClass('checkbox-checked');
			}
		});

		// Efeito de pulsação no preview quando há mudanças
		var pulseTimeout;
		function pulsePreview() {
			clearTimeout(pulseTimeout);
			elements.preview.addClass('preview-updating');

			pulseTimeout = setTimeout(function () {
				elements.preview.removeClass('preview-updating');
			}, 300);
		}

		// Event listeners para atualização em tempo real
		elements.titleInput.on('input', function () {
			updatePreview();
			pulsePreview();
		});

		$('input[type="checkbox"]').on('change', function () {
			updatePreview();
			pulsePreview();
		});
	}

	// Função de inicialização
	function init() {
		// Verifica se os elementos existem
		if (elements.preview.length === 0) {
			console.log('AlvoBot Author Box: Preview não encontrado');
			return;
		}

		// Adiciona CSS customizado para melhorar a UX
		$('<style>')
			.prop('type', 'text/css')
			.html(`
                .field-focused {
                    background-color: var(--alvobot-gray-50, #f9f9f9) !important;
                    border-radius: 4px;
                    transition: background-color 0.2s ease;
                }
                
                .checkbox-checked {
                    font-weight: 600;
                    color: var(--alvobot-primary, #CD9042) !important;
                }
                
                .preview-updating {
                    animation: previewPulse 0.3s ease-in-out;
                }
                
                .preview-active::before {
                    background: linear-gradient(90deg, var(--alvobot-primary, #CD9042), var(--alvobot-primary-dark, #B8803A)) !important;
                }
                
                @keyframes previewPulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                }
                
                .author-box-preview-status {
                    animation: statusSlideIn 0.4s ease-out;
                }
                
                @keyframes statusSlideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `)
			.appendTo('head');

		// Inicializa efeitos visuais
		addVisualEffects();

		// Atualização inicial
		updatePreview();

		console.log('AlvoBot Author Box: Admin scripts inicializados com sucesso');
	}

	// Executa a inicialização
	init();

	// =====================================================
	// Multilingual Bios
	// =====================================================

	// Tab switching (scoped to parent .ab-bio-lang-tabs container)
	$(document).on('click', '.ab-bio-lang-tab-btn', function () {
		var lang = $(this).data('lang');
		var $tabs = $(this).closest('.ab-bio-lang-tabs');
		$tabs.find('.ab-bio-lang-tab-btn').removeClass('active').attr('aria-selected', 'false');
		$(this).addClass('active').attr('aria-selected', 'true');
		$tabs.find('.ab-bio-lang-tab-content').removeClass('active');
		$tabs.find('.ab-bio-lang-tab-content[data-lang="' + lang + '"]').addClass('active');
	});

	// User selector change - reload bios
	$('#bio-lang-user').on('change', function () {
		var userId = $(this).val();
		if (typeof alvobotAuthorBox === 'undefined') return;

		$('.ab-bio-textarea').prop('disabled', true);
		$.post(alvobotAuthorBox.ajaxurl, {
			action: 'ab_load_user_bios',
			nonce: alvobotAuthorBox.nonce,
			user_id: userId,
		}, function (response) {
			$('.ab-bio-textarea').prop('disabled', false);
			if (response.success) {
				var bios = response.data.bios;
				$('.ab-bio-textarea').each(function () {
					var lang = $(this).data('lang');
					$(this).val(bios[lang] || '');
				});
			}
		});
	});

	// Save bios
	$('#ab-save-bios').on('click', function () {
		if (typeof alvobotAuthorBox === 'undefined') return;

		var $btn = $(this);
		var $status = $('#ab-bio-save-status');
		$btn.prop('disabled', true);

		var bios = {};
		$('.ab-bio-textarea').each(function () {
			bios[$(this).data('lang')] = $(this).val();
		});

		$.post(alvobotAuthorBox.ajaxurl, {
			action: 'ab_save_bios',
			nonce: alvobotAuthorBox.nonce,
			user_id: $('#bio-lang-user').val(),
			bios: bios,
		}, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$status.text(response.data.message).fadeIn(200);
				setTimeout(function () { $status.fadeOut(200); }, 3000);
			}
		});
	});

	// =====================================================
	// AI Author Generation
	// =====================================================

	var $generateBtn = $('#alvobot-ai-generate-author');
	var $regenerateBtn = $('#alvobot-ai-regenerate-author');
	var $applyBtn = $('#alvobot-ai-apply-author');

	if ($generateBtn.length === 0) {
		return; // AI section not present
	}

	var aiSections = {
		form: $('#alvobot-ai-author-form'),
		loading: $('#alvobot-ai-author-loading'),
		preview: $('#alvobot-ai-author-preview'),
		error: $('#alvobot-ai-author-error'),
		success: $('#alvobot-ai-author-success'),
	};

	function showAiSection(name) {
		Object.keys(aiSections).forEach(function (key) {
			aiSections[key].hide();
		});
		if (aiSections[name]) {
			aiSections[name].fadeIn(200);
		}
	}

	function showAiError(msg) {
		$('#alvobot-ai-author-error-msg').text(msg);
		aiSections.error.fadeIn(200);
	}

	function hideAiMessages() {
		aiSections.error.hide();
		aiSections.success.hide();
	}

	// Check if multilingual mode is active
	var isMultilingual = typeof alvobotAuthorBox !== 'undefined' && alvobotAuthorBox.isMultilingual;
	var siteLanguages = isMultilingual ? alvobotAuthorBox.siteLanguages : {};

	function doGenerate() {
		if (isMultilingual && Object.keys(siteLanguages).length > 1) {
			doGenerateMultilingual();
			return;
		}

		var niche = $('#ai-author-niche').val().trim();
		if (!niche) {
			showAiError('Por favor, informe o nicho do site.');
			return;
		}

		hideAiMessages();
		showAiSection('loading');
		$('#alvobot-ai-author-loading p').text('Gerando perfil de autor com IA... Isso pode levar alguns segundos.');

		var params = {
			niche: niche,
			site_title: typeof alvobotAI !== 'undefined' && alvobotAI.site_title ? alvobotAI.site_title : '',
			country: $('#ai-author-country').val().trim() || 'Brasil',
			language: $('#ai-author-language').val().trim() || 'Português',
		};

		if (typeof window.AlvobotAI !== 'undefined') {
			window.AlvobotAI.generate('generate_author', params, {
				onSuccess: function (response) {
					var author = response.data || response;

					$('#ai-preview-name').val(author.name || '');
					$('#ai-preview-bio').val(author.description || '').show();
					$('#ai-preview-avatar-url').val(author.avatar_url || '');
					$('#ai-preview-bio-multilingual').hide();

					if (author.avatar_url) {
						$('#alvobot-ai-author-avatar').attr('src', author.avatar_url).show();
					} else {
						$('#alvobot-ai-author-avatar').hide();
					}

					showAiSection('preview');
				},
				onError: function (error) {
					showAiSection('form');
					var msg = (error && error.message) ? error.message : (typeof error === 'string' ? error : 'Erro ao gerar autor. Tente novamente.');
					showAiError(msg);
				},
			});
		} else {
			showAiSection('form');
			showAiError('Sistema de IA não está carregado. Recarregue a página.');
		}
	}

	function doGenerateMultilingual() {
		var niche = $('#ai-author-niche').val().trim();
		if (!niche) {
			showAiError('Por favor, informe o nicho do site.');
			return;
		}

		if (typeof window.AlvobotAI === 'undefined') {
			showAiError('Sistema de IA não está carregado. Recarregue a página.');
			return;
		}

		hideAiMessages();
		showAiSection('loading');

		var langCodes = Object.keys(siteLanguages);
		var totalLangs = langCodes.length;
		var firstLangCode = langCodes[0];
		var firstLangName = siteLanguages[firstLangCode];

		// Step 1: Generate author in the first language
		$('#alvobot-ai-author-loading p').text(
			'Gerando autor em ' + firstLangName + ' (1/' + totalLangs + ')...'
		);

		var params = {
			niche: niche,
			site_title: typeof alvobotAI !== 'undefined' && alvobotAI.site_title ? alvobotAI.site_title : '',
			country: $('#ai-author-country').val().trim() || 'Brasil',
			language: firstLangName,
		};

		window.AlvobotAI.generate('generate_author', params, {
			onSuccess: function (response) {
				var author = response.data || response;
				var results = {};
				results[firstLangCode] = author;

				if (totalLangs <= 1) {
					showMultilingualPreview(author, results);
					return;
				}

				// Step 2: Translate bio to remaining languages
				var remainingLangs = langCodes.slice(1);
				var translateIndex = 0;

				function translateNext() {
					if (translateIndex >= remainingLangs.length) {
						showMultilingualPreview(author, results);
						return;
					}

					var targetCode = remainingLangs[translateIndex];
					var targetName = siteLanguages[targetCode];
					var step = translateIndex + 2; // +2 because first step was generation

					$('#alvobot-ai-author-loading p').text(
						'Traduzindo biografia para ' + targetName + ' (' + step + '/' + totalLangs + ')...'
					);

					window.AlvobotAI.generate('translate_bio', {
						text: author.description,
						target_language: targetName,
					}, {
						onSuccess: function (translateResponse) {
							var data = translateResponse.data || translateResponse;
							results[targetCode] = {
								name: author.name,
								description: data.translated_text || '',
								sex: author.sex,
								avatar_url: author.avatar_url,
							};
							translateIndex++;
							translateNext();
						},
						onError: function (error) {
							showAiSection('form');
							var msg = (error && error.message) ? error.message : 'Erro ao traduzir para ' + targetName + '.';
							showAiError(msg);
						},
					});
				}

				translateNext();
			},
			onError: function (error) {
				showAiSection('form');
				var msg = (error && error.message) ? error.message : (typeof error === 'string' ? error : 'Erro ao gerar autor. Tente novamente.');
				showAiError(msg);
			},
		});
	}

	function showMultilingualPreview(firstResult, allResults) {
		// Name and avatar from first result
		$('#ai-preview-name').val(firstResult.name || '');
		$('#ai-preview-avatar-url').val(firstResult.avatar_url || '');

		if (firstResult.avatar_url) {
			$('#alvobot-ai-author-avatar').attr('src', firstResult.avatar_url).show();
		} else {
			$('#alvobot-ai-author-avatar').hide();
		}

		// Hide single bio textarea, show multilingual container
		$('#ai-preview-bio').hide();
		var $multiContainer = $('#ai-preview-bio-multilingual');

		var langCodes = Object.keys(allResults);
		var tabsHtml = '<div class="ab-bio-lang-tabs"><div class="ab-bio-lang-tab-headers" role="tablist">';
		var isFirst = true;

		langCodes.forEach(function (code) {
			var name = siteLanguages[code] || code.toUpperCase();
			tabsHtml += '<button type="button" class="ab-bio-lang-tab-btn ai-bio-tab-btn' + (isFirst ? ' active' : '') + '" data-lang="' + code + '" aria-selected="' + (isFirst ? 'true' : 'false') + '">' + code.toUpperCase() + ' — ' + name + '</button>';
			isFirst = false;
		});
		tabsHtml += '</div>';

		isFirst = true;
		langCodes.forEach(function (code) {
			var bio = (allResults[code] && allResults[code].description) ? allResults[code].description : '';
			tabsHtml += '<div class="ab-bio-lang-tab-content ai-bio-tab-content' + (isFirst ? ' active' : '') + '" data-lang="' + code + '">';
			tabsHtml += '<textarea class="large-text ai-preview-bio-lang" data-lang="' + code + '" rows="5">' + $('<div>').text(bio).html() + '</textarea>';
			tabsHtml += '</div>';
			isFirst = false;
		});
		tabsHtml += '</div>';

		$multiContainer.html(tabsHtml).show();

		// Store first language bio in hidden field for backward compat
		$('#ai-preview-bio').val(allResults[langCodes[0]] ? allResults[langCodes[0]].description : '');

		showAiSection('preview');
	}

	// Generate button
	$generateBtn.on('click', function () {
		var costs = typeof alvobotAI !== 'undefined' && alvobotAI.costs ? alvobotAI.costs : {};
		var generateCost = costs.generate_author || 3;
		var translateCost = costs.translate_bio || 1;
		var numLangs = isMultilingual ? Object.keys(siteLanguages).length : 1;
		// 1 generation + (N-1) translations
		var cost = numLangs > 1 ? generateCost + (numLangs - 1) * translateCost : generateCost;

		if (typeof window.AlvobotAI !== 'undefined') {
			window.AlvobotAI.showCostConfirmation('Gerar Autor', cost, doGenerate);
		} else {
			doGenerate();
		}
	});

	// Regenerate button
	$regenerateBtn.on('click', function () {
		// Clean up multilingual preview state
		$('#ai-preview-bio').show();
		$('#ai-preview-bio-multilingual').hide().empty();

		showAiSection('form');
		hideAiMessages();
		doGenerate();
	});

	function resetApplyBtn() {
		$applyBtn.prop('disabled', false).html('<i data-lucide="circle-check" class="alvobot-icon" style="width: 18px; height: 18px;"></i> Aplicar ao Autor');
		if (typeof lucide !== 'undefined') { lucide.createIcons(); }
	}

	function applySuccess(msg, displayName, avatarUrl) {
		$('#alvobot-ai-author-success-msg').text(msg);
		aiSections.preview.hide();
		aiSections.success.fadeIn(200);
		aiSections.form.fadeIn(200);
		resetApplyBtn();

		// Update Author Box preview
		var $authorBox = $('.alvobot-author-box');
		if ($authorBox.length) {
			$authorBox.find('.author-name a').text(displayName);
			if (avatarUrl) {
				$authorBox.find('.author-avatar img, .author-avatar .avatar').attr('src', avatarUrl);
			}
		}
	}

	// Apply author button
	$applyBtn.on('click', function () {
		var userId = $('#ai-author-user').val();
		var displayName = $('#ai-preview-name').val().trim();
		var avatarUrl = $('#ai-preview-avatar-url').val();

		if (!displayName) {
			showAiError('O nome do autor é obrigatório.');
			return;
		}

		if (typeof window.AlvobotAI === 'undefined') {
			showAiError('Sistema de IA não está carregado.');
			return;
		}

		hideAiMessages();
		$applyBtn.prop('disabled', true).text('Aplicando...');

		var hasMultilingualBios = $('.ai-preview-bio-lang').length > 0;

		if (isMultilingual && hasMultilingualBios) {
			// Multilingual apply: collect all bios from per-language textareas
			var bios = {};
			var firstLangCode = '';
			var firstBio = '';
			$('.ai-preview-bio-lang').each(function () {
				var lang = $(this).data('lang');
				var bio = $(this).val();
				bios[lang] = bio;
				if (!firstLangCode) {
					firstLangCode = lang;
					firstBio = bio;
				}
			});

			// Step 1: Apply name + avatar + first language bio via applyAuthor
			window.AlvobotAI.applyAuthor(
				{
					user_id: userId,
					display_name: displayName,
					description: firstBio,
					avatar_url: avatarUrl,
					lang_code: firstLangCode,
				},
				{
					onSuccess: function () {
						// Step 2: Save all bios via ab_save_bios
						$.post(alvobotAuthorBox.ajaxurl, {
							action: 'ab_save_bios',
							nonce: alvobotAuthorBox.nonce,
							user_id: userId,
							bios: bios,
						}, function () {
							var numLangs = Object.keys(bios).length;
							applySuccess(
								'Autor aplicado com sucesso! Biografias salvas em ' + numLangs + ' idiomas.',
								displayName,
								avatarUrl
							);

							// Update multilingual bios textareas if same user selected
							if ($('#bio-lang-user').val() === userId) {
								Object.keys(bios).forEach(function (lang) {
									var $textarea = $('.ab-bio-textarea[data-lang="' + lang + '"]');
									if ($textarea.length) {
										$textarea.val(bios[lang]);
									}
								});
							}
						});
					},
					onError: function (error) {
						var msg = (error && error.message) ? error.message : (typeof error === 'string' ? error : 'Erro ao aplicar autor.');
						showAiError(msg);
						resetApplyBtn();
					},
				}
			);
		} else {
			// Single language apply
			var description = $('#ai-preview-bio').val().trim();
			var langCode = $('#ai-author-language option:selected').data('code') || '';

			window.AlvobotAI.applyAuthor(
				{
					user_id: userId,
					display_name: displayName,
					description: description,
					avatar_url: avatarUrl,
					lang_code: langCode,
				},
				{
					onSuccess: function (data) {
						var msg = (data && data.message) ? data.message : 'Autor aplicado com sucesso!';
						applySuccess(msg, displayName, avatarUrl);

						// Update author box preview description
						var $authorBox = $('.alvobot-author-box');
						if ($authorBox.length && description) {
							$authorBox.find('.author-description').text(description).show();
						}

						// Update multilingual bio textarea if applicable
						if (langCode && $('#bio-lang-user').val() === userId) {
							var $textarea = $('.ab-bio-textarea[data-lang="' + langCode + '"]');
							if ($textarea.length) {
								$textarea.val(description);
							}
						}
					},
					onError: function (error) {
						var msg = (error && error.message) ? error.message : (typeof error === 'string' ? error : 'Erro ao aplicar autor.');
						showAiError(msg);
						resetApplyBtn();
					},
				}
			);
		}
	});
});
