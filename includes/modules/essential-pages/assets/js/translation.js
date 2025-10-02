jQuery(document).ready(function($) {
    'use strict';

    let translationInProgress = false;

    function openTranslationModal() {
        if (translationInProgress) {
            alert('Uma tradução já está em andamento. Aguarde a conclusão.');
            return;
        }

        $('#alvobot-essential-pages-translation-modal').fadeIn(300);
        loadAvailableLanguages();
    }

    function closeTranslationModal() {
        $('#alvobot-essential-pages-translation-modal').fadeOut(300);
        resetModalState();
    }

    function resetModalState() {
        $('#translation-mode').val('existing');
        $('#existing-pages-container').show();
        $('#new-pages-container').hide();
        $('.alvobot-languages-grid input[type="checkbox"]').prop('checked', false);
        updateSelectedCount();
        hideProgressContainer();
        $('.translation-results').html('');
    }

    function loadAvailableLanguages() {
        $.ajax({
            url: alvobotTranslation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alvobot_get_essential_pages_languages',
                nonce: alvobotTranslation.nonce
            },
            success: function(response) {
                if (response.success && response.data.languages) {
                    updateLanguagesGrid(response.data.languages);

                    if (response.data.existing_pages) {
                        updateExistingPagesList(response.data.existing_pages);
                    }
                } else {
                    console.error('Erro ao carregar idiomas:', response.data);
                    alert('Erro ao carregar idiomas disponíveis.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', error);
                alert('Erro ao conectar com o servidor.');
            }
        });
    }

    function updateLanguagesGrid(languages) {
        const grid = $('.alvobot-languages-grid');
        grid.empty();

        if (languages && languages.length > 0) {
            languages.forEach(function(lang) {
                const langHtml = `
                    <label class="alvobot-language-option">
                        <input type="checkbox" name="selected_languages[]" value="${lang.code}">
                        <span class="language-flag">${lang.flag || '🌐'}</span>
                        <span class="language-name">${lang.native_name || lang.name}</span>
                        <span class="language-code">(${lang.code})</span>
                    </label>
                `;
                grid.append(langHtml);
            });
        } else {
            grid.html('<p>Nenhum idioma configurado. Configure os idiomas no módulo Multi-Languages primeiro.</p>');
        }
    }

    function updateExistingPagesList(pages) {
        const container = $('#existing-pages-list');
        container.empty();

        if (pages && pages.length > 0) {
            pages.forEach(function(page) {
                const pageHtml = `
                    <div class="existing-page-item">
                        <label>
                            <input type="checkbox" name="selected_pages[]" value="${page.ID}">
                            <strong>${page.post_title}</strong>
                            <span class="page-info">(ID: ${page.ID}, Tipo: ${page.page_type})</span>
                        </label>
                    </div>
                `;
                container.append(pageHtml);
            });
        } else {
            container.html('<p>Nenhuma página essencial encontrada.</p>');
        }
    }

    function updateSelectedCount() {
        const selectedLanguages = $('.alvobot-languages-grid input[type="checkbox"]:checked').length;
        const selectedPages = $('#existing-pages-list input[type="checkbox"]:checked').length;

        $('.selected-languages-count').text(selectedLanguages);
        $('.selected-pages-count').text(selectedPages);

        const canTranslate = selectedLanguages > 0 &&
                           ($('#translation-mode').val() === 'new' || selectedPages > 0);

        $('#start-translation-btn').prop('disabled', !canTranslate);
    }

    function showProgressContainer() {
        $('.progress-container').show();
        updateProgress(0, 'Iniciando tradução...');
    }

    function hideProgressContainer() {
        $('.progress-container').hide();
        updateProgress(0, '');
    }

    function updateProgress(percentage, message) {
        $('.progress-bar').css('width', percentage + '%');
        $('.progress-text').text(message);
    }

    function startTranslation() {
        if (translationInProgress) return;

        const selectedLanguages = [];
        $('.alvobot-languages-grid input[type="checkbox"]:checked').each(function() {
            selectedLanguages.push($(this).val());
        });

        const mode = $('#translation-mode').val();
        const selectedPages = [];

        if (mode === 'existing') {
            $('#existing-pages-list input[type="checkbox"]:checked').each(function() {
                selectedPages.push($(this).val());
            });
        }

        if (selectedLanguages.length === 0) {
            alert('Selecione pelo menos um idioma.');
            return;
        }

        if (mode === 'existing' && selectedPages.length === 0) {
            alert('Selecione pelo menos uma página para traduzir.');
            return;
        }

        translationInProgress = true;
        showProgressContainer();
        $('#start-translation-btn').prop('disabled', true);

        const actionName = mode === 'existing' ? 'alvobot_translate_essential_pages' : 'alvobot_create_translated_pages';

        $.ajax({
            url: alvobotTranslation.ajaxUrl,
            type: 'POST',
            data: {
                action: actionName,
                nonce: alvobotTranslation.nonce,
                languages: selectedLanguages,
                pages: selectedPages,
                mode: mode
            },
            success: function(response) {
                translationInProgress = false;

                if (response.success) {
                    updateProgress(100, 'Tradução concluída com sucesso!');
                    displayTranslationResults(response.data);
                } else {
                    updateProgress(0, 'Erro na tradução: ' + (response.data || 'Erro desconhecido'));
                    console.error('Erro na tradução:', response.data);
                }

                $('#start-translation-btn').prop('disabled', false);
            },
            error: function(xhr, status, error) {
                translationInProgress = false;
                updateProgress(0, 'Erro de conexão: ' + error);
                console.error('Erro AJAX:', error);
                $('#start-translation-btn').prop('disabled', false);
            }
        });
    }

    function displayTranslationResults(results) {
        const container = $('.translation-results');
        container.empty();

        if (!results || (!results.translated_pages && !results.created_pages)) {
            container.html('<p>Nenhum resultado para exibir.</p>');
            return;
        }

        let html = '<h3>Resultados da Tradução:</h3>';

        if (results.translated_pages && results.translated_pages.length > 0) {
            html += '<h4>Páginas Traduzidas:</h4><ul>';
            results.translated_pages.forEach(function(page) {
                html += `<li><strong>${page.title}</strong> - Idioma: ${page.language}
                        <a href="${page.edit_link}" target="_blank">(Editar)</a></li>`;
            });
            html += '</ul>';
        }

        if (results.created_pages && results.created_pages.length > 0) {
            html += '<h4>Páginas Criadas:</h4><ul>';
            results.created_pages.forEach(function(page) {
                html += `<li><strong>${page.title}</strong> - Idioma: ${page.language}
                        <a href="${page.edit_link}" target="_blank">(Editar)</a></li>`;
            });
            html += '</ul>';
        }

        if (results.errors && results.errors.length > 0) {
            html += '<h4>Erros:</h4><ul style="color: #d63384;">';
            results.errors.forEach(function(error) {
                html += `<li>${error}</li>`;
            });
            html += '</ul>';
        }

        container.html(html);
    }

    // Event Listeners
    $(document).on('click', '.alvobot-translate-pages-btn', function(e) {
        e.preventDefault();
        openTranslationModal();
    });

    $(document).on('click', '.alvobot-modal-close, .alvobot-modal-overlay', function(e) {
        if (e.target === this) {
            closeTranslationModal();
        }
    });

    $(document).on('change', '#translation-mode', function() {
        const mode = $(this).val();
        if (mode === 'existing') {
            $('#existing-pages-container').show();
            $('#new-pages-container').hide();
        } else {
            $('#existing-pages-container').hide();
            $('#new-pages-container').show();
        }
        updateSelectedCount();
    });

    $(document).on('change', '.alvobot-languages-grid input[type="checkbox"], #existing-pages-list input[type="checkbox"]', function() {
        updateSelectedCount();
    });

    $(document).on('click', '#start-translation-btn', function(e) {
        e.preventDefault();

        if (translationInProgress) {
            alert('Uma tradução já está em andamento.');
            return;
        }

        const selectedLanguages = $('.alvobot-languages-grid input[type="checkbox"]:checked').length;
        const mode = $('#translation-mode').val();
        const selectedPages = $('#existing-pages-list input[type="checkbox"]:checked').length;

        let confirmMessage = `Confirma a tradução para ${selectedLanguages} idioma(s)?`;

        if (mode === 'existing') {
            confirmMessage = `Confirma a tradução de ${selectedPages} página(s) para ${selectedLanguages} idioma(s)?`;
        } else {
            confirmMessage = `Confirma a criação de páginas essenciais em ${selectedLanguages} idioma(s)?`;
        }

        if (confirm(confirmMessage)) {
            startTranslation();
        }
    });

    // Esc key to close modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTranslationModal();
        }
    });

    // Prevent form submission on Enter in modal
    $(document).on('keydown', '.alvobot-modal input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    // Check for existing translate action links in page list
    $(document).on('click', '.alvobot-translate-page-action', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');

        // Open modal and pre-select this page
        openTranslationModal();

        // Wait for modal to load, then select the page
        setTimeout(function() {
            $(`#existing-pages-list input[value="${pageId}"]`).prop('checked', true);
            $('#translation-mode').val('existing').trigger('change');
            updateSelectedCount();
        }, 500);
    });

    // Initialize on page load
    updateSelectedCount();
});