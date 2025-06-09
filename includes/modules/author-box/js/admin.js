jQuery(document).ready(function($) {
    'use strict';

    // Cache dos elementos DOM
    var elements = {
        preview: $('.alvobot-author-box'),
        title: $('.alvobot-author-box .author-box-title'),
        description: $('.alvobot-author-box .author-description'),
        avatar: $('.alvobot-author-box .author-avatar img, .alvobot-author-box .author-avatar .avatar'),
        titleInput: $('#title_text'),
        showDescInput: $('#show_description'),
        displayPostsInput: $('#display_on_posts'),
        displayPagesInput: $('#display_on_pages')
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
            $status.html('✅ <strong>Author Box ativo</strong> - Exibindo em: ' + displayLocations.join(' e '))
                .removeClass('status-disabled')
                .addClass('status-enabled')
                .css({
                    'margin-bottom': '16px',
                    'padding': '12px 16px',
                    'background': 'linear-gradient(135deg, #e7f5ea 0%, #d4edda 100%)',
                    'border': '1px solid #c3e6cb',
                    'border-radius': '8px',
                    'color': '#155724',
                    'font-size': '14px',
                    'border-left': '4px solid #28a745'
                });
        } else {
            $status.html('⚠️ <strong>Author Box desativado</strong> - Selecione onde exibir')
                .removeClass('status-enabled')
                .addClass('status-disabled')
                .css({
                    'margin-bottom': '16px',
                    'padding': '12px 16px',
                    'background': 'linear-gradient(135deg, #fbeaea 0%, #f8d7da 100%)',
                    'border': '1px solid #f5c6cb',
                    'border-radius': '8px',
                    'color': '#721c24',
                    'font-size': '14px',
                    'border-left': '4px solid #dc3545'
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
        $('input[type="text"], input[type="number"]').on('focus', function() {
            $(this).closest('tr').addClass('field-focused');
        }).on('blur', function() {
            $(this).closest('tr').removeClass('field-focused');
        });

        // Efeito nos checkboxes
        $('input[type="checkbox"]').on('change', function() {
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
            
            pulseTimeout = setTimeout(function() {
                elements.preview.removeClass('preview-updating');
            }, 300);
        }

        // Event listeners para atualização em tempo real
        elements.titleInput.on('input', function() {
            updatePreview();
            pulsePreview();
        });

        $('input[type="checkbox"]').on('change', function() {
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
});