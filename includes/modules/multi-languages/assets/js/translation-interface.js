/**
 * Interface de Tradução AlvoBot Multi Languages
 * 
 * Integração com Gutenberg e Elementor para tradução em tempo real
 */

(function($) {
    'use strict';

    // Configuração global
    const AlvoBotTranslation = {
        nonce: alvobotTranslation.nonce || '',
        ajaxUrl: alvobotTranslation.ajaxUrl || '/wp-admin/admin-ajax.php',
        adminUrl: alvobotTranslation.adminUrl || '/wp-admin/',
        currentPostId: alvobotTranslation.postId || 0,
        languages: alvobotTranslation.languages || [],
        languagesList: alvobotTranslation.languagesList || alvobotTranslation.languages || [],
        isTranslating: false,
        translationProgress: {
            current: 0,
            total: 0
        }
    };

    /**
     * Inicialização principal
     */
    $(document).ready(function() {
        console.log('AlvoBot Translation: Inicializando...');
        console.log('AlvoBot Translation: Dados disponíveis:', {
            hasGlobalObject: typeof alvobotTranslation !== 'undefined',
            languages: AlvoBotTranslation.languages,
            languagesList: AlvoBotTranslation.languagesList,
            nonce: AlvoBotTranslation.nonce
        });
        
        // Detecta o tipo de editor e inicializa apropriadamente
        if (window.wp && window.wp.blocks) {
            initGutenbergIntegration();
        } else if (window.elementor) {
            initElementorIntegration();
        }
        
        // Inicializa interface comum
        initCommonInterface();
    });

    /**
     * Integração com Gutenberg
     */
    function initGutenbergIntegration() {
        // Aguarda o editor estar pronto
        wp.domReady(function() {
            // Adiciona meta box de tradução na sidebar
            addGutenbergMetaBox();
            
            // Adiciona botões nos blocos (se possível)
            addGutenbergBlockButtons();
        });
    }

    /**
     * Adiciona meta box na sidebar do Gutenberg
     */
    function addGutenbergMetaBox() {
        // Verifica se estamos em uma nova tradução (parâmetros from_post e new_lang)
        const urlParams = new URLSearchParams(window.location.search);
        const fromPost = urlParams.get('from_post');
        const newLang = urlParams.get('new_lang');
        
        if (!fromPost || !newLang) {
            return; // Não é uma nova tradução
        }

        // Cria o meta box
        const metaBox = createTranslationMetaBox(fromPost, newLang);
        
        // Adiciona à sidebar (se disponível)
        const sidebar = document.querySelector('.edit-post-sidebar');
        if (sidebar) {
            sidebar.appendChild(metaBox);
        } else {
            // Fallback: adiciona ao final da página
            document.body.appendChild(metaBox);
        }
    }

    /**
     * Cria o meta box de tradução
     */
    function createTranslationMetaBox(fromPostId, targetLang) {
        const container = document.createElement('div');
        container.className = 'alvobot-translation-metabox';
        container.innerHTML = `
            <div class="alvobot-card alvobot-fade-in">
                <div class="alvobot-card-header">
                    <h3 class="alvobot-card-title">Tradução Automática</h3>
                    <div class="alvobot-badge alvobot-badge-info">OpenAI</div>
                </div>
                <div class="alvobot-card-content">
                    <div class="alvobot-translation-info">
                        <p>Traduzir conteúdo de <strong>${getLanguageName(getSourceLanguage(fromPostId))}</strong> para <strong>${getLanguageName(targetLang)}</strong></p>
                    </div>
                    
                    <div class="alvobot-translation-progress" style="display: none;">
                        <div class="alvobot-progress-bar">
                            <div class="alvobot-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="alvobot-progress-text">Preparando tradução...</div>
                    </div>
                    
                    <div class="alvobot-translation-actions">
                        <button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-start-translation" data-from-post="${fromPostId}" data-target-lang="${targetLang}">
                            <span class="dashicons dashicons-translation"></span>
                            Traduzir Página
                        </button>
                        <button type="button" class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" id="alvobot-translate-settings">
                            <span class="dashicons dashicons-admin-generic"></span>
                            Configurações
                        </button>
                    </div>
                    
                    <div class="alvobot-translation-options" style="display: none;">
                        <label class="alvobot-checkbox-label">
                            <input type="checkbox" id="translate-meta-fields" checked>
                            Traduzir campos SEO (Yoast, RankMath)
                        </label>
                        <label class="alvobot-checkbox-label">
                            <input type="checkbox" id="preserve-formatting" checked>
                            Preservar formatação HTML
                        </label>
                        <label class="alvobot-checkbox-label">
                            <input type="checkbox" id="translate-links">
                            Traduzir links internos
                        </label>
                    </div>
                </div>
            </div>
        `;

        return container;
    }

    /**
     * Adiciona botões inline nos blocos do Gutenberg
     */
    function addGutenbergBlockButtons() {
        // Esta funcionalidade requer desenvolvimento mais avançado
        // Por agora, vamos focar no meta box
        console.log('Gutenberg block buttons: Funcionalidade futura');
    }

    /**
     * Integração com Elementor
     */
    function initElementorIntegration() {
        // Aguarda o Elementor estar pronto
        $(window).on('elementor:init', function() {
            addElementorTranslationButton();
        });
    }

    /**
     * Adiciona botão de tradução no Elementor
     */
    function addElementorTranslationButton() {
        // Verifica se estamos em uma nova tradução
        const urlParams = new URLSearchParams(window.location.search);
        const fromPost = urlParams.get('from_post');
        const newLang = urlParams.get('new_lang');
        
        if (!fromPost || !newLang) {
            return;
        }

        // Adiciona botão à toolbar do Elementor
        const toolbar = document.querySelector('#elementor-panel-header-menu-button');
        if (toolbar) {
            const translationButton = document.createElement('div');
            translationButton.className = 'elementor-panel-menu-item alvobot-elementor-translate-btn';
            translationButton.innerHTML = `
                <div class="elementor-panel-menu-item-icon">
                    <i class="eicon-globe"></i>
                </div>
                <div class="elementor-panel-menu-item-title">Traduzir com AlvoBot</div>
            `;
            
            translationButton.addEventListener('click', function() {
                showElementorTranslationModal(fromPost, newLang);
            });
            
            toolbar.parentNode.insertBefore(translationButton, toolbar.nextSibling);
        }
    }

    /**
     * Mostra modal de tradução do Elementor
     */
    function showElementorTranslationModal(fromPostId, targetLang) {
        const modal = createTranslationModal(fromPostId, targetLang, 'elementor');
        document.body.appendChild(modal);
        
        // Anima entrada
        setTimeout(() => {
            modal.classList.add('alvobot-modal-show');
        }, 10);
    }

    /**
     * Interface comum para ambos os editores
     */
    function initCommonInterface() {
        // Event listeners
        $(document).on('click', '#alvobot-start-translation', handleStartTranslation);
        $(document).on('click', '#alvobot-translate-settings', toggleTranslationSettings);
        $(document).on('click', '.alvobot-modal-close', closeModal);
        $(document).on('click', '.alvobot-modal-backdrop', closeModal);
        
        // Tecla ESC para fechar modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC
                closeModal(); // Closes generic modals
                if ($('#alvobot-language-selection-modal').length) {
                    $('#alvobot-language-selection-modal').remove(); // Closes specific language selection modal
                }
            }
        });

        // Handler for the translate button in post/page lists
        $(document).on('click', '.alvobot-translate-btn', handleTranslateButtonFromListClick);
    }

    /**
     * Manipula o início da tradução
     */
    function handleStartTranslation(e) {
        e.preventDefault();
        
        if (AlvoBotTranslation.isTranslating) {
            return;
        }

        const $button = $(e.currentTarget);
        const fromPostId = $button.data('from-post');
        const targetLang = $button.data('target-lang');
        
        // Coleta opções
        const options = {
            translateMetaFields: $('#translate-meta-fields').is(':checked'),
            preserveFormatting: $('#preserve-formatting').is(':checked'),
            translateLinks: $('#translate-links').is(':checked')
        };

        startTranslation(fromPostId, targetLang, options);
    }

    /**
     * Inicia o processo de tradução
     */
    async function startTranslation(fromPostId, targetLang, options = {}) {
        AlvoBotTranslation.isTranslating = true;
        
        try {
            // Mostra progresso
            showTranslationProgress();
            updateProgress(0, 'Iniciando tradução...');
            
            // Busca conteúdo do post original
            updateProgress(10, 'Analisando conteúdo...');
            const sourceContent = await fetchPostContent(fromPostId);
            
            if (!sourceContent.success) {
                throw new Error(sourceContent.error || 'Erro ao buscar conteúdo');
            }

            // Inicia tradução
            updateProgress(30, 'Conectando com OpenAI...');
            const translationResult = await translatePostContent(fromPostId, targetLang, options);
            
            if (!translationResult.success) {
                throw new Error(translationResult.error || 'Erro na tradução');
            }

            // Aplica tradução ao editor atual
            updateProgress(80, 'Aplicando tradução...');
            await applyTranslationToEditor(translationResult);
            
            updateProgress(100, 'Tradução concluída!');
            
            // Mostra resultado
            setTimeout(() => {
                showTranslationSuccess(translationResult);
                hideTranslationProgress();
            }, 1000);

        } catch (error) {
            console.error('Erro na tradução:', error);
            showTranslationError(error.message);
            hideTranslationProgress();
        } finally {
            AlvoBotTranslation.isTranslating = false;
        }
    }

    /**
     * Busca conteúdo do post via AJAX
     */
    function fetchPostContent(postId) {
        return $.ajax({
            url: AlvoBotTranslation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alvobot_fetch_post_content',
                post_id: postId,
                nonce: AlvoBotTranslation.nonce
            }
        });
    }

    /**
     * Traduz conteúdo do post via AJAX
     */
    function translatePostContent(postId, targetLang, options) {
        return $.ajax({
            url: AlvoBotTranslation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alvobot_translate_post_content',
                post_id: postId,
                target_lang: targetLang,
                options: options,
                nonce: AlvoBotTranslation.nonce
            }
        });
    }

    /**
     * Aplica tradução ao editor atual
     */
    async function applyTranslationToEditor(translationResult) {
        if (window.wp && window.wp.blocks) {
            // Gutenberg
            await applyGutenbergTranslation(translationResult);
        } else if (window.elementor) {
            // Elementor
            await applyElementorTranslation(translationResult);
        } else {
            // Editor clássico
            await applyClassicEditorTranslation(translationResult);
        }
    }

    /**
     * Aplica tradução no Gutenberg
     */
    function applyGutenbergTranslation(translationResult) {
        return new Promise((resolve) => {
            const blocks = wp.blocks.parse(translationResult.translated_content);
            wp.data.dispatch('core/block-editor').resetBlocks(blocks);
            
            // Aplica título se traduzido
            if (translationResult.translated_title) {
                wp.data.dispatch('core/editor').editPost({
                    title: translationResult.translated_title
                });
            }
            
            resolve();
        });
    }

    /**
     * Aplica tradução no Elementor
     */
    function applyElementorTranslation(translationResult) {
        return new Promise((resolve) => {
            if (window.elementor && elementor.getPreviewView) {
                // Aplica dados do Elementor
                const elementsData = JSON.parse(translationResult.translated_content);
                elementor.getPreviewView().addChildModel(elementsData);
            }
            resolve();
        });
    }

    /**
     * Aplica tradução no editor clássico
     */
    function applyClassicEditorTranslation(translationResult) {
        return new Promise((resolve) => {
            // Editor TinyMCE ou textarea
            const editor = window.tinymce ? tinymce.activeEditor : null;
            
            if (editor) {
                editor.setContent(translationResult.translated_content);
            } else {
                const textarea = document.getElementById('content');
                if (textarea) {
                    textarea.value = translationResult.translated_content;
                }
            }
            
            resolve();
        });
    }

    /**
     * Mostra progresso da tradução
     */
    function showTranslationProgress() {
        const $progress = $('.alvobot-translation-progress');
        const $actions = $('.alvobot-translation-actions');
        
        $actions.hide();
        $progress.show();
    }

    /**
     * Esconde progresso da tradução
     */
    function hideTranslationProgress() {
        const $progress = $('.alvobot-translation-progress');
        const $actions = $('.alvobot-translation-actions');
        
        $progress.hide();
        $actions.show();
    }

    /**
     * Atualiza progresso
     */
    function updateProgress(percentage, message) {
        $('.alvobot-progress-fill').css('width', percentage + '%');
        $('.alvobot-progress-text').text(message);
    }

    /**
     * Mostra sucesso da tradução
     */
    function showTranslationSuccess(result) {
        showNotification('success', `Tradução concluída! ${result.strings_translated} textos traduzidos.`);
    }

    /**
     * Mostra erro da tradução
     */
    function showTranslationError(message) {
        showNotification('error', 'Erro na tradução: ' + message);
    }

    /**
     * Toggle configurações de tradução
     */
    function toggleTranslationSettings(e) {
        e.preventDefault();
        $('.alvobot-translation-options').slideToggle();
    }

    /**
     * Cria modal de tradução
     */
    function createTranslationModal(fromPostId, targetLang, editorType) {
        const modal = document.createElement('div');
        modal.className = 'alvobot-modal';
        modal.innerHTML = `
            <div class="alvobot-modal-backdrop"></div>
            <div class="alvobot-modal-content">
                <div class="alvobot-modal-header">
                    <h2>Tradução Automática com OpenAI</h2>
                    <button class="alvobot-modal-close">×</button>
                </div>
                <div class="alvobot-modal-body">
                    ${createTranslationMetaBox(fromPostId, targetLang).innerHTML}
                </div>
            </div>
        `;
        
        return modal;
    }

    /**
     * Fecha modal
     */
    function closeModal() {
        const modal = document.querySelector('.alvobot-modal');
        if (modal) {
            modal.classList.remove('alvobot-modal-show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }

    /**
     * Mostra notificação
     */
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `alvobot-notification alvobot-notification-${type}`;
        notification.innerHTML = `
            <div class="alvobot-notification-content">
                <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : 'warning'}"></span>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Remove após 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Utilitários
     */
    function getLanguageName(slug) {
        const language = AlvoBotTranslation.languages.find(lang => lang.slug === slug);
        return language ? language.name : slug;
    }

    function getSourceLanguage(postId) {
        // This function seems to be a placeholder or for a different context.
        // For the post list button, source language is passed via data-attribute.
        // If used elsewhere, it would need proper implementation.
        console.warn('getSourceLanguage called, ensure context is appropriate.');
        return 'auto'; // Placeholder
    }

    /**
     * Handles click on the "Traduzir" button from the post/page list rows.
     */
    function handleTranslateButtonFromListClick(e) {
        e.preventDefault();
        console.log('AlvoBot Translation: Botão Traduzir clicado');
        
        const $button = $(e.currentTarget);
        const postId = $button.data('post-id');
        const postTitle = $button.data('post-title');
        const postType = $button.data('post-type');
        const sourceLang = $button.data('source-lang');
        
        console.log('AlvoBot Translation: Dados do post:', { postId, postTitle, postType, sourceLang });
        console.log('AlvoBot Translation: Idiomas disponíveis:', AlvoBotTranslation.languagesList);

        if (!AlvoBotTranslation.languagesList || AlvoBotTranslation.languagesList.length === 0) {
            console.error('AlvoBot Translation: Nenhum idioma configurado');
            showNotification('error', alvobotTranslation.strings?.noLanguagesConfigured || 'Idiomas não configurados.');
            return;
        }

        showLanguageSelectionModal(postId, postTitle, postType, sourceLang);
    }

    /**
     * Shows a modal for selecting the target language(s) for translation.
     */
    function showLanguageSelectionModal(postId, postTitle, postType, sourceLang) {
        // Remove existing modal first
        $('#alvobot-language-selection-modal').remove();

        // Build language grid for multiselect
        let languageGrid = '';
        let availableLanguages = 0;
        
        AlvoBotTranslation.languagesList.forEach(lang => {
            if (lang.slug !== sourceLang) {
                availableLanguages++;
                const flagIcon = lang.flag ? `<img src="${lang.flag}" alt="${lang.name}" class="alvobot-language-flag">` : 
                                             `<span class="alvobot-language-flag">🌐</span>`;
                
                languageGrid += `
                    <div class="alvobot-language-option" data-lang="${lang.slug}">
                        <input type="checkbox" id="lang-${lang.slug}" value="${lang.slug}" />
                        <label for="lang-${lang.slug}" class="alvobot-checkmark"></label>
                        <div class="alvobot-option-content">
                            ${flagIcon}
                            <strong class="alvobot-language-name">${lang.native_name || lang.name}</strong>
                            <small>${lang.name !== (lang.native_name || lang.name) ? lang.name : ''}</small>
                        </div>
                    </div>
                `;
            }
        });

        if (availableLanguages === 0) {
            showNotification('info', 'Não há idiomas de destino disponíveis para este post.');
            return;
        }

        const sourceLangName = AlvoBotTranslation.languagesList.find(l => l.slug === sourceLang)?.name || sourceLang;

        const modalHTML = `
            <div class="alvobot-modal" id="alvobot-language-selection-modal" style="display: flex;">
                <div class="alvobot-modal-backdrop"></div>
                <div class="alvobot-modal-content">
                    <div class="alvobot-modal-header">
                        <h2>
                            <span class="dashicons dashicons-translation"></span>
                            Traduzir "${postTitle}"
                        </h2>
                        <button type="button" class="alvobot-modal-close">&times;</button>
                    </div>
                    <div class="alvobot-modal-body">
                        <div class="alvobot-translation-step">
                            <h3>Selecione os idiomas de destino:</h3>
                            <p>Escolha um ou mais idiomas para traduzir este ${postType === 'page' ? 'página' : 'post'}. 
                               ${sourceLang ? `Idioma de origem: <strong>${sourceLangName}</strong>` : ''}</p>
                            
                            <div class="alvobot-language-grid">
                                ${languageGrid}
                            </div>
                            
                            <div class="alvobot-selected-count" style="margin-top: 16px; padding: 12px; background: #f6f7f7; border-radius: 6px; display: none;">
                                <strong>Idiomas selecionados:</strong> <span id="selected-lang-count">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="alvobot-modal-footer">
                        <button type="button" class="alvobot-btn alvobot-btn-outline" id="alvobot-cancel-translation">
                            ${alvobotTranslation.strings.cancel || 'Cancelar'}
                        </button>
                        <button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-start-bulk-translation" disabled>
                            <span class="dashicons dashicons-translation"></span>
                            ${alvobotTranslation.strings.translate || 'Traduzir'}
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);
        
        // Initialize modal interactions
        initLanguageSelectionModal(postId, postTitle, postType);
    }

    /**
     * Initializes the language selection modal interactions
     */
    function initLanguageSelectionModal(postId, postTitle, postType) {
        let selectedLanguages = [];

        // Language option click handler
        $('.alvobot-language-option').on('click', function(e) {
            // Previne duplo evento quando clica no checkbox diretamente
            if (e.target.type === 'checkbox') {
                return;
            }
            
            const $option = $(this);
            const $checkbox = $option.find('input[type="checkbox"]');
            
            // Toggle checkbox
            $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        });

        // Checkbox change handler (único ponto de controle)
        $('.alvobot-language-option input').on('change', function() {
            const $checkbox = $(this);
            const $option = $checkbox.closest('.alvobot-language-option');
            const langCode = $option.data('lang');
            const isSelected = $checkbox.prop('checked');
            
            $option.toggleClass('selected', isSelected);
            
            // Update selected languages array
            if (isSelected && !selectedLanguages.includes(langCode)) {
                selectedLanguages.push(langCode);
                console.log('AlvoBot Translation: Idioma adicionado:', langCode, 'Total:', selectedLanguages.length);
            } else if (!isSelected) {
                selectedLanguages = selectedLanguages.filter(lang => lang !== langCode);
                console.log('AlvoBot Translation: Idioma removido:', langCode, 'Total:', selectedLanguages.length);
            }
            
            console.log('AlvoBot Translation: Idiomas selecionados:', selectedLanguages);
            updateSelectionCount(selectedLanguages.length);
        });

        // Update selection count and enable/disable translate button
        function updateSelectionCount(count) {
            $('#selected-lang-count').text(count);
            $('.alvobot-selected-count').toggle(count > 0);
            $('#alvobot-start-bulk-translation').prop('disabled', count === 0);
        }

        // Cancel button
        $('#alvobot-cancel-translation').on('click', function() {
            $('#alvobot-language-selection-modal').remove();
        });

        // Start translation button
        $('#alvobot-start-bulk-translation').on('click', function() {
            if (selectedLanguages.length === 0) {
                showNotification('error', 'Selecione pelo menos um idioma de destino.');
                return;
            }

            // Start bulk translation process
            startBulkTranslation(postId, postTitle, postType, selectedLanguages);
        });

        // Close modal button
        $('.alvobot-modal-close').on('click', function() {
            $('#alvobot-language-selection-modal').remove();
        });

        // Close on backdrop click
        $('.alvobot-modal-backdrop').on('click', function() {
            $('#alvobot-language-selection-modal').remove();
        });
    }

    /**
     * Starts bulk translation process for multiple languages
     */
    function startBulkTranslation(postId, postTitle, postType, targetLanguages) {
        // Close selection modal
        $('#alvobot-language-selection-modal').remove();
        
        // Always add to queue for consistent processing (whether single or multiple languages)
        addToTranslationQueue(postId, postTitle, postType, targetLanguages);
    }

    /**
     * Adds multiple languages to translation queue
     */
    function addToTranslationQueue(postId, postTitle, postType, targetLanguages) {
        // Show progress modal
        showQueueProgress(postId, postTitle, postType, targetLanguages);
        
        // Add to queue via AJAX
        console.log('AlvoBot Translation: Enviando para fila:', {
            action: 'alvobot_add_to_translation_queue',
            post_id: postId,
            target_langs: targetLanguages,
            nonce: AlvoBotTranslation.nonce,
            languages_count: targetLanguages.length
        });
        
        $.ajax({
            url: AlvoBotTranslation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alvobot_add_to_translation_queue',
                post_id: postId,
                target_langs: targetLanguages,
                nonce: AlvoBotTranslation.nonce,
                options: {
                    preserveFormatting: true,
                    translateMetaFields: true,
                    translateLinks: false
                }
            },
            success: function(response) {
                console.log('AlvoBot Translation: Queue response:', response);
                if (response.success) {
                    showQueueSuccess(postTitle, targetLanguages, response.data);
                } else {
                    showQueueError(response.data || 'Erro desconhecido ao adicionar à fila');
                }
            },
            error: function(xhr, status, error) {
                console.error('AlvoBot Translation: Queue error:', {xhr, status, error});
                showQueueError('Erro de comunicação ao adicionar à fila');
            }
        });
    }

    /**
     * Shows queue progress modal
     */
    function showQueueProgress(postId, postTitle, postType, targetLanguages) {
        const languageNames = targetLanguages.map(langCode => {
            const lang = AlvoBotTranslation.languagesList.find(l => l.slug === langCode);
            return lang ? (lang.native_name || lang.name) : langCode;
        }).join(', ');

        const progressHTML = `
            <div class="alvobot-modal" id="alvobot-queue-progress-modal" style="display: flex;">
                <div class="alvobot-modal-backdrop"></div>
                <div class="alvobot-modal-content">
                    <div class="alvobot-modal-header">
                        <h2>
                            <span class="dashicons dashicons-plus-alt"></span>
                            Adicionando à Fila de Tradução
                        </h2>
                    </div>
                    <div class="alvobot-modal-body">
                        <div class="alvobot-progress-container">
                            <div class="alvobot-progress-bar">
                                <div class="alvobot-progress-fill" style="width: 50%; transition: width 0.3s ease;"></div>
                            </div>
                            <div class="alvobot-progress-text">Adicionando "${postTitle}" à fila...</div>
                        </div>
                        
                        <div class="alvobot-progress-details">
                            <div class="alvobot-progress-item">
                                <span class="alvobot-progress-label">Post:</span>
                                <span class="alvobot-progress-value">${postTitle}</span>
                            </div>
                            <div class="alvobot-progress-item">
                                <span class="alvobot-progress-label">Idiomas:</span>
                                <span class="alvobot-progress-value">${targetLanguages.length}</span>
                            </div>
                            <div class="alvobot-progress-item">
                                <span class="alvobot-progress-label">Destinos:</span>
                                <span class="alvobot-progress-value">${languageNames}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(progressHTML);
    }

    /**
     * Shows queue success message
     */
    function showQueueSuccess(postTitle, targetLanguages, data) {
        $('#alvobot-queue-progress-modal .alvobot-progress-fill').css('width', '100%');
        $('#alvobot-queue-progress-modal .alvobot-progress-text').text('Adicionado à fila com sucesso!');
        
        setTimeout(() => {
            $('#alvobot-queue-progress-modal .alvobot-modal-body').html(`
                <div class="alvobot-completion-stats">
                    <div class="alvobot-stat-item">
                        <div class="alvobot-stat-number">✓</div>
                        <div class="alvobot-stat-label">Sucesso</div>
                    </div>
                    <div class="alvobot-stat-item">
                        <div class="alvobot-stat-number">${targetLanguages.length}</div>
                        <div class="alvobot-stat-label">Idiomas</div>
                    </div>
                    <div class="alvobot-stat-item">
                        <div class="alvobot-stat-number">${data.queue_id || 'N/A'}</div>
                        <div class="alvobot-stat-label">ID Fila</div>
                    </div>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                    <p><strong>✓ "${postTitle}" foi adicionado à fila de tradução!</strong></p>
                    <p>O post será traduzido automaticamente em segundo plano para os idiomas selecionados.</p>
                    <p>Você pode acompanhar o progresso na página de fila de traduções.</p>
                </div>
            `);

            // Update footer
            $('#alvobot-queue-progress-modal .alvobot-modal-content').append(`
                <div class="alvobot-modal-footer">
                    <button type="button" class="alvobot-btn alvobot-btn-primary" onclick="window.location.href = '${AlvoBotTranslation.adminUrl}admin.php?page=alvobot-pro-multi-languages&tab=queue'">
                        Ver Fila de Tradução
                    </button>
                    <button type="button" class="alvobot-btn alvobot-btn-outline" id="alvobot-close-queue-results">
                        Fechar
                    </button>
                </div>
            `);

            $('#alvobot-close-queue-results').on('click', function() {
                $('#alvobot-queue-progress-modal').remove();
            });
            
        }, 1000);
    }

    /**
     * Shows queue error message
     */
    function showQueueError(errorMessage) {
        $('#alvobot-queue-progress-modal .alvobot-progress-fill').css('width', '100%').css('background', '#dc3545');
        $('#alvobot-queue-progress-modal .alvobot-progress-text').text('Erro ao adicionar à fila');
        
        setTimeout(() => {
            $('#alvobot-queue-progress-modal .alvobot-modal-body').html(`
                <div style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                    <p><strong>❌ Erro ao adicionar à fila:</strong></p>
                    <p>${errorMessage}</p>
                    <p>Tente novamente ou entre em contato com o administrador.</p>
                </div>
            `);

            // Update footer
            $('#alvobot-queue-progress-modal .alvobot-modal-content').append(`
                <div class="alvobot-modal-footer">
                    <button type="button" class="alvobot-btn alvobot-btn-outline" id="alvobot-close-queue-error">
                        Fechar
                    </button>
                </div>
            `);

            $('#alvobot-close-queue-error').on('click', function() {
                $('#alvobot-queue-progress-modal').remove();
            });
            
        }, 1000);
    }




    // Expõe API global
    window.AlvoBotTranslation = AlvoBotTranslation;

})(jQuery);