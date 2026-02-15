/**
 * Smart Internal Links - Admin JS
 * Handles meta box generation, bulk generation, and settings.
 */
(function ($) {
	'use strict';

	// ========================
	// Meta Box - Single Post Generation
	// ========================

	$(document).on('click', '#alvobot-sil-generate', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $spinner = $('#alvobot-sil-spinner');
		var $message = $('#alvobot-sil-message');
		var postId = $btn.data('post-id');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$message.hide();

		$.ajax({
			url: alvobotSmartLinks.ajax_url,
			type: 'POST',
			data: {
				action: 'alvobot_generate_smart_links',
				nonce: alvobotSmartLinks.nonce,
				post_id: postId,
			},
			success: function (response) {
				if (response.success) {
					$message
						.html(
							'<p style="color:var(--alvobot-success, #46b450);margin:4px 0;">' +
								response.data.message +
								' <a href="javascript:location.reload()">Atualizar</a></p>'
						)
						.show();
				} else {
					var msg = response.data?.message || 'Erro desconhecido';
					$message
						.html('<p style="color:var(--alvobot-error, #dc3232);margin:4px 0;">' + msg + '</p>')
						.show();
				}
			},
			error: function () {
				$message
					.html(
						'<p style="color:var(--alvobot-error, #dc3232);margin:4px 0;">Erro de conexão.</p>'
					)
					.show();
			},
			complete: function () {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			},
		});
	});

	// ========================
	// Settings Page
	// ========================

	// Save settings
	$(document).on('click', '#alvobot-sil-save-settings', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $msg = $('#alvobot-sil-settings-message');

		var positions = [];
		$('input[name="sil_positions[]"]:checked').each(function () {
			positions.push($(this).val());
		});

		var postTypes = [];
		$('input[name="sil_post_types[]"]:checked').each(function () {
			postTypes.push($(this).val());
		});

		$btn.prop('disabled', true).text('Salvando...');

		$.ajax({
			url: alvobotSmartLinks.ajax_url,
			type: 'POST',
			data: {
				action: 'alvobot_save_smart_links_settings',
				nonce: alvobotSmartLinks.nonce,
				links_per_block: $('#sil_links_per_block').val(),
				num_blocks: $('#sil_num_blocks').val(),
				positions: positions,
				post_types: postTypes,
				button_bg_color: $('#sil_button_bg_color').val(),
				button_text_color: $('#sil_button_text_color').val(),
				button_border_color: $('#sil_button_border_color').val(),
				button_border_size: $('#sil_button_border_size').val(),
			},
			success: function (response) {
				if (response.success) {
					$msg
						.html(
							'<div class="alvobot-notice alvobot-notice-success"><p>' +
								response.data.message +
								'</p></div>'
						)
						.show();
				} else {
					$msg
						.html(
							'<div class="alvobot-notice alvobot-notice-error"><p>' +
								(response.data?.message || 'Erro') +
								'</p></div>'
						)
						.show();
				}
			},
			error: function () {
				$msg
					.html(
						'<div class="alvobot-notice alvobot-notice-error"><p>Erro de conexão.</p></div>'
					)
					.show();
			},
			complete: function () {
				$btn.prop('disabled', false).text('Salvar Configurações');
				setTimeout(function () {
					$msg.fadeOut();
				}, 3000);
			},
		});
	});

	// ========================
	// Bulk Generation
	// ========================

	// Load posts
	$(document).on('click', '#alvobot-sil-load-posts', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $list = $('#alvobot-sil-post-list');
		var category = $('#sil_bulk_category').val();
		var language = $('#sil_bulk_language').val();

		$btn.prop('disabled', true).text('Carregando...');
		$list.html('<p>Carregando posts...</p>');

		$.ajax({
			url: alvobotSmartLinks.ajax_url,
			type: 'POST',
			data: {
				action: 'alvobot_load_posts_for_bulk',
				nonce: alvobotSmartLinks.nonce,
				category: category,
				language: language,
			},
			success: function (response) {
				if (response.success && response.data.posts.length > 0) {
					var html = '<table class="alvobot-table alvobot-table-striped">';
					html +=
						'<thead><tr><th style="width:30px"><input type="checkbox" id="sil-select-all"></th><th>Título</th><th>Status</th><th style="width:40px;text-align:center;">Ver</th></tr></thead><tbody>';

					response.data.posts.forEach(function (post) {
						var status = post.has_links
							? '<span style="color:var(--alvobot-success);">Gerado (' +
								post.generated_at +
								')</span>'
							: '<span style="color:var(--alvobot-gray-400);">Não gerado</span>';

						html +=
							'<tr><td><input type="checkbox" class="sil-post-check" value="' +
							post.id +
							'"></td>';
						html +=
							'<td>' +
							$('<span>').text(post.title).html() +
							'</td>';
						html += '<td>' + status + '</td>';
						html +=
							'<td style="text-align:center;"><a href="' +
							$('<span>').text(post.url).html() +
							'" target="_blank" title="Ver artigo" style="color:var(--alvobot-gray-600);display:inline-flex;">' +
							'<i data-lucide="external-link" class="alvobot-icon"></i>' +
							'</a></td>';
						html += '</tr>';
					});

					html += '</tbody></table>';
					html +=
						'<p style="margin-top:var(--alvobot-space-md);font-size:var(--alvobot-font-size-sm);color:var(--alvobot-gray-600);">' +
						'<span id="sil-selected-count">0</span> posts selecionados × 2 créditos = ' +
						'<strong id="sil-total-credits">0</strong> créditos' +
						'</p>';
					html +=
						'<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-bulk-generate" disabled>Gerar para Selecionados</button>';
					$list.html(html);
					if (typeof lucide !== 'undefined') {
						lucide.createIcons();
					}
				} else {
					$list.html(
						'<p>Nenhum post encontrado com esses filtros.</p>'
					);
				}
			},
			error: function () {
				$list.html('<p style="color:var(--alvobot-error);">Erro ao carregar posts.</p>');
			},
			complete: function () {
				$btn.prop('disabled', false).text('Carregar Posts');
			},
		});
	});

	// Select all
	$(document).on('change', '#sil-select-all', function () {
		$('.sil-post-check').prop('checked', $(this).is(':checked')).trigger('change');
	});

	// Update count
	$(document).on('change', '.sil-post-check', function () {
		var count = $('.sil-post-check:checked').length;
		$('#sil-selected-count').text(count);
		$('#sil-total-credits').text(count * 2);
		$('#alvobot-sil-bulk-generate').prop('disabled', count === 0);
	});

	// Bulk generate
	$(document).on('click', '#alvobot-sil-bulk-generate', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var postIds = [];
		$('.sil-post-check:checked').each(function () {
			postIds.push(parseInt($(this).val()));
		});

		if (postIds.length === 0) return;

		var total = postIds.length;
		var current = 0;
		var success = 0;
		var failed = 0;

		$btn.prop('disabled', true);

		// Add progress bar
		var $progress = $(
			'<div id="sil-bulk-progress" style="margin-top:var(--alvobot-space-md);">' +
				'<div style="background:var(--alvobot-gray-100);border-radius:var(--alvobot-radius-md);overflow:hidden;height:24px;">' +
				'<div id="sil-progress-bar" style="background:var(--alvobot-primary);height:100%;width:0;transition:width 0.3s;display:flex;align-items:center;justify-content:center;color:var(--alvobot-white);font-size:var(--alvobot-font-size-xs);font-weight:600;">0%</div>' +
				'</div>' +
				'<p id="sil-progress-text" style="margin:var(--alvobot-space-sm) 0;font-size:var(--alvobot-font-size-sm);color:var(--alvobot-gray-600);">Gerando...</p>' +
				'</div>'
		);
		$btn.after($progress);

		function processNext() {
			if (current >= total) {
				$('#sil-progress-text').html(
					'<strong>Concluído!</strong> ' +
						success +
						' sucesso, ' +
						failed +
						' falha(s)'
				);
				$btn.prop('disabled', false).text('Gerar para Selecionados');
				return;
			}

			var postId = postIds[current];
			$('#sil-progress-text').text(
				'Gerando ' + (current + 1) + ' de ' + total + '...'
			);

			$.ajax({
				url: alvobotSmartLinks.ajax_url,
				type: 'POST',
				data: {
					action: 'alvobot_bulk_generate_smart_links',
					nonce: alvobotSmartLinks.nonce,
					post_id: postId,
				},
				success: function (response) {
					if (response.success) {
						success++;
						$('.sil-post-check[value="' + postId + '"]')
							.closest('tr')
							.find('td:nth-child(3)')
							.html(
								'<span style="color:var(--alvobot-success);">Gerado agora</span>'
							);
					} else {
						failed++;
						$('.sil-post-check[value="' + postId + '"]')
							.closest('tr')
							.find('td:nth-child(3)')
							.html(
								'<span style="color:var(--alvobot-error);">Erro: ' +
									(response.data?.message || '?') +
									'</span>'
							);
					}
				},
				error: function () {
					failed++;
				},
				complete: function () {
					current++;
					var pct = Math.round((current / total) * 100);
					$('#sil-progress-bar')
						.css('width', pct + '%')
						.text(pct + '%');
					processNext();
				},
			});
		}

		processNext();
	});
})(jQuery);
