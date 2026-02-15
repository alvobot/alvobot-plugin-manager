/**
 * Smart Internal Links - Admin JS
 * Handles meta box generation, bulk generation, settings, and link editing.
 */
(function ($) {
	'use strict';

	var positionLabels = {
		after_first: 'Após 1° parágrafo',
		middle: 'No meio do artigo',
		before_last: 'Antes do último parágrafo',
	};

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
						.html('<p style="color:var(--alvobot-error, #dc3232);margin:4px 0;">' + escHtml(msg) + '</p>')
						.show();
				}
			},
			error: function () {
				$message
					.html(
						'<p style="color:var(--alvobot-error, #dc3232);margin:4px 0;">Erro de conexão. Tente novamente.</p>'
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

		// Validação: pelo menos 1 posição e 1 post type
		if (positions.length === 0) {
			$msg
				.html(
					'<div class="alvobot-notice alvobot-notice-error"><p>Selecione pelo menos uma posição.</p></div>'
				)
				.show();
			return;
		}

		if (postTypes.length === 0) {
			$msg
				.html(
					'<div class="alvobot-notice alvobot-notice-error"><p>Selecione pelo menos um post type.</p></div>'
				)
				.show();
			return;
		}

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
								escHtml(response.data?.message || 'Erro') +
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

	// State for pagination
	var bulkState = {
		page: 1,
		totalPages: 1,
		total: 0,
	};

	// Load posts
	$(document).on('click', '#alvobot-sil-load-posts', function (e) {
		e.preventDefault();
		bulkState.page = 1;
		loadBulkPosts();
	});

	function loadBulkPosts(page) {
		var $btn = $('#alvobot-sil-load-posts');
		var $list = $('#alvobot-sil-post-list');
		var category = $('#sil_bulk_category').val();
		var language = $('#sil_bulk_language').val();

		if (page) {
			bulkState.page = page;
		}

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
				page: bulkState.page,
			},
			success: function (response) {
				if (response.success && response.data.posts.length > 0) {
					bulkState.totalPages = response.data.total_pages || 1;
					bulkState.total = response.data.total || 0;

					var html = '<table class="alvobot-table alvobot-table-striped">';
					html +=
						'<thead><tr>' +
						'<th style="width:30px"><input type="checkbox" id="sil-select-all"></th>' +
						'<th>Título</th>' +
						'<th>Status</th>' +
						'<th style="width:80px;text-align:center;">Ações</th>' +
						'</tr></thead><tbody>';

					response.data.posts.forEach(function (post) {
						var status = post.has_links
							? '<span style="color:var(--alvobot-success);">Gerado (' +
								escHtml(post.generated_at) +
								')</span>'
							: '<span style="color:var(--alvobot-gray-400);">Não gerado</span>';

						html +=
							'<tr><td><input type="checkbox" class="sil-post-check" value="' +
							post.id +
							'"></td>';
						html +=
							'<td>' +
							escHtml(post.title) +
							'</td>';
						html += '<td>' + status + '</td>';
						html += '<td style="text-align:center;display:flex;gap:4px;justify-content:center;">';
						if (post.has_links) {
							html +=
								'<button type="button" class="sil-edit-links-btn" data-post-id="' + post.id + '" title="Editar links" style="background:none;border:none;cursor:pointer;color:var(--alvobot-gray-600);padding:4px;display:inline-flex;">' +
								'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
								'</button>';
						}
						html +=
							'<a href="' +
							escAttr(post.url) +
							'" target="_blank" title="Ver artigo" style="color:var(--alvobot-gray-600);display:inline-flex;padding:4px;">' +
							'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
							'</a>';
						html += '</td>';
						html += '</tr>';
					});

					html += '</tbody></table>';

					// Paginação
					if (bulkState.totalPages > 1) {
						html += '<div style="margin-top:var(--alvobot-space-md);display:flex;align-items:center;gap:var(--alvobot-space-sm);justify-content:space-between;">';
						html += '<span style="font-size:var(--alvobot-font-size-sm);color:var(--alvobot-gray-600);">' +
							bulkState.total + ' posts encontrados (página ' + bulkState.page + ' de ' + bulkState.totalPages + ')</span>';
						html += '<div style="display:flex;gap:var(--alvobot-space-xs);">';
						if (bulkState.page > 1) {
							html += '<button type="button" class="alvobot-btn alvobot-btn-secondary alvobot-btn-sm" id="sil-page-prev">← Anterior</button>';
						}
						if (bulkState.page < bulkState.totalPages) {
							html += '<button type="button" class="alvobot-btn alvobot-btn-secondary alvobot-btn-sm" id="sil-page-next">Próxima →</button>';
						}
						html += '</div></div>';
					}

					html +=
						'<p style="margin-top:var(--alvobot-space-md);font-size:var(--alvobot-font-size-sm);color:var(--alvobot-gray-600);">' +
						'<span id="sil-selected-count">0</span> posts selecionados × 2 créditos = ' +
						'<strong id="sil-total-credits">0</strong> créditos' +
						'</p>';
					html +=
						'<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-bulk-generate" disabled>Gerar para Selecionados</button>';
					$list.html(html);
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
	}

	// Pagination buttons
	$(document).on('click', '#sil-page-prev', function (e) {
		e.preventDefault();
		if (bulkState.page > 1) {
			loadBulkPosts(bulkState.page - 1);
		}
	});

	$(document).on('click', '#sil-page-next', function (e) {
		e.preventDefault();
		if (bulkState.page < bulkState.totalPages) {
			loadBulkPosts(bulkState.page + 1);
		}
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
		var retryQueue = [];

		$btn.prop('disabled', true);

		// Remove previous progress bar if any
		$('#sil-bulk-progress').remove();

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
				// Se tem falhas, oferecer retry
				var resultHtml = '<strong>Concluído!</strong> ' +
					success + ' sucesso, ' + failed + ' falha(s)';

				if (retryQueue.length > 0) {
					resultHtml += ' — <button type="button" class="alvobot-btn alvobot-btn-sm" id="sil-retry-failed">Tentar novamente (' + retryQueue.length + ')</button>';
				}

				$('#sil-progress-text').html(resultHtml);
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
				timeout: 90000, // 90s timeout per post
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
						retryQueue.push(postId);
						$('.sil-post-check[value="' + postId + '"]')
							.closest('tr')
							.find('td:nth-child(3)')
							.html(
								'<span style="color:var(--alvobot-error);">Erro: ' +
									escHtml(response.data?.message || '?') +
									'</span>'
							);
					}
				},
				error: function () {
					failed++;
					retryQueue.push(postId);
					$('.sil-post-check[value="' + postId + '"]')
						.closest('tr')
						.find('td:nth-child(3)')
						.html(
							'<span style="color:var(--alvobot-error);">Erro de conexão</span>'
						);
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

	// Retry failed posts
	$(document).on('click', '#sil-retry-failed', function (e) {
		e.preventDefault();
		// Re-check only the failed posts
		$('.sil-post-check').prop('checked', false);
		var retryIds = [];
		$('.sil-post-check').each(function () {
			var $row = $(this).closest('tr');
			if ($row.find('td:nth-child(3) .alvobot-error, td:nth-child(3) span[style*="error"]').length > 0 ||
				$row.find('td:nth-child(3)').text().indexOf('Erro') >= 0) {
				$(this).prop('checked', true);
				retryIds.push(parseInt($(this).val()));
			}
		});
		if (retryIds.length > 0) {
			$('.sil-post-check').trigger('change');
			$('#alvobot-sil-bulk-generate').trigger('click');
		}
	});

	// ========================
	// Edit Links Modal
	// ========================

	var editState = {
		postId: null,
		meta: null,
		searchTimer: null,
	};

	// Open modal from meta box or bulk table
	$(document).on('click', '.sil-edit-links-btn', function (e) {
		e.preventDefault();
		var postId = $(this).data('post-id');
		if (postId) {
			openEditModal(postId);
		}
	});

	function openEditModal(postId) {
		editState.postId = postId;

		// Create modal if it doesn't exist
		if ($('#sil-edit-modal').length === 0) {
			$('body').append(buildModalHtml());
		}

		var $modal = $('#sil-edit-modal');
		var $body = $modal.find('.sil-modal-body');

		$modal.show();
		$body.html('<div style="text-align:center;padding:40px;color:var(--alvobot-gray-500, #6B7280);">Carregando links...</div>');
		$modal.find('.sil-modal-title').text('Editar Smart Links');
		$modal.find('#sil-modal-save').prop('disabled', true);

		// Fetch links data
		$.ajax({
			url: alvobotSmartLinks.ajax_url,
			type: 'POST',
			data: {
				action: 'alvobot_get_smart_links',
				nonce: alvobotSmartLinks.nonce,
				post_id: postId,
			},
			success: function (response) {
				if (response.success) {
					editState.meta = response.data.meta;
					$modal.find('.sil-modal-title').text('Editar Links — ' + escHtml(response.data.post_title));
					renderEditForm($body);
					$modal.find('#sil-modal-save').prop('disabled', false);
				} else {
					$body.html('<div style="text-align:center;padding:40px;color:var(--alvobot-error, #F63D68);">' + escHtml(response.data?.message || 'Erro ao carregar links') + '</div>');
				}
			},
			error: function () {
				$body.html('<div style="text-align:center;padding:40px;color:var(--alvobot-error, #F63D68);">Erro de conexão.</div>');
			},
		});
	}

	function buildModalHtml() {
		return '<div id="sil-edit-modal" class="sil-edit-modal" style="display:none;">' +
			'<div class="sil-modal-overlay"></div>' +
			'<div class="sil-modal-container">' +
				'<div class="sil-modal-header">' +
					'<h2 class="sil-modal-title">Editar Smart Links</h2>' +
					'<button type="button" class="sil-modal-close-btn" title="Fechar">&times;</button>' +
				'</div>' +
				'<div class="sil-modal-body"></div>' +
				'<div class="sil-modal-footer">' +
					'<div id="sil-modal-message" style="flex:1;"></div>' +
					'<button type="button" class="alvobot-btn alvobot-btn-secondary sil-modal-cancel-btn">Cancelar</button>' +
					'<button type="button" class="alvobot-btn alvobot-btn-primary" id="sil-modal-save">Salvar Alterações</button>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderEditForm($body) {
		var meta = editState.meta;
		if (!meta || !meta.blocks) return;

		var html = '';

		// Enabled toggle
		html += '<div class="sil-edit-field" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--alvobot-gray-200, #E5E7EB);">';
		html += '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">';
		html += '<input type="checkbox" id="sil-edit-enabled" ' + (meta.enabled ? 'checked' : '') + '>';
		html += '<strong>Links ativos</strong>';
		html += '</label>';
		html += '</div>';

		// Blocks
		meta.blocks.forEach(function (block, blockIdx) {
			var posLabel = positionLabels[block.position] || block.position;
			html += '<div class="sil-edit-block" data-block-idx="' + blockIdx + '">';
			html += '<div class="sil-edit-block-header">';
			html += '<span class="sil-edit-block-title">Bloco ' + (blockIdx + 1) + ' — ' + escHtml(posLabel) + '</span>';
			html += '<select class="sil-edit-position" data-block-idx="' + blockIdx + '" style="font-size:12px;padding:2px 6px;">';
			html += '<option value="after_first"' + (block.position === 'after_first' ? ' selected' : '') + '>Após 1° parágrafo</option>';
			html += '<option value="middle"' + (block.position === 'middle' ? ' selected' : '') + '>No meio</option>';
			html += '<option value="before_last"' + (block.position === 'before_last' ? ' selected' : '') + '>Antes do último</option>';
			html += '</select>';
			html += '</div>';
			html += '<div class="sil-edit-links" data-block-idx="' + blockIdx + '">';

			block.links.forEach(function (link, linkIdx) {
				html += buildLinkRow(blockIdx, linkIdx, link);
			});

			html += '</div>';
			html += '<button type="button" class="sil-add-link-btn" data-block-idx="' + blockIdx + '">';
			html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
			html += ' Adicionar link';
			html += '</button>';
			html += '</div>';
		});

		// Disclaimer
		html += '<div class="sil-edit-field" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--alvobot-gray-200, #E5E7EB);">';
		html += '<label style="font-size:13px;font-weight:600;color:var(--alvobot-gray-700, #52525B);display:block;margin-bottom:4px;">Disclaimer</label>';
		html += '<input type="text" id="sil-edit-disclaimer" value="' + escAttr(meta.disclaimer || '') + '" placeholder="Ex: *Você permanecerá neste site." style="width:100%;padding:6px 10px;border:1px solid var(--alvobot-gray-300, #D4D4D8);border-radius:6px;font-size:13px;">';
		html += '</div>';

		$body.html(html);
	}

	function buildLinkRow(blockIdx, linkIdx, link) {
		var targetTitle = link.target_title || '(Post #' + link.post_id + ')';
		var html = '<div class="sil-edit-link-row" data-block-idx="' + blockIdx + '" data-link-idx="' + linkIdx + '" data-post-id="' + link.post_id + '">';

		html += '<div class="sil-edit-link-target">';
		html += '<span class="sil-link-post-label" title="' + escAttr(targetTitle) + '">';
		html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;opacity:0.5;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> ';
		html += escHtml(targetTitle);
		html += '</span>';
		html += '</div>';

		html += '<div class="sil-edit-link-text">';
		html += '<input type="text" class="sil-link-text-input" value="' + escAttr(link.text) + '" placeholder="Texto do link" data-block-idx="' + blockIdx + '" data-link-idx="' + linkIdx + '">';
		html += '</div>';

		html += '<button type="button" class="sil-remove-link-btn" data-block-idx="' + blockIdx + '" data-link-idx="' + linkIdx + '" title="Remover link">';
		html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
		html += '</button>';

		html += '</div>';
		return html;
	}

	// Close modal
	$(document).on('click', '.sil-modal-overlay, .sil-modal-close-btn, .sil-modal-cancel-btn', function () {
		$('#sil-edit-modal').hide();
		editState.postId = null;
		editState.meta = null;
	});

	// ESC to close
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $('#sil-edit-modal').is(':visible')) {
			$('#sil-edit-modal').hide();
			editState.postId = null;
			editState.meta = null;
		}
	});

	// Update position in state
	$(document).on('change', '.sil-edit-position', function () {
		var blockIdx = parseInt($(this).data('block-idx'));
		if (editState.meta && editState.meta.blocks[blockIdx]) {
			editState.meta.blocks[blockIdx].position = $(this).val();
		}
	});

	// Update link text in state
	$(document).on('input', '.sil-link-text-input', function () {
		var blockIdx = parseInt($(this).data('block-idx'));
		var linkIdx = parseInt($(this).data('link-idx'));
		if (editState.meta && editState.meta.blocks[blockIdx] && editState.meta.blocks[blockIdx].links[linkIdx]) {
			editState.meta.blocks[blockIdx].links[linkIdx].text = $(this).val();
		}
	});

	// Remove link
	$(document).on('click', '.sil-remove-link-btn', function () {
		var blockIdx = parseInt($(this).data('block-idx'));
		var linkIdx = parseInt($(this).data('link-idx'));

		if (editState.meta && editState.meta.blocks[blockIdx]) {
			editState.meta.blocks[blockIdx].links.splice(linkIdx, 1);

			// If block is now empty, remove it
			if (editState.meta.blocks[blockIdx].links.length === 0) {
				editState.meta.blocks.splice(blockIdx, 1);
			}

			// Re-render
			renderEditForm($('#sil-edit-modal .sil-modal-body'));
		}
	});

	// Add link - show search
	$(document).on('click', '.sil-add-link-btn', function () {
		var blockIdx = parseInt($(this).data('block-idx'));
		var $btn = $(this);

		// Remove any existing search box
		$('.sil-post-search-wrap').remove();

		var searchHtml = '<div class="sil-post-search-wrap" data-block-idx="' + blockIdx + '" style="margin-top:8px;">';
		searchHtml += '<input type="text" class="sil-post-search-input" placeholder="Buscar post por título..." style="width:100%;padding:8px 12px;border:1px solid var(--alvobot-gray-300, #D4D4D8);border-radius:6px;font-size:13px;" autofocus>';
		searchHtml += '<div class="sil-post-search-results" style="display:none;"></div>';
		searchHtml += '</div>';

		$btn.after(searchHtml);
		$btn.next('.sil-post-search-wrap').find('.sil-post-search-input').trigger('focus');
	});

	// Search posts as user types
	$(document).on('input', '.sil-post-search-input', function () {
		var $input = $(this);
		var query = $input.val().trim();
		var $results = $input.closest('.sil-post-search-wrap').find('.sil-post-search-results');

		clearTimeout(editState.searchTimer);

		if (query.length < 2) {
			$results.hide().empty();
			return;
		}

		// Collect existing post IDs to exclude
		var excludeIds = [editState.postId];
		if (editState.meta && editState.meta.blocks) {
			editState.meta.blocks.forEach(function (block) {
				block.links.forEach(function (link) {
					excludeIds.push(link.post_id);
				});
			});
		}

		editState.searchTimer = setTimeout(function () {
			$.ajax({
				url: alvobotSmartLinks.ajax_url,
				type: 'POST',
				data: {
					action: 'alvobot_search_posts_for_links',
					nonce: alvobotSmartLinks.nonce,
					search: query,
					exclude: excludeIds,
				},
				success: function (response) {
					if (response.success && response.data.posts.length > 0) {
						var html = '';
						response.data.posts.forEach(function (post) {
							html += '<div class="sil-search-result" data-post-id="' + post.id + '" data-post-title="' + escAttr(post.title) + '" data-post-url="' + escAttr(post.url) + '">';
							html += '<span class="sil-search-result-title">' + escHtml(post.title) + '</span>';
							html += '<span class="sil-search-result-type">' + escHtml(post.type) + '</span>';
							html += '</div>';
						});
						$results.html(html).show();
					} else {
						$results.html('<div style="padding:8px 12px;color:var(--alvobot-gray-500, #6B7280);font-size:12px;">Nenhum post encontrado</div>').show();
					}
				},
			});
		}, 300);
	});

	// Select post from search results
	$(document).on('click', '.sil-search-result[data-post-id]', function () {
		var postId = parseInt($(this).data('post-id'));
		var postTitle = $(this).data('post-title');
		var postUrl = $(this).data('post-url');
		var blockIdx = parseInt($(this).closest('.sil-post-search-wrap').data('block-idx'));

		if (!postId || !editState.meta || !editState.meta.blocks[blockIdx]) return;

		// Add link to block
		editState.meta.blocks[blockIdx].links.push({
			post_id: postId,
			text: postTitle,
			url: postUrl,
			target_title: postTitle,
		});

		// Remove search and re-render
		$('.sil-post-search-wrap').remove();
		renderEditForm($('#sil-edit-modal .sil-modal-body'));
	});

	// Close search on click outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.sil-post-search-wrap, .sil-add-link-btn').length) {
			$('.sil-post-search-wrap').remove();
		}
	});

	// Save edited links
	$(document).on('click', '#sil-modal-save', function () {
		var $btn = $(this);
		var $msg = $('#sil-modal-message');

		if (!editState.postId || !editState.meta) return;

		// Read current values from the form
		var enabled = $('#sil-edit-enabled').is(':checked') ? '1' : '0';
		var disclaimer = $('#sil-edit-disclaimer').val() || '';

		// Read link texts from inputs (in case state wasn't updated)
		$('.sil-link-text-input').each(function () {
			var bIdx = parseInt($(this).data('block-idx'));
			var lIdx = parseInt($(this).data('link-idx'));
			if (editState.meta.blocks[bIdx] && editState.meta.blocks[bIdx].links[lIdx]) {
				editState.meta.blocks[bIdx].links[lIdx].text = $(this).val();
			}
		});

		// Read positions
		$('.sil-edit-position').each(function () {
			var bIdx = parseInt($(this).data('block-idx'));
			if (editState.meta.blocks[bIdx]) {
				editState.meta.blocks[bIdx].position = $(this).val();
			}
		});

		// Validate
		var hasLinks = false;
		editState.meta.blocks.forEach(function (block) {
			block.links.forEach(function (link) {
				if (link.text && link.text.trim()) hasLinks = true;
			});
		});

		if (!hasLinks) {
			$msg.html('<span style="color:var(--alvobot-error, #F63D68);font-size:13px;">Adicione pelo menos um link com texto.</span>');
			return;
		}

		$btn.prop('disabled', true).text('Salvando...');
		$msg.empty();

		$.ajax({
			url: alvobotSmartLinks.ajax_url,
			type: 'POST',
			data: {
				action: 'alvobot_update_smart_links',
				nonce: alvobotSmartLinks.nonce,
				post_id: editState.postId,
				blocks: JSON.stringify(editState.meta.blocks),
				disclaimer: disclaimer,
				enabled: enabled,
			},
			success: function (response) {
				if (response.success) {
					$msg.html('<span style="color:var(--alvobot-success, #12B76A);font-size:13px;">' + escHtml(response.data.message) + '</span>');
					setTimeout(function () {
						$('#sil-edit-modal').hide();
						editState.postId = null;
						editState.meta = null;
						// Refresh the page if we're on a post edit screen
						if ($('#alvobot-sil-metabox').length) {
							location.reload();
						}
					}, 800);
				} else {
					$msg.html('<span style="color:var(--alvobot-error, #F63D68);font-size:13px;">' + escHtml(response.data?.message || 'Erro ao salvar') + '</span>');
				}
			},
			error: function () {
				$msg.html('<span style="color:var(--alvobot-error, #F63D68);font-size:13px;">Erro de conexão.</span>');
			},
			complete: function () {
				$btn.prop('disabled', false).text('Salvar Alterações');
			},
		});
	});

	// ========================
	// Helper: HTML escaping
	// ========================

	function escHtml(str) {
		if (str === null || str === undefined) return '';
		str = String(str);
		if (!str) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	function escAttr(str) {
		if (str === null || str === undefined) return '';
		str = String(str);
		if (!str) return '';
		return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

})(jQuery);
