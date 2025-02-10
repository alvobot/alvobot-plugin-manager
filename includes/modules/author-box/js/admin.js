jQuery(document).ready(function($) {
    // Função para atualizar o preview
    function updatePreview() {
        var $preview = $('.author-box-preview');
        var $authorBox = $preview.find('.alvobot-author-box');
        var $title = $authorBox.find('.author-box-title');
        var $description = $authorBox.find('.author-description');
        var $socialIcons = $authorBox.find('.author-social-icons');
        var $avatar = $authorBox.find('.author-avatar img');

        // Atualiza o título
        var titleText = $('#title_text').val();
        if (titleText) {
            $title.text(titleText).show();
        } else {
            $title.hide();
        }

        // Atualiza o tamanho do avatar
        var avatarSize = $('#avatar_size').val();
        if (avatarSize) {
            $avatar.css({
                'width': avatarSize + 'px',
                'height': avatarSize + 'px'
            });
        }

        // Atualiza a visibilidade da descrição
        if ($('#show_description').is(':checked')) {
            $description.show();
        } else {
            $description.hide();
        }

        // Atualiza a visibilidade dos ícones sociais
        if ($('#show_social_icons').is(':checked')) {
            $socialIcons.show();
        } else {
            $socialIcons.hide();
        }

        // Atualiza o status de exibição
        var displayStatus = [];
        if ($('#display_on_posts').is(':checked')) displayStatus.push('Posts');
        if ($('#display_on_pages').is(':checked')) displayStatus.push('Páginas');

        var $status = $('.author-box-preview-status');
        if ($status.length === 0) {
            $status = $('<div class="author-box-preview-status"></div>').insertBefore($authorBox);
        }

        if (displayStatus.length > 0) {
            $status.html('Author Box será exibido em: ' + displayStatus.join(' e '))
                .css({
                    'margin-bottom': '1em',
                    'padding': '0.75em 1em',
                    'background': '#e7f5ea',
                    'border-radius': '4px',
                    'color': '#0a5624'
                });
        } else {
            $status.html('Author Box está desativado')
                .css({
                    'margin-bottom': '1em',
                    'padding': '0.75em 1em',
                    'background': '#fbeaea',
                    'border-radius': '4px',
                    'color': '#8b1014'
                });
        }
    }

    // Atualiza o preview quando os campos são alterados
    $('input[type="checkbox"], input[type="number"], input[type="text"]').on('change input', updatePreview);

    // Atualiza o preview quando a página carrega
    updatePreview();
});
