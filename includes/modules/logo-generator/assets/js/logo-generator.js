jQuery(document).ready(function($) {
    // Inicializa o color picker
    $('.color-picker').wpColorPicker({
        change: function() {
            updatePreview();
        }
    });

    // Função para atualizar o preview em tempo real
    function updatePreview() {
        var blogName = $('#blog_name').val();
        var fontChoice = $('#font_choice').val();
        var fontColor = $('#font_color').val();
        var backgroundColor = $('#background_color').val();
        var iconChoice = $('input[name="icon_choice"]:checked').val();

        if (blogName && fontChoice && fontColor && backgroundColor && iconChoice) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'generate_logo',
                    nonce: logoGenerator.nonce,
                    blog_name: blogName,
                    font_choice: fontChoice,
                    font_color: fontColor,
                    background_color: backgroundColor,
                    icon_choice: iconChoice
                },
                success: function(response) {
                    if (response.success) {
                        $('#preview-container').html(response.data.svg);
                        $('#logo-preview').show();
                        
                        // Ajusta o alinhamento do SVG
                        $('#preview-container svg').css({
                            'display': 'block',
                            'margin': '0'
                        });
                    } else {
                        alert('Erro ao gerar o logo: ' + response.data);
                    }
                },
                error: function() {
                    alert('Erro ao comunicar com o servidor');
                }
            });
        }
    }

    // Eventos para atualizar o preview em tempo real
    $('#blog_name').on('input', updatePreview);
    $('#font_choice').on('change', updatePreview);
    $('input[name="icon_choice"]').on('change', updatePreview);

    // Preview inicial
    updatePreview();

    // Botão para salvar o logo
    $('#save-logo').on('click', function() {
        var svg = $('#preview-container').html();
        if (!svg) {
            alert('Por favor, gere um logo primeiro');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_logo',
                nonce: logoGenerator.nonce,
                svg: svg
            },
            success: function(response) {
                if (response.success) {
                    alert('Logo salvo com sucesso!');
                } else {
                    alert('Erro ao salvar o logo: ' + response.data);
                }
            },
            error: function() {
                alert('Erro ao comunicar com o servidor');
            }
        });
    });

    // Botão para gerar novo logo
    $('#regenerate-logo').on('click', function() {
        updatePreview();
    });
});
