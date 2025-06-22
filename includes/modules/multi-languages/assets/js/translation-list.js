/**
 * Interface de Tradução para Listas de Posts/Páginas
 * 
 * Gerencia o botão "Traduzir" nas listas administrativas
 */

(function($) {
    'use strict';

    // Estado global
    let currentTranslationModal = null;
    let isTranslating = false;

    /**
     * Inicialização
     */
    $(document).ready(function() {
        initTranslateButtons();
        setupEventListeners();
    });

    /**
     * Inicializa os botões de tradução
     */
    function initTranslateButtons() {
        $('.alvobot-translate-link').each(function() {
            const $link = $(this);
            const postId = $link.data('post-id');
            const postTitle = $link.data('post-title');
            
            // Adiciona ícone
            $link.html('<span class="dashicons dashicons-translation"></span> ' + alvobotTranslation.strings.translate);
            
            // Adiciona data attributes
            $link.attr({
                'data-post-id': postId,
                'data-post-title': postTitle
            });
        });
    }

    /**
     * Configura event listeners
     */
    function setupEventListeners() {
        // Clique no botão "Traduzir" - agora usa o novo modal avançado
        $(document).on('click', '.alvobot-translate-link', function(e) {
            e.preventDefault();
            
            if (isTranslating) {
                return;
            }
            
            const postId = $(this).data('post-id');
            
            // Usa o novo modal avançado se disponível
            if (window.AlvoBotTranslationModal) {
                console.log('AlvoBot Translation List: Usando novo modal para post ID:', postId);
                window.AlvoBotTranslationModal.openModal(postId);
                return;
            }
            
            console.log('AlvoBot Translation List: Modal avançado não disponível, usando fallback');
            
            // Fallback para o modal simples
            const postTitle = $(this).data('post-title');
            showLanguageSelectionModal(postId, postTitle);
        });
        
        // Fechar modal
        $(document).on('click', '.alvobot-translation-modal-close, .alvobot-translation-backdrop', function(e) {
            e.preventDefault();
            closeTranslationModal();
        });
        
        // Tecla ESC
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && currentTranslationModal) { // ESC
                closeTranslationModal();
            }
        });
        
        // Seleção de idioma
        $(document).on('change', 'input[name="target_language"]', function() {
            updateLanguageSelection();
        });
        
        // Iniciar tradução
        $(document).on('click', '.alvobot-start-translation', function(e) {
            e.preventDefault();
            startTranslation();
        });
        
        // Gerenciar opção de sobrescrever
        $(document).on('change', '#force-overwrite', function() {
            toggleExistingLanguages($(this).is(':checked'));
        });
    }

    /**
     * Mostra modal de seleção de idioma
     */
    function showLanguageSelectionModal(postId, postTitle) {
        // Verifica se há idiomas configurados
        if (!alvobotTranslation.languages || alvobotTranslation.languages.length < 2) {
            showNotification('error', alvobotTranslation.strings.noLanguagesConfigured);
            return;
        }
        
        // Cria o modal
        const modal = createLanguageSelectionModal(postId, postTitle);
        
        // Adiciona ao DOM
        $('body').append(modal);
        currentTranslationModal = modal;
        
        // Anima entrada
        setTimeout(() => {
            modal.addClass('show');
        }, 10);
    }

    /**
     * Cria o modal de seleção de idioma
     */
    function createLanguageSelectionModal(postId, postTitle) {
        // Busca informações do post de forma assíncrona
        let postInfo = null;
        
        $.ajax({
            url: alvobotTranslation.ajaxUrl,
            type: 'POST',
            async: false,
            data: {
                action: 'alvobot_get_post_language',
                post_id: postId,
                nonce: alvobotTranslation.nonce
            },
            success: function(response) {
                if (response.success) {
                    postInfo = response.data;
                }
            }
        });
        
        if (!postInfo) {
            showNotification('error', 'Erro ao carregar informações do post');
            return null;
        }
        
        const currentLang = postInfo.language;
        const existingTranslations = postInfo.existing_translations || [];
        // Inicialmente mostra apenas idiomas sem tradução
        let availableLanguages = alvobotTranslation.languages.filter(lang => 
            lang.slug !== currentLang && !existingTranslations.includes(lang.slug)
        );
        
        // Se existem traduções, também inclui elas como opções para sobrescrever
        const languagesWithTranslations = alvobotTranslation.languages.filter(lang => 
            lang.slug !== currentLang && existingTranslations.includes(lang.slug)
        );
        
        // Busca informações do idioma atual
        const currentLanguageInfo = alvobotTranslation.languages.find(lang => lang.slug === currentLang);
        
        // Se há traduções existentes, mostra quais são
        let existingTranslationsInfo = '';
        if (existingTranslations.length > 0) {
            const existingLangNames = existingTranslations.map(slug => {
                const lang = alvobotTranslation.languages.find(l => l.slug === slug);
                return lang ? lang.native_name : slug;
            });
            existingTranslationsInfo = `
                <div style="margin-bottom: 16px; padding: 12px; background: var(--alvobot-warning-bg); border-left: 3px solid var(--alvobot-warning); border-radius: 6px;">
                    <div style="color: var(--alvobot-gray-700); font-size: 14px; margin-bottom: 4px;">
                        <strong>Traduções existentes:</strong>
                    </div>
                    <div style="color: var(--alvobot-gray-600); font-size: 13px;">
                        ${existingLangNames.join(', ')}
                    </div>
                </div>
            `;
        }
        
        if (availableLanguages.length === 0 && languagesWithTranslations.length === 0) {
            showNotification('error', 'Nenhum idioma disponível para tradução.');
            return null;
        }
        
        let languageOptionsHtml = '';
        
        // Adiciona idiomas disponíveis (sem tradução)
        availableLanguages.forEach(language => {
            languageOptionsHtml += `
                <label class="alvobot-language-option">
                    <input type="radio" name="target_language" value="${language.slug}">
                    ${language.flag ? `<img src="${language.flag}" alt="${language.name}" class="alvobot-language-flag">` : ''}
                    <div class="alvobot-language-info">
                        <div class="alvobot-language-name">${language.native_name}</div>
                        <div class="alvobot-language-code">${language.slug}</div>
                    </div>
                    <div class="alvobot-language-check"></div>
                </label>
            `;
        });
        
        // Adiciona idiomas com traduções existentes (desabilitados inicialmente)
        languagesWithTranslations.forEach(language => {
            languageOptionsHtml += `
                <label class="alvobot-language-option alvobot-language-existing" data-existing="true">
                    <input type="radio" name="target_language" value="${language.slug}" disabled>
                    ${language.flag ? `<img src="${language.flag}" alt="${language.name}" class="alvobot-language-flag">` : ''}
                    <div class="alvobot-language-info">
                        <div class="alvobot-language-name">${language.native_name}</div>
                        <div class="alvobot-language-code">${language.slug} (existente)</div>
                    </div>
                    <div class="alvobot-language-check"></div>
                </label>
            `;
        });

        const modalHtml = `
            <div class="alvobot-translation-modal">
                <div class="alvobot-translation-backdrop"></div>
                <div class="alvobot-translation-modal-content">
                    <div class="alvobot-translation-modal-header">
                        <h2 class="alvobot-translation-modal-title">
                            <span class="dashicons dashicons-translation"></span>
                            ${alvobotTranslation.strings.translateTo}
                        </h2>
                        <button class="alvobot-translation-modal-close" type="button">&times;</button>
                    </div>
                    
                    <div class="alvobot-translation-modal-body">
                        <div class="alvobot-translation-source">
                            <h3 style="margin-top: 0; color: var(--alvobot-gray-700); font-size: 16px;">
                                <strong>"${postTitle}"</strong>
                            </h3>
                            <div style="margin-bottom: 24px; padding: 12px; background: var(--alvobot-gray-50); border-radius: 6px;">
                                <div style="color: var(--alvobot-gray-600); font-size: 14px; margin-bottom: 4px;">Idioma atual:</div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    ${currentLanguageInfo && currentLanguageInfo.flag ? `<img src="${currentLanguageInfo.flag}" alt="${currentLanguageInfo.name}" style="width: 20px; height: 15px;">` : ''}
                                    <strong style="color: var(--alvobot-gray-800);">${currentLanguageInfo ? currentLanguageInfo.native_name : currentLang}</strong>
                                    <span style="color: var(--alvobot-gray-500); text-transform: uppercase; font-size: 12px;">(${currentLang})</span>
                                </div>
                            </div>
                            ${existingTranslationsInfo}
                            <p style="margin-bottom: 24px; color: var(--alvobot-gray-600);">
                                ${alvobotTranslation.strings.selectLanguage}
                            </p>
                        </div>
                        
                        <div class="alvobot-language-selector">
                            <div class="alvobot-language-grid">
                                ${languageOptionsHtml}
                            </div>
                        </div>
                        
                        <div class="alvobot-translation-options">
                            <h4>${alvobotTranslation.strings.options}</h4>
                            <div class="alvobot-option-group">
                                <div class="alvobot-checkbox-option">
                                    <input type="checkbox" id="preserve-formatting" checked>
                                    <label for="preserve-formatting">${alvobotTranslation.strings.preserveFormatting}</label>
                                </div>
                                <div class="alvobot-checkbox-option">
                                    <input type="checkbox" id="translate-meta-fields" checked>
                                    <label for="translate-meta-fields">${alvobotTranslation.strings.translateMetaFields}</label>
                                </div>
                                <div class="alvobot-checkbox-option">
                                    <input type="checkbox" id="translate-links">
                                    <label for="translate-links">${alvobotTranslation.strings.translateLinks}</label>
                                </div>
                                ${existingTranslations.length > 0 ? `
                                <div class="alvobot-checkbox-option">
                                    <input type="checkbox" id="force-overwrite">
                                    <label for="force-overwrite">Sobrescrever traduções existentes</label>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="alvobot-translation-progress" style="display: none;">
                            <div class="alvobot-progress-header">
                                <div class="alvobot-progress-title">${alvobotTranslation.strings.translating}</div>
                                <div class="alvobot-progress-percentage">0%</div>
                            </div>
                            <div class="alvobot-progress-bar">
                                <div class="alvobot-progress-fill"></div>
                            </div>
                            <div class="alvobot-progress-text">Iniciando tradução...</div>
                        </div>
                    </div>
                    
                    <div class="alvobot-translation-modal-footer">
                        <button type="button" class="alvobot-btn alvobot-btn-outline" onclick="closeTranslationModal()">
                            ${alvobotTranslation.strings.cancel}
                        </button>
                        <button type="button" class="alvobot-btn alvobot-btn-primary alvobot-start-translation" disabled data-post-id="${postId}">
                            <span class="dashicons dashicons-translation"></span>
                            ${alvobotTranslation.strings.translate}
                        </button>
                    </div>
                </div>
            </div>
        `;

        return $(modalHtml);
    }

    /**
     * Alterna disponibilidade de idiomas com traduções existentes
     */
    function toggleExistingLanguages(enabled) {
        const existingLanguages = $('.alvobot-language-existing');
        
        if (enabled) {
            existingLanguages.addClass('enabled');
            existingLanguages.find('input[type="radio"]').prop('disabled', false);
        } else {
            existingLanguages.removeClass('enabled selected');
            existingLanguages.find('input[type="radio"]').prop('disabled', true).prop('checked', false);
            updateLanguageSelection(); // Atualiza seleção após desabilitar
        }
    }
    
    /**
     * Atualiza seleção de idioma
     */
    function updateLanguageSelection() {
        const selectedLang = $('input[name="target_language"]:checked').val();
        const $startButton = $('.alvobot-start-translation');
        
        // Remove seleção anterior
        $('.alvobot-language-option').removeClass('selected');
        
        if (selectedLang) {
            // Marca como selecionado
            $('input[name="target_language"]:checked').closest('.alvobot-language-option').addClass('selected');
            
            // Habilita botão
            $startButton.prop('disabled', false);
            $startButton.attr('data-target-lang', selectedLang);
        } else {
            // Desabilita botão
            $startButton.prop('disabled', true);
            $startButton.removeAttr('data-target-lang');
        }
    }

    /**
     * Inicia o processo de tradução
     */
    async function startTranslation() {
        if (isTranslating) {
            return;
        }

        const postId = $('.alvobot-start-translation').data('post-id');
        const targetLang = $('.alvobot-start-translation').data('target-lang');
        
        if (!postId || !targetLang) {
            showNotification('error', 'Dados de tradução inválidos');
            return;
        }

        // Coleta opções
        const options = {
            preserveFormatting: $('#preserve-formatting').is(':checked'),
            translateMetaFields: $('#translate-meta-fields').is(':checked'),
            translateLinks: $('#translate-links').is(':checked'),
            force_overwrite: $('#force-overwrite').is(':checked')
        };

        isTranslating = true;
        
        try {
            // Mostra progresso
            showTranslationProgress();
            updateProgress(10, 'Analisando conteúdo...');

            // Inicia tradução via AJAX
            const result = await $.ajax({
                url: alvobotTranslation.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_translate_and_create_post',
                    post_id: postId,
                    target_lang: targetLang,
                    options: options,
                    nonce: alvobotTranslation.nonce
                }
            });

            if (result.success) {
                updateProgress(100, alvobotTranslation.strings.translationComplete);
                
                setTimeout(() => {
                    showTranslationSuccess(result.data);
                }, 1000);
            } else {
                throw new Error(result.data || 'Erro desconhecido na tradução');
            }

        } catch (error) {
            console.error('Erro na tradução:', error);
            showTranslationError(error.message || error.responseText || 'Erro na conexão');
        } finally {
            isTranslating = false;
        }
    }

    /**
     * Mostra progresso da tradução
     */
    function showTranslationProgress() {
        $('.alvobot-translation-modal-footer').hide();
        $('.alvobot-translation-progress').show();
    }

    /**
     * Atualiza progresso
     */
    function updateProgress(percentage, message) {
        $('.alvobot-progress-fill').css('width', percentage + '%');
        $('.alvobot-progress-percentage').text(percentage + '%');
        $('.alvobot-progress-text').text(message);
    }

    /**
     * Mostra sucesso da tradução
     */
    function showTranslationSuccess(data) {
        const message = `
            <div style="text-align: center; padding: 20px;">
                <div style="color: var(--alvobot-success); font-size: 48px; margin-bottom: 16px;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3 style="margin: 0 0 16px; color: var(--alvobot-gray-800);">
                    ${alvobotTranslation.strings.translationComplete}
                </h3>
                <p style="margin: 0 0 24px; color: var(--alvobot-gray-600);">
                    Post traduzido com sucesso! Redirecionando para o editor...
                </p>
                <div class="alvobot-btn-group">
                    <a href="${data.edit_url}" class="alvobot-btn alvobot-btn-primary">
                        <span class="dashicons dashicons-edit"></span>
                        Editar Tradução
                    </a>
                    <button type="button" class="alvobot-btn alvobot-btn-outline" onclick="closeTranslationModal()">
                        Fechar
                    </button>
                </div>
            </div>
        `;
        
        $('.alvobot-translation-modal-body').html(message);
        
        // Redireciona após 3 segundos se não fechou o modal
        setTimeout(() => {
            if (currentTranslationModal) {
                window.location.href = data.edit_url;
            }
        }, 3000);
    }

    /**
     * Mostra erro da tradução
     */
    function showTranslationError(message) {
        $('.alvobot-translation-progress').hide();
        $('.alvobot-translation-modal-footer').show();
        
        showNotification('error', alvobotTranslation.strings.translationError + ' ' + message);
    }

    /**
     * Fecha modal de tradução
     */
    function closeTranslationModal() {
        if (currentTranslationModal) {
            currentTranslationModal.removeClass('show');
            
            setTimeout(() => {
                currentTranslationModal.remove();
                currentTranslationModal = null;
                isTranslating = false;
            }, 300);
        }
    }

    /**
     * Mostra notificação
     */
    function showNotification(type, message) {
        const notification = $(`
            <div class="alvobot-notification alvobot-notification-${type}">
                <div class="alvobot-notification-content">
                    <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : 'warning'}"></span>
                    <span>${message}</span>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        // Remove após 5 segundos
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Detecta idioma atual do post via AJAX
     */
    function getCurrentPostLanguage(postId) {
        // Faz requisição AJAX para detectar idioma do post
        let currentLang = 'auto'; // Default fallback
        
        $.ajax({
            url: alvobotTranslation.ajaxUrl,
            type: 'POST',
            async: false, // Síncono para ter o resultado imediato
            data: {
                action: 'alvobot_get_post_language',
                post_id: postId,
                nonce: alvobotTranslation.nonce
            },
            success: function(response) {
                if (response.success && response.data.language) {
                    currentLang = response.data.language;
                }
            }
        });
        
        return currentLang;
    }

    // Expõe funções globais
    window.closeTranslationModal = closeTranslationModal;

})(jQuery);