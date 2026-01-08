<?php
/**
 * Classe principal do Alvobot Pre Article
 *
 * @package Alvobot_Pre_Article
 */

declare(strict_types=1);

if (!class_exists('Alvobot_Pre_Article')) {

    class Alvobot_Pre_Article {
        /**
         * Versão do plugin
         */
        private $version;

        /**
         * Nome do plugin
         */
        private $plugin_name;

        /**
         * Nome da opção no banco de dados
         */
        private $option_name = 'alvobot_pre_artigo_options';

        /**
         * Textos padrão para as CTAs
         */
        private $default_cta_texts = [
            'Desejo Saber Mais Sobre o Assunto',
            'Desbloquear o Conteúdo Agora',
            'Quero Ler o Artigo Completo!',
            'Continuar Lendo Este Conteúdo',
            'Ver o Artigo na Íntegra',
            'Acessar o Conteúdo Completo',
            'Não Quero Perder o Resto',
            'Mostrar o Artigo Inteiro',
            'Ler Mais Sobre Este Tema',
            'Explorar o Assunto Completo'
        ];

        /**
         * Construtor
         */
        public function __construct() {
            // Define a versão e nome do plugin com base na versão (Pro ou Free)
            if (defined('ALVOBOT_PRO_VERSION')) {
                $this->version = ALVOBOT_PRO_VERSION;
                $this->plugin_name = 'alvobot-pro';
            } else {
                $this->version = ALVOBOT_PRE_ARTICLE_VERSION;
                $this->plugin_name = 'alvobot-pre-artigo';
            }

            // Inclui a classe de traduções de CTAs
            $this->load_cta_translations_class();
        }

        /**
         * Carrega a classe de traduções de CTAs
         */
        private function load_cta_translations_class() {
            $translations_file = dirname(__FILE__) . '/class-cta-translations.php';
            if (file_exists($translations_file)) {
                require_once $translations_file;
            }
        }

        /**
         * Inicializa o módulo
         */
        public function run() {
            // Hooks do Admin
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post', [$this, 'save_meta_box_data']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

            // Hooks do Front-end
            add_action('init', [$this, 'register_rewrite_rules']);
            add_action('init', [$this, 'load_plugin_textdomain']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

            // Hooks de Template e Query
            add_filter('query_vars', [$this, 'add_query_vars']);
            add_action('pre_get_posts', [$this, 'modify_main_query']);
            add_filter('template_include', [$this, 'load_pre_article_template']);
            add_filter('post_row_actions', [$this, 'add_pre_article_link'], 10, 2);

            // Hooks específicos da versão Pro
            if (defined('ALVOBOT_PRO_VERSION')) {
                add_filter('alvobot_pro_modules', [$this, 'register_module']);
            }

            // Força flush das regras de rewrite se necessário
            add_action('init', [$this, 'maybe_flush_rewrite_rules'], 20);

            // Multi-language integration
            $this->init_multilanguage_support();
        }

        /**
         * Registra o meta box no editor de posts
         */
        public function add_meta_boxes() {
            add_meta_box(
                'alvobot_pre_artigo_meta_box',
                __('Configuração do Pré-Artigo', 'alvobot-pre-artigo'),
                [$this, 'render_meta_box'],
                'post',
                'normal',
                'high'
            );
        }

        /**
         * Renderiza o conteúdo do meta box
         */
        public function render_meta_box($post) {
            wp_nonce_field('alvobot_pre_article_meta_box', 'alvobot_pre_article_meta_box_nonce');

            // Enfileira scripts e estilos
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script(
                'alvobot-pre-article-admin-js',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
                ['jquery', 'wp-color-picker'],
                ALVOBOT_PRE_ARTICLE_VERSION,
                true
            );

            // Obtém CTAs traduzidas para o idioma atual
            $translated_ctas = class_exists('Alvobot_PreArticle_CTA_Translations') 
                ? Alvobot_PreArticle_CTA_Translations::get_translated_ctas() 
                : $this->default_cta_texts;

            // Localiza script
            wp_localize_script('alvobot-pre-article-admin-js', 'alvobotTranslations', [
                'cta' => __('CTA', 'alvobot-pre-artigo'),
                'buttonText' => __('Texto do Botão:', 'alvobot-pre-artigo'),
                'buttonColor' => __('Cor do Botão:', 'alvobot-pre-artigo'),
                'defaultTexts' => $translated_ctas,
                'currentLanguage' => class_exists('Alvobot_PreArticle_CTA_Translations') 
                    ? Alvobot_PreArticle_CTA_Translations::get_current_language() 
                    : 'pt'
            ]);

            // Obtém os dados salvos
            $use_custom = get_post_meta($post->ID, '_alvobot_use_custom', true);

            // Se não há valor salvo (post novo ou nunca configurado), habilita por padrão
            if ($use_custom === '' && !get_post_meta($post->ID, '_alvobot_use_custom_set', true)) {
                $use_custom = '1';
                // Log para debug
                if (function_exists('AlvoBotPro') && method_exists('AlvoBotPro', 'debug_log')) {
                    AlvoBotPro::debug_log('pre-article', "Página de pré-artigo habilitada por padrão para post ID: {$post->ID}");
                }
            }

            $num_ctas = get_post_meta($post->ID, '_alvobot_num_ctas', true);
            $ctas = get_post_meta($post->ID, '_alvobot_ctas', true);
            $use_shortcode = get_post_meta($post->ID, '_alvobot_use_shortcode', true);
            $shortcode = get_post_meta($post->ID, '_alvobot_shortcode', true);

            // Define valores padrão se não estão definidos
            if (empty($num_ctas)) {
                $num_ctas = 3; // 3 CTAs por padrão
            }
            $pre_article_url = home_url('/pre/' . $post->post_name);
            ?>
            <div class="alvobot-meta-box">
                <div class="pre-article-section">
                    <h4><?php _e('URL do Pré-Artigo', 'alvobot-pre-artigo'); ?></h4>
                    <div class="pre-article-url">
                        <input type="text" value="<?php echo esc_url($pre_article_url); ?>" class="widefat code" readonly onclick="this.select();" />
                    </div>
                </div>

                <div class="cta-config-section">
                    <div class="form-field">
                        <label>
                            <input type="checkbox" name="alvobot_use_custom" value="1" <?php checked($use_custom, '1'); ?> />
                            <?php _e('Habilitar página de pré-artigo', 'alvobot-pre-artigo'); ?>
                        </label>
                    </div>

                    <div id="alvobot_custom_options" class="custom-cta-options" <?php echo ('1' === $use_custom) ? '' : 'style="display:none;"'; ?>>
                        <div class="form-field">
                            <label>
                                <input type="radio" name="alvobot_cta_type" value="default" <?php checked($use_shortcode, ''); checked($use_shortcode, '0'); ?> />
                                <?php _e('Usar CTAs padrão', 'alvobot-pre-artigo'); ?>
                            </label>
                            <label style="margin-left: 20px;">
                                <input type="radio" name="alvobot_cta_type" value="shortcode" <?php checked($use_shortcode, '1'); ?> />
                                <?php _e('Usar shortcode personalizado', 'alvobot-pre-artigo'); ?>
                            </label>
                        </div>

                        <div id="alvobot_default_cta_options" <?php echo ($use_shortcode !== '1') ? '' : 'style="display:none;"'; ?>>
                            <div class="form-field">
                                <label for="alvobot_num_ctas"><?php _e('Número de CTAs:', 'alvobot-pre-artigo'); ?></label>
                                <input type="number" name="alvobot_num_ctas" id="alvobot_num_ctas"
                                       value="<?php echo esc_attr($num_ctas); ?>"
                                       min="1" max="10" class="small-text" />
                            </div>

                            <div id="alvobot_ctas_container" class="cta-boxes">
                            <?php
                            $num_ctas = $num_ctas ? intval($num_ctas) : 1;
                            for ($i = 0; $i < $num_ctas; $i++) {
                                // Usa traduções automáticas para CTAs vazias ou padrão
                                $saved_text = isset($ctas[$i]['text']) ? $ctas[$i]['text'] : '';
                                
                                if (empty($saved_text)) {
                                    // Se não há texto salvo, usa tradução automática
                                    $cta_text = class_exists('Alvobot_PreArticle_CTA_Translations') 
                                        ? Alvobot_PreArticle_CTA_Translations::get_translated_cta_by_index($i) 
                                        : ($this->default_cta_texts[$i] ?? '');
                                } else {
                                    // Se há texto salvo, tenta traduzir se for um texto padrão
                                    $cta_text = class_exists('Alvobot_PreArticle_CTA_Translations')
                                        ? Alvobot_PreArticle_CTA_Translations::translate_default_cta($saved_text)
                                        : $saved_text;
                                }
                                
                                $cta_color = isset($ctas[$i]['color']) ? $ctas[$i]['color'] : '#1E73BE';
                                ?>
                                <div class="cta-box">
                                    <div class="form-field">
                                        <label><?php printf(__('Texto da CTA %d:', 'alvobot-pre-artigo'), $i + 1); ?></label>
                                        <input type="text" name="alvobot_ctas[<?php echo $i; ?>][text]" 
                                               value="<?php echo esc_attr($cta_text); ?>" class="widefat" />
                                    </div>
                                    <div class="form-field">
                                        <label><?php printf(__('Cor da CTA %d:', 'alvobot-pre-artigo'), $i + 1); ?></label>
                                        <input type="text" class="wp-color-picker-field" 
                                               name="alvobot_ctas[<?php echo $i; ?>][color]" 
                                               value="<?php echo esc_attr($cta_color); ?>" 
                                               data-default-color="#1E73BE" />
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                            </div>
                        </div>

                        <div id="alvobot_shortcode_options" <?php echo ($use_shortcode === '1') ? '' : 'style="display:none;"'; ?>>
                            <div class="form-field">
                                <label for="alvobot_shortcode"><?php _e('Shortcode:', 'alvobot-pre-artigo'); ?></label>
                                <textarea name="alvobot_shortcode" id="alvobot_shortcode" rows="3" class="widefat" placeholder="[meu_shortcode parametro='valor']"><?php echo esc_textarea($shortcode); ?></textarea>
                                <p class="description"><?php _e('Cole aqui o shortcode que será usado no lugar dos botões CTA padrão.', 'alvobot-pre-artigo'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Carrega scripts e estilos no admin
         */
        public function enqueue_admin_scripts($hook) {
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                // Usa o CSS unificado do sistema
                wp_enqueue_style(
                    'alvobot-pro-styles',
                    ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
                    [],
                    $this->version
                );

                // Color Picker
                wp_enqueue_style('wp-color-picker');
                
                // Scripts
                wp_enqueue_script(
                    'alvobot-pre-article-admin',
                    ALVOBOT_PRE_ARTICLE_URL . 'assets/js/admin.js',
                    ['jquery', 'wp-color-picker'],
                    $this->version,
                    true
                );

                // Localize script
                wp_localize_script('alvobot-pre-article-admin', 'alvobotPreArticle', [
                    'defaultTexts' => $this->default_cta_texts,
                    'translations' => [
                        'buttonText' => __('Texto da CTA', 'alvobot-pre-artigo'),
                        'buttonColor' => __('Cor da CTA', 'alvobot-pre-artigo')
                    ]
                ]);

                // CSS simples para melhorar interação dos radio buttons
                wp_add_inline_style('alvobot-pro-styles', '
                    .position-option:hover {
                        border-color: var(--alvobot-primary) !important;
                        background: var(--alvobot-gray-50) !important;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }
                    .position-option:has(input:checked) {
                        border-color: var(--alvobot-primary) !important;
                        background: rgba(205, 144, 66, 0.1) !important;
                    }
                ');
            }
        }

        /**
         * Salva os dados do meta box
         */
        public function save_meta_box_data($post_id) {
            if (!isset($_POST['alvobot_pre_article_meta_box_nonce']) || 
                !wp_verify_nonce($_POST['alvobot_pre_article_meta_box_nonce'], 'alvobot_pre_article_meta_box')) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            // Salva o estado do checkbox
            $use_custom = isset($_POST['alvobot_use_custom']) ? '1' : '';
            update_post_meta($post_id, '_alvobot_use_custom', $use_custom);

            // Marca que o valor foi definido explicitamente pelo usuário
            update_post_meta($post_id, '_alvobot_use_custom_set', '1');

            // Se habilitado, salva as configurações das CTAs
            if ($use_custom === '1') {
                // Salva o tipo de CTA (default ou shortcode)
                $cta_type = isset($_POST['alvobot_cta_type']) ? sanitize_text_field($_POST['alvobot_cta_type']) : 'default';
                $use_shortcode = ($cta_type === 'shortcode') ? '1' : '0';
                update_post_meta($post_id, '_alvobot_use_shortcode', $use_shortcode);

                if ($use_shortcode === '1') {
                    // Salva o shortcode
                    $shortcode = isset($_POST['alvobot_shortcode']) ? sanitize_textarea_field($_POST['alvobot_shortcode']) : '';
                    update_post_meta($post_id, '_alvobot_shortcode', $shortcode);
                } else {
                    // Salva as CTAs padrão
                    $num_ctas = isset($_POST['alvobot_num_ctas']) ? 
                               absint($_POST['alvobot_num_ctas']) : 1;
                    
                    update_post_meta($post_id, '_alvobot_num_ctas', $num_ctas);

                    $ctas = [];
                    if (isset($_POST['alvobot_ctas']) && is_array($_POST['alvobot_ctas'])) {
                        foreach ($_POST['alvobot_ctas'] as $i => $cta) {
                            $ctas[$i] = [
                                'text' => sanitize_text_field($cta['text']),
                                'color' => sanitize_hex_color($cta['color'])
                            ];
                        }
                    }
                    update_post_meta($post_id, '_alvobot_ctas', $ctas);
                }
            }
        }

        /**
         * Registra o módulo no Alvobot Pro
         */
        public function register_module($modules) {
            $modules['pre_article'] = [
                'title' => __('Pré-Artigo', 'alvobot-pre-artigo'),
                'description' => __('Gere páginas de pré-artigo automaticamente para seus posts existentes.', 'alvobot-pre-artigo'),
                'icon' => 'dashicons-welcome-write-blog',
                'settings_url' => admin_url('admin.php?page=alvobot-pro-pre-article'),
                'is_active' => true,
                'pro_only' => true
            ];
            return $modules;
        }

        /**
         * Registra as configurações e campos
         */
        public function register_settings() {
            register_setting(
                'alvobot_pre_artigo_settings',
                'alvobot_pre_artigo_options',
                [
                    'sanitize_callback' => [$this, 'sanitize_options'],
                    'default' => [
                        'num_ctas' => 2,
                        'custom_script' => '',
                        'script_position' => 'after_ctas'
                    ]
                ]
            );

            add_settings_section(
                'alvobot_pre_artigo_cta_section',
                __('Configurações dos Botões CTA', 'alvobot-pre-artigo'),
                null,
                'alvobot-pre-artigo'
            );

            add_settings_field(
                'num_ctas',
                __('Quantidade de CTAs', 'alvobot-pre-artigo'),
                [$this, 'num_ctas_callback'],
                'alvobot-pre-artigo',
                'alvobot_pre_artigo_cta_section'
            );

            add_settings_section(
                'alvobot_pre_artigo_footer_section',
                __('Configurações do Rodapé', 'alvobot-pre-artigo'),
                null,
                'alvobot-pre-artigo'
            );

            add_settings_field(
                'footer_text',
                __('Texto do Rodapé', 'alvobot-pre-artigo'),
                [$this, 'footer_text_callback'],
                'alvobot-pre-artigo',
                'alvobot_pre_artigo_footer_section'
            );

            add_settings_section(
                'alvobot_pre_artigo_script_section',
                __('Scripts Personalizados', 'alvobot-pre-artigo'),
                null,
                'alvobot-pre-artigo'
            );

            add_settings_field(
                'custom_script',
                __('Script Personalizado', 'alvobot-pre-artigo'),
                [$this, 'custom_script_callback'],
                'alvobot-pre-artigo',
                'alvobot_pre_artigo_script_section'
            );

            add_settings_field(
                'script_position',
                __('Posição do Script', 'alvobot-pre-artigo'),
                [$this, 'script_position_callback'],
                'alvobot-pre-artigo',
                'alvobot_pre_artigo_script_section'
            );

            add_settings_section(
                'alvobot_pre_artigo_quiz_section',
                __('Integração com Quiz', 'alvobot-pre-artigo'),
                null,
                'alvobot-pre-artigo'
            );

            add_settings_field(
                'allow_quiz',
                __('Permitir Quiz', 'alvobot-pre-artigo'),
                [$this, 'allow_quiz_callback'],
                'alvobot-pre-artigo',
                'alvobot_pre_artigo_quiz_section'
            );
        }

        /**
         * Callback para o campo de quantidade de CTAs
         */
        public function num_ctas_callback() {
            $options = get_option('alvobot_pre_artigo_options');
            $num_ctas = $options['num_ctas'] ?? 2;
            ?>
            <input type="number" name="alvobot_pre_artigo_options[num_ctas]" value="<?php echo esc_attr($num_ctas); ?>" min="1" max="10" />
            <p class="description"><?php _e('Defina a quantidade padrão de CTAs a serem exibidas nos Pré-Artigos.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Callback para o campo de texto do rodapé
         */
        public function footer_text_callback() {
            $options = get_option('alvobot_pre_artigo_options');
            $default_footer = 'Aviso Legal: As informações deste site são meramente informativas e não substituem orientação profissional. Os resultados apresentados são ilustrativos, sem garantia de sucesso específico. Somos um site independente, não afiliado a outras marcas, que preza pela privacidade do usuário e protege suas informações pessoais, utilizando apenas para comunicações relacionadas aos nossos serviços.';
            $footer_text = isset($options['footer_text']) ? $options['footer_text'] : $default_footer;
            ?>
            <textarea id="footer_text" name="alvobot_pre_artigo_options[footer_text]" rows="10" class="large-text code"><?php echo esc_textarea($footer_text); ?></textarea>
            <p class="description"><?php _e('Use {NOME DO SITE} como placeholder para o nome do site configurado no WordPress.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Callback para o campo de script personalizado
         */
        public function custom_script_callback() {
            $options = get_option('alvobot_pre_artigo_options');
            $custom_script = $options['custom_script'] ?? '';
            ?>
            <textarea id="custom_script" name="alvobot_pre_artigo_options[custom_script]" rows="10" class="large-text code"><?php echo esc_textarea($custom_script); ?></textarea>
            <p class="description"><?php _e('Cole aqui seu script personalizado (ex: AdSense, Analytics, etc.). Aceita HTML, JavaScript e iframes.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Callback para o campo de posição do script
         */
        public function script_position_callback() {
            $options = get_option('alvobot_pre_artigo_options');
            $script_position = $options['script_position'] ?? 'after_ctas';
            $positions = [
                'after_first_paragraph' => __('Após o primeiro parágrafo', 'alvobot-pre-artigo'),
                'after_ctas' => __('Após os botões CTA', 'alvobot-pre-artigo'),
                'after_second_paragraph' => __('Após o segundo parágrafo', 'alvobot-pre-artigo'),
                'before_footer' => __('Antes do rodapé', 'alvobot-pre-artigo')
            ];
            ?>
            <select id="script_position" name="alvobot_pre_artigo_options[script_position]">
                <?php foreach ($positions as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($script_position, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Escolha onde o script deve aparecer na página de pré-artigo.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Callback para o campo de permitir quiz
         */
        public function allow_quiz_callback() {
            $options = get_option('alvobot_pre_artigo_options');
            $allow_quiz = isset($options['allow_quiz']) ? $options['allow_quiz'] : false;
            ?>
            <label>
                <input type="checkbox" name="alvobot_pre_artigo_options[allow_quiz]" value="1" <?php checked($allow_quiz, true); ?> />
                <?php _e('Permitir que quizzes sejam exibidos nas páginas de pré-artigo', 'alvobot-pre-artigo'); ?>
            </label>
            <p class="description"><?php _e('Quando ativado, os shortcodes de quiz funcionarão normalmente nas páginas de pré-artigo, preservando todos os scripts e estilos necessários.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Sanitiza as opções
         */
        public function sanitize_options($input) {
            $sanitized_input = [];
            $sanitized_input['num_ctas'] = isset($input['num_ctas']) ? absint($input['num_ctas']) : 2;
            
            if (isset($input['footer_text'])) {
                $sanitized_input['footer_text'] = wp_kses_post($input['footer_text']);
            }
            
            if (isset($input['custom_script'])) {
                $sanitized_input['custom_script'] = wp_kses($input['custom_script'], [
                    'script' => ['src' => [], 'type' => [], 'async' => [], 'defer' => []],
                    'iframe' => ['src' => [], 'width' => [], 'height' => [], 'frameborder' => [], 'allowfullscreen' => [], 'style' => []],
                    'div' => ['id' => [], 'class' => [], 'style' => []],
                    'ins' => ['class' => [], 'style' => [], 'data-ad-client' => [], 'data-ad-slot' => [], 'data-ad-format' => []],
                    'a' => ['href' => [], 'target' => [], 'rel' => []],
                    'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => [], 'style' => []],
                    'span' => ['class' => [], 'style' => []],
                    'p' => ['class' => [], 'style' => []],
                    'br' => []
                ]);
            }
            
            if (isset($input['script_position'])) {
                $allowed_positions = ['after_first_paragraph', 'after_ctas', 'after_second_paragraph', 'before_footer'];
                $sanitized_input['script_position'] = in_array($input['script_position'], $allowed_positions) ? $input['script_position'] : 'after_ctas';
            }
            
            // Sanitize allow_quiz checkbox
            $sanitized_input['allow_quiz'] = isset($input['allow_quiz']) && $input['allow_quiz'] == '1' ? true : false;

            $num_ctas = $sanitized_input['num_ctas'];
            for ($i = 1; $i <= $num_ctas; $i++) {
                if (isset($input["button_text_{$i}"])) {
                    $sanitized_input["button_text_{$i}"] = sanitize_text_field($input["button_text_{$i}"]);
                }
                if (isset($input["button_color_{$i}"])) {
                    $sanitized_input["button_color_{$i}"] = sanitize_hex_color($input["button_color_{$i}"]);
                }
            }

            return $sanitized_input;
        }

        /**
         * Registra as regras de rewrite
         */
        public function register_rewrite_rules() {
            // Regra padrão: /pre/nome-do-post/
            add_rewrite_rule(
                '^pre/([^/]+)/?$',
                'index.php?name=$matches[1]&alvobot_pre_article=1',
                'top'
            );

            // Regra com prefixo de idioma: /en/pre/nome-do-post/ (Polylang, WPML, etc.)
            add_rewrite_rule(
                '^([a-z]{2})/pre/([^/]+)/?$',
                'index.php?name=$matches[2]&alvobot_pre_article=1&alvobot_lang=$matches[1]',
                'top'
            );

            // Regra alternativa: /pre/en/nome-do-post/ (estrutura alternativa)
            add_rewrite_rule(
                '^pre/([a-z]{2})/([^/]+)/?$',
                'index.php?name=$matches[2]&alvobot_pre_article=1&alvobot_lang=$matches[1]',
                'top'
            );

            // Regra com sufixo do quiz: /pre/nome-do-post-aquiz-e1/
            add_rewrite_rule(
                '^pre/([^/]+)-aquiz-e([0-9]+)/?$',
                'index.php?name=$matches[1]&alvobot_pre_article=1&quiz_step_suffix=$matches[2]',
                'top'
            );

            // Regra com prefixo de idioma + sufixo do quiz: /en/pre/nome-do-post-aquiz-e1/
            add_rewrite_rule(
                '^([a-z]{2})/pre/([^/]+)-aquiz-e([0-9]+)/?$',
                'index.php?name=$matches[2]&alvobot_pre_article=1&alvobot_lang=$matches[1]&quiz_step_suffix=$matches[3]',
                'top'
            );

            // Regra alternativa com quiz: /pre/en/nome-do-post-aquiz-e1/
            add_rewrite_rule(
                '^pre/([a-z]{2})/([^/]+)-aquiz-e([0-9]+)/?$',
                'index.php?name=$matches[2]&alvobot_pre_article=1&alvobot_lang=$matches[1]&quiz_step_suffix=$matches[3]',
                'top'
            );
        }
        
        /**
         * Método para ser chamado durante a ativação do plugin
         * Faz flush das regras de rewrite apenas quando necessário
         */
        public static function activate() {
            if (!get_option('alvobot_pre_article_rewrite_flushed_v3')) {
                flush_rewrite_rules(true);
                update_option('alvobot_pre_article_rewrite_flushed_v3', true);
                // Remove versões antigas
                delete_option('alvobot_pre_article_rewrite_flushed');
                delete_option('alvobot_pre_article_rewrite_flushed_v2');
            }
        }

        /**
         * Força flush das regras de rewrite se necessário
         */
        public function maybe_flush_rewrite_rules() {
            // v5: Suporte a URLs com prefixo de idioma (/en/pre/, /pre/en/)
            if (!get_option('alvobot_pre_article_rewrite_flushed_v5')) {
                flush_rewrite_rules(true);
                update_option('alvobot_pre_article_rewrite_flushed_v5', true);
                // Remove versões antigas
                delete_option('alvobot_pre_article_rewrite_flushed');
                delete_option('alvobot_pre_article_rewrite_flushed_v2');
                delete_option('alvobot_pre_article_rewrite_flushed_v3');
                delete_option('alvobot_pre_article_rewrite_flushed_v4');
            }
        }

        /**
         * Adiciona variáveis de consulta
         */
        public function add_query_vars($vars) {
            $vars[] = 'alvobot_pre_article';
            $vars[] = 'quiz_step_suffix';
            $vars[] = 'alvobot_lang';
            return $vars;
        }

        /**
         * Modifica a consulta principal
         */
        public function modify_main_query($query) {
            if (!is_admin() && $query->is_main_query()) {
                $is_pre_article = get_query_var('alvobot_pre_article');
                $has_quiz_suffix = get_query_var('quiz_step_suffix');
                $pagename = get_query_var('pagename');
                $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

                // Detecta pré-artigo via query var OU via padrões de URL:
                // 1. /pre/slug/
                // 2. /en/pre/slug/ (prefixo de idioma)
                // 3. /pre/en/slug/ (estrutura alternativa)
                if (!$is_pre_article && (
                    ($has_quiz_suffix && $pagename && strpos($pagename, 'pre/') === 0) ||
                    preg_match('#^/pre/[^/]+/?$#', $request_uri) ||
                    preg_match('#^/[a-z]{2}/pre/[^/]+/?$#', $request_uri) ||
                    preg_match('#^/pre/[a-z]{2}/[^/]+/?$#', $request_uri)
                )) {
                    $is_pre_article = true;
                    set_query_var('alvobot_pre_article', '1');
                }

                if ($is_pre_article) {
                    $query->set('post_type', 'post');

                    // Determina o slug do post
                    $post_slug = '';
                    if (get_query_var('name')) {
                        $post_slug = get_query_var('name');
                        // Remove prefixos de caminho se existirem
                        $post_slug = preg_replace('/^pre\//', '', $post_slug);
                        $post_slug = preg_replace('/^[a-z]{2}\//', '', $post_slug);
                        $post_slug = preg_replace('/-aquiz-e\d+$/', '', $post_slug);
                    } elseif ($pagename && strpos($pagename, 'pre/') === 0) {
                        $post_slug = preg_replace('/^pre\//', '', $pagename);
                        $post_slug = preg_replace('/^[a-z]{2}\//', '', $post_slug);
                        $post_slug = preg_replace('/-aquiz-e\d+$/', '', $post_slug);
                    }

                    if ($post_slug) {
                        $query->set('name', $post_slug);
                    }

                    // Se temos um idioma na URL, configura para Polylang/WPML
                    $alvobot_lang = get_query_var('alvobot_lang');
                    if ($alvobot_lang) {
                        // Polylang
                        if (function_exists('pll_set_post_language')) {
                            $query->set('lang', $alvobot_lang);
                        }
                        // WPML
                        if (defined('ICL_LANGUAGE_CODE')) {
                            do_action('wpml_switch_language', $alvobot_lang);
                        }
                    }

                    $query->set('posts_per_page', 1);
                    $query->set('post_status', 'publish');
                }
            }
        }

        /**
         * Carrega o template do pré-artigo
         */
        public function load_pre_article_template($template) {
            $is_pre_article = get_query_var('alvobot_pre_article');
            $has_quiz_suffix = get_query_var('quiz_step_suffix');
            $pagename = get_query_var('pagename');
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

            // Detecta pré-artigo via query var OU via padrões de URL:
            // 1. /pre/slug/
            // 2. /en/pre/slug/ (prefixo de idioma)
            // 3. /pre/en/slug/ (estrutura alternativa)
            if (!$is_pre_article && (
                ($has_quiz_suffix && $pagename && strpos($pagename, 'pre/') === 0) ||
                preg_match('#^/pre/[^/]+/?$#', $request_uri) ||
                preg_match('#^/[a-z]{2}/pre/[^/]+/?$#', $request_uri) ||
                preg_match('#^/pre/[a-z]{2}/[^/]+/?$#', $request_uri)
            )) {
                $is_pre_article = true;
                set_query_var('alvobot_pre_article', '1');
            }
            
            if ($is_pre_article) {
                $post = get_post();
                if (!($post instanceof WP_Post)) {
                    return $template;
                }

                // Recupera CTAs: usa as configurações personalizadas se existirem
                $use_custom = get_post_meta($post->ID, '_alvobot_use_custom', true);
                $use_shortcode = get_post_meta($post->ID, '_alvobot_use_shortcode', true);
                
                if ('1' === $use_custom && '1' === $use_shortcode) {
                    // Usa shortcode personalizado
                    $shortcode = get_post_meta($post->ID, '_alvobot_shortcode', true);
                    set_query_var('alvobot_use_shortcode', true);
                    set_query_var('alvobot_shortcode', $shortcode);
                } elseif ('1' === $use_custom) {
                    // Usa CTAs personalizadas do post
                    $ctas = get_post_meta($post->ID, '_alvobot_ctas', true);
                    
                    // Aplica traduções automáticas para CTAs vazias
                    if (is_array($ctas) && class_exists('Alvobot_PreArticle_CTA_Translations')) {
                        foreach ($ctas as $index => &$cta) {
                            if (empty($cta['text'])) {
                                $cta['text'] = Alvobot_PreArticle_CTA_Translations::get_translated_cta_by_index($index);
                            }
                        }
                        unset($cta);
                    }
                    
                    set_query_var('alvobot_ctas', $ctas);
                } else {
                    // Usa CTAs das configurações globais
                    $options = get_option('alvobot_pre_artigo_options');
                    $num_ctas = $options['num_ctas'] ?? 2;
                    $ctas = [];
                    for ($i = 1; $i <= $num_ctas; $i++) {
                        $saved_text = $options["button_text_{$i}"] ?? '';
                        
                        // Se não há texto salvo, usa tradução automática
                        if (empty($saved_text) && class_exists('Alvobot_PreArticle_CTA_Translations')) {
                            $saved_text = Alvobot_PreArticle_CTA_Translations::get_translated_cta_by_index($i - 1);
                        }
                        
                        $ctas[] = [
                            'text' => $saved_text,
                            'color' => $options["button_color_{$i}"] ?? '#1E73BE'
                        ];
                    }
                    set_query_var('alvobot_ctas', $ctas);
                }
                
                // Configura o script personalizado
                $this->set_custom_script_vars();
                
                $plugin_dir = plugin_dir_path(__FILE__);
                $template_path = $plugin_dir . 'templates/template-pre-article.php';
                if (file_exists($template_path)) {
                    return $template_path;
                } else {
                    AlvoBotPro::debug_log('pre-article', 'Template não encontrado: ' . $template_path);
                    return $template;
                }
            }
            return $template;
        }

        /**
         * Carrega scripts no front-end
         */
        public function enqueue_scripts() {
            if (get_query_var('alvobot_pre_article')) {
                // Carrega a versão minificada do CSS para performance otimizada
                wp_enqueue_style(
                    'alvobot-pre-artigo-style',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/style.css',
                    [],
                    ALVOBOT_PRE_ARTICLE_VERSION
                );
            }
        }

        /**
         * Adiciona link para o pré-artigo na lista de posts
         */
        public function add_pre_article_link($actions, $post) {
            if ('post' === $post->post_type && 'publish' === $post->post_status) {
                $pre_article_url = home_url('/pre/' . $post->post_name);
                $actions['view_pre_article'] = sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($pre_article_url),
                    __('Ver Pré-Artigo', 'alvobot-pre-artigo')
                );
            }
            return $actions;
        }

        /**
         * Adiciona menu no admin
         */
        public function add_admin_menu() {
            // Na versão Pro, o menu é gerenciado pelo Alvobot Pro
            if (defined('ALVOBOT_PRO_VERSION')) {
                return;
            }

            // Adiciona menu apenas na versão free
            add_menu_page(
                'Alvobot',
                'Alvobot',
                'manage_options',
                'alvobot-pre-artigo',
                [$this, 'create_admin_page'],
                'dashicons-admin-generic',
                6
            );
        }

        /**
         * Cria a página de admin
         */
        public function create_admin_page() {
            // Enfileira color picker
            wp_enqueue_style('wp-color-picker');

            // Enfileira scripts
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script(
                'alvobot-pre-article-admin-js',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
                ['jquery', 'wp-color-picker'],
                ALVOBOT_PRE_ARTICLE_VERSION,
                true
            );
            // Obtém CTAs traduzidas para o idioma atual
            $translated_ctas = class_exists('Alvobot_PreArticle_CTA_Translations') 
                ? Alvobot_PreArticle_CTA_Translations::get_translated_ctas() 
                : $this->default_cta_texts;

            wp_localize_script('alvobot-pre-article-admin-js', 'alvobotTranslations', [
                'cta' => __('CTA', 'alvobot-pre-artigo'),
                'buttonText' => __('Texto do Botão:', 'alvobot-pre-artigo'),
                'buttonColor' => __('Cor do Botão:', 'alvobot-pre-artigo'),
                'defaultTexts' => $translated_ctas,
                'currentLanguage' => class_exists('Alvobot_PreArticle_CTA_Translations') 
                    ? Alvobot_PreArticle_CTA_Translations::get_current_language() 
                    : 'pt'
            ]);

            $options = get_option('alvobot_pre_artigo_options');
            $num_ctas = $options['num_ctas'] ?? 2;
            $custom_script = $options['custom_script'] ?? '';
            $footer_text = $options['footer_text'] ?? '';
            $script_position = $options['script_position'] ?? 'after_ctas';
            ?>
            <div class="alvobot-admin-wrap alvobot-pre-article-wrap">
                <div class="alvobot-admin-container">
                    <div class="alvobot-admin-header">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h1><?php _e('Páginas de Pré-Artigo', 'alvobot-pre-artigo'); ?></h1>
                                <p><?php _e('Configure as opções do módulo de pré-artigo para melhorar a experiência dos seus leitores e aumentar o engajamento.', 'alvobot-pre-artigo'); ?></p>
                            </div>
                            <?php if (class_exists('Alvobot_PreArticle_CTA_Translations')): ?>
                            <div>
                                <a href="<?php echo esc_url(add_query_arg('debug_translations', '1')); ?>" class="alvobot-btn alvobot-btn-secondary" style="margin-top: 10px;">
                                    <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
                                    <?php _e('Debug Traduções', 'alvobot-pre-artigo'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alvobot-notice-container">
                        <!-- Notificações serão inseridas aqui -->
                    </div>
                <form action="options.php" method="POST">
                    <?php settings_fields('alvobot_pre_artigo_settings'); ?>
                    
                    <!-- Seção de Configurações dos Botões CTA -->
                    <div class="alvobot-card cta-config-section">
                        <div class="alvobot-card-header">
                            <h2 class="alvobot-card-title"><?php _e('Configurações dos Botões CTA', 'alvobot-pre-artigo'); ?></h2>
                            <p class="alvobot-card-subtitle"><?php _e('Configure os botões de chamada para ação que aparecerão nas páginas de pré-artigo', 'alvobot-pre-artigo'); ?></p>
                            <?php if (class_exists('Alvobot_PreArticle_CTA_Translations')): ?>
                            <?php 
                                $current_lang = Alvobot_PreArticle_CTA_Translations::get_current_language();
                                $lang_name = Alvobot_PreArticle_CTA_Translations::get_language_native_name($current_lang);
                                $supported_count = count(Alvobot_PreArticle_CTA_Translations::get_supported_languages());
                            ?>
                            <?php endif; ?>
                        </div>
                        <div class="alvobot-card-content">
                            <!-- Campo Quantidade de CTAs -->
                            <div class="alvobot-form-field">
                                <label for="num_ctas" class="alvobot-form-label"><?php _e('Quantidade de CTAs:', 'alvobot-pre-artigo'); ?></label>
                                <div class="alvobot-form-control">
                                    <input type="number" id="num_ctas" name="alvobot_pre_artigo_options[num_ctas]" value="<?php echo esc_attr($num_ctas); ?>" min="1" max="10" class="alvobot-input alvobot-input-sm" />
                                    <p class="alvobot-description"><?php _e('Defina a quantidade padrão de CTAs (máximo 10).', 'alvobot-pre-artigo'); ?></p>
                                </div>
                            </div>

                            <!-- Container dos CTAs -->
                            <div id="ctas_container" class="alvobot-mt-xl">
                                <?php 
                                for ($i = 1; $i <= $num_ctas; $i++) {
                                    // Usa traduções automáticas para CTAs vazias ou padrão
                                    $saved_text = $options["button_text_{$i}"] ?? '';
                                    
                                    if (empty($saved_text)) {
                                        // Se não há texto salvo, usa tradução automática
                                        $button_text = class_exists('Alvobot_PreArticle_CTA_Translations') 
                                            ? Alvobot_PreArticle_CTA_Translations::get_translated_cta_by_index($i - 1) 
                                            : ($this->default_cta_texts[$i - 1] ?? '');
                                    } else {
                                        // Se há texto salvo, tenta traduzir se for um texto padrão
                                        $button_text = class_exists('Alvobot_PreArticle_CTA_Translations')
                                            ? Alvobot_PreArticle_CTA_Translations::translate_default_cta($saved_text)
                                            : $saved_text;
                                    }
                                    
                                    $button_color = $options["button_color_{$i}"] ?? '#1E73BE';
                                    ?>
                                    <div class="alvobot-card alvobot-mt-lg" style="border: 1px solid var(--alvobot-gray-300); background: var(--alvobot-gray-50);">
                                        <div class="alvobot-card-header">
                                            <h3 class="alvobot-card-title" style="font-size: var(--alvobot-font-size-lg); margin: 0;">
                                                <?php printf(__('CTA %d', 'alvobot-pre-artigo'), $i); ?>
                                            </h3>
                                        </div>
                                        <div class="alvobot-card-content">
                                            <div class="alvobot-grid alvobot-grid-2">
                                                <div class="alvobot-form-field">
                                                    <label for="button_text_<?php echo $i; ?>" class="alvobot-form-label"><?php _e('Texto do Botão:', 'alvobot-pre-artigo'); ?></label>
                                                    <div class="alvobot-form-control">
                                                        <input type="text" id="button_text_<?php echo $i; ?>" name="alvobot_pre_artigo_options[button_text_<?php echo $i; ?>]" value="<?php echo esc_attr($button_text); ?>" class="alvobot-input" placeholder="<?php _e('Digite o texto do botão...', 'alvobot-pre-artigo'); ?>" />
                                                    </div>
                                                </div>
                                                <div class="alvobot-form-field">
                                                    <label for="button_color_<?php echo $i; ?>" class="alvobot-form-label"><?php _e('Cor do Botão:', 'alvobot-pre-artigo'); ?></label>
                                                    <div class="alvobot-form-control">
                                                        <input type="text" id="button_color_<?php echo $i; ?>" name="alvobot_pre_artigo_options[button_color_<?php echo $i; ?>]" value="<?php echo esc_attr($button_color); ?>" class="wp-color-picker-field alvobot-input" data-default-color="#1E73BE" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="alvobot-card-footer">
                            <div class="alvobot-btn-group alvobot-btn-group-right">
                                <button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
                                    <?php _e('Salvar Configurações dos CTAs', 'alvobot-pre-artigo'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Scripts Personalizados -->
                    <div class="alvobot-card alvobot-mt-2xl">
                        <div class="alvobot-card-header">
                            <h2 class="alvobot-card-title">
                                <span class="dashicons dashicons-editor-code" style="margin-right: var(--alvobot-space-sm); color: var(--alvobot-primary);"></span>
                                <?php _e('Scripts Personalizados', 'alvobot-pre-artigo'); ?>
                            </h2>
                            <p class="alvobot-card-subtitle"><?php _e('Configure scripts como AdSense ou Analytics para monetizar suas páginas de pré-artigo.', 'alvobot-pre-artigo'); ?></p>
                        </div>
                        
                        <div class="alvobot-card-content">
                            <!-- Campo Script Personalizado -->
                            <div class="alvobot-form-field">
                                <label for="custom_script" class="alvobot-form-label">
                                    <?php _e('Código do Script', 'alvobot-pre-artigo'); ?>
                                </label>
                                <textarea 
                                    id="custom_script" 
                                    name="alvobot_pre_artigo_options[custom_script]" 
                                    rows="12" 
                                    class="large-text code"
                                    placeholder="<?php _e('<!-- Cole aqui seu código -->
<script async src=&quot;https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js&quot;></script>
<ins class=&quot;adsbygoogle&quot; style=&quot;display:block&quot; data-ad-client=&quot;ca-pub-xxx&quot;></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>', 'alvobot-pre-artigo'); ?>"><?php echo esc_textarea($custom_script); ?></textarea>
                                
                                <p class="description">
                                    <?php _e('Aceita códigos HTML, JavaScript e iframes. O script só aparece se houver conteúdo configurado.', 'alvobot-pre-artigo'); ?>
                                </p>
                            </div>

                            <!-- Campo Posição do Script -->
                            <div class="alvobot-form-field alvobot-mt-xl">
                                <fieldset>
                                    <legend class="alvobot-form-label">
                                        <?php _e('Onde exibir o script', 'alvobot-pre-artigo'); ?>
                                    </legend>
                                    <?php
                                    $script_position = $options['script_position'] ?? 'after_ctas';
                                    $positions = [
                                        'after_first_paragraph' => [
                                            'label' => __('Após o primeiro parágrafo', 'alvobot-pre-artigo'),
                                            'desc' => __('Logo depois do primeiro bloco de texto', 'alvobot-pre-artigo')
                                        ],
                                        'after_ctas' => [
                                            'label' => __('Após os botões CTA', 'alvobot-pre-artigo'),
                                            'desc' => __('Entre os botões e o segundo parágrafo (recomendado para anúncios)', 'alvobot-pre-artigo')
                                        ],
                                        'after_second_paragraph' => [
                                            'label' => __('Após o segundo parágrafo', 'alvobot-pre-artigo'),
                                            'desc' => __('Depois do segundo bloco de conteúdo', 'alvobot-pre-artigo')
                                        ],
                                        'before_footer' => [
                                            'label' => __('Antes do rodapé', 'alvobot-pre-artigo'),
                                            'desc' => __('No final da página, antes dos links legais', 'alvobot-pre-artigo')
                                        ]
                                    ];
                                    ?>
                                    
                                    <?php foreach ($positions as $value => $position) : ?>
                                        <label class="position-option" style="display: block; margin-bottom: var(--alvobot-space-md); cursor: pointer; padding: var(--alvobot-space-lg); border: 2px solid var(--alvobot-gray-300); border-radius: var(--alvobot-radius-md); background: var(--alvobot-white); transition: all 0.2s ease;">
                                            <input 
                                                type="radio" 
                                                name="alvobot_pre_artigo_options[script_position]" 
                                                value="<?php echo esc_attr($value); ?>" 
                                                <?php checked($script_position, $value); ?>
                                                style="margin-right: var(--alvobot-space-sm);"
                                            />
                                            <strong style="font-size: var(--alvobot-font-size-base);"><?php echo esc_html($position['label']); ?></strong>
                                            <br>
                                            <span class="description" style="margin-left: var(--alvobot-space-2xl); color: var(--alvobot-gray-600); font-size: var(--alvobot-font-size-sm);"><?php echo esc_html($position['desc']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    
                                    <p class="description alvobot-mt-md">
                                        <span class="dashicons dashicons-info" style="color: var(--alvobot-info);"></span>
                                        <?php _e('Para máximo desempenho de anúncios, recomendamos a posição "Após os botões CTA".', 'alvobot-pre-artigo'); ?>
                                    </p>
                                </fieldset>
                            </div>
                        </div>
                        
                        <div class="alvobot-card-footer">
                            <div class="alvobot-btn-group alvobot-btn-group-right">
                                <button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
                                    <span class="dashicons dashicons-saved" style="margin-right: var(--alvobot-space-xs);"></span>
                                    <?php _e('Salvar Configurações', 'alvobot-pre-artigo'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Integração com Quiz -->
                    <div class="alvobot-card alvobot-mt-2xl">
                        <div class="alvobot-card-header">
                            <h2 class="alvobot-card-title">
                                <span class="dashicons dashicons-format-status" style="margin-right: var(--alvobot-space-sm); color: var(--alvobot-primary);"></span>
                                <?php _e('Integração com Quiz', 'alvobot-pre-artigo'); ?>
                            </h2>
                            <p class="alvobot-card-subtitle"><?php _e('Configure se deseja permitir que quizzes funcionem nas páginas de pré-artigo.', 'alvobot-pre-artigo'); ?></p>
                        </div>
                        
                        <div class="alvobot-card-content">
                            <div class="alvobot-form-field">
                                <?php
                                $options = get_option('alvobot_pre_artigo_options');
                                $allow_quiz = isset($options['allow_quiz']) ? $options['allow_quiz'] : false;
                                ?>
                                <label class="alvobot-checkbox-label" style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="alvobot_pre_artigo_options[allow_quiz]" value="1" <?php checked($allow_quiz, true); ?> style="margin-right: var(--alvobot-space-sm);" />
                                    <span style="font-weight: 500;"><?php _e('Permitir Quiz nas páginas de pré-artigo', 'alvobot-pre-artigo'); ?></span>
                                </label>
                                <p class="alvobot-description alvobot-mt-sm">
                                    <span class="dashicons dashicons-info" style="color: var(--alvobot-info);"></span>
                                    <?php _e('Quando ativado, os shortcodes [quiz] funcionarão normalmente nas páginas de pré-artigo. Os scripts e estilos necessários serão preservados para garantir o funcionamento completo do quiz, incluindo a navegação entre perguntas.', 'alvobot-pre-artigo'); ?>
                                </p>
                                <p class="alvobot-description alvobot-mt-sm">
                                    <span class="dashicons dashicons-lightbulb" style="color: var(--alvobot-warning);"></span>
                                    <?php _e('Nota: Esta opção só tem efeito se o módulo Quiz Builder estiver ativo.', 'alvobot-pre-artigo'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="alvobot-card-footer">
                            <div class="alvobot-btn-group alvobot-btn-group-right">
                                <button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
                                    <span class="dashicons dashicons-saved" style="margin-right: var(--alvobot-space-xs);"></span>
                                    <?php _e('Salvar Configurações', 'alvobot-pre-artigo'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                </form>
                </div>
            </div>
            <?php
        }

        /**
         * Renderiza a página de configurações quando chamada pelo Alvobot Pro
         */
        public function render_settings_page() {
            AlvoBotPro::debug_log('pre-article', 'Iniciando render_settings_page');
            AlvoBotPro::debug_log('pre-article', 'User ID: ' . get_current_user_id());

            // Verifica permissões
            if (!current_user_can('manage_options')) {
                AlvoBotPro::debug_log('pre-article', 'Usuário não tem permissão manage_options');
                wp_die(__('Você não tem permissão para acessar esta página.', 'alvobot-pre-artigo'));
            }

            AlvoBotPro::debug_log('pre-article', 'Permissões OK, renderizando página');

            // Debug de traduções se solicitado
            if (isset($_GET['debug_translations'])) {
                include dirname(dirname(__FILE__)) . '/debug-translations.php';
                return;
            }

            // Renderiza a página de configurações
            $this->create_admin_page();
        }

        /**
         * Configura as variáveis do script personalizado
         */
        private function set_custom_script_vars() {
            $options = get_option('alvobot_pre_artigo_options');
            $custom_script = $options['custom_script'] ?? '';
            $script_position = $options['script_position'] ?? 'after_ctas';
            
            set_query_var('alvobot_custom_script', $custom_script);
            set_query_var('alvobot_script_position', $script_position);
        }

        /**
         * Renderiza o script personalizado na posição especificada
         */
        public static function render_custom_script($position) {
            $custom_script = get_query_var('alvobot_custom_script');
            $script_position = get_query_var('alvobot_script_position');
            
            // Só renderiza se houver script e se for a posição correta
            if (!empty($custom_script) && $script_position === $position) {
                echo '<div class="alvobot-custom-script-container" style="margin: 20px 0; text-align: center;">';
                echo wp_kses($custom_script, [
                    'script' => ['src' => [], 'type' => [], 'async' => [], 'defer' => []],
                    'iframe' => ['src' => [], 'width' => [], 'height' => [], 'frameborder' => [], 'allowfullscreen' => [], 'style' => []],
                    'div' => ['id' => [], 'class' => [], 'style' => []],
                    'ins' => ['class' => [], 'style' => [], 'data-ad-client' => [], 'data-ad-slot' => [], 'data-ad-format' => []],
                    'a' => ['href' => [], 'target' => [], 'rel' => []],
                    'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => [], 'style' => []],
                    'span' => ['class' => [], 'style' => []],
                    'p' => ['class' => [], 'style' => []],
                    'br' => []
                ]);
                echo '</div>';
            }
        }

        /**
         * Carrega a tradução do plugin
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain(
                'alvobot-pre-artigo',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
            );
        }

        /**
         * Initialize multi-language support
         */
        private function init_multilanguage_support() {
            // Hook para adicionar interface de tradução no metabox
            add_action('admin_footer-post.php', [$this, 'add_translation_interface']);
            add_action('admin_footer-post-new.php', [$this, 'add_translation_interface']);

            // AJAX handlers para tradução dinâmica
            add_action('wp_ajax_alvobot_translate_pre_article_ctas', [$this, 'ajax_translate_ctas']);
            add_action('wp_ajax_alvobot_get_available_languages', [$this, 'ajax_get_available_languages']);

            AlvoBotPro::debug_log('pre-article', 'Multi-language support initialized');
        }

        /**
         * Add translation interface to the metabox
         */
        public function add_translation_interface() {
            global $post;

            // Só adiciona se o post tem pre-article ativo
            $is_enabled = get_post_meta($post->ID, '_alvobot_pre_article_enabled', true);
            if (!$is_enabled) {
                return;
            }

            // Verifica se Polylang está ativo
            if (!function_exists('PLL') || !PLL()->model) {
                return;
            }
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Adiciona botão de tradução ao metabox
                const metabox = $('#alvobot_pre_article_meta_box .inside');
                if (metabox.length) {
                    // Adiciona seção de tradução após as CTAs
                    const translationSection = `
                        <div class="alvobot-translation-section" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                            <h4 style="margin-top: 0;">
                                <span class="dashicons dashicons-translation"></span>
                                <?php _e('Tradução Multi-idioma', 'alvobot-pre-artigo'); ?>
                            </h4>
                            <p><?php _e('Use esta funcionalidade para traduzir automaticamente os textos das CTAs para outros idiomas.', 'alvobot-pre-artigo'); ?></p>
                            <div class="translation-controls">
                                <button type="button" class="button button-secondary alvobot-translate-ctas">
                                    <span class="dashicons dashicons-translation" style="margin-right: 5px;"></span>
                                    <?php _e('Traduzir CTAs', 'alvobot-pre-artigo'); ?>
                                </button>
                                <button type="button" class="button alvobot-sync-translations" style="margin-left: 10px;">
                                    <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
                                    <?php _e('Sincronizar com Traduções', 'alvobot-pre-artigo'); ?>
                                </button>
                            </div>
                            <div class="translation-status" style="margin-top: 10px; display: none;">
                                <span class="spinner is-active" style="float: none;"></span>
                                <span class="status-text"><?php _e('Traduzindo...', 'alvobot-pre-artigo'); ?></span>
                            </div>
                            <div class="translation-results" style="margin-top: 15px; display: none;">
                                <!-- Resultados das traduções aparecerão aqui -->
                            </div>
                        </div>
                    `;

                    // Insere após as configurações das CTAs
                    metabox.find('fieldset').last().after(translationSection);

                    // Handler para tradução das CTAs
                    $('.alvobot-translate-ctas').on('click', function() {
                        const button = $(this);
                        const statusDiv = $('.translation-status');
                        const resultsDiv = $('.translation-results');

                        // Coleta os textos atuais das CTAs
                        const ctas = [];
                        $('input[name^="alvobot_pre_article_ctas["][name$="[text]"]').each(function() {
                            const value = $(this).val();
                            if (value) {
                                ctas.push({
                                    index: $(this).attr('name').match(/\[(\d+)\]/)[1],
                                    text: value
                                });
                            }
                        });

                        if (ctas.length === 0) {
                            alert('<?php _e('Nenhuma CTA para traduzir. Configure pelo menos uma CTA primeiro.', 'alvobot-pre-artigo'); ?>');
                            return;
                        }

                        // Mostra modal de seleção de idiomas
                        showLanguageSelectionModal(ctas);
                    });

                    // Handler para sincronização com traduções existentes
                    $('.alvobot-sync-translations').on('click', function() {
                        syncWithExistingTranslations();
                    });
                }

                // Função para mostrar modal de seleção de idiomas
                function showLanguageSelectionModal(ctas) {
                    // Busca idiomas disponíveis
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alvobot_get_available_languages',
                            nonce: '<?php echo wp_create_nonce('alvobot_pre_article_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                createLanguageModal(response.data.languages, ctas);
                            }
                        }
                    });
                }

                // Função para criar modal de idiomas
                function createLanguageModal(languages, ctas) {
                    const modalHtml = `
                        <div id="alvobot-language-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                            <div style="background: white; border-radius: 8px; padding: 30px; max-width: 500px; max-height: 80vh; overflow-y: auto;">
                                <h3><?php _e('Selecione os Idiomas de Destino', 'alvobot-pre-artigo'); ?></h3>
                                <p><?php _e('As CTAs serão traduzidas para os idiomas selecionados:', 'alvobot-pre-artigo'); ?></p>
                                <div class="language-checkboxes" style="margin: 20px 0;">
                                    ${Object.entries(languages).map(([code, name]) => `
                                        <label style="display: block; margin: 10px 0;">
                                            <input type="checkbox" value="${code}" name="target_lang[]">
                                            ${name}
                                        </label>
                                    `).join('')}
                                </div>
                                <div class="modal-actions" style="text-align: right; margin-top: 20px;">
                                    <button type="button" class="button cancel-modal"><?php _e('Cancelar', 'alvobot-pre-artigo'); ?></button>
                                    <button type="button" class="button button-primary translate-now" style="margin-left: 10px;"><?php _e('Traduzir', 'alvobot-pre-artigo'); ?></button>
                                </div>
                            </div>
                        </div>
                    `;

                    $('body').append(modalHtml);

                    // Handlers do modal
                    $('#alvobot-language-modal .cancel-modal').on('click', function() {
                        $('#alvobot-language-modal').remove();
                    });

                    $('#alvobot-language-modal .translate-now').on('click', function() {
                        const selectedLangs = [];
                        $('#alvobot-language-modal input:checked').each(function() {
                            selectedLangs.push($(this).val());
                        });

                        if (selectedLangs.length === 0) {
                            alert('<?php _e('Selecione pelo menos um idioma.', 'alvobot-pre-artigo'); ?>');
                            return;
                        }

                        $('#alvobot-language-modal').remove();
                        translateCTAs(ctas, selectedLangs);
                    });
                }

                // Função para traduzir CTAs
                function translateCTAs(ctas, languages) {
                    const statusDiv = $('.translation-status');
                    const resultsDiv = $('.translation-results');

                    statusDiv.show();
                    resultsDiv.hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alvobot_translate_pre_article_ctas',
                            ctas: ctas,
                            languages: languages,
                            post_id: <?php echo isset($post->ID) ? $post->ID : '0'; ?>,
                            nonce: '<?php echo wp_create_nonce('alvobot_pre_article_nonce'); ?>'
                        },
                        success: function(response) {
                            statusDiv.hide();

                            if (response.success) {
                                displayTranslationResults(response.data);
                            } else {
                                alert('<?php _e('Erro ao traduzir:', 'alvobot-pre-artigo'); ?> ' + response.data);
                            }
                        },
                        error: function() {
                            statusDiv.hide();
                            alert('<?php _e('Erro ao conectar com o servidor.', 'alvobot-pre-artigo'); ?>');
                        }
                    });
                }

                // Função para exibir resultados das traduções
                function displayTranslationResults(data) {
                    const resultsDiv = $('.translation-results');
                    let html = '<h4><?php _e('Traduções Geradas:', 'alvobot-pre-artigo'); ?></h4>';

                    Object.entries(data.translations).forEach(([lang, translations]) => {
                        html += `<div style="margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">`;
                        html += `<h5>${data.language_names[lang] || lang}</h5>`;
                        html += '<ul>';
                        translations.forEach(translation => {
                            html += `<li>"${translation.original}" → "${translation.translated}"</li>`;
                        });
                        html += '</ul>';
                        html += `<button type="button" class="button apply-translation" data-lang="${lang}" data-translations='${JSON.stringify(translations)}'>
                                    <?php _e('Aplicar estas traduções', 'alvobot-pre-artigo'); ?>
                                </button>`;
                        html += '</div>';
                    });

                    resultsDiv.html(html).show();

                    // Handler para aplicar traduções
                    $('.apply-translation').on('click', function() {
                        const lang = $(this).data('lang');
                        const translations = $(this).data('translations');

                        translations.forEach(translation => {
                            const input = $(`input[name="alvobot_pre_article_ctas[${translation.index}][text]"]`);
                            if (input.length) {
                                input.val(translation.translated);
                            }
                        });

                        alert(`<?php _e('Traduções aplicadas para', 'alvobot-pre-artigo'); ?> ${lang}`);
                    });
                }

                // Função para sincronizar com traduções existentes
                function syncWithExistingTranslations() {
                    if (typeof Alvobot_PreArticle_CTA_Translations !== 'undefined') {
                        alert('<?php _e('Sincronização com traduções pré-definidas disponível.', 'alvobot-pre-artigo'); ?>');
                    } else {
                        alert('<?php _e('Classe de traduções não encontrada.', 'alvobot-pre-artigo'); ?>');
                    }
                }
            });
            </script>
            <?php
        }

        /**
         * AJAX handler para traduzir CTAs
         */
        public function ajax_translate_ctas() {
            // Verifica nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alvobot_pre_article_nonce')) {
                wp_send_json_error('Nonce inválido');
            }

            // Verifica permissões
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permissões insuficientes');
            }

            $ctas = isset($_POST['ctas']) ? $_POST['ctas'] : array();
            $languages = isset($_POST['languages']) ? $_POST['languages'] : array();
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            if (empty($ctas) || empty($languages)) {
                wp_send_json_error('Parâmetros inválidos');
            }

            $translations = array();
            $language_names = array();

            // Se o módulo multi-languages está ativo, usa ele
            if (class_exists('AlvoBotPro_MultiLanguages')) {
                foreach ($languages as $lang) {
                    $translations[$lang] = array();

                    foreach ($ctas as $cta) {
                        $translated_text = $this->translate_text_with_service($cta['text'], $lang);

                        $translations[$lang][] = array(
                            'index' => $cta['index'],
                            'original' => $cta['text'],
                            'translated' => $translated_text
                        );
                    }

                    // Nome do idioma
                    if (function_exists('PLL') && PLL()->model) {
                        $pll_languages = PLL()->model->get_languages_list();
                        foreach ($pll_languages as $pll_lang) {
                            if ($pll_lang->slug === $lang) {
                                $language_names[$lang] = $pll_lang->name;
                                break;
                            }
                        }
                    }
                }
            } else {
                // Fallback: usa traduções estáticas da classe CTA_Translations
                if (class_exists('Alvobot_PreArticle_CTA_Translations')) {
                    foreach ($languages as $lang) {
                        $translations[$lang] = array();

                        foreach ($ctas as $cta) {
                            $translated_text = Alvobot_PreArticle_CTA_Translations::translate_default_cta($cta['text'], $lang);

                            $translations[$lang][] = array(
                                'index' => $cta['index'],
                                'original' => $cta['text'],
                                'translated' => $translated_text
                            );
                        }

                        $language_names[$lang] = Alvobot_PreArticle_CTA_Translations::get_language_native_name($lang);
                    }
                }
            }

            wp_send_json_success(array(
                'translations' => $translations,
                'language_names' => $language_names
            ));
        }

        /**
         * AJAX handler para obter idiomas disponíveis
         */
        public function ajax_get_available_languages() {
            // Verifica nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alvobot_pre_article_nonce')) {
                wp_send_json_error('Nonce inválido');
            }

            $languages = array();

            // Primeiro tenta Polylang
            if (function_exists('PLL') && PLL()->model) {
                $pll_languages = PLL()->model->get_languages_list();
                foreach ($pll_languages as $lang) {
                    $languages[$lang->slug] = $lang->name;
                }
            }
            // Se não tem Polylang, usa as traduções estáticas disponíveis
            elseif (class_exists('Alvobot_PreArticle_CTA_Translations')) {
                $supported = Alvobot_PreArticle_CTA_Translations::get_supported_languages();
                foreach ($supported as $code) {
                    $languages[$code] = Alvobot_PreArticle_CTA_Translations::get_language_native_name($code);
                }
            }

            // Remove o idioma atual da lista (não faz sentido traduzir para o mesmo idioma)
            $current_lang = $this->get_current_language();
            unset($languages[$current_lang]);

            wp_send_json_success(array(
                'languages' => $languages,
                'current' => $current_lang
            ));
        }

        /**
         * Traduz texto usando o serviço de tradução
         */
        private function translate_text_with_service($text, $target_language) {
            // Tenta usar o serviço do módulo multi-languages
            if (class_exists('AlvoBotPro_MultiLanguages')) {
                $multi_lang = AlvoBotPro_MultiLanguages::get_instance();

                if (method_exists($multi_lang, 'translate_text_simple')) {
                    return $multi_lang->translate_text_simple($text, $target_language);
                }
            }

            // Fallback: usa traduções estáticas
            if (class_exists('Alvobot_PreArticle_CTA_Translations')) {
                return Alvobot_PreArticle_CTA_Translations::translate_default_cta($text, $target_language);
            }

            // Último fallback: retorna com prefixo do idioma
            return '[' . strtoupper($target_language) . '] ' . $text;
        }

        /**
         * Obtém o idioma atual
         */
        private function get_current_language() {
            // Verifica Polylang
            if (function_exists('pll_current_language')) {
                $lang = pll_current_language();
                if ($lang) return $lang;
            }

            // Verifica WPML
            if (defined('ICL_LANGUAGE_CODE')) {
                return ICL_LANGUAGE_CODE;
            }

            // Fallback: idioma do WordPress
            $locale = get_locale();
            return substr($locale, 0, 2);
        }
    }
}

// Se necessário, chame o método run() em algum hook (por exemplo, plugins_loaded)
// Exemplo:
if (!function_exists('run_alvobot_pre_artigo')) {
    function run_alvobot_pre_artigo(): void {
        $plugin = new Alvobot_Pre_Article();
        $plugin->run();
    }
    add_action('plugins_loaded', 'run_alvobot_pre_artigo');
}