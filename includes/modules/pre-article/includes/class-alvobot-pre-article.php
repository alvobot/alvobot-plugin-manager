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
        private $option_name = 'alvobot_pro_pre_article_settings'; // Standardized option name

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
                'alvobot_pro_pre_article_settings_group', // Standardized option group name
                $this->option_name,                       // Use standardized name from class property
                [
                    'sanitize_callback' => [$this, 'sanitize_options'],
                    'default' => $this->get_default_options_for_sanitize()
                ]
            );

            // Define a consistent page slug for settings sections and fields
            $settings_page_slug = 'alvobot_pro_pre_article_settings_page';

            add_settings_section(
                'alvobot_pro_pre_article_cta_section',
                __('Configurações Globais de CTA', 'alvobot-pre-artigo'),
                null,
                $settings_page_slug 
            );

            add_settings_field(
                'num_ctas',
                __('Quantidade de CTAs Padrão (Global)', 'alvobot-pre-artigo'),
                [$this, 'num_ctas_callback'],
                $settings_page_slug,
                'alvobot_pro_pre_article_cta_section'
            );
            
            add_settings_section(
                'alvobot_pro_pre_article_adsense_section',
                __('Bloco de Anúncio Global', 'alvobot-pre-artigo'),
                null,
                $settings_page_slug
            );

            add_settings_field(
                'adsense_content',
                __('Conteúdo do Bloco de Anúncio (Global)', 'alvobot-pre-artigo'),
                [$this, 'adsense_content_callback'],
                $settings_page_slug,
                'alvobot_pro_pre_article_adsense_section'
            );

            add_settings_section(
                'alvobot_pro_pre_article_footer_section',
                __('Configurações do Rodapé Global', 'alvobot-pre-artigo'),
                null,
                $settings_page_slug
            );

            add_settings_field(
                'footer_text',
                __('Texto do Rodapé Padrão (Global)', 'alvobot-pre-artigo'),
                [$this, 'footer_text_callback'],
                $settings_page_slug,
                'alvobot_pro_pre_article_footer_section'
            );
        }

        /**
         * Callback para o campo de quantidade de CTAs
         */
        public function num_ctas_callback() {
            $options = get_option($this->option_name);
            $num_ctas = isset($options['num_ctas']) ? $options['num_ctas'] : $this->get_default_options_for_sanitize()['num_ctas'];
            ?>
            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[num_ctas]" value="<?php echo esc_attr($num_ctas); ?>" min="1" max="10" />
            <p class="description"><?php _e('Defina a quantidade padrão de CTAs a serem exibidas globalmente nos Pré-Artigos. Isto pode ser sobrescrito por post.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }
        
        /**
         * Callback para o campo de AdSense
         */
        public function adsense_content_callback() {
            $options = get_option($this->option_name);
            $adsense_content = isset($options['adsense_content']) ? $options['adsense_content'] : $this->get_default_options_for_sanitize()['adsense_content'];
            ?>
            <textarea name="<?php echo esc_attr($this->option_name); ?>[adsense_content]" rows="5" class="large-text code" placeholder="<?php esc_attr_e('Cole aqui o código do AdSense ou digite o texto/HTML que deseja exibir no bloco de anúncio global.', 'alvobot-pre-artigo'); ?>"><?php echo esc_textarea($adsense_content); ?></textarea>
            <p class="description"><?php _e('Este conteúdo será exibido no bloco de anúncio da página de pré-artigo (globalmente).', 'alvobot-pre-artigo'); ?></p>
            <?php
        }


        /**
         * Callback para o campo de texto do rodapé
         */
        public function footer_text_callback() {
            $options = get_option($this->option_name);
            $footer_text = isset($options['footer_text']) ? $options['footer_text'] : $this->get_default_options_for_sanitize()['footer_text'];
            ?>
            <textarea id="footer_text" name="<?php echo esc_attr($this->option_name); ?>[footer_text]" rows="5" class="large-text code"><?php echo esc_textarea($footer_text); ?></textarea>
            <p class="description"><?php _e('Use {NOME DO SITE} como placeholder para o nome do site configurado no WordPress. Este é o rodapé padrão global.', 'alvobot-pre-artigo'); ?></p>
            <?php
        }

        /**
         * Sanitiza as opções
         */
        public function sanitize_options($input) {
            $sanitized_input = [];
            $defaults = $this->get_default_options_for_sanitize();

            $sanitized_input['num_ctas'] = isset($input['num_ctas']) ? absint($input['num_ctas']) : $defaults['num_ctas'];
            $sanitized_input['footer_text'] = isset($input['footer_text']) ? sanitize_textarea_field($input['footer_text']) : $defaults['footer_text'];

            if (isset($input['adsense_content'])) {
                $allowed_html = array_merge(
                    wp_kses_allowed_html('post'),
                    [
                        'script' => [
                            'async' => true, 
                            'src' => true,
                            'crossorigin' => true,
                            'type' => true,
                            'data-ad-client' => true,
                            'data-ad-slot' => true
                        ],
                        'ins' => [
                            'class' => true,
                            'style' => true,
                            'data-ad-client' => true,
                            'data-ad-slot' => true,
                            'data-ad-format' => true,
                            'data-full-width-responsive' => true
                        ]
                    ]
                );
                $sanitized_input['adsense_content'] = wp_kses($input['adsense_content'], $allowed_html);
            } else {
                 $sanitized_input['adsense_content'] = $defaults['adsense_content'];
            }
            
            // Global default CTA texts and colors are not part of this option array directly.
            // They are handled by the default_cta_texts property if not overridden per post.
            // If these were to become global settings, they would need their own fields and sanitization.

            return $sanitized_input;
        }
        
        // Helper for default values used in sanitization and settings registration
        private function get_default_options_for_sanitize() {
            return [
                'num_ctas' => 2,
                'adsense_content' => '',
                'footer_text' => 'Aviso Legal: As informações deste site são meramente informativas e não substituem orientação profissional. Os resultados apresentados são ilustrativos, sem garantia de sucesso específico. Somos um site independente, não afiliado a outras marcas, que preza pela privacidade do usuário e protege suas informações pessoais, utilizando apenas para comunicações relacionadas aos nossos serviços.'
            ];
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
                $final_ctas = [];

                if ('1' === $use_custom) {
                    $custom_ctas_data = get_post_meta($post->ID, '_alvobot_ctas', true);
                    $num_custom_ctas = get_post_meta($post->ID, '_alvobot_num_ctas', true);
                    $num_custom_ctas = $num_custom_ctas ? intval($num_custom_ctas) : 0;

                    if (is_array($custom_ctas_data)) {
                        for ($i = 0; $i < $num_custom_ctas; $i++) {
                            $final_ctas[] = [
                                'text' => $custom_ctas_data[$i]['text'] ?? ($this->default_cta_texts[$i] ?? __('Saiba Mais', 'alvobot-pre-artigo')),
                                'color' => $custom_ctas_data[$i]['color'] ?? '#1E73BE'
                            ];
                        }
                    }
                } else {
                    // Use global defaults
                    $options = get_option($this->option_name); // Use standardized name
                    $num_global_ctas = $options['num_ctas'] ?? $this->get_default_options_for_sanitize()['num_ctas'];
                    for ($i = 0; $i < $num_global_ctas; $i++) {
                         $final_ctas[] = [
                            'text' => $this->default_cta_texts[$i] ?? __('Saiba Mais', 'alvobot-pre-artigo'), // Fallback to default texts
                            'color' => '#1E73BE' // Default color, or could be made a global setting later
                        ];
                    }
                }
                set_query_var('alvobot_ctas', $final_ctas);

                $options = get_option($this->option_name); // Use standardized name
                $adsense_content = $options['adsense_content'] ?? $this->get_default_options_for_sanitize()['adsense_content'];
                set_query_var('alvobot_adsense_content', $adsense_content);
                
                $footer_text_template = $options['footer_text'] ?? $this->get_default_options_for_sanitize()['footer_text'];
                set_query_var('alvobot_footer_text', str_replace('{NOME DO SITE}', get_bloginfo('name'), $footer_text_template));


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
                __('Alvobot Pré-Artigo', 'alvobot-pre-artigo'), // Page Title
                __('Pré-Artigo', 'alvobot-pre-artigo'),    // Menu Title
                'manage_options',
                'alvobot_pro_pre_article_settings_page', // Standardized page slug
                [$this, 'create_admin_page'],
                'dashicons-welcome-write-blog', // Icon
                26 // Position
            );
        }

        /**
         * Cria a página de admin
         */
        public function create_admin_page() {
            // Enqueue assets needed for this specific settings page if not already handled by main plugin
            // For standalone operation, this is essential.
            // For AlvoBot Pro integration, these might be enqueued by AlvoBotPro class based on hook.
             if (!did_action('admin_enqueue_scripts')) { // Simple check if assets might have been enqueued
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                // Consider enqueuing module-specific admin CSS/JS if not handled globally
             }

            ?>
            <div class="alvobot-pro-wrap alvobot-pre-article-wrap">
                <div class="alvobot-pro-header">
                    <h1><?php _e('AlvoBot Pré-Artigo Configurações Globais', 'alvobot-pre-artigo'); ?></h1>
                    <p><?php _e('Configure as opções globais padrão para o módulo de pré-artigo.', 'alvobot-pre-artigo'); ?></p>
                </div>
                <form action="options.php" method="POST">
                    <?php 
                    settings_fields('alvobot_pro_pre_article_settings_group'); // Use the standardized group name
                    do_settings_sections('alvobot_pro_pre_article_settings_page'); // Use the standardized page slug
                    submit_button(__('Salvar Configurações Globais', 'alvobot-pre-artigo')); 
                    ?>
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