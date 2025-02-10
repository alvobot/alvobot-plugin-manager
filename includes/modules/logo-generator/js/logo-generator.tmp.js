jQuery(document).ready(function($) {
    let previewTimeout;
    
    // Inicializa o color picker
    $('.color-picker').wpColorPicker({
        change: function(event, ui) {
            $(event.target).val(ui.color.toString());
            schedulePreviewUpdate();
        }
    });

    // Atualiza a fonte selecionada
    $('#font_choice').on('change', function() {
        var $selected = $(this).find('option:selected');
        $(this).css('font-family', $selected.css('font-family'));
        schedulePreviewUpdate();
    }).trigger('change');

    // Atualiza quando o ícone é alterado
    $('.icon-grid input[type="radio"]').on('change', function() {
        schedulePreviewUpdate();
    });

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
        formData.append('nonce', logoGeneratorParams.nonce);

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
                    showError('Erro ao gerar preview do logo');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                showError('Erro ao gerar preview do logo');
                $previewContent.css('opacity', '1');
            }
        });
    }

    function updateFaviconPreview() {
        const $previewContent = $('#favicon-preview-content');
        $previewContent.css('opacity', '0.5');

        var formData = new FormData();
        formData.append('action', 'generate_favicon');
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_color', $('#font_color').val());
        formData.append('background_color', $('#background_color').val());
        formData.append('nonce', logoGeneratorParams.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success && response.data) {
                    $previewContent.html(response.data).css('opacity', '1');
                    const $svg = $previewContent.find('svg').first();
                    if ($svg.length) {
                        $svg.css({
                            'width': '32px',
                            'height': '32px'
                        });
                    }
                } else {
                    console.error('Erro ao gerar preview do favicon:', response.data);
                    showError('Erro ao gerar preview do favicon');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                showError('Erro ao gerar preview do favicon');
                $previewContent.css('opacity', '1');
            }
        });
    }

    function saveLogo() {
        const $form = $('#logo-generator-form');
        const $submitButton = $form.find('button[type="submit"]');
        
        $submitButton.prop('disabled', true);
        
        var formData = new FormData();
        formData.append('action', 'save_logo');
        formData.append('blog_name', $('#blog_name').val());
        formData.append('font_color', $('#font_color').val());
        formData.append('background_color', $('#background_color').val());
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_choice', $('#font_choice').val());
        formData.append('set_as_logo', $('#set_as_logo').is(':checked'));
        formData.append('nonce', logoGeneratorParams.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $submitButton.prop('disabled', false);
                if (response.success) {
                    showSuccess('Logo salvo com sucesso!');
                    if ($('#set_as_favicon').is(':checked')) {
                        saveFavicon();
                    }
                } else {
                    console.error('Erro ao salvar o logo:', response.data);
                    showError(response.data || 'Erro ao salvar o logo');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                showError('Erro ao salvar o logo: ' + error);
                $submitButton.prop('disabled', false);
            }
        });
    }

    function saveFavicon() {
        var formData = new FormData();
        formData.append('action', 'save_favicon');
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_color', $('#font_color').val());
        formData.append('background_color', $('#background_color').val());
        formData.append('nonce', logoGeneratorParams.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showSuccess('Favicon salvo com sucesso!');
                } else {
                    console.error('Erro ao salvar o favicon:', response.data);
                    showError(response.data || 'Erro ao salvar o favicon');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                showError('Erro ao salvar o favicon: ' + error);
            }
        });
    }

    function showSuccess(message) {
        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('#logo-generator-form').before($notice);
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    function showError(message) {
        const $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('#logo-generator-form').before($notice);
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Atualiza os previews iniciais
    updateLogoPreview();
    updateFaviconPreview();
});
