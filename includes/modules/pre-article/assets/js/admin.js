jQuery(document).ready(function($) {
    // Array de cores predefinidas para as CTAs
    const ALVOBOT_CTA_COLORS = [
        '#FF6B6B', // Vermelho coral
        '#4ECDC4', // Verde água
        '#45B7D1', // Azul claro
        '#96CEB4', // Verde menta
        '#FFEEAD', // Amarelo claro
        '#D4A5A5', // Rosa antigo
        '#9B59B6', // Roxo
        '#3498DB', // Azul
        '#E67E22', // Laranja
        '#2ECC71'  // Verde
    ];

    // Função para obter uma cor aleatória
    function getRandomColor(usedColors = []) {
        // Filtra cores que já estão em uso
        const availableColors = ALVOBOT_CTA_COLORS.filter(color => !usedColors.includes(color));
        
        // Se todas as cores estiverem em uso, gera uma cor aleatória
        if (availableColors.length === 0) {
            return '#' + Math.floor(Math.random()*16777215).toString(16);
        }

        // Retorna uma cor aleatória das disponíveis
        return availableColors[Math.floor(Math.random() * availableColors.length)];
    }

    // Inicializa o color picker para CTAs existentes
    $('.wp-color-picker-field').wpColorPicker();

    // Função para criar uma nova CTA box para o metabox
    function createMetaBoxCTABox(index, translations) {
        const randomColor = getRandomColor();
        const defaultText = translations.defaultTexts[(index - 1) % translations.defaultTexts.length] || '';
        
        return `
            <div class="cta-box">
                <div class="form-field">
                    <label>${translations.buttonText} ${index}:</label>
                    <input type="text" name="alvobot_ctas[${index - 1}][text]" value="${defaultText}" class="widefat" />
                </div>
                <div class="form-field">
                    <label>${translations.buttonColor} ${index}:</label>
                    <input type="text" class="wp-color-picker-field" name="alvobot_ctas[${index - 1}][color]" value="${randomColor}" data-default-color="#1E73BE" />
                </div>
            </div>
        `;
    }

    // Handler para o checkbox de habilitar/desabilitar
    $('input[name="alvobot_use_custom"]').on('change', function() {
        var $customOptions = $('#alvobot_custom_options');
        if ($(this).is(':checked')) {
            $customOptions.slideDown('fast', function() {
                // Verifica se já existem CTAs
                if ($('#alvobot_ctas_container').children('.cta-box').length === 0) {
                    // Inicializa com o número atual de CTAs
                    var numCTAs = $('#alvobot_num_ctas').val() || 1;
                    $('#alvobot_num_ctas').trigger('change');
                }
            });
        } else {
            $customOptions.slideUp('fast');
        }
    });

    // Handler para mudança na quantidade de CTAs no metabox
    $('#alvobot_num_ctas').on('change', function() {
        var numCTAs = parseInt($(this).val()) || 1;
        var container = $('#alvobot_ctas_container');
        var currentCTAs = container.children('.cta-box').length;

        // Limitar o número de CTAs entre 1 e 10
        if (numCTAs < 1) numCTAs = 1;
        if (numCTAs > 10) numCTAs = 10;
        $(this).val(numCTAs);

        if (numCTAs > currentCTAs) {
            // Adiciona apenas as novas CTAs
            for (var i = currentCTAs + 1; i <= numCTAs; i++) {
                var newCTA = $(createMetaBoxCTABox(i, alvobotTranslations));
                container.append(newCTA);
                // Inicializar color picker para nova CTA
                newCTA.find('.wp-color-picker-field').wpColorPicker();
            }
        } else if (numCTAs < currentCTAs) {
            // Remove CTAs excedentes
            container.children('.cta-box').slice(numCTAs).remove();
        }
    });

    // Inicializar o estado do checkbox
    $('input[name="alvobot_use_custom"]').trigger('change');

    // Handler para a página de configurações
    $('#num_ctas').on('change input', function() {
        var quantidade = parseInt($(this).val());
        var container = $('#ctas_container');
        var ctasAtuais = container.find('.cta-box').length;

        // Limitar o número de CTAs entre 1 e 10
        if (quantidade < 1) quantidade = 1;
        if (quantidade > 10) quantidade = 10;
        $(this).val(quantidade);

        // Obtém cores já em uso
        const usedColors = [];
        container.find('.wp-color-picker-field').each(function() {
            usedColors.push($(this).val());
        });

        if (quantidade > ctasAtuais) {
            // Adiciona novas CTAs
            for (var i = ctasAtuais + 1; i <= quantidade; i++) {
                var novaCor = getRandomColor(usedColors);
                usedColors.push(novaCor);
                
                var novaCta = $(`
                    <div class="cta-box">
                        <h3>CTA ${i}</h3>
                        <div class="cta-field">
                            <label for="button_text_${i}">Texto do Botão:</label>
                            <input type="text" id="button_text_${i}" name="alvobot_pre_artigo_options[button_text_${i}]" value="">
                        </div>
                        <div class="cta-field">
                            <label for="button_color_${i}">Cor do Botão:</label>
                            <input type="text" class="wp-color-picker-field" id="button_color_${i}" name="alvobot_pre_artigo_options[button_color_${i}]" value="${novaCor}" data-default-color="#1E73BE">
                        </div>
                    </div>
                `);
                container.append(novaCta);
                novaCta.find('.wp-color-picker-field').wpColorPicker();
            }
        } else {
            // Remove CTAs excedentes
            container.find('.cta-box').slice(quantidade).remove();
        }
    });
});