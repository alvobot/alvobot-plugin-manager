jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.color-picker').wpColorPicker({
        change: function() {
            updatePreview();
        }
    });
    
    // Event handlers para inputs
    $('#blog_name, #font_choice').on('change input', function() {
        updatePreview();
    });

    // Atualiza quando um ícone é selecionado
    $('input[name="icon_choice"]').on('change', function() {
        updatePreview();
    });
    
    // Função unificada para atualizar previews
    function updatePreview() {
        const formData = new FormData();
        formData.append('action', 'generate_logo');
        formData.append('_wpnonce', logoGeneratorParams.nonce);
        formData.append('blog_name', $('#blog_name').val());
        formData.append('font_color', $('#font_color').wpColorPicker('color'));
        formData.append('background_color', $('#background_color').wpColorPicker('color'));
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_choice', $('#font_choice').val());
        
        // Atualiza preview do logo
        $.ajax({
            url: logoGeneratorParams.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#logo-preview-content').html(response.data);
                    // Após sucesso do logo, atualiza o favicon
                    updateFavicon();
                } else {
                    showNotice('error', 'Erro ao gerar o logo: ' + (response.data || 'Erro desconhecido'));
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Erro ao comunicar com o servidor: ' + error);
            }
        });
    }
    
    // Função para atualizar apenas o favicon
    function updateFavicon() {
        const formData = new FormData();
        formData.append('action', 'generate_favicon');
        formData.append('_wpnonce', logoGeneratorParams.nonce);
        formData.append('icon_choice', $('input[name="icon_choice"]:checked').val());
        formData.append('font_color', $('#font_color').wpColorPicker('color'));
        formData.append('background_color', $('#background_color').wpColorPicker('color'));
        
        $.ajax({
            url: logoGeneratorParams.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#favicon-preview-content').html(response.data);
                } else {
                    showNotice('error', 'Erro ao gerar o favicon: ' + (response.data || 'Erro desconhecido'));
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Erro ao comunicar com o servidor: ' + error);
            }
        });
    }

    // Variáveis para controle de busca e filtro
    let currentCategory = 'all';
    let currentSearch = '';
    
    // Função para filtrar ícones
    function filterIcons() {
        const searchTerm = currentSearch.toLowerCase();
        let found = false;
        
        $('.icon-option').each(function() {
            const $icon = $(this);
            const name = $icon.data('name').toLowerCase();
            const keywords = $icon.data('keywords').toLowerCase();
            const category = $icon.data('category');
            
            // Verifica se o ícone corresponde à categoria e à busca
            const matchesCategory = currentCategory === 'all' || category === currentCategory;
            const matchesSearch = !searchTerm || 
                                name.includes(searchTerm) || 
                                keywords.includes(searchTerm);
            
            const shouldShow = matchesCategory && matchesSearch;
            $icon.toggle(shouldShow);
            
            if (shouldShow) found = true;
        });
        
        // Mostra/esconde mensagem de "nenhum ícone encontrado"
        $('.no-icons-found').toggle(!found);
        
        // Se não houver ícones visíveis, desmarca o radio selecionado
        if (!found) {
            $('input[name="icon_choice"]:checked').prop('checked', false);
        }
    }
    
    // Handler para busca de ícones
    $('#icon-search').on('input', function() {
        currentSearch = $(this).val();
        filterIcons();
    });
    
    // Handler para filtro por categoria
    $('.icon-category').on('click', function() {
        const $this = $(this);
        
        // Remove classe active de todas as categorias
        $('.icon-category').removeClass('active');
        
        // Adiciona classe active na categoria clicada
        $this.addClass('active');
        
        // Atualiza categoria atual
        currentCategory = $this.data('category');
        
        // Filtra os ícones
        filterIcons();
    });
    
    // Handler para seleção de ícone
    $('.icon-option').on('click', function() {
        const $radio = $(this).find('input[type="radio"]');
        $radio.prop('checked', true);
        updatePreview();
    });
    
    // Handler para o formulário
    $('#logo-generator-form').on('submit', function(e) {
        e.preventDefault();
        
        const $button = $('input[type="submit"]', this);
        const originalText = $button.val();
        
        // Verifica se um ícone foi selecionado
        const selectedIcon = $('input[name="icon_choice"]:checked').val();
        if (!selectedIcon) {
            showNotice('error', 'Por favor, selecione um ícone primeiro.');
            return;
        }
        
        // Desabilita o botão durante o salvamento
        $button.prop('disabled', true).val('Salvando...');
        
        // Primeiro salva o logo se a opção estiver marcada
        const saveLogo = $('#set_as_logo').prop('checked') ? new Promise((resolve, reject) => {
            const logoData = new FormData();
            logoData.append('action', 'save_logo');
            logoData.append('_wpnonce', logoGeneratorParams.nonce);
            logoData.append('blog_name', $('#blog_name').val());
            logoData.append('font_color', $('#font_color').wpColorPicker('color'));
            logoData.append('background_color', $('#background_color').wpColorPicker('color'));
            logoData.append('icon_choice', selectedIcon);
            logoData.append('font_choice', $('#font_choice').val());
            logoData.append('set_as_logo', '1');

            $.ajax({
                url: logoGeneratorParams.ajaxurl,
                type: 'POST',
                data: logoData,
                processData: false,
                contentType: false
            }).then(resolve, reject);
        }) : Promise.resolve(null);

        // Depois salva o favicon se a opção estiver marcada
        const saveFavicon = $('#set_as_favicon').prop('checked') ? new Promise((resolve, reject) => {
            const faviconData = new FormData();
            faviconData.append('action', 'save_favicon');
            faviconData.append('_wpnonce', logoGeneratorParams.nonce);
            faviconData.append('icon_choice', selectedIcon);
            faviconData.append('font_color', $('#font_color').wpColorPicker('color'));
            faviconData.append('background_color', $('#background_color').wpColorPicker('color'));

            $.ajax({
                url: logoGeneratorParams.ajaxurl,
                type: 'POST',
                data: faviconData,
                processData: false,
                contentType: false
            }).then(resolve, reject);
        }) : Promise.resolve(null);

        // Executa as promessas em sequência
        Promise.all([saveLogo, saveFavicon])
            .then(([logoResponse, faviconResponse]) => {
                let messages = [];
                
                if (logoResponse && logoResponse.success) {
                    messages.push(logoResponse.data.message);
                    
                    // Atualiza o logo no site
                    if (logoResponse.data.logo) {
                        const customLogoImg = $('.custom-logo');
                        if (customLogoImg.length) {
                            customLogoImg.attr('src', logoResponse.data.logo.url + '?t=' + new Date().getTime());
                        }
                    }
                }
                
                if (faviconResponse && faviconResponse.success) {
                    messages.push(faviconResponse.data.message);
                    
                    // Atualiza o favicon
                    if (faviconResponse.data.favicon) {
                        const faviconUrl = faviconResponse.data.favicon.url + '?t=' + new Date().getTime();
                        
                        // Atualiza favicons existentes
                        $('link[rel*="icon"]').each(function() {
                            $(this).attr('href', faviconUrl);
                        });
                        
                        // Adiciona favicon se não existir
                        if (!$('link[rel="icon"]').length) {
                            $('head').append('<link rel="icon" type="image/svg+xml" href="' + faviconUrl + '">');
                        }
                        
                        // Atualiza ícone da Apple
                        if (!$('link[rel="apple-touch-icon"]').length) {
                            $('head').append('<link rel="apple-touch-icon" href="' + faviconUrl + '">');
                        } else {
                            $('link[rel="apple-touch-icon"]').attr('href', faviconUrl);
                        }
                    }
                }
                
                if (messages.length > 0) {
                    showNotice('success', messages.join(' '));
                }
            })
            .catch(error => {
                showNotice('error', 'Erro ao salvar: ' + (error.message || 'Erro desconhecido'));
            })
            .finally(() => {
                // Reabilita o botão
                $button.prop('disabled', false).val(originalText);
            });
    });
    
    function showNotice(type, message) {
        // Remove notificações anteriores
        $('.notice').remove();
        
        // Cria nova notificação no estilo WordPress
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Insere a notificação após o header
        $('.alvobot-pro-header').after(notice);
        
        // Adiciona botão de fechar
        notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Fechar</span></button>');
        
        // Handler para fechar a notificação
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // Seleciona primeiro ícone por padrão e inicia preview
    if (!$('input[name="icon_choice"]:checked').length) {
        $('input[name="icon_choice"]').first().prop('checked', true);
    }
    
    // Inicia o preview ao carregar a página
    updatePreview();
});
