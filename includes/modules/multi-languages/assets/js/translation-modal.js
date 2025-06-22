/**
 * Modal de Tradução Aprimorado
 */
(function($) {
    'use strict';

    const TranslationModal = {
        modal: null,
        currentPostId: null,
        selectedLanguages: [],
        
        init() {
            this.createModal();
            this.bindEvents();
            this.initLanguageSelection();
        },
        
        createModal() {
            console.log('AlvoBot Translation Modal: Criando modal HTML');
            const modalHtml = `
                <div id="alvobot-translation-modal" class="alvobot-modal" style="display: none;">
                    <div class="alvobot-modal-content">
                        <div class="alvobot-modal-header">
                            <h2>Traduzir Conteúdo</h2>
                            <span class="alvobot-modal-close">&times;</span>
                        </div>
                        
                        <div class="alvobot-modal-body">
                            <div class="alvobot-translation-step" id="step-languages">
                                <h3>Selecionar Idiomas</h3>
                                <p>Escolha os idiomas para tradução:</p>
                                
                                <div class="alvobot-language-grid">
                                    ${this.createLanguageOptions()}
                                </div>
                                
                                <div class="alvobot-language-actions">
                                    <button type="button" class="alvobot-btn alvobot-btn-outline" id="select-all-languages">
                                        Selecionar Todos
                                    </button>
                                    <button type="button" class="alvobot-btn alvobot-btn-outline" id="clear-all-languages">
                                        Limpar Seleção
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alvobot-translation-step" id="step-options">
                                <h3>Opções de Tradução</h3>
                                
                                <div class="alvobot-options-grid">
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="preserve-formatting" checked>
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Preservar Formatação HTML</strong>
                                            <small>Mantém tags HTML e formatação original</small>
                                        </div>
                                    </label>
                                    
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="translate-meta-fields" checked>
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Traduzir Campos SEO</strong>
                                            <small>Inclui título, descrição e outros metadados</small>
                                        </div>
                                    </label>
                                    
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="translate-links">
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Traduzir Links Internos</strong>
                                            <small>Converte links para versões traduzidas</small>
                                        </div>
                                    </label>
                                    
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="force-overwrite">
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Sobrescrever Existentes</strong>
                                            <small>Substitui traduções já existentes</small>
                                        </div>
                                    </label>
                                    
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="disable-cache">
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Desativar Cache</strong>
                                            <small>Força nova tradução sem usar cache</small>
                                        </div>
                                    </label>
                                    
                                    <label class="alvobot-option-item">
                                        <input type="checkbox" id="background-processing" checked>
                                        <span class="alvobot-checkmark"></span>
                                        <div class="alvobot-option-content">
                                            <strong>Processamento em Background</strong>
                                            <small>Adiciona à fila para processamento assíncrono</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alvobot-translation-step" id="step-progress" style="display: none;">
                                <h3>Processando Tradução</h3>
                                
                                <div class="alvobot-progress-container">
                                    <div class="alvobot-progress-bar">
                                        <div class="alvobot-progress-fill" style="width: 0%"></div>
                                    </div>
                                    <div class="alvobot-progress-text">Preparando...</div>
                                </div>
                                
                                <div class="alvobot-progress-details">
                                    <div class="alvobot-progress-item">
                                        <span class="alvobot-progress-label">Idiomas:</span>
                                        <span class="alvobot-progress-value" id="progress-languages">-</span>
                                    </div>
                                    <div class="alvobot-progress-item">
                                        <span class="alvobot-progress-label">Strings:</span>
                                        <span class="alvobot-progress-value" id="progress-strings">-</span>
                                    </div>
                                    <div class="alvobot-progress-item">
                                        <span class="alvobot-progress-label">Status:</span>
                                        <span class="alvobot-progress-value" id="progress-status">-</span>
                                    </div>
                                </div>
                                
                                <div class="alvobot-progress-log" id="translation-log">
                                    <h4>Log de Tradução</h4>
                                    <div class="alvobot-log-content"></div>
                                </div>
                            </div>
                            
                            <div class="alvobot-translation-step" id="step-complete" style="display: none;">
                                <h3>Tradução Concluída</h3>
                                
                                <div class="alvobot-completion-stats">
                                    <div class="alvobot-stat-item">
                                        <div class="alvobot-stat-number" id="completed-languages">0</div>
                                        <div class="alvobot-stat-label">Idiomas Traduzidos</div>
                                    </div>
                                    <div class="alvobot-stat-item">
                                        <div class="alvobot-stat-number" id="completed-strings">0</div>
                                        <div class="alvobot-stat-label">Strings Traduzidas</div>
                                    </div>
                                    <div class="alvobot-stat-item">
                                        <div class="alvobot-stat-number" id="processing-time">0s</div>
                                        <div class="alvobot-stat-label">Tempo Total</div>
                                    </div>
                                </div>
                                
                                <div class="alvobot-completion-actions">
                                    <button type="button" class="alvobot-btn alvobot-btn-primary" id="view-translations">
                                        Ver Traduções
                                    </button>
                                    <button type="button" class="alvobot-btn alvobot-btn-outline" id="translate-another">
                                        Traduzir Outro
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alvobot-modal-footer">
                            <button type="button" class="alvobot-btn alvobot-btn-outline" id="cancel-translation">
                                Cancelar
                            </button>
                            <button type="button" class="alvobot-btn alvobot-btn-primary" id="start-translation" disabled>
                                Iniciar Tradução
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            this.modal = $('#alvobot-translation-modal');
        },
        
        createLanguageOptions() {
            console.log('AlvoBot Translation Modal: Criando opções de idiomas');
            console.log('AlvoBot Translation Modal: alvobotTranslation object:', window.alvobotTranslation);
            
            if (!window.alvobotTranslation || !window.alvobotTranslation.languages) {
                console.warn('AlvoBot Translation Modal: Nenhum idioma configurado');
                return '<p>Nenhum idioma configurado.</p>';
            }
            
            let html = '';
            Object.entries(window.alvobotTranslation.languages).forEach(([code, lang]) => {
                let flagHtml = '';
                if (lang.flag && (lang.flag.startsWith('http') || lang.flag.startsWith('//'))) {
                    flagHtml = `<img src="${lang.flag}" alt="${lang.name || code}" class="alvobot-flag-icon">`;
                } else {
                    flagHtml = lang.flag || '🏳️';
                }
                html += `
                    <label class="alvobot-language-option" data-lang="${code}">
                        <input type="checkbox" value="${code}">
                        <span class="alvobot-language-flag">${flagHtml}</span>
                        <span class="alvobot-language-name">${lang.name}</span>
                    </label>
                `;
            });
            
            return html;
        },
        
        bindEvents() {
            // Fechar modal
            $(document).on('click', '.alvobot-modal-close, #cancel-translation', () => {
                this.closeModal();
            });
            
            // Seleção de idiomas
            $(document).on('change', '.alvobot-language-option input', () => {
                this.updateLanguageSelection();
            });
            
            $(document).on('click', '#select-all-languages', () => {
                $('.alvobot-language-option input').prop('checked', true);
                this.updateLanguageSelection();
            });
            
            $(document).on('click', '#clear-all-languages', () => {
                $('.alvobot-language-option input').prop('checked', false);
                this.updateLanguageSelection();
            });
            
            // Iniciar tradução
            $(document).on('click', '#start-translation', () => {
                this.startTranslation();
            });
            
            // Ações de conclusão
            $(document).on('click', '#view-translations', () => {
                this.viewTranslations();
            });
            
            // Usa delegação de eventos para botões criados dinamicamente
            $(document).on('click', '#translate-another', (e) => {
                e.preventDefault();
                console.log('AlvoBot Translation Modal: Botão Traduzir Outro clicado');
                this.resetModal();
            });
            
            // Fechar modal clicando fora
            $(document).on('click', '.alvobot-modal', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });
        },
        
        initLanguageSelection() {
            this.updateLanguageSelection();
        },
        
        updateLanguageSelection() {
            this.selectedLanguages = [];
            $('.alvobot-language-option input:checked').each((i, el) => {
                this.selectedLanguages.push($(el).val());
            });
            
            // Atualiza estado do botão
            $('#start-translation').prop('disabled', this.selectedLanguages.length === 0);
            
            // Atualiza visual das opções selecionadas
            $('.alvobot-language-option').removeClass('selected');
            $('.alvobot-language-option input:checked').closest('.alvobot-language-option').addClass('selected');
        },
        
        openModal(postId) {
            this.currentPostId = postId;
            this.resetModal();
            this.modal.fadeIn(300);
            
            // Atualiza informações do post
            this.loadPostInfo(postId);
        },
        
        closeModal() {
            this.modal.fadeOut(300);
            this.resetModal();
        },
        
        resetModal() {
            // Volta para a primeira etapa
            $('.alvobot-translation-step').hide();
            $('#step-languages').show();
            
            // Limpa seleções
            $('.alvobot-language-option input').prop('checked', false);
            this.selectedLanguages = [];
            
            // Reset botão
            $('#start-translation').prop('disabled', true).text('Iniciar Tradução');
        },
        
        loadPostInfo(postId) {
            // Aqui você pode carregar informações específicas do post se necessário
            console.log('Loading post info for:', postId);
        },
        
        startTranslation() {
            if (this.selectedLanguages.length === 0) {
                alert('Selecione pelo menos um idioma para tradução.');
                return;
            }
            
            // Coleta opções
            const options = {
                preserveFormatting: $('#preserve-formatting').is(':checked'),
                translateMetaFields: $('#translate-meta-fields').is(':checked'),
                translateLinks: $('#translate-links').is(':checked'),
                force_overwrite: $('#force-overwrite').is(':checked'),
                disable_cache: $('#disable-cache').is(':checked'),
                background_processing: $('#background-processing').is(':checked')
            };
            
            // Muda para etapa de progresso
            $('.alvobot-translation-step').hide();
            $('#step-progress').show();
            
            if (options.background_processing) {
                this.startBackgroundTranslation(options);
            } else {
                this.startDirectTranslation(options);
            }
        },
        
        startBackgroundTranslation(options) {
            $.ajax({
                url: window.alvobotTranslation.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'alvobot_add_to_translation_queue',
                    nonce: window.alvobotTranslation.nonce,
                    post_id: this.currentPostId,
                    target_languages: this.selectedLanguages,
                    options: options
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.existing) {
                            this.showQueueExisting(response.data);
                        } else {
                            this.showQueueSuccess(response.data);
                        }
                    } else {
                        this.showError(response.data);
                    }
                },
                error: () => {
                    this.showError('Erro de comunicação com o servidor');
                }
            });
        },
        
        startDirectTranslation(options) {
            let completedLanguages = 0;
            const totalLanguages = this.selectedLanguages.length;
            let totalStrings = 0;
            let translatedStrings = 0;
            
            const startTime = Date.now();
            
            // Processa cada idioma sequencialmente
            const processLanguage = (langIndex) => {
                if (langIndex >= this.selectedLanguages.length) {
                    this.showCompletion(completedLanguages, translatedStrings, Date.now() - startTime);
                    return;
                }
                
                const targetLang = this.selectedLanguages[langIndex];
                
                this.updateProgress(
                    (langIndex / totalLanguages) * 100,
                    `Traduzindo para ${targetLang}...`,
                    `${langIndex}/${totalLanguages}`,
                    translatedStrings,
                    'Processando...'
                );
                
                this.addLog('info', `Iniciando tradução para ${targetLang}`);
                
                $.ajax({
                    url: window.alvobotTranslation.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'alvobot_translate_and_create_post',
                        nonce: window.alvobotTranslation.nonce,
                        post_id: this.currentPostId,
                        target_language: targetLang,
                        options: options
                    },
                    success: (response) => {
                        if (response.success) {
                            completedLanguages++;
                            translatedStrings += response.data.strings_translated || 0;
                            
                            this.addLog('success', `Tradução para ${targetLang} concluída`);
                            
                            // Próximo idioma
                            setTimeout(() => processLanguage(langIndex + 1), 500);
                        } else {
                            this.addLog('error', `Erro na tradução para ${targetLang}: ${response.data}`);
                            // Continua para próximo idioma mesmo com erro
                            setTimeout(() => processLanguage(langIndex + 1), 500);
                        }
                    },
                    error: () => {
                        this.addLog('error', `Erro de comunicação na tradução para ${targetLang}`);
                        setTimeout(() => processLanguage(langIndex + 1), 500);
                    }
                });
            };
            
            // Inicia processamento
            processLanguage(0);
        },
        
        updateProgress(percent, text, languages, strings, status) {
            $('.alvobot-progress-fill').css('width', percent + '%');
            $('.alvobot-progress-text').text(text);
            $('#progress-languages').text(languages);
            $('#progress-strings').text(strings);
            $('#progress-status').text(status);
        },
        
        addLog(type, message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `
                <div class="alvobot-log-entry alvobot-log-${type}">
                    <span class="alvobot-log-time">${timestamp}</span>
                    <span class="alvobot-log-message">${message}</span>
                </div>
            `;
            
            $('.alvobot-log-content').append(logEntry);
            $('.alvobot-log-content').scrollTop($('.alvobot-log-content')[0].scrollHeight);
        },
        
        showQueueSuccess(data) {
            console.log('AlvoBot Translation Modal: Exibindo sucesso na fila', data);
            
            this.modal.find('#step-progress').hide();
            this.modal.find('#step-complete').show();
            this.modal.find('.alvobot-modal-footer').hide();
            
            this.modal.find('.alvobot-modal-body').html(`
                <div class="alvobot-feedback-message alvobot-feedback-success">
                    <span class="alvobot-feedback-icon">&#10004;</span>
                    <h4>${data.message}</h4>
                    <p>O post foi adicionado à fila e será processado em segundo plano.</p>
                    <div class="alvobot-feedback-actions">
                        <a href="${window.alvobotTranslation.queue_url}" class="alvobot-btn alvobot-btn-primary">Ver Fila de Tradução</a>
                        <button type="button" class="alvobot-btn alvobot-btn-outline" id="translate-another">Traduzir Outro</button>
                    </div>
                </div>
            `);
        },
        
        showQueueExisting(data) {
            console.log('AlvoBot Translation Modal: Exibindo item existente na fila', data);

            this.modal.find('#step-progress').hide();
            this.modal.find('#step-complete').show();
            this.modal.find('.alvobot-modal-footer').hide();

            this.modal.find('.alvobot-modal-body').html(`
                <div class="alvobot-feedback-message alvobot-feedback-info">
                    <span class="alvobot-feedback-icon">&#8505;</span>
                    <h4>${data.message}</h4>
                    <p>Você pode acompanhar o progresso na fila de tradução.</p>
                    <div class="alvobot-feedback-actions">
                        <a href="${window.alvobotTranslation.queue_url}" class="alvobot-btn alvobot-btn-primary">Ver Fila de Tradução</a>
                        <button type="button" class="alvobot-btn alvobot-btn-outline" id="translate-another">Traduzir Outro</button>
                    </div>
                </div>
            `);
        },
        
        showCompletion(languages, strings, time) {
            $('.alvobot-translation-step').hide();
            $('#step-complete').show();
            
            $('#completed-languages').text(languages);
            $('#completed-strings').text(strings);
            $('#processing-time').text(Math.round(time / 1000) + 's');
        },
        
        showError(message) {
            this.addLog('error', message);
            alert('Erro: ' + message);
        },
        
        viewTranslations() {
            // Redireciona para página de posts ou abre nova aba
            window.open(window.alvobotTranslation.adminUrl + 'edit.php', '_blank');
        }
    };
    
    // Inicializa quando o documento estiver pronto
    $(document).ready(() => {
        console.log('AlvoBot Translation Modal: Inicializando...');
        TranslationModal.init();
        
        // Expõe globalmente para uso em outros scripts
        window.AlvoBotTranslationModal = TranslationModal;
        console.log('AlvoBot Translation Modal: Inicializado com sucesso');
    });
    
    // Removido handler conflitante - a funcionalidade está em translation-interface.js
    
})(jQuery);