/**
 * Quiz Builder V2 — Admin UI
 * Uses AlvoBot design system (alvobot-* classes)
 */
(function () {
  'use strict';

  var root, cfg, state;

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    root = document.getElementById('alvobot-qbv2-root');
    if (!root) return;

    cfg = {
      nonce: root.dataset.nonce,
      ajax: root.dataset.ajax,
    };

    state = {
      view: 'list',
      quizzes: JSON.parse(root.dataset.quizzes || '[]'),
      editing: null,
    };

    root.addEventListener('click', onClick);
    root.addEventListener('change', onChange);
    root.addEventListener('input', onInput);
    render();
  }

  /* =========================================================================
   * RENDER
   * ====================================================================== */

  function render() {
    if (state.view === 'list') renderList();
    else renderEditor();
  }

  function renderList() {
    var quizzes = state.quizzes;
    var html = `
      <div class="alvobot-admin-header">
        <div class="alvobot-header-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="alvobot-header-content">
          <h1>Quiz Builder</h1>
          <p>Crie quizzes em tela cheia para engajar visitantes e direcionar para artigos.</p>
        </div>
      </div>
      <div class="qbv2-list-toolbar">
        <div class="qbv2-filter-row">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" class="alvobot-input alvobot-input-sm" id="qbv2-filter-search" placeholder="Buscar por título ou URL..." value="${esc(state.filterSearch || '')}">
        </div>
        <button class="alvobot-btn alvobot-btn-primary" data-action="new">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Novo Quiz
        </button>
      </div>`;

    if (!quizzes.length) {
      html += `
        <div class="alvobot-empty-state">
          <h3>Nenhum quiz criado ainda</h3>
          <p>Crie seu primeiro quiz para engajar visitantes e direcionar para seus artigos.</p>
          <button class="alvobot-btn alvobot-btn-primary" data-action="new">Criar primeiro quiz</button>
        </div>`;
    } else {
      var search = (state.filterSearch || '').toLowerCase();
      var filtered = search ? quizzes.filter(function (q) {
        return (q.title || '').toLowerCase().indexOf(search) !== -1 ||
               (q.redirect_url || '').toLowerCase().indexOf(search) !== -1;
      }) : quizzes;

      html += '<div class="alvobot-grid alvobot-grid-2">';
      if (!filtered.length) {
        html += '<div class="alvobot-empty-state" style="grid-column:1/-1;"><h3>Nenhum quiz encontrado</h3><p>Altere a busca para ver outros quizzes.</p></div>';
      }
      filtered.forEach(function (q) {
        var shortcode = '[alvobot_quiz id="' + q.id + '"]';
        var fullUrl = window.location.origin + '/?alvobot_quiz=' + q.id;
        var redirectDisplay = q.redirect_url ? q.redirect_url.replace(/^https?:\/\//, '') : 'Não configurado';
        html += `
          <div class="alvobot-card">
            <div class="alvobot-card-header">
              <div>
                <h2 class="alvobot-card-title">${esc(q.title)}</h2>
                <p class="alvobot-card-subtitle">${(q.questions || []).length} perguntas</p>
              </div>
            </div>
            <div class="alvobot-card-content">
              <div class="qbv2-tags" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
                ${q.enable_lead ? '<span class="qbv2-tag qbv2-tag--lead">Lead capture</span>' : ''}
              </div>
              <div class="qbv2-redirect-display">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                <span>${esc(redirectDisplay)}</span>
              </div>
              <div class="qbv2-urls">
                <div class="qbv2-url-row">
                  <label>Shortcode:</label>
                  <code>${esc(shortcode)}</code>
                  <button class="qbv2-copy" data-copy="${esc(shortcode)}" title="Copiar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  </button>
                </div>
                <div class="qbv2-url-row">
                  <label>Full-page:</label>
                  <code>${esc(fullUrl)}</code>
                  <button class="qbv2-copy" data-copy="${esc(fullUrl)}" title="Copiar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  </button>
                </div>
              </div>
            </div>
            <div class="alvobot-card-footer">
              <div class="alvobot-btn-group">
                <button class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" data-action="edit" data-id="${q.id}">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Editar
                </button>
                <button class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" data-action="leads" data-id="${q.id}">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  Leads
                </button>
                <button class="alvobot-btn alvobot-btn-danger alvobot-btn-sm" data-action="delete" data-id="${q.id}">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  Excluir
                </button>
              </div>
            </div>
          </div>`;
      });
      html += '</div>';
    }

    root.innerHTML = html;

    // Populate filter dropdown
    var searchInput = document.getElementById('qbv2-filter-search');
    if (searchInput) {
      searchInput.focus();
      // Place cursor at end of text
      var val = searchInput.value;
      searchInput.value = '';
      searchInput.value = val;

      var debounceTimer;
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
          state.filterSearch = searchInput.value;
          render();
        }, 300);
      });
    }
  }

  function renderEditor() {
    var q = state.editing;
    var html = `
      <div class="alvobot-admin-header">
        <div class="alvobot-header-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="alvobot-header-content">
          <h1>${q.id ? 'Editar Quiz' : 'Novo Quiz'}</h1>
          <p>Configure as perguntas, respostas e redirecionamento do seu quiz.</p>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-bottom:20px;">
        <button class="alvobot-btn alvobot-btn-outline" data-action="back">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Voltar
        </button>
        <button class="alvobot-btn alvobot-btn-primary" data-action="save">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Salvar Quiz
        </button>
      </div>

      <div class="qbv2-editor">
        <!-- SIDEBAR -->
        <div class="qbv2-sidebar">

          <div class="alvobot-card qbv2-ai-card">
            <div class="alvobot-card-header">
              <div>
                <h2 class="alvobot-card-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.66 7.66l-.71-.71M4.05 4.05l-.71-.71"/><circle cx="12" cy="12" r="4"/></svg>
                  Gerar com IA
                </h2>
                <p class="alvobot-card-subtitle">Crie perguntas automaticamente a partir de um artigo</p>
              </div>
            </div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">URL do artigo</label>
                <input type="url" class="alvobot-input" id="qbv2-ai-article-url" placeholder="https://seusite.com/artigo">
                <p class="alvobot-description">Cole a URL do artigo que será a base do quiz.</p>
              </div>
              <div id="qbv2-ai-status" style="display:none;margin-bottom:12px;"></div>
              <button class="alvobot-btn alvobot-btn-primary" data-action="ai-generate" style="width:100%;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.66 7.66l-.71-.71M4.05 4.05l-.71-.71"/><circle cx="12" cy="12" r="4"/></svg>
                Gerar 3 perguntas
              </button>
            </div>
          </div>

          <div class="alvobot-card">
            <div class="alvobot-card-header"><div><h2 class="alvobot-card-title">Identidade</h2></div></div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Título do quiz *</label>
                <input type="text" class="alvobot-input" data-field="title" value="${esc(q.title)}" placeholder="Ex: Descubra o cartão ideal">
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Headline</label>
                <input type="text" class="alvobot-input" data-field="headline" value="${esc(q.headline)}" placeholder="Texto chamativo em destaque">
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Sub-headline</label>
                <input type="text" class="alvobot-input" data-field="subheadline" value="${esc(q.subheadline)}" placeholder="Texto de apoio">
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">URL do Logo</label>
                <input type="url" class="alvobot-input" data-field="logo_url" value="${esc(q.logo_url)}" placeholder="https://...">
              </div>
            </div>
          </div>

          <div class="alvobot-card">
            <div class="alvobot-card-header"><div><h2 class="alvobot-card-title">Cores</h2></div></div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Header</label>
                <div class="qbv2-color-row">
                  <input type="color" data-field="color_header" value="${q.color_header || '#1a3a2a'}">
                  <input type="text" class="alvobot-input" data-field="color_header" value="${q.color_header || '#1a3a2a'}" maxlength="7">
                </div>
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Primária (barra progresso)</label>
                <div class="qbv2-color-row">
                  <input type="color" data-field="color_primary" value="${q.color_primary || '#22c55e'}">
                  <input type="text" class="alvobot-input" data-field="color_primary" value="${q.color_primary || '#22c55e'}" maxlength="7">
                </div>
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Acento (barra progresso)</label>
                <div class="qbv2-color-row">
                  <input type="color" data-field="color_accent" value="${q.color_accent || '#f97316'}">
                  <input type="text" class="alvobot-input" data-field="color_accent" value="${q.color_accent || '#f97316'}" maxlength="7">
                </div>
              </div>
            </div>
          </div>

          <div class="alvobot-card">
            <div class="alvobot-card-header"><div><h2 class="alvobot-card-title">Redirecionamento</h2></div></div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">URL padrão de redirecionamento *</label>
                <input type="url" class="alvobot-input" data-field="redirect_url" value="${esc(q.redirect_url)}" placeholder="https://seusite.com/artigo">
                <p class="alvobot-description">URL usada quando a resposta não tem URL própria.</p>
              </div>
            </div>
          </div>

          <div class="alvobot-card">
            <div class="alvobot-card-header"><div><h2 class="alvobot-card-title">Captura de Leads</h2></div></div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-toggle">
                  <input type="checkbox" data-field="enable_lead" ${q.enable_lead ? 'checked' : ''}>
                  <span class="alvobot-toggle-slider"></span>
                </label>
                <span style="margin-left:8px;font-size:13px;font-weight:500;">Ativar captura de leads</span>
              </div>
              <div class="qbv2-lead-fields" ${!q.enable_lead ? 'style="display:none"' : ''}>
                <div class="alvobot-form-field">
                  <label class="alvobot-form-label">Título da tela de lead</label>
                  <input type="text" class="alvobot-input" data-field="lead_title" value="${esc(q.lead_title)}" placeholder="VOCÊ ESTÁ NO CAMINHO CERTO!">
                </div>
                <div class="alvobot-form-field">
                  <label class="alvobot-form-label">Subtítulo</label>
                  <input type="text" class="alvobot-input" data-field="lead_subtitle" value="${esc(q.lead_subtitle)}" placeholder="Preencha para receber seu resultado.">
                </div>
                <div class="alvobot-form-field">
                  <label class="alvobot-form-label">Texto do botão</label>
                  <input type="text" class="alvobot-input" data-field="lead_btn_text" value="${esc(q.lead_btn_text || 'ENVIAR RESPOSTAS')}" placeholder="ENVIAR RESPOSTAS">
                </div>
              </div>
            </div>
          </div>

          <div class="alvobot-card">
            <div class="alvobot-card-header"><div><h2 class="alvobot-card-title">Footer</h2></div></div>
            <div class="alvobot-card-content">
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Texto do footer</label>
                <textarea class="alvobot-input" data-field="footer_text" rows="3" placeholder="Texto legal, disclaimers...">${esc(q.footer_text)}</textarea>
              </div>
              <div class="alvobot-form-field">
                <label class="alvobot-form-label">Links do footer</label>
                <div class="qbv2-footer-links">
                  ${(q.footer_links || []).map(function (l, i) { return '<div class="qbv2-footer-link-row" data-fli="' + i + '"><input type="text" class="alvobot-input" data-fl-field="text" value="' + esc(l.text) + '" placeholder="Texto do link"><input type="url" class="alvobot-input" data-fl-field="url" value="' + esc(l.url) + '" placeholder="URL"><button class="alvobot-btn alvobot-btn-danger alvobot-btn-sm" data-action="remove-footer-link" data-fli="' + i + '">&times;</button></div>'; }).join('')}
                </div>
                <button class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" data-action="add-footer-link" style="margin-top:8px;">+ Link</button>
              </div>
            </div>
          </div>

        </div>

        <!-- MAIN: QUESTIONS -->
        <div class="qbv2-main">
          <div class="alvobot-card">
            <div class="alvobot-card-header">
              <div>
                <h2 class="alvobot-card-title">Perguntas</h2>
                <p class="alvobot-card-subtitle">${(q.questions || []).length} pergunta(s) configurada(s)</p>
              </div>
              <button class="alvobot-btn alvobot-btn-primary alvobot-btn-sm" data-action="add-question">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Pergunta
              </button>
            </div>
            <div class="alvobot-card-content">
              ${(q.questions || []).map(function (question, qi) { return renderQuestion(question, qi); }).join('')}
            </div>
          </div>
        </div>
      </div>`;

    root.innerHTML = html;
  }

  function renderQuestion(q, qi) {
    return '<div class="qbv2-q-block" data-qi="' + qi + '">' +
      '<div class="qbv2-q-head">' +
        '<span class="qbv2-q-num">' + (qi + 1) + '</span>' +
        '<input type="text" class="alvobot-input" data-qi="' + qi + '" data-qfield="text" value="' + esc(q.text) + '" placeholder="Texto da pergunta">' +
        '<button class="alvobot-btn alvobot-btn-danger alvobot-btn-sm" data-action="delete-question" data-qi="' + qi + '">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
        '</button>' +
      '</div>' +
      '<div class="qbv2-answers-list">' +
        (q.answers || []).map(function (a, ai) { return renderAnswer(a, qi, ai); }).join('') +
        '<button class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" data-action="add-answer" data-qi="' + qi + '">+ Resposta</button>' +
      '</div>' +
    '</div>';
  }

  function renderAnswer(a, qi, ai) {
    return '<div class="qbv2-ans-row" data-qi="' + qi + '" data-ai="' + ai + '">' +
      '<input type="text" class="alvobot-input" data-afield="text" value="' + esc(a.text) + '" placeholder="Texto da resposta">' +
      '<input type="url" class="alvobot-input" data-afield="url" value="' + esc(a.url) + '" placeholder="URL de redirecionamento (opcional)">' +
      '<button class="alvobot-btn alvobot-btn-danger alvobot-btn-sm" data-action="delete-answer" data-qi="' + qi + '" data-ai="' + ai + '">&times;</button>' +
    '</div>';
  }

  /* =========================================================================
   * SYNC STATE FROM FORM
   * ====================================================================== */

  function syncForm() {
    var q = state.editing;
    if (!q) return;

    root.querySelectorAll('[data-field]').forEach(function (el) {
      var f = el.dataset.field;
      if (el.type === 'checkbox') {
        q[f] = el.checked;
      } else if (el.type !== 'color') {
        q[f] = el.value;
      }
    });

    q.questions = [];
    root.querySelectorAll('.qbv2-q-block').forEach(function (block) {
      var qi = parseInt(block.dataset.qi, 10);
      var text = (block.querySelector('[data-qfield="text"]') || {}).value || '';
      var answers = [];
      block.querySelectorAll('.qbv2-ans-row').forEach(function (row) {
        answers.push({
          id: 'a_' + qi + '_' + answers.length,
          text: (row.querySelector('[data-afield="text"]') || {}).value || '',
          url: (row.querySelector('[data-afield="url"]') || {}).value || '',
        });
      });
      q.questions.push({ id: 'q_' + qi, text: text, answers: answers });
    });

    q.footer_links = [];
    root.querySelectorAll('.qbv2-footer-link-row').forEach(function (row) {
      q.footer_links.push({
        text: (row.querySelector('[data-fl-field="text"]') || {}).value || '',
        url: (row.querySelector('[data-fl-field="url"]') || {}).value || '',
      });
    });
  }

  /* =========================================================================
   * EVENTS
   * ====================================================================== */

  function onClick(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) {
      var copyBtn = e.target.closest('[data-copy]');
      if (copyBtn) {
        copyToClipboard(copyBtn.dataset.copy).then(function () {
          copyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#12B76A" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
          setTimeout(function () {
            copyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
          }, 1500);
        });
      }
      return;
    }

    var action = btn.dataset.action;

    switch (action) {
      case 'new':
        state.editing = defaultQuiz();
        state.view = 'editor';
        render();
        break;

      case 'edit':
        state.editing = JSON.parse(JSON.stringify(state.quizzes.find(function (q) { return q.id === btn.dataset.id; })));
        state.view = 'editor';
        render();
        break;

      case 'back':
        state.view = 'list';
        state.editing = null;
        render();
        break;

      case 'delete':
        if (!confirm('Excluir este quiz e todos os leads associados?')) return;
        deleteQuiz(btn.dataset.id);
        break;

      case 'save':
        syncForm();
        saveQuiz();
        break;

      case 'add-question':
        syncForm();
        state.editing.questions.push({
          id: 'q_' + Date.now(),
          text: '',
          answers: [
            { id: 'a_new_0', text: '', url: '' },
            { id: 'a_new_1', text: '', url: '' },
          ],
        });
        render();
        break;

      case 'delete-question':
        syncForm();
        state.editing.questions.splice(parseInt(btn.dataset.qi, 10), 1);
        render();
        break;

      case 'add-answer':
        syncForm();
        state.editing.questions[parseInt(btn.dataset.qi, 10)].answers.push({
          id: 'a_' + Date.now(),
          text: '',
          url: '',
        });
        render();
        break;

      case 'delete-answer':
        syncForm();
        state.editing.questions[parseInt(btn.dataset.qi, 10)].answers.splice(parseInt(btn.dataset.ai, 10), 1);
        render();
        break;

      case 'add-footer-link':
        syncForm();
        if (!state.editing.footer_links) state.editing.footer_links = [];
        state.editing.footer_links.push({ text: '', url: '' });
        render();
        break;

      case 'remove-footer-link':
        syncForm();
        state.editing.footer_links.splice(parseInt(btn.dataset.fli, 10), 1);
        render();
        break;

      case 'leads':
        showLeads(btn.dataset.id);
        break;

      case 'ai-generate':
        syncForm();
        aiGenerateQuiz();
        break;
    }
  }

  function onChange(e) {
    var el = e.target;
    if (el.type === 'color' && el.dataset.field) {
      var textInput = el.parentElement.querySelector('input[type="text"][data-field="' + el.dataset.field + '"]');
      if (textInput) textInput.value = el.value;
    }
    if (el.dataset.field === 'enable_lead') {
      var wrap = root.querySelector('.qbv2-lead-fields');
      if (wrap) wrap.style.display = el.checked ? '' : 'none';
    }
  }

  function onInput(e) {
    var el = e.target;
    if (el.type === 'text' && el.dataset.field && el.parentElement.classList.contains('qbv2-color-row')) {
      var colorInput = el.parentElement.querySelector('input[type="color"]');
      if (colorInput && /^#[0-9a-fA-F]{6}$/.test(el.value)) {
        colorInput.value = el.value;
      }
    }
  }

  /* =========================================================================
   * AJAX
   * ====================================================================== */

  function saveQuiz() {
    var q = state.editing;
    if (!q.title) {
      showNotice('O título é obrigatório.', 'error');
      return;
    }

    var fd = new FormData();
    fd.append('action', 'alvobot_qbv2_save');
    fd.append('nonce', cfg.nonce);
    fd.append('quiz', JSON.stringify(q));

    fetch(cfg.ajax, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          var saved = res.data;
          var idx = state.quizzes.findIndex(function (x) { return x.id === saved.id; });
          if (idx >= 0) state.quizzes[idx] = saved;
          else state.quizzes.push(saved);
          state.editing = saved;
          showNotice('Quiz salvo com sucesso!', 'success');
        } else {
          showNotice(res.data || 'Erro ao salvar.', 'error');
        }
      })
      .catch(function () { showNotice('Erro de rede.', 'error'); });
  }

  function deleteQuiz(id) {
    var fd = new FormData();
    fd.append('action', 'alvobot_qbv2_delete');
    fd.append('nonce', cfg.nonce);
    fd.append('id', id);

    fetch(cfg.ajax, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          state.quizzes = state.quizzes.filter(function (q) { return q.id !== id; });
          render();
          showNotice('Quiz excluído.', 'success');
        } else {
          showNotice(res.data || 'Erro ao excluir.', 'error');
        }
      });
  }

  function showLeads(quizId) {
    var fd = new FormData();
    fd.append('action', 'alvobot_qbv2_get_leads');
    fd.append('nonce', cfg.nonce);
    fd.append('quiz_id', quizId);

    fetch(cfg.ajax, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) { showNotice('Erro ao carregar leads.', 'error'); return; }
        var leads = res.data || [];
        var quiz = state.quizzes.find(function (q) { return q.id === quizId; });

        var html = '<div class="alvobot-admin-header">' +
          '<div class="alvobot-header-icon">' +
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' +
          '</div>' +
          '<div class="alvobot-header-content">' +
            '<h1>Leads &mdash; ' + esc(quiz ? quiz.title : quizId) + '</h1>' +
            '<p>' + leads.length + ' lead(s) capturado(s)</p>' +
          '</div>' +
        '</div>' +
        '<div style="display:flex;gap:8px;margin-bottom:20px;">' +
          '<button class="alvobot-btn alvobot-btn-outline" data-action="back">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Voltar' +
          '</button>' +
          '<a class="alvobot-btn alvobot-btn-outline" href="' + cfg.ajax + '?action=alvobot_qbv2_export_leads&nonce=' + cfg.nonce + '&quiz_id=' + quizId + '" target="_blank">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Exportar CSV' +
          '</a>' +
        '</div>';

        if (!leads.length) {
          html += '<div class="alvobot-empty-state"><h3>Nenhum lead capturado ainda</h3><p>Os leads aparecerão aqui quando visitantes preencherem o formulário do quiz.</p></div>';
        } else {
          html += '<div class="alvobot-card"><div class="alvobot-card-content">' +
            '<table class="alvobot-table" style="width:100%;border-collapse:collapse;">' +
            '<thead><tr><th>Nome</th><th>Email</th><th>Data</th><th>IP</th></tr></thead><tbody>';
          leads.forEach(function (l) {
            html += '<tr><td>' + esc(l.name) + '</td><td>' + esc(l.email) + '</td><td>' + esc(l.created_at) + '</td><td>' + esc(l.ip) + '</td></tr>';
          });
          html += '</tbody></table></div></div>';
        }

        state.view = 'leads';
        root.innerHTML = html;

        root.querySelector('[data-action="back"]').addEventListener('click', function () {
          state.view = 'list';
          render();
        });
      });
  }

  /* =========================================================================
   * AI GENERATION
   * ====================================================================== */

  function aiGenerateQuiz() {
    var articleUrl = document.getElementById('qbv2-ai-article-url');
    if (!articleUrl || !articleUrl.value.trim()) {
      showNotice('Informe a URL do artigo para gerar o quiz.', 'error');
      return;
    }

    var url = articleUrl.value.trim();
    var statusEl = document.getElementById('qbv2-ai-status');
    var genBtn = root.querySelector('[data-action="ai-generate"]');

    // Check if AlvobotAI is available
    if (typeof window.AlvobotAI === 'undefined' || typeof alvobotAI === 'undefined') {
      showNotice('Sistema de IA não está carregado. Verifique se o site está conectado ao AlvoBot.', 'error');
      return;
    }

    // Get cost
    var costs = alvobotAI.costs || {};
    var cost = costs.generate_quiz || 2;

    // Show cost confirmation
    window.AlvobotAI.showCostConfirmation('Gerar Quiz', cost, function () {
      // Show loading state
      if (genBtn) {
        genBtn.disabled = true;
        genBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="qbv2-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Gerando...';
      }
      if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.className = 'alvobot-notice alvobot-notice-info';
        statusEl.innerHTML = '<p>Buscando artigo e gerando perguntas...</p>';
      }

      // First: fetch article content via our AJAX endpoint
      var fd = new FormData();
      fd.append('action', 'alvobot_qbv2_fetch_article');
      fd.append('nonce', cfg.nonce);
      fd.append('url', url);

      fetch(cfg.ajax, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.success) {
            throw new Error(res.data || 'Erro ao buscar artigo.');
          }

          var article = res.data;

          // Now call the AI API with article content
          window.AlvobotAI.generate('generate_quiz', {
            article_url: url,
            article_title: article.title || '',
            article_content: article.content || '',
            num_questions: '3',
          }, {
            onSuccess: function (response) {
              var data = response.data || response;
              applyAiResult(data, url);

              if (statusEl) {
                statusEl.className = 'alvobot-notice alvobot-notice-success';
                statusEl.innerHTML = '<p>Quiz gerado com sucesso! Revise as perguntas abaixo.</p>';
                setTimeout(function () { statusEl.style.display = 'none'; }, 3000);
              }
            },
            onError: function (error) {
              var msg = (error && error.message) ? error.message : 'Erro ao gerar quiz.';
              showNotice(msg, 'error');
              if (statusEl) statusEl.style.display = 'none';
            },
            onComplete: function () {
              resetAiButton(genBtn);
            },
          });
        })
        .catch(function (err) {
          showNotice(err.message || 'Erro ao buscar artigo.', 'error');
          if (statusEl) statusEl.style.display = 'none';
          resetAiButton(genBtn);
        });
    });
  }

  function applyAiResult(data, articleUrl) {
    var q = state.editing;

    // Apply generated questions
    if (data.questions && data.questions.length) {
      q.questions = data.questions.map(function (gq, qi) {
        return {
          id: 'q_' + qi,
          text: gq.text || '',
          answers: (gq.answers || []).map(function (ga, ai) {
            return {
              id: 'a_' + qi + '_' + ai,
              text: ga.text || '',
              url: ga.url || '',
            };
          }),
        };
      });
    }

    // Apply suggested headline if empty
    if (!q.headline && data.suggested_headline) {
      q.headline = data.suggested_headline;
    }
    if (!q.subheadline && data.suggested_subheadline) {
      q.subheadline = data.suggested_subheadline;
    }

    // Set redirect URL to the article if not already set
    if (!q.redirect_url && articleUrl) {
      q.redirect_url = articleUrl;
    }

    render();
  }

  function resetAiButton(btn) {
    if (!btn) return;
    btn.disabled = false;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.66 7.66l-.71-.71M4.05 4.05l-.71-.71"/><circle cx="12" cy="12" r="4"/></svg> Gerar 3 perguntas';
  }

  /* =========================================================================
   * HELPERS
   * ====================================================================== */

  function defaultQuiz() {
    return {
      id: '',
      title: '',
      headline: '',
      subheadline: '',
      logo_url: '',
      color_header: '#1a3a2a',
      color_primary: '#22c55e',
      color_accent: '#f97316',
      redirect_url: '',
      enable_lead: false,
      lead_title: '',
      lead_subtitle: '',
      lead_btn_text: 'ENVIAR RESPOSTAS',
      footer_text: '',
      footer_links: [],
      questions: [
        {
          id: 'q_0',
          text: '',
          answers: [
            { id: 'a_0_0', text: '', url: '' },
            { id: 'a_0_1', text: '', url: '' },
          ],
        },
      ],
    };
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    return Promise.resolve();
  }

  function esc(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function showNotice(msg, type) {
    type = type || 'success';
    var n = document.createElement('div');
    n.className = 'alvobot-notice alvobot-notice-' + type;
    n.innerHTML = '<p>' + esc(msg) + '</p>';
    root.prepend(n);
    setTimeout(function () { n.remove(); }, 3000);
  }
})();
