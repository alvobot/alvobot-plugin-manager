jQuery(document).ready(function ($) {
	// Função para gerenciar visibilidade do container de notices
	function updateNoticesVisibility() {
		$('.alvobot-notice-container').each(function () {
			const $container = $(this);
			const $notices = $container.find('.alvobot-pro-notices');

			// Verifica se há notices visíveis ou conteúdo real
			const hasVisibleNotices = $notices.children().filter(':visible').length > 0;
			const hasOtherContent =
				$container
					.children()
					.not('.alvobot-pro-notices')
					.filter(function () {
						return $.trim($(this).text()) !== '' || $(this).find('*').length > 0;
					}).length > 0;

			if (hasVisibleNotices || hasOtherContent) {
				$container.removeClass('alvobot-notice-container-hidden').show();
			} else {
				$container.addClass('alvobot-notice-container-hidden').hide();
			}
		});
	}

	// Executa na inicialização
	updateNoticesVisibility();

	// Observa mudanças no DOM para atualizar visibilidade
	if (window.MutationObserver) {
		const observer = new MutationObserver(function () {
			updateNoticesVisibility();
		});

		$('.alvobot-notice-container').each(function () {
			observer.observe(this, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class', 'style'],
			});
		});
	}

	// Função para exibir notificações
	function showNotice(message, type = 'success') {
		const notice = $('<div>')
			.addClass('alvobot-pro-notice')
			.addClass('alvobot-pro-notice-' + type)
			.text(message)
			.hide();

		$('.alvobot-pro-notices').prepend(notice);
		notice.fadeIn();

		// Atualiza visibilidade após adicionar o notice
		updateNoticesVisibility();

		setTimeout(function () {
			notice.fadeOut(300, function () {
				$(this).remove();
				// Atualiza visibilidade após remover o notice
				updateNoticesVisibility();
			});
		}, 3000);
	}

	// Manipulador para toggles de módulos (apenas checkboxes com data-module, não debug toggles)
	$('.alvobot-toggle input[type="checkbox"][data-module]').on('change', function (e) {
		// Previne o comportamento padrão para controlar manualmente
		e.preventDefault();

		var $toggle = $(this);
		var moduleId = $toggle.data('module');

		// Impede a desativação do Plugin Manager
		if (moduleId === 'plugin-manager') {
			$toggle.prop('checked', true);
			showNotice('O Plugin Manager é um módulo essencial e não pode ser desativado.', 'error');
			return;
		}

		var isEnabled = $toggle.is(':checked');

		// Desabilita o toggle durante a requisição
		$toggle.prop('disabled', true);

		// Mostra indicador de carregamento
		var $card = $toggle.closest('.alvobot-card');
		$card.addClass('alvobot-loading');

		// Envia requisição AJAX
		$.ajax({
			url: alvobotPro.ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_pro_toggle_module',
				module: moduleId,
				enabled: isEnabled ? 'true' : 'false',
				nonce: alvobotPro.nonce,
			},
			success: function (response) {
				if (response.success) {
					// Verifica o estado real do módulo retornado pelo servidor
					var serverState = response.data.module_state;

					// Atualiza o toggle de acordo com o estado do servidor
					$toggle.prop('checked', serverState.enabled);

					// Atualiza a classe do card
					if (serverState.enabled) {
						$card.addClass('module-enabled');
					} else {
						$card.removeClass('module-enabled');
					}

					// Mostra a mensagem de sucesso
					showNotice(response.data.message);

					// Recarrega a página após 1 segundo para atualizar todos os estados
					setTimeout(function () {
						window.location.href = window.location.href.split('#')[0];
					}, 1000);
				} else {
					// Reverte o toggle em caso de erro
					$toggle.prop('checked', !isEnabled);
					showNotice(response.data.message, 'error');
				}
			},
			error: function (xhr, status, error) {
				// Reverte o toggle em caso de erro
				$toggle.prop('checked', !isEnabled);
				showNotice('Erro ao comunicar com o servidor: ' + error, 'error');
			},
			complete: function () {
				// Reabilita o toggle e remove o indicador de carregamento
				$toggle.prop('disabled', false);
				$card.removeClass('alvobot-loading');
			},
		});
	});

	// ====================================
	// COLOR PICKER ENHANCEMENT — Popover Style
	// NO DOM moves — just swatch + CSS positioning + backdrop
	// ====================================
	function enhanceColorPickers() {
		var scope = '.alvobot-admin-wrap .wp-picker-container, .alvobot-admin-container .wp-picker-container';
		$(scope).each(function () {
			var $container = $(this);
			if ($container.data('alvobot-enhanced')) return;
			$container.data('alvobot-enhanced', true);

			var $result = $container.find('.wp-color-result');
			var $inputWrap = $container.find('.wp-picker-input-wrap');
			var $holder = $container.find('.wp-picker-holder');
			var $colorInput = $container.find('input.wp-color-picker');

			// 1) Add color swatch to the trigger button
			var initialColor = $colorInput.val() || $result[0].style.backgroundColor || '#000000';
			var $swatch = $('<span class="alvobot-color-swatch"></span>');
			$swatch.css('background-color', initialColor);
			$result.prepend($swatch);

			// Sync swatch color
			$colorInput.on('change input', function () {
				var val = $(this).val();
				if (val) $swatch.css('background-color', val);
			});
			new MutationObserver(function () {
				var bg = $result[0].style.backgroundColor;
				if (bg) $swatch.css('background-color', bg);
			}).observe($result[0], { attributes: true, attributeFilter: ['style'] });

			// 2) Add a backdrop element (does NOT wrap/move existing elements)
			var $backdrop = $('<div class="alvobot-color-backdrop"></div>');
			$container.append($backdrop);

			// 3) Position the holder below the input-wrap and size the backdrop
			function updatePositions() {
				var isOpen = !$inputWrap.hasClass('hidden');
				$container.toggleClass('alvobot-picker-open', isOpen);

				if (!isOpen) {
					$backdrop.hide();
					return;
				}

				// Wait a tick for Iris to render and input-wrap to layout
				setTimeout(function () {
					var buttonH = $result.outerHeight();
					var inputWrapH = $inputWrap.outerHeight() || 0;
					var holderH = $holder.outerHeight() || 0;
					var gap = 8;

					// Position holder below input-wrap
					$holder.css('top', (buttonH + gap + inputWrapH) + 'px');

					// Size & position backdrop to cover both
					var totalH = inputWrapH + holderH;
					$backdrop.css({
						top: (buttonH + gap) + 'px',
						height: totalH + 'px',
						width: Math.max($inputWrap.outerWidth(), $holder.outerWidth(), 280) + 'px'
					}).show();
				}, 20);
			}

			// Watch for open/close
			new MutationObserver(updatePositions).observe($inputWrap[0], { attributes: true, attributeFilter: ['class'] });

			// 4) Close on outside click
			$(document).on('mousedown', function (e) {
				if ($container.hasClass('alvobot-picker-open') && !$(e.target).closest($container).length) {
					$result.trigger('click');
				}
			});
		});
	}

	// Run after wpColorPicker has initialized
	setTimeout(enhanceColorPickers, 300);

	// Watch for dynamically added color pickers
	if (window.MutationObserver) {
		var cpObserver = new MutationObserver(function (mutations) {
			for (var m = 0; m < mutations.length; m++) {
				for (var n = 0; n < mutations[m].addedNodes.length; n++) {
					var node = mutations[m].addedNodes[n];
					if (node.nodeType === 1 && ($(node).hasClass('wp-picker-container') || $(node).find('.wp-picker-container').length)) {
						setTimeout(enhanceColorPickers, 200);
						return;
					}
				}
			}
		});
		$('.alvobot-admin-wrap, .alvobot-admin-container').each(function () {
			cpObserver.observe(this, { childList: true, subtree: true });
		});
	}

	// Manipulador do formulário de registro
	$('form[name="grp_registration"]').on('submit', function (e) {
		e.preventDefault();

		const form = $(this);
		const submitButton = form.find('input[type="submit"]');
		const appPassword = form.find('input[name="grp_app_password"]').val();

		if (!appPassword) {
			showNotice('Por favor, insira a senha do aplicativo.', 'error');
			return;
		}

		submitButton.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'grp_register_site',
				nonce: $('#grp_register_nonce').val(),
				app_password: appPassword,
			},
			success: function (response) {
				if (response.success) {
					showNotice('Site registrado com sucesso!');
					setTimeout(function () {
						window.location.reload();
					}, 1500);
				} else {
					showNotice(response.data.message || 'Erro ao registrar o site.', 'error');
				}
			},
			error: function () {
				showNotice('Erro ao conectar com o servidor.', 'error');
			},
			complete: function () {
				submitButton.prop('disabled', false);
			},
		});
	});

	// Manipulador do formulário de configurações
	$('form[name="grp_settings"]').on('submit', function (e) {
		e.preventDefault();

		const form = $(this);
		const submitButton = form.find('input[type="submit"]');
		const formData = form.serialize();

		submitButton.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'grp_save_settings',
				nonce: $('#grp_settings_nonce').val(),
				settings: formData,
			},
			success: function (response) {
				if (response.success) {
					showNotice('Configurações salvas com sucesso!');
				} else {
					showNotice(response.data.message || 'Erro ao salvar as configurações.', 'error');
				}
			},
			error: function () {
				showNotice('Erro ao conectar com o servidor.', 'error');
			},
			complete: function () {
				submitButton.prop('disabled', false);
			},
		});
	});

	// Manipulador do botão de reset
	$('.grp-reset-button').on('click', function (e) {
		e.preventDefault();

		if (!confirm('Tem certeza que deseja resetar o plugin? Todas as configurações serão perdidas.')) {
			return;
		}

		const button = $(this);
		button.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'grp_reset_plugin',
				nonce: $('#grp_reset_nonce').val(),
			},
			success: function (response) {
				if (response.success) {
					showNotice('Plugin resetado com sucesso!');
					setTimeout(function () {
						window.location.reload();
					}, 1500);
				} else {
					showNotice(response.data.message || 'Erro ao resetar o plugin.', 'error');
				}
			},
			error: function () {
				showNotice('Erro ao conectar com o servidor.', 'error');
			},
			complete: function () {
				button.prop('disabled', false);
			},
		});
	});

	// Atualiza a tabela de log em tempo real
	function updateActivityLog() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'grp_get_activity_log',
				nonce: $('#grp_log_nonce').val(),
			},
			success: function (response) {
				if (response.success && response.data.log) {
					const logTable = $('.activity-log table tbody');
					logTable.empty();

					if (response.data.log.length === 0) {
						logTable.append('<tr><td colspan="3">Nenhuma atividade registrada.</td></tr>');
					} else {
						response.data.log.forEach(function (entry) {
							logTable.append(
								'<tr>' +
									'<td>' +
									entry.date +
									'</td>' +
									'<td>' +
									entry.action +
									'</td>' +
									'<td>' +
									entry.status +
									'</td>' +
									'</tr>',
							);
						});
					}
				}
			},
		});
	}

	// Atualiza o log a cada 30 segundos
	if ($('.activity-log').length) {
		setInterval(updateActivityLog, 30000);
	}

	// Copiar token para a área de transferência
	$('.copy-token').on('click', function (e) {
		e.preventDefault();

		const token = $(this).data('token');
		const tempInput = $('<input>');

		$('body').append(tempInput);
		tempInput.val(token).select();
		document.execCommand('copy');
		tempInput.remove();

		showNotice('Token copiado para a área de transferência!');
	});

	// Manipulador do botão "Refazer Registro"
	$('#retry-registration-form').on('submit', function (e) {
		e.preventDefault();

		const form = $(this);
		const submitButton = form.find('.retry-registration-btn');

		if (!confirm('Tem certeza que deseja refazer o registro? Uma nova senha de aplicativo será gerada.')) {
			return;
		}

		submitButton.prop('disabled', true).val('Processando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'grp_retry_registration',
				nonce: $('#retry_registration_nonce').val(),
			},
			success: function (response) {
				if (response.success) {
					showNotice('Registro refeito com sucesso!');
					setTimeout(function () {
						window.location.reload();
					}, 1500);
				} else {
					showNotice(response.data.message || 'Erro ao refazer o registro.', 'error');
				}
			},
			error: function () {
				showNotice('Erro ao conectar com o servidor.', 'error');
			},
			complete: function () {
				submitButton.prop('disabled', false).val('Refazer Registro');
			},
		});
	});
});
