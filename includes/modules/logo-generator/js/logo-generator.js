jQuery(document).ready(function($) {
    let previewTimeout;
    
    // Inicializa o color picker
    $('.color-picker').wpColorPicker({
        change: function(event, ui) {
            $(event.target).val(ui.color.toString());
            schedulePreviewUpdate();
        }
    });

    // Atualiza o preview quando mudar o ícone
    $('input[name="icon_choice"]').on('change', function() {
        schedulePreviewUpdate();
    });

    // Atualiza a fonte selecionada
    $('#font_choice').on('change', function() {
        var $selected = $(this).find('option:selected');
        $(this).css('font-family', $selected.css('font-family'));
        schedulePreviewUpdate();
    }).trigger('change');

    // Atualiza quando o nome é alterado
    $('#blog_name').on('input', function() {
        schedulePreviewUpdate();
    });

    // Manipula o envio do formulário
    $('#logo-generator-form').on('submit', function(e) {
        e.preventDefault();
        saveLogo();
    });

    function schedulePreviewUpdate() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(function() {
            updateLogoPreview();
            updateFaviconPreview();
        }, 300);
    }

    function updateLogoPreview() {
        const $previewContent = $('#logo-preview-content');
        $previewContent.css('opacity', '0.5');

        var formData = new FormData();
        formData.append('action', 'generate_logo');
        formData.append('blog_name', $('#blog_name').val());
        formData.append('font_color', $('#font_color').val());
        formData.append('background_color', $('#background_color').val());
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_choice', $('#font_choice').val());
        formData.append('_wpnonce', $('#logo-generator-form input[name="_wpnonce"]').val());

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success && response.data) {
                    $previewContent.html(response.data).css('opacity', '1');
                } else {
                    console.error('Erro ao gerar preview:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                $previewContent.css('opacity', '1');
            }
        });
    }

    function updateFaviconPreview() {
        const data = new FormData();
        data.append('action', 'generate_favicon');
        data.append('nonce', logoGeneratorParams.nonce);
        data.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        data.append('font_color', $('#font_color').val());
        data.append('background_color', $('#background_color').val());

        const $previewContent = $('#favicon-preview-content');
        $previewContent.css('opacity', '0.5');

        $.ajax({
            url: logoGeneratorParams.ajaxurl,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Limpa o conteúdo atual
                    $previewContent.empty();
                    
                    // Adiciona apenas o SVG
                    $previewContent.html(response.data).css('opacity', '1');
                    
                    // Ajusta o tamanho do SVG
                    $previewContent.find('svg').css({
                        width: '128px',
                        height: '128px'
                    });
                } else {
                    console.error('Erro ao gerar preview do favicon:', response.data);
                    $previewContent.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX do favicon:', error);
                $previewContent.css('opacity', '1');
            }
        });
    }

    function saveLogo() {
        const $previewContent = $('#logo-preview-content');
        if (!$previewContent.length) {
            showMessage('Erro: Container do logo não encontrado', 'error');
            return;
        }

        const svg = $previewContent.find('svg')[0];
        if (!svg) {
            showMessage('Erro: SVG não encontrado', 'error');
            return;
        }

        const svgContent = svg.outerHTML;
        const blogName = $('#blog_name').val();

        const formData = new FormData();
        formData.append('action', 'save_logo');
        formData.append('_wpnonce', $('#logo-generator-form input[name="_wpnonce"]').val());
        formData.append('svg', svgContent);
        formData.append('title', blogName);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage('Logo salvo com sucesso!', 'success');
                    if (response.data && response.data.logo) {
                        const customLogoPreview = document.getElementById('custom-logo-preview');
                        if (customLogoPreview) {
                            customLogoPreview.src = response.data.logo.url;
                        }
                    }
                } else {
                    showMessage(response.data || 'Erro ao salvar o logo', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', xhr.responseText);
                showMessage('Erro ao salvar o logo: ' + error, 'error');
            }
        });
    }

    function saveFavicon() {
        const $previewContent = $('#favicon-preview-content');
        if (!$previewContent.length) {
            showNotice('error', 'Container do favicon não encontrado');
            return;
        }

        const svg = $previewContent.find('svg')[0];
        if (!svg) {
            showNotice('error', 'SVG não encontrado');
            return;
        }

        const svgContent = svg.outerHTML;

        // Mostra mensagem de loading
        const $loadingDiv = $('<div>', {
            class: 'loading',
            text: 'Salvando favicon...',
            css: {
                width: $previewContent.width(),
                height: $previewContent.height()
            }
        });
        $previewContent.html($loadingDiv);
        
        // Prepara os dados
        const data = new FormData();
        data.append('action', 'save_favicon');
        data.append('nonce', logoGeneratorParams.nonce);
        data.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        data.append('font_color', $('#font_color').val());
        data.append('background_color', $('#background_color').val());
        
        // Envia a requisição
        $.ajax({
            url: logoGeneratorParams.ajaxurl,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Atualiza o preview com o novo favicon
                    const faviconUrl = response.data.favicon.url;
                    const $img = $('<img>', {
                        src: faviconUrl + '?' + Date.now(),
                        alt: 'Favicon',
                        css: {
                            width: '128px',
                            height: '128px'
                        }
                    });
                    $previewContent.html($img);
                    
                    // Mostra mensagem de sucesso
                    showNotice('success', response.data.message);
                    
                    // Força o navegador a recarregar o favicon
                    $('link[rel*="icon"]').each(function() {
                        $(this).attr('href', faviconUrl + '?' + Date.now());
                    });
                } else {
                    $previewContent.html(svgContent);
                    showNotice('error', response.data || 'Erro ao salvar o favicon');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro:', error);
                $previewContent.html(svgContent);
                showNotice('error', 'Erro ao salvar o favicon');
            }
        });
    }

    function showMessage(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.logo-generator-container').prepend($notice);
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    function showNotice(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.logo-generator-container').prepend($notice);
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Adiciona o handler para o botão de salvar favicon
    $('#save-favicon-button').on('click', function(e) {
        e.preventDefault();
        saveFavicon();
    });

    // Atualiza os previews iniciais
    updateLogoPreview();
    updateFaviconPreview();
});
