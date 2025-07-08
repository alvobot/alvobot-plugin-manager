<?php

declare(strict_types=1);

if (have_posts()) : while (have_posts()) : the_post();

// Verifica se deve usar shortcode ou CTAs padrão
$use_shortcode = get_query_var('alvobot_use_shortcode');
$shortcode = get_query_var('alvobot_shortcode');

// Obtém as CTAs personalizadas ou padrão
$ctas = get_query_var('alvobot_ctas');

if (empty($ctas)) {
    $ctas = [];
}

// Adiciona o link do post a cada CTA
foreach ($ctas as &$cta) {
    $cta['link'] = get_permalink();
}
unset($cta);

// Prepara o conteúdo com filtros melhorados
$content = get_the_content();

// Aplica apenas filtros essenciais, evitando plugins que injetam scripts
$content = wpautop($content); // Converte quebras de linha em parágrafos
$content = do_shortcode($content); // Processa shortcodes se necessário

// Remove scripts, styles e outros elementos indesejados
$content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
$content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
$content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $content);
$content = preg_replace('/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi', '', $content);
$content = preg_replace('/<embed\b[^>]*>/mi', '', $content);
$content = preg_replace('/<noscript\b[^<]*(?:(?!<\/noscript>)<[^<]*)*<\/noscript>/mi', '', $content);

// Remove atributos JavaScript
$content = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);

// Remove divs e spans com classes/ids suspeitos (comuns em plugins de ads)
$content = preg_replace('/<div[^>]*class=["\'][^"\']*(?:ad|advertisement|banner|popup|modal)[^"\']*["\'][^>]*>.*?<\/div>/si', '', $content);
$content = preg_replace('/<span[^>]*class=["\'][^"\']*(?:ad|advertisement|banner)[^"\']*["\'][^>]*>.*?<\/span>/si', '', $content);

// Remove comentários HTML
$content = preg_replace('/<!--.*?-->/s', '', $content);

// Tags permitidas para o conteúdo limpo
$allowed_tags = '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote>';
$content = strip_tags($content, $allowed_tags);

// Remove espaços extras e quebras de linha desnecessárias
$content = preg_replace('/\s+/', ' ', $content);
$content = trim($content);

// Função para truncar o conteúdo sem quebrar palavras ou tags HTML
function truncate_html_words($text, $word_limit) {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $body = $doc->getElementsByTagName('body')->item(0);
    $word_count = 0;
    $truncated = false;

    $stack = [$body];
    while ($stack) {
        $node = array_pop($stack);
        if ($node instanceof DOMText) {
            $words = preg_split('/\s+/u', $node->nodeValue, -1, PREG_SPLIT_NO_EMPTY);
            $current_word_count = count($words);

            if ($word_count + $current_word_count > $word_limit) {
                $remaining_words = $word_limit - $word_count;
                $node->nodeValue = implode(' ', array_slice($words, 0, $remaining_words));
                $truncated = true;
                // Remove nós irmãos seguintes
                $nextNode = $node->nextSibling;
                while ($nextNode) {
                    $toRemove = $nextNode;
                    $nextNode = $nextNode->nextSibling;
                    $toRemove->parentNode->removeChild($toRemove);
                }
                // Remove nós filhos
                if ($node->hasChildNodes()) {
                    $childNodes = $node->childNodes;
                    $remove = false;
                    foreach ($childNodes as $child) {
                        if ($remove) {
                            $node->removeChild($child);
                        }
                        if ($child->isSameNode($node)) {
                            $remove = true;
                        }
                    }
                }
                break;
            } else {
                $word_count += $current_word_count;
            }
        }

        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                array_unshift($children, $child);
            }
            foreach ($children as $child) {
                $stack[] = $child;
            }
        }
    }

    $result = '';
    foreach ($body->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }

    return [$result, $truncated];
}

// Primeira parte - 400 caracteres
$first_part = '';
$first_truncated = false;
$words = explode(' ', strip_tags($content));
$word_count = 0;
$first_part_words = [];

foreach ($words as $word) {
    $word_count += strlen($word);
    $first_part_words[] = $word;
    
    if ($word_count >= 200) {
        $first_truncated = true;
        break;
    }
}

$first_part = implode(' ', $first_part_words);

// Pega o restante do conteúdo
$remaining_words = array_slice($words, count($first_part_words));
$second_part = '';
$second_truncated = false;
$char_count = 0;
$second_part_words = [];

