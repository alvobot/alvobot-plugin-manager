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

            // Localiza script
            wp_localize_script('alvobot-pre-article-admin-js', 'alvobotTranslations', [
                'cta' => __('CTA', 'alvobot-pre-artigo'),
                'buttonText' => __('Texto do Botão:', 'alvobot-pre-artigo'),
                'buttonColor' => __('Cor do Botão:', 'alvobot-pre-artigo'),
                'defaultTexts' => $this->default_cta_texts
            ]);

            // Obtém os dados salvos
            $use_custom = get_post_meta($post->ID, '_alvobot_use_custom', true);
            $num_ctas = get_post_meta($post->ID, '_alvobot_num_ctas', true);
            $ctas = get_post_meta($post->ID, '_alvobot_ctas', true);
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
                            <label for="alvobot_num_ctas"><?php _e('Número de CTAs:', 'alvobot-pre-artigo'); ?></label>
                            <input type="number" name="alvobot_num_ctas" id="alvobot_num_ctas" 
                                   value="<?php echo esc_attr($num_ctas ? $num_ctas : 1); ?>" 
                                   min="1" max="10" class="small-text" />
                        </div>

                        <div id="alvobot_ctas_container" class="cta-boxes">
                            <?php
                            $num_ctas = $num_ctas ? intval($num_ctas) : 1;
                            for ($i = 0; $i < $num_ctas; $i++) {
                                $cta_text = isset($ctas[$i]['text']) ? $ctas[$i]['text'] : ($this->default_cta_texts[$i] ?? '');
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
                </div>
            </div>
            <?php
        }

        /**
         * Carrega scripts e estilos no admin
         */
        public function enqueue_admin_scripts($hook) {
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                // Estilos base
                wp_enqueue_style(
                    'alvobot-pre-article-admin',
                    ALVOBOT_PRE_ARTICLE_URL . 'assets/css/admin.css',
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

                // Se for versão Pro, adiciona assets específicos
                if (defined('ALVOBOT_PRO_VERSION')) {
                    wp_enqueue_style(
                        'alvobot-pre-article-pro-admin',
                        ALVOBOT_PRE_ARTICLE_URL . 'assets/css/admin-pro.css',
                        ['alvobot-pre-article-admin'],
                        $this->version
                    );
                }
            }
        }

        /**
         * Salva os dados do meta box
         */
        public function save_meta_box_data($post_id) {
            if (!isset($_POST['alvobot_pre_artigo_nonce']) || 
                !wp_verify_nonce($_POST['alvobot_pre_artigo_nonce'], 'alvobot_pre_artigo_nonce_action')) {
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

            // Se habilitado, salva as configurações das CTAs
            if ($use_custom === '1') {
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
                        'adsense_content' => ''
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
         * Sanitiza as opções
         */
        public function sanitize_options($input) {
            $sanitized_input = [];
            $sanitized_input['num_ctas'] = isset($input['num_ctas']) ? absint($input['num_ctas']) : 2;

            if (isset($input['adsense_content'])) {
                $allowed_html = array_merge(
                    wp_kses_allowed_html('post'),
                    [
                        'script' => [
                            'async' => [],
                            'src' => [],
                            'crossorigin' => [],
                            'type' => [],
                            'data-ad-client' => [],
                            'data-ad-slot' => []
                        ],
                        'ins' => [
                            'class' => [],
                            'style' => [],
                            'data-ad-client' => [],
                            'data-ad-slot' => [],
                            'data-ad-format' => [],
                            'data-full-width-responsive' => []
                        ]
                    ]
                );
                $sanitized_input['adsense_content'] = wp_kses($input['adsense_content'], $allowed_html);
            }

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
            add_rewrite_rule(
                'pre/([^/]+)/?$',
                'index.php?name=$matches[1]&alvobot_pre_article=1',
                'top'
            );
        }

        /**
         * Adiciona variáveis de consulta
         */
        public function add_query_vars($vars) {
            $vars[] = 'alvobot_pre_article';
            return $vars;
        }

        /**
         * Modifica a consulta principal
         */
        public function modify_main_query($query) {
            if (!is_admin() && $query->is_main_query() && get_query_var('alvobot_pre_article')) {
                $query->set('post_type', 'post');
                if (get_query_var('name')) {
                    $pagename = preg_replace('/^pre\//', '', get_query_var('name'));
                    $query->set('name', $pagename);
                }
                $query->set('posts_per_page', 1);
                $query->set('post_status', 'publish');
            }
        }

        /**
         * Carrega o template do pré-artigo
         */
        public function load_pre_article_template($template) {
            if (get_query_var('alvobot_pre_article')) {
                $post = get_post();
                if (!($post instanceof WP_Post)) {
                    return $template;
                }

                // Recupera CTAs: usa as configurações personalizadas se existirem
                $use_custom = get_post_meta($post->ID, '_alvobot_use_custom', true);
                if ('1' === $use_custom) {
                    $ctas = get_post_meta($post->ID, '_alvobot_ctas', true);
                } else {
                    $options = get_option('alvobot_pre_artigo_options');
                    $num_ctas = $options['num_ctas'] ?? 2;
                    $ctas = [];
                    for ($i = 1; $i <= $num_ctas; $i++) {
                        $ctas[] = [
                            'text' => $options["button_text_{$i}"] ?? '',
                            'color' => $options["button_color_{$i}"] ?? '#1E73BE'
                        ];
                    }
                }
                set_query_var('alvobot_ctas', $ctas);

                $options = get_option('alvobot_pre_artigo_options');
                $adsense_content = $options['adsense_content'] ?? '';
                set_query_var('alvobot_adsense_content', $adsense_content);

                $plugin_dir = plugin_dir_path(__FILE__);
                $template_path = $plugin_dir . '/templates/template-pre-article.php';
                if (file_exists($template_path)) {
                    return $template_path;
                } else {
                    error_log('Template não encontrado: ' . $template_path);
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
                wp_enqueue_style(
                    'alvobot-pre-artigo-style',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/style.css',
                    [],
                    '1.0.0'
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
            // Enfileira color picker e estilos customizados
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_style(
                'alvobot-pre-article-admin-css',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
                [],
                ALVOBOT_PRE_ARTICLE_VERSION
            );

            // Enfileira scripts
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script(
                'alvobot-pre-article-admin-js',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
                ['jquery', 'wp-color-picker'],
                ALVOBOT_PRE_ARTICLE_VERSION,
                true
            );
            wp_localize_script('alvobot-pre-article-admin-js', 'alvobotTranslations', [
                'cta' => __('CTA', 'alvobot-pre-artigo'),
                'buttonText' => __('Texto do Botão:', 'alvobot-pre-artigo'),
                'buttonColor' => __('Cor do Botão:', 'alvobot-pre-artigo'),
                'defaultTexts' => $this->default_cta_texts
            ]);

            $options = get_option('alvobot_pre_artigo_options');
            $num_ctas = $options['num_ctas'] ?? 2;
            $adsense_content = $options['adsense_content'] ?? '';
            ?>
            <div class="alvobot-pro-wrap alvobot-pre-article-wrap">
                <div class="alvobot-pro-header">
                    <h1><?php _e('Alvobot Pré-artigo', 'alvobot-pre-artigo'); ?></h1>
                    <p><?php _e('Configure as opções do módulo de pré-artigo para melhorar a experiência dos seus leitores.', 'alvobot-pre-artigo'); ?></p>
                </div>
                <form action="options.php" method="POST">
                    <?php settings_fields('alvobot_pre_artigo_settings'); ?>
                    
                    <!-- Seção de Configurações dos Botões CTA -->
                    <div class="alvobot-pro-module-card cta-config-section">
                        <h2><?php _e('Configurações dos Botões CTA', 'alvobot-pre-artigo'); ?></h2>
                        <div class="cta-quantity">
                            <label for="num_ctas"><strong><?php _e('Quantidade de CTAs:', 'alvobot-pre-artigo'); ?></strong></label>
                            <input type="number" id="num_ctas" name="alvobot_pre_artigo_options[num_ctas]" value="<?php echo esc_attr($num_ctas); ?>" min="1" max="10" />
                            <span class="description"><?php _e('Defina a quantidade padrão de CTAs (máximo 10).', 'alvobot-pre-artigo'); ?></span>
                        </div>
                        <div id="ctas_container">
                            <?php 
                            for ($i = 1; $i <= $num_ctas; $i++) {
                                $button_text = $options["button_text_{$i}"] ?? ($this->default_cta_texts[$i - 1] ?? '');
                                $button_color = $options["button_color_{$i}"] ?? '#1E73BE';
                                ?>
                                <div class="cta-box">
                                    <h3><?php printf(__('CTA %d', 'alvobot-pre-artigo'), $i); ?></h3>
                                    <div class="cta-field">
                                        <label for="button_text_<?php echo $i; ?>"><?php _e('Texto do Botão:', 'alvobot-pre-artigo'); ?></label>
                                        <input type="text" id="button_text_<?php echo $i; ?>" name="alvobot_pre_artigo_options[button_text_<?php echo $i; ?>]" value="<?php echo esc_attr($button_text); ?>" />
                                    </div>
                                    <div class="cta-field">
                                        <label for="button_color_<?php echo $i; ?>"><?php _e('Cor do Botão:', 'alvobot-pre-artigo'); ?></label>
                                        <input type="text" id="button_color_<?php echo $i; ?>" name="alvobot_pre_artigo_options[button_color_<?php echo $i; ?>]" value="<?php echo esc_attr($button_color); ?>" class="wp-color-picker-field" data-default-color="#1E73BE" />
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="submit-wrapper">
                            <?php submit_button(__('Salvar Configurações dos CTAs', 'alvobot-pre-artigo')); ?>
                        </div>
                    </div>
                    
                    <!-- Seção do Bloco de Anúncio -->
                    <div class="alvobot-pro-module-card cta-config-section">
                        <h2><?php _e('Bloco de Anúncio', 'alvobot-pre-artigo'); ?></h2>
                        <div class="form-group">
                            <label for="adsense_content" style="display: block; margin-bottom: 0.5em;"><strong><?php _e('Conteúdo do Bloco:', 'alvobot-pre-artigo'); ?></strong></label>
                            <textarea id="adsense_content" name="alvobot_pre_artigo_options[adsense_content]" rows="8" class="large-text code" placeholder="<?php esc_attr_e('Cole aqui o código do AdSense ou digite o texto que deseja exibir no bloco de anúncio.', 'alvobot-pre-artigo'); ?>"><?php echo esc_textarea($adsense_content); ?></textarea>
                            <p class="description"><?php _e('Este conteúdo será exibido no bloco de anúncio da página de pré-artigo. Você pode colar um código HTML do AdSense ou digitar um texto personalizado.', 'alvobot-pre-artigo'); ?></p>
                        </div>
                        <div class="submit-wrapper">
                            <?php submit_button(__('Salvar Bloco de Anúncio', 'alvobot-pre-artigo')); ?>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        }

        /**
         * Renderiza a página de configurações quando chamada pelo Alvobot Pro
         */
        public function render_settings_page() {
            error_log('Alvobot Pre Article: Iniciando render_settings_page');
            error_log('Alvobot Pre Article: User ID: ' . get_current_user_id());
            error_log('Alvobot Pre Article: User Caps: ' . json_encode(wp_get_current_user()->allcaps));

            // Verifica permissões
            if (!current_user_can('manage_options')) {
                error_log('Alvobot Pre Article: Usuário não tem permissão manage_options');
                wp_die(__('Você não tem permissão para acessar esta página.', 'alvobot-pre-artigo'));
            }

            error_log('Alvobot Pre Article: Permissões OK, renderizando página');

            // Renderiza a página de configurações
            $this->create_admin_page();
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