/* Quiz Funnel — Admin UI */
/* global jQuery, lucide */
(function ($) {
	'use strict';

	var root    = document.getElementById('aqf-root');
	var AJAX    = root.dataset.ajaxurl;
	var NONCE   = root.dataset.nonce;
	var SITEURL = root.dataset.siteurl;

	var state = {
		view:    'list',
		quizzes: JSON.parse(root.dataset.quizzes || '[]'),
		editing: null,
	};

	// ── Defaults ──────────────────────────────────────────────────────────────

	function defaultQuiz() {
		return {
			id: '', title: '', subtitle: '(É rápido e fácil)',
			logo_text: '', logo_url: '',
			color_primary: '#22c55e', color_btn_bg: '#f0fdf4',
			footer_text: '', final_url: '',
			questions: [ defaultQuestion() ],
		};
	}

	function defaultQuestion() {
		return { id: uid('q'), text: '', answers: [ defaultAnswer(), defaultAnswer() ] };
	}

	function defaultAnswer() {
		return { id: uid('a'), text: '', url: '' };
	}

	function uid(prefix) {
		return (prefix || 'id') + '_' + Math.random().toString(36).slice(2, 9);
	}

	// ── Render ────────────────────────────────────────────────────────────────

	function render() {
		$(root).html(state.view === 'list' ? renderList() : renderEditor(state.editing));
		bindEvents();
		if (typeof lucide !== 'undefined') { lucide.createIcons(); }
	}

	// ── List view ─────────────────────────────────────────────────────────────

	function renderList() {
		var newBtn = '<button class="alvobot-btn alvobot-btn-primary js-new-quiz" style="display:inline-flex;align-items:center;gap:6px;">' +
			'<i data-lucide="plus" class="alvobot-icon" style="width:15px;height:15px;"></i> Novo Quiz</button>';

		if (!state.quizzes.length) {
			return '<div class="aqf-empty">' +
				'<i data-lucide="list-checks" style="width:52px;height:52px;color:var(--alvobot-gray-300);"></i>' +
				'<h3>Nenhum quiz criado ainda</h3>' +
				'<p>Crie quizzes interativos que encaminham os visitantes para o artigo certo.</p>' +
				newBtn + '</div>';
		}

		var cards = state.quizzes.map(function (quiz) {
			var qcount   = (quiz.questions || []).length;
			var sc       = '[alvobot_quiz id=&quot;' + quiz.id + '&quot;]';
			var fullUrl  = esc(SITEURL) + '?alvobot_quiz=' + quiz.id;

			return '<div class="aqf-card">' +
				'<div class="aqf-card__hd">' +
					'<h3 class="aqf-card__title">' + esc(quiz.title) + '</h3>' +
					'<div class="aqf-card__actions">' +
						'<button class="alvobot-btn alvobot-btn-secondary js-edit-quiz" data-id="' + quiz.id + '">Editar</button>' +
						'<button class="alvobot-btn aqf-btn-danger js-del-quiz" data-id="' + quiz.id + '">Excluir</button>' +
					'</div>' +
				'</div>' +
				'<div class="aqf-card__bd">' +
					'<div class="aqf-code-row">' +
						'<span class="aqf-label">Shortcode</span>' +
						'<code class="aqf-code">' + sc + '</code>' +
						'<button class="aqf-copy-btn" data-copy="' + sc + '">Copiar</button>' +
					'</div>' +
					'<div class="aqf-code-row">' +
						'<span class="aqf-label">Full-page</span>' +
						'<code class="aqf-code">' + fullUrl + '</code>' +
						'<button class="aqf-copy-btn" data-copy="' + fullUrl + '">Copiar</button>' +
					'</div>' +
					'<div class="aqf-card__meta">' +
						'<i data-lucide="help-circle" style="width:13px;height:13px;vertical-align:middle;margin-right:4px;"></i>' +
						qcount + ' pergunta' + (qcount !== 1 ? 's' : '') +
					'</div>' +
				'</div>' +
			'</div>';
		}).join('');

		return '<div class="aqf-toolbar">' +
			'<h2 class="aqf-toolbar__title">Meus Quizzes <span class="aqf-badge">' + state.quizzes.length + '</span></h2>' +
			newBtn +
		'</div>' +
		'<div class="aqf-grid">' + cards + '</div>';
	}

	// ── Editor view ───────────────────────────────────────────────────────────

	function renderEditor(quiz) {
		var isNew     = !quiz.id;
		var qBlocks   = (quiz.questions || []).map(renderQuestion).join('');
		var emptyMsg  = quiz.questions.length === 0
			? '<div class="aqf-empty-q">Adicione pelo menos uma pergunta.</div>' : '';

		return '<div class="aqf-editor">' +
			/* topbar */
			'<div class="aqf-editor__topbar">' +
				'<button class="aqf-back-btn js-back">' +
					'<i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Voltar' +
				'</button>' +
				'<span class="aqf-editor__heading">' + (isNew ? 'Novo Quiz' : 'Editar Quiz') + '</span>' +
				'<button class="alvobot-btn alvobot-btn-primary js-save-quiz" style="display:inline-flex;align-items:center;gap:6px;">' +
					'<i data-lucide="save" style="width:15px;height:15px;"></i> Salvar Quiz' +
				'</button>' +
			'</div>' +

			/* two-column */
			'<div class="aqf-editor__layout">' +

				/* sidebar */
				'<div class="aqf-editor__sidebar">' + renderSidebar(quiz) + '</div>' +

				/* main: questions */
				'<div class="aqf-editor__main">' +
					'<div class="aqf-editor__q-header">' +
						'<h3>Perguntas</h3>' +
						'<button class="alvobot-btn alvobot-btn-secondary js-add-q" style="display:inline-flex;align-items:center;gap:5px;">' +
							'<i data-lucide="plus" style="width:14px;height:14px;"></i> Adicionar Pergunta' +
						'</button>' +
					'</div>' +
					'<div id="aqf-qlist">' + qBlocks + emptyMsg + '</div>' +
				'</div>' +

			'</div>' +

			'<div class="aqf-save-status" id="aqf-save-status"></div>' +
		'</div>';
	}

	function renderSidebar(quiz) {
		return card('Identidade',
				field('Título *',              'text',  'aqf-f-title',         quiz.title,         'Ex: Qual é o melhor cartão para você?') +
				field('Subtítulo',             'text',  'aqf-f-subtitle',      quiz.subtitle,      'Ex: (É rápido e fácil)') +
				field('Logo — texto',          'text',  'aqf-f-logo-text',     quiz.logo_text,     'Nome da marca') +
				field('Logo — URL da imagem',  'url',   'aqf-f-logo-url',      quiz.logo_url,      'https://...')
			) +
			card('Aparência',
				colorField('Cor primária (botões / barra)', 'aqf-f-color-primary', quiz.color_primary || '#22c55e') +
				colorField('Fundo dos botões',              'aqf-f-color-btn-bg',  quiz.color_btn_bg  || '#f0fdf4')
			) +
			card('Destino e Rodapé',
				field('URL final',        'url',  'aqf-f-final-url',    quiz.final_url,   'https://... (redireciona quando não há URL na resposta)') +
				field('Texto do rodapé',  'text', 'aqf-f-footer-text',  quiz.footer_text, 'Ex: © 2025 Marca')
			);
	}

	function card(title, body) {
		return '<div class="alvobot-card" style="margin-bottom:0;">' +
			'<div class="alvobot-card-header"><h2 class="alvobot-card-title">' + title + '</h2></div>' +
			'<div class="alvobot-card-body">' + body + '</div>' +
		'</div>';
	}

	function field(label, type, id, value, placeholder) {
		return '<div class="alvobot-form-field">' +
			'<label class="alvobot-form-label" for="' + id + '">' + label + '</label>' +
			'<input class="alvobot-form-control" type="' + type + '" id="' + id + '" ' +
				'value="' + esc(value || '') + '" placeholder="' + esc(placeholder || '') + '">' +
		'</div>';
	}

	function colorField(label, id, value) {
		return '<div class="alvobot-form-field aqf-color-field">' +
			'<label class="alvobot-form-label" for="' + id + '">' + label + '</label>' +
			'<div class="aqf-color-input">' +
				'<input type="color" id="' + id + '" value="' + esc(value) + '" class="aqf-color-picker">' +
				'<input type="text"  id="' + id + '-txt" value="' + esc(value) + '" ' +
					'class="alvobot-form-control aqf-color-text" maxlength="7" placeholder="#22c55e">' +
			'</div>' +
		'</div>';
	}

	function renderQuestion(q, qi) {
		var answers = (q.answers || []).map(function (a, ai) { return renderAnswer(a, qi, ai); }).join('');
		return '<div class="aqf-q-block" data-qi="' + qi + '">' +
			'<div class="aqf-q-block__hd">' +
				'<span class="aqf-q-num">Pergunta ' + (qi + 1) + '</span>' +
				'<button class="aqf-icon-btn js-del-q" data-qi="' + qi + '" title="Excluir pergunta">' +
					'<i data-lucide="trash-2" style="width:14px;height:14px;"></i>' +
				'</button>' +
			'</div>' +
			'<div class="aqf-q-block__bd">' +
				'<textarea class="alvobot-form-control aqf-q-text" rows="2" ' +
					'placeholder="Texto da pergunta..." data-qi="' + qi + '">' + esc(q.text) + '</textarea>' +
				'<div class="aqf-ans-list" id="aqf-ans-' + qi + '">' + answers + '</div>' +
				'<button class="aqf-add-ans-btn js-add-ans" data-qi="' + qi + '">' +
					'<i data-lucide="plus" style="width:13px;height:13px;"></i> Adicionar Resposta' +
				'</button>' +
			'</div>' +
		'</div>';
	}

	function renderAnswer(a, qi, ai) {
		return '<div class="aqf-ans-row" data-qi="' + qi + '" data-ai="' + ai + '">' +
			'<div class="aqf-ans-fields">' +
				'<input class="alvobot-form-control aqf-ans-text" type="text" ' +
					'placeholder="Texto da resposta..." value="' + esc(a.text) + '" ' +
					'data-qi="' + qi + '" data-ai="' + ai + '">' +
				'<input class="alvobot-form-control aqf-ans-url" type="url" ' +
					'placeholder="URL do artigo (opcional)" value="' + esc(a.url) + '" ' +
					'data-qi="' + qi + '" data-ai="' + ai + '">' +
			'</div>' +
			'<button class="aqf-icon-btn js-del-ans" data-qi="' + qi + '" data-ai="' + ai + '" title="Remover resposta">' +
				'<i data-lucide="x" style="width:14px;height:14px;"></i>' +
			'</button>' +
		'</div>';
	}

	// ── Event binding ─────────────────────────────────────────────────────────

	function bindEvents() {
		$(root).off('click.aqf change.aqf input.aqf');

		// List actions
		$(root).on('click.aqf', '.js-new-quiz', function () {
			state.editing = defaultQuiz();
			state.view    = 'editor';
			render();
		});

		$(root).on('click.aqf', '.js-edit-quiz', function () {
			var id   = $(this).data('id');
			var quiz = state.quizzes.filter(function (q) { return q.id === id; })[0];
			state.editing = JSON.parse(JSON.stringify(quiz));
			state.view    = 'editor';
			render();
		});

		$(root).on('click.aqf', '.js-del-quiz', function () {
			var id = $(this).data('id');
			if (!window.confirm('Excluir este quiz permanentemente?')) { return; }
			$.post(AJAX, { action: 'alvobot_qf_delete', nonce: NONCE, quiz_id: id }, function (res) {
				if (res.success) {
					state.quizzes = state.quizzes.filter(function (q) { return q.id !== id; });
					render();
				}
			});
		});

		// Editor: back
		$(root).on('click.aqf', '.js-back', function () {
			state.view = 'list';
			render();
		});

		// Editor: add question
		$(root).on('click.aqf', '.js-add-q', function () {
			syncForm();
			state.editing.questions.push(defaultQuestion());
			render();
			// scroll to new question
			setTimeout(function () {
				var blocks = root.querySelectorAll('.aqf-q-block');
				if (blocks.length) { blocks[blocks.length - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
			}, 80);
		});

		// Editor: delete question
		$(root).on('click.aqf', '.js-del-q', function () {
			var qi = parseInt($(this).data('qi'), 10);
			if (state.editing.questions.length <= 1) {
				window.alert('O quiz precisa ter pelo menos uma pergunta.');
				return;
			}
			syncForm();
			state.editing.questions.splice(qi, 1);
			render();
		});

		// Editor: add answer
		$(root).on('click.aqf', '.js-add-ans', function () {
			var qi = parseInt($(this).data('qi'), 10);
			syncForm();
			state.editing.questions[qi].answers.push(defaultAnswer());
			render();
		});

		// Editor: delete answer
		$(root).on('click.aqf', '.js-del-ans', function () {
			var qi = parseInt($(this).data('qi'), 10);
			var ai = parseInt($(this).data('ai'), 10);
			if (state.editing.questions[qi].answers.length <= 1) {
				window.alert('A pergunta precisa ter pelo menos uma resposta.');
				return;
			}
			syncForm();
			state.editing.questions[qi].answers.splice(ai, 1);
			render();
		});

		// Editor: save
		$(root).on('click.aqf', '.js-save-quiz', saveQuiz);

		// Copy buttons
		$(root).on('click.aqf', '.aqf-copy-btn', function () {
			copyText(decodeHtml($(this).data('copy')), $(this));
		});

		// Color picker ↔ text sync
		$(root).on('input.aqf', '.aqf-color-picker', function () {
			$('#' + this.id + '-txt').val(this.value);
		});

		$(root).on('change.aqf', '.aqf-color-text', function () {
			var val = this.value.trim();
			if (/^#[0-9a-fA-F]{6}$/.test(val)) {
				$('#' + this.id.replace('-txt', '')).val(val);
			}
		});
	}

	// ── Form → state sync ─────────────────────────────────────────────────────

	function syncForm() {
		if (state.view !== 'editor' || !state.editing) { return; }
		var e = state.editing;

		e.title         = val('aqf-f-title');
		e.subtitle      = val('aqf-f-subtitle');
		e.logo_text     = val('aqf-f-logo-text');
		e.logo_url      = val('aqf-f-logo-url');
		e.color_primary = val('aqf-f-color-primary') || '#22c55e';
		e.color_btn_bg  = val('aqf-f-color-btn-bg')  || '#f0fdf4';
		e.final_url     = val('aqf-f-final-url');
		e.footer_text   = val('aqf-f-footer-text');

		e.questions.forEach(function (q, qi) {
			var $qt = $('.aqf-q-text[data-qi="' + qi + '"]');
			if ($qt.length) { q.text = $qt.val() || ''; }

			q.answers.forEach(function (a, ai) {
				var $t = $('.aqf-ans-text[data-qi="' + qi + '"][data-ai="' + ai + '"]');
				var $u = $('.aqf-ans-url[data-qi="'  + qi + '"][data-ai="' + ai + '"]');
				if ($t.length) { a.text = $t.val() || ''; }
				if ($u.length) { a.url  = $u.val() || ''; }
			});
		});
	}

	function val(id) {
		var el = document.getElementById(id);
		return el ? el.value : '';
	}

	// ── Save ──────────────────────────────────────────────────────────────────

	function saveQuiz() {
		syncForm();

		var quiz = state.editing;

		if (!quiz.title.trim()) {
			window.alert('Informe o título do quiz.');
			var el = document.getElementById('aqf-f-title');
			if (el) { el.focus(); }
			return;
		}

		if (!quiz.questions.length) {
			window.alert('Adicione pelo menos uma pergunta.');
			return;
		}

		var $btn = $(root).find('.js-save-quiz').prop('disabled', true).text('Salvando...');

		$.post(AJAX, {
			action: 'alvobot_qf_save',
			nonce:  NONCE,
			quiz:   JSON.stringify(quiz),
		}, function (res) {
			$btn.prop('disabled', false).html('<i data-lucide="save" style="width:15px;height:15px;"></i> Salvar Quiz');
			if (typeof lucide !== 'undefined') { lucide.createIcons(); }

			if (res.success) {
				var saved = res.data.quiz;
				var found = false;
				state.quizzes = state.quizzes.map(function (q) {
					if (q.id === saved.id) { found = true; return saved; }
					return q;
				});
				if (!found) { state.quizzes.push(saved); }

				state.editing = JSON.parse(JSON.stringify(saved));

				var $st = $('#aqf-save-status');
				$st.text('Quiz salvo com sucesso!').addClass('aqf-status--ok').show();
				setTimeout(function () { $st.fadeOut(400, function () { $st.removeClass('aqf-status--ok').show(); }); }, 3000);

				// Re-render editor to show generated ID in topbar shortcode tip
				render();
			} else {
				var msg = res.data && res.data.message ? res.data.message : 'Erro ao salvar. Tente novamente.';
				window.alert(msg);
			}
		}).fail(function () {
			$btn.prop('disabled', false).html('<i data-lucide="save" style="width:15px;height:15px;"></i> Salvar Quiz');
			window.alert('Erro de conexão. Verifique e tente novamente.');
		});
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	function esc(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function decodeHtml(str) {
		return String(str || '')
			.replace(/&quot;/g, '"')
			.replace(/&amp;/g, '&')
			.replace(/&lt;/g, '<')
			.replace(/&gt;/g, '>');
	}

	function copyText(text, $btn) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				$btn.text('Copiado!');
				setTimeout(function () { $btn.text('Copiar'); }, 2200);
			});
		} else {
			// Fallback
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity  = '0';
			document.body.appendChild(ta);
			ta.focus();
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			$btn.text('Copiado!');
			setTimeout(function () { $btn.text('Copiar'); }, 2200);
		}
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	$(document).ready(function () { render(); });

})(jQuery);