// Segunda parte - 150 caracteres
foreach ($remaining_words as $word) {
    $char_count += strlen($word);
    $second_part_words[] = $word;
    
    if ($char_count >= 400) {
        $second_truncated = true;
        break;
    }
}

$second_part = implode(' ', $second_part_words);

// Reaplica as tags HTML ao conteúdo
$first_part = wpautop($first_part);
$second_part = wpautop($second_part);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?></title>
    <link rel="canonical" href="<?php echo esc_url(get_permalink()); ?>" />
    <?php wp_head(); ?>
    <style>
        @keyframes ctaGrow {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .cta-button {
            transition: transform 0.3s ease;
        }
        
        .cta-button.animate {
            animation: ctaGrow 2s ease;
        }

        /* Estilos do rodapé */
        .pre-article-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-section {
            font-size: 0.9rem;
            color: #666;
        }

        .footer-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin: 10px;
        }

        .footer-link {
            color: #2271b1;
            text-decoration: none;
            transition: color 0.2s ease;
            font-size: 0.9rem;
        }

        .footer-link:hover {
            color: #135e96;
            text-decoration: underline;
        }

        .legal-disclaimer {
            text-align: center;
            line-height: 1.5;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Responsividade */
        @media screen and (max-width: 782px) {
            .footer-links {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctaButtons = document.querySelectorAll('.cta-button');
            let lastAnimationTime = {};
            
            // Função para animar um botão específico
            function animateButton(button) {
                if (Date.now() - (lastAnimationTime[button.textContent] || 0) < 7000) {
                    return; // Evita animar se passou menos de 7 segundos
                }
                
                button.classList.add('animate');
                lastAnimationTime[button.textContent] = Date.now();
                
                setTimeout(() => {
                    button.classList.remove('animate');
                }, 2000);
            }
            
            // Inicializa tempos aleatórios para cada botão
            ctaButtons.forEach((button, index) => {
                const initialDelay = Math.random() * (30000 - 15000) + 15000; // Entre 15 e 30 segundos
                setTimeout(() => {
                    animateButton(button);
                    
                    // Configura o intervalo de 40 segundos após a primeira animação
                    setInterval(() => {
                        animateButton(button);
                    }, 40000);
                }, initialDelay);
            });
        });
    </script>
</head>
<body <?php body_class('pre-article-page'); ?>>
    <?php wp_body_open(); ?>
    
    <div class="pre-article-container">
        <main class="pre-article-content">
            <div class="pre-article-text">
                <h2><?php the_title(); ?></h2>
                
                <!-- Primeira parte do conteúdo -->
                <div class="excerpt">
                    <?php 
                    // Remove qualquer HTML no final que possa causar quebra de linha
                    $first_part = preg_replace('/<\/p>\s*$/', '', $first_part);
                    // Remove espaços e pontuação final
                    $first_part = rtrim($first_part, " \n\r\t\v\x00.");
                    // Adiciona UTM ao link
                    $continue_1_url = add_query_arg([
                        'utm_content' => 'continue_1'
                    ], get_permalink());
                    // Adiciona o continue na mesma linha
                    echo $first_part . ' <a href="' . esc_url($continue_1_url) . '" class="continue-reading">...continue</a>';
                    ?>
                </div>

                <?php 
                // Script após o primeiro parágrafo
                if (class_exists('Alvobot_Pre_Article')) {
                    Alvobot_Pre_Article::render_custom_script('after_first_paragraph'); 
                }
                ?>

                <!-- CTAs ou Shortcode -->
                <div class="cta-buttons">
                    <?php
                    if ($use_shortcode && !empty($shortcode)) {
                        // Renderiza o shortcode
                        echo do_shortcode($shortcode);
                    } elseif (!empty($ctas)) {
                        // Renderiza os botões CTA padrão
                        foreach ($ctas as $index => $cta) {
                            // Constrói a UTM
                            $utm_params = [
                                'utm_content' => sanitize_title($cta['text']) . '_cta_' . ($index + 1)
                            ];
                            
                            // Adiciona os parâmetros UTM à URL
                            $cta_url = add_query_arg($utm_params, esc_url($cta['link']));
                            ?>
                            <a href="<?php echo esc_url($cta_url); ?>" class="cta-button" style="background-color: <?php echo esc_attr($cta['color']); ?>;">
                                <?php echo esc_html($cta['text']); ?>
                            </a>
                            <?php
                        }
                    }
                    ?>
                </div>

                <?php 
                // Script após os botões CTA
                if (class_exists('Alvobot_Pre_Article')) {
                    Alvobot_Pre_Article::render_custom_script('after_ctas'); 
                }
                ?>

                <!-- Segunda parte do conteúdo -->
                <div class="excerpt-continuation">
                    <?php 
                    if (!empty($second_part)) {
                        // Remove qualquer HTML no final que possa causar quebra de linha
                        $second_part = preg_replace('/<\/p>\s*$/', '', $second_part);
                        // Remove espaços e pontuação final
                        $second_part = rtrim($second_part, " \n\r\t\v\x00.");
                        // Adiciona UTM ao link
                        $continue_2_url = add_query_arg([
                            'utm_content' => 'continue_2'
                        ], get_permalink());
                        // Adiciona o continue na mesma linha
                        echo $second_part . ' <a href="' . esc_url($continue_2_url) . '" class="continue-reading">...continue</a>';
                    }
                    ?>
                </div>

                <?php 
                // Script após o segundo parágrafo
                if (class_exists('Alvobot_Pre_Article')) {
                    Alvobot_Pre_Article::render_custom_script('after_second_paragraph'); 
                }
                ?>

            </div>

            <?php 
            // Script antes do rodapé
            if (class_exists('Alvobot_Pre_Article')) {
                Alvobot_Pre_Article::render_custom_script('before_footer'); 
            }
            ?>

            <footer class="pre-article-footer">
                <div class="footer-content">
                <div class="footer-section links-section">
                        <nav class="footer-links">
                            <?php
                            // Obtém as páginas essenciais
                            $contact_page = get_page_by_path('contato');
                            $privacy_page = get_page_by_path('politica-de-privacidade');
                            $terms_page = get_page_by_path('termos-de-uso');

                            // Array com as páginas e seus títulos
                            $footer_pages = array(
                                'contato' => array(
                                    'title' => 'Contato',
                                    'page' => $contact_page
                                ),
                                'politica-de-privacidade' => array(
                                    'title' => 'Política de Privacidade',
                                    'page' => $privacy_page
                                ),
                                'termos-de-uso' => array(
                                    'title' => 'Termos de Uso',
                                    'page' => $terms_page
                                )
                            );

                            // Cria os links
                            foreach ($footer_pages as $slug => $data) {
                                if ($data['page']) {
                                    $page_url = get_permalink($data['page']);
                                    // Adiciona UTM
                                    $page_url = add_query_arg(array(
                                        'utm_content' => 'footer_' . $slug
                                    ), $page_url);
                                    ?>
                                    <a href="<?php echo esc_url($page_url); ?>" class="footer-link">
                                        <?php echo esc_html($data['title']); ?>
                                    </a>
                                    <?php
                                }
                            }
                            ?>
                        </nav>
                    </div>
                    <div class="footer-section disclaimer-section">
                        <div class="legal-disclaimer">
                            <?php
                            $options = get_option('alvobot_pre_artigo_options');
                            $default_footer = 'Aviso Legal: As informações deste site são meramente informativas e não substituem orientação profissional. Os resultados apresentados são ilustrativos, sem garantia de sucesso específico. Somos um site independente, não afiliado a outras marcas, que preza pela privacidade do usuário e protege suas informações pessoais, utilizando apenas para comunicações relacionadas aos nossos serviços.';
                            
                            $footer_text = $options['footer_text'] ?? $default_footer;
                            
                            // Substitui o nome do site
                            $footer_text = str_replace('{NOME DO SITE}', get_bloginfo('name'), $footer_text);
                            
                            echo wp_kses_post($footer_text);
                            ?>
                        </div>
                    </div>
                    <div class="footer-section copyright-section">
                        <div class="footer-links">
                            <?php
                            // Links padrão do rodapé
                            $privacy_policy_url = get_privacy_policy_url();
                            if ($privacy_policy_url) {
                                echo '<a href="' . esc_url($privacy_policy_url) . '" class="footer-link">' . __('Política de Privacidade', 'alvobot-pre-artigo') . '</a>';
                            }
                            ?>
                        </div>
                        <p class="copyright">
                            &copy; <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>. 
                            <?php _e('Todos os direitos reservados.', 'alvobot-pre-artigo'); ?>
                        </p>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
<?php
endwhile; endif;
?>