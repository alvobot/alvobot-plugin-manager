<?php
/**
 * Classe responsável pelo gerador de logo
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_LogoGenerator {
    private $fonts;
    private $icons_dir;
    private $upload_dir;
    private $plugin_url;

    public function __construct() {
        $this->icons_dir = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/assets/icons/';
        $this->upload_dir = wp_upload_dir();
        $this->plugin_url = ALVOBOT_PRO_PLUGIN_URL;
        
        // Hooks
        add_action('admin_init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_generate_logo', array($this, 'ajax_generate_logo'));
        add_action('wp_ajax_save_logo', array($this, 'ajax_save_logo'));
        add_action('wp_ajax_generate_favicon', array($this, 'ajax_generate_favicon'));
        add_action('wp_ajax_save_favicon', array($this, 'ajax_save_favicon'));
        add_action('wp_ajax_preview_logo', array($this, 'ajax_generate_logo')); // Preview em tempo real
        
        // Filtros para SVG
        add_filter('upload_mimes', array($this, 'allow_svg_upload'));
        add_filter('wp_prepare_attachment_for_js', array($this, 'fix_svg_display'), 10, 3);
    }

    public function init() {
        if (!$this->is_module_active()) {
            return;
        }

        // Cria o diretório de ícones se não existir
        if (!file_exists($this->icons_dir)) {
            wp_mkdir_p($this->icons_dir);
        }
    }

    public function is_module_active() {
        $active_modules = get_option('alvobot_pro_active_modules', array());
        return isset($active_modules['logo_generator']) && $active_modules['logo_generator'];
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'alvobot-pro-logo') === false) {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue main stylesheet
        wp_enqueue_style(
            'alvobot-pro-logo-generator',
            $this->plugin_url . 'includes/modules/logo-generator/assets/css/logo-generator.css',
            array(),
            ALVOBOT_PRO_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'alvobot-pro-logo-generator', 
            $this->plugin_url . 'includes/modules/logo-generator/assets/js/logo-generator.js', 
            array('jquery', 'wp-color-picker'), 
            ALVOBOT_PRO_VERSION, 
            true
        );
        
        wp_localize_script('alvobot-pro-logo-generator', 'logoGeneratorParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('logo_generator_nonce')
        ));
        
        $this->enqueue_google_fonts();
    }

    public function enqueue_scripts($hook) {
        // Removido pois o CSS já é carregado em enqueue_admin_scripts
        return;
    }

    public function enqueue_google_fonts() {
        if (isset($_GET['page']) && $_GET['page'] === 'alvobot-pro-logo') {
            $fonts = array(
                'Montserrat:400,700',
                'Playfair+Display:400,700',
                'Raleway:400,700',
                'Abril+Fatface',
                'Roboto:400,700',
                'Lora:400,700',
                'Oswald:400,700',
                'Pacifico',
                'Quicksand:400,700',
                'Cinzel:400,700'
            );
            
            $query_args = array(
                'family'  => implode('|', $fonts),
                'display' => 'swap',
            );
            wp_enqueue_style('blg-google-fonts', add_query_arg($query_args, "https://fonts.googleapis.com/css"), array(), null);
            
            // Adiciona CSS para preview da fonte
            wp_add_inline_style('blg-google-fonts', '
                .font-preview-select {
                    max-width: 400px;
                    width: 100%;
                }
                .font-preview-select option {
                    padding: 8px;
                    font-size: 16px;
                }
            ');
        }
    }

    public function add_preview_styles() {
        ?>
        <style>
            .logo-generator-container {
                max-width: 1200px;
                margin: 20px auto;
            }
            
            .preview-container {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .logo-preview, .favicon-preview {
                background: #fff;
                padding: 20px;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .logo-preview h3, .favicon-preview h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            
            #logo-preview-content, #favicon-preview-content {
                min-height: 100px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 20px 0;
            }
            
            #logo-preview-content svg {
                max-width: 100%;
                height: auto;
            }
            
            #favicon-preview-content svg {
                width: 32px;
                height: 32px;
            }
            
            .favicon-sizes {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin: 15px 0;
                font-size: 12px;
                color: #666;
            }
            
            .favicon-preview .submit {
                text-align: center;
                margin-top: 20px;
                padding: 0;
            }

            /* Icon Search */
            .icon-search {
                margin-bottom: 15px;
            }

            .icon-search input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            /* Icon Categories */
            .icon-categories {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
                padding: 10px;
                background: #f0f0f1;
                border-radius: 4px;
            }

            .icon-category {
                padding: 6px 12px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 20px;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s ease;
            }

            .icon-category:hover {
                border-color: #2271b1;
                color: #2271b1;
            }

            .icon-category.active {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
            }

            .icon-category .count {
                opacity: 0.7;
                font-size: 12px;
                margin-left: 4px;
            }
            
            /* Icon Grid */
            .icon-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 15px;
                margin-top: 10px;
                max-height: 400px;
                overflow-y: auto;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .icon-option {
                position: relative;
                transition: all 0.2s ease;
            }
            
            .icon-option label {
                display: flex;
                flex-direction: column;
                align-items: center;
                cursor: pointer;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                transition: all 0.2s ease;
            }
            
            .icon-option:hover label {
                border-color: #2271b1;
                background: #f6f7f7;
                transform: translateY(-2px);
            }
            
            .icon-option input[type="radio"] {
                display: none;
            }
            
            .icon-option input[type="radio"]:checked + label {
                background: #f0f6fc;
                border-color: #2271b1;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .icon-preview {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 48px;
                height: 48px;
                margin-bottom: 8px;
            }
            
            .icon-preview img {
                width: 32px;
                height: 32px;
                object-fit: contain;
            }
            
            .icon-name {
                font-size: 11px;
                text-align: center;
                color: #50575e;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                width: 100%;
            }

            .no-icons-found {
                text-align: center;
                padding: 20px;
                background: #f0f6fc;
                border-radius: 4px;
                color: #50575e;
            }

            /* Hide icons that don't match search or category */
            .icon-option.hidden {
                display: none;
            }
            
            .notice {
                margin: 15px 0;
            }
        </style>
        <?php
    }

    public function get_available_fonts() {
        error_log('[Logo Generator] Getting available fonts');
        return array(
            'montserrat' => array(
                'name'        => 'Montserrat',
                'family'      => "'Montserrat', sans-serif",
                'description' => 'Moderna e limpa'
            ),
            'playfair' => array(
                'name'        => 'Playfair Display',
                'family'      => "'Playfair Display', serif",
                'description' => 'Elegante e clássica'
            ),
            'raleway' => array(
                'name'        => 'Raleway',
                'family'      => "'Raleway', sans-serif",
                'description' => 'Minimalista e contemporânea'
            ),
            'abril' => array(
                'name'        => 'Abril Fatface',
                'family'      => "'Abril Fatface', cursive",
                'description' => 'Ousada e decorativa'
            ),
            'roboto' => array(
                'name'        => 'Roboto',
                'family'      => "'Roboto', sans-serif",
                'description' => 'Versátil e profissional'
            ),
            'lora' => array(
                'name'        => 'Lora',
                'family'      => "'Lora', serif",
                'description' => 'Elegante e legível'
            ),
            'oswald' => array(
                'name'        => 'Oswald',
                'family'      => "'Oswald', sans-serif",
                'description' => 'Forte e condensada'
            ),
            'pacifico' => array(
                'name'        => 'Pacifico',
                'family'      => "'Pacifico', cursive",
                'description' => 'Manuscrita moderna'
            ),
            'quicksand' => array(
                'name'        => 'Quicksand',
                'family'      => "'Quicksand', sans-serif",
                'description' => 'Geométrica e amigável'
            ),
            'cinzel' => array(
                'name'        => 'Cinzel',
                'family'      => "'Cinzel', serif",
                'description' => 'Clássica e luxuosa'
            )
        );
    }

    public function get_available_icons() {
        $icons = array();
        $icon_metadata = array();
        
        // Obtém todos os arquivos do diretório de ícones
        $files = scandir($this->icons_dir);
        if ($files === false) {
            return array();
        }
        
        // Primeiro: coleta metadados dos arquivos JSON
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $json_content = file_get_contents($this->icons_dir . $file);
                if ($json_content !== false) {
                    $metadata = json_decode($json_content, true);
                    if ($metadata) {
                        $icon_metadata[$name] = $metadata;
                    }
                }
            }
        }
        
        // Segundo: coleta os arquivos SVG e mescla com os metadados
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $metadata = isset($icon_metadata[$name]) ? $icon_metadata[$name] : array();
                
                $category = $this->get_icon_category($name, $metadata);
                $keywords = $this->get_icon_keywords($name, $metadata);
                
                $icons[] = array(
                    'name'     => $name,
                    'path'     => $file,
                    'url'      => $this->plugin_url . 'includes/modules/logo-generator/assets/icons/' . $file,
                    'category' => $category,
                    'keywords' => $keywords
                );
            }
        }
        
        // Ordena os ícones por categoria e nome
        usort($icons, function($a, $b) {
            $cat_compare = strcmp($a['category'], $b['category']);
            if ($cat_compare === 0) {
                return strcmp($a['name'], $b['name']);
            }
            return $cat_compare;
        });
        
        return $icons;
    }

    private function get_icon_category($name, $metadata) {
        // Se os metadados tiverem categoria, usa-a
        if (!empty($metadata['category'])) {
            return $metadata['category'];
        }

        // Caso contrário, tenta determinar a categoria pelo nome
        $categories = array(
            'interface'     => array('arrow', 'button', 'menu', 'cursor', 'zoom'),
            'business'      => array('chart', 'graph', 'office', 'money', 'coin'),
            'communication' => array('mail', 'chat', 'message', 'phone'),
            'media'         => array('play', 'pause', 'video', 'audio', 'music'),
            'social'        => array('share', 'like', 'heart', 'user'),
            'weather'       => array('sun', 'cloud', 'rain', 'snow'),
            'technology'    => array('device', 'computer', 'phone', 'tablet'),
            'tools'         => array('settings', 'tool', 'wrench', 'gear'),
        );

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($name, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    private function get_icon_keywords($name, $metadata) {
        $keywords = array();

        // Adiciona palavras-chave dos metadados
        if (!empty($metadata['keywords'])) {
            $keywords = array_merge($keywords, (array)$metadata['keywords']);
        }

        // Adiciona palavras-chave com base no nome
        $name_keywords = explode('-', $name);
        $keywords = array_merge($keywords, $name_keywords);

        // Adiciona a categoria como palavra-chave
        $category = $this->get_icon_category($name, $metadata);
        $keywords[] = $category;

        // Remove duplicatas e limpa
        $keywords = array_unique(array_filter($keywords));
        $keywords = array_map('strtolower', $keywords);

        return $keywords;
    }

    /**
     * Ajusta as cores de um SVG
     */
    private function adjust_svg_colors($svg_content, $color) {
        // Adiciona a cor como variável CSS
        $svg_content = preg_replace('/<svg/', '<svg style="color: ' . esc_attr($color) . '"', $svg_content, 1);
        
        // Substitui cores específicas por currentColor
        $svg_content = preg_replace('/fill="(?!none)[^"]*"/', 'fill="currentColor"', $svg_content);
        $svg_content = preg_replace('/stroke="(?!none)[^"]*"/', 'stroke="currentColor"', $svg_content);
        
        return $svg_content;
    }

    public function generate_logo($blog_name, $font_color, $background_color, $icon_file, $font_choice) {
        if (empty($blog_name) || empty($icon_file) || !file_exists($icon_file)) {
            error_log('Logo Generator: Arquivo não encontrado ou parâmetros inválidos - ' . $icon_file);
            return false;
        }

        try {
            // Carrega o SVG original
            $svg_content = file_get_contents($icon_file);
            if ($svg_content === false) {
                error_log('Logo Generator: Erro ao ler arquivo SVG - ' . $icon_file);
                return false;
            }

            // Ajusta as cores do SVG
            $svg_content = $this->adjust_svg_colors($svg_content, $font_color);

            // Extrai o viewBox
            preg_match('/viewBox=["\']([\d\s\.-]+)["\']/', $svg_content, $matches);
            $viewBox = isset($matches[1]) ? $matches[1] : '0 0 24 24';
            $viewBoxParts = explode(' ', $viewBox);
            
            $vbWidth = isset($viewBoxParts[2]) ? floatval($viewBoxParts[2]) : 24;
            $vbHeight = isset($viewBoxParts[3]) ? floatval($viewBoxParts[3]) : 24;

            // Dimensões do SVG principal
            $icon_size = 80;
            $padding = 20;
            $font_size = 48;
            $height = 120;
            
            // Definimos uma largura de referência para o viewBox, mas o SVG usará largura 100%
            // Calcula largura aproximada com base no texto (aprox. 0.6 * font_size por caractere + espaço para ícone)
            $text_width = strlen($blog_name) * ($font_size * 0.6);
            $viewbox_width = $icon_size + (3 * $padding) + $text_width; // ícone + espaçamento + texto
            
            // Calcula a escala mantendo a proporção
            $scale = min($icon_size / $vbWidth, $icon_size / $vbHeight);
            
            // Calcula posições com centralização precisa
            $iconX = $padding;
            $iconY = ($height - ($vbHeight * $scale)) / 2;
            
            // Garante uma largura mínima razoável para o viewBox
            $min_width = 250;
            if ($viewbox_width < $min_width) {
                $viewbox_width = $min_width;
            }
            $textY = $height / 2; // Centralização vertical precisa

            // Obtém a família da fonte
            $fonts = $this->get_available_fonts();
            $font_family = isset($fonts[$font_choice]) ? $fonts[$font_choice]['family'] : "'Montserrat', sans-serif";

            // Cria o novo SVG com alinhamento vertical melhorado
            return sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="100%%" height="%d" viewBox="0 0 %d %d" style="background-color: %s">
                    <defs>
                        <style type="text/css">
                            @import url("https://fonts.googleapis.com/css2?family=%s");
                        </style>
                    </defs>
                    <g transform="translate(%f, %f) scale(%f)">%s</g>
                    <text x="%d" y="%d" font-family="%s" font-size="%d" font-weight="bold" 
                          fill="%s" dominant-baseline="middle" text-anchor="start">%s</text>
                </svg>',
                $height,
                $viewbox_width,
                $height,
                esc_attr($background_color),
                str_replace(' ', '+', $font_family),
                $iconX,
                $iconY,
                $scale,
                $svg_content,
                $icon_size + (2 * $padding),
                $textY,
                esc_attr($font_family),
                $font_size,
                esc_attr($font_color),
                esc_html($blog_name)
            );

        } catch (Exception $e) {
            error_log('Logo Generator: Erro ao gerar logo - ' . $e->getMessage());
            return false;
        }
    }

    public function generate_square_svg($icon_file, $font_color = '#000000', $background_color = '#FFFFFF') {
        // Adiciona o caminho completo se não for fornecido
        if (strpos($icon_file, '/') === false) {
            $icon_file = $this->icons_dir . $icon_file;
        }

        // Verifica se o arquivo existe
        if (!file_exists($icon_file)) {
            error_log('Logo Generator: Arquivo de favicon não encontrado - ' . $icon_file);
            return false;
        }

        try {
            // Carrega o SVG original
            $svg_content = file_get_contents($icon_file);
            if ($svg_content === false) {
                error_log('Logo Generator: Erro ao ler arquivo SVG do favicon - ' . $icon_file);
                return false;
            }

            // Ajusta as cores do SVG
            $svg_content = $this->adjust_svg_colors($svg_content, $font_color);

            // Extrai o viewBox
            preg_match('/viewBox=["\']([\d\s\.-]+)["\']/', $svg_content, $matches);
            $viewBox = isset($matches[1]) ? $matches[1] : '0 0 24 24';
            $viewBoxParts = explode(' ', $viewBox);
            
            $vbWidth = isset($viewBoxParts[2]) ? floatval($viewBoxParts[2]) : 24;
            $vbHeight = isset($viewBoxParts[3]) ? floatval($viewBoxParts[3]) : 24;

            // Define o tamanho do favicon
            $size = 512;
            $padding = $size * 0.1;
            $content_size = $size - (2 * $padding);
            
            // Calcula a escala mantendo a proporção
            $scale = min($content_size / $vbWidth, $content_size / $vbHeight);
            
            // Calcula as posições para centralizar o ícone
            $centerX = ($size - ($vbWidth * $scale)) / 2;
            $centerY = ($size - ($vbHeight * $scale)) / 2;

            // Cria o novo SVG
            return sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" style="background-color: %s">
                    <g transform="translate(%f, %f) scale(%f)">%s</g>
                </svg>',
                $size,
                $size,
                $size,
                $size,
                esc_attr($background_color),
                $centerX,
                $centerY,
                $scale,
                $svg_content
            );

        } catch (Exception $e) {
            error_log('Logo Generator: Erro ao gerar favicon - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Permite o upload de arquivos SVG.
     */
    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Ajusta a exibição de arquivos SVG na biblioteca de mídia.
     */
    public function fix_svg_display($response, $attachment, $meta) {
        if ($response['mime'] === 'image/svg+xml') {
            $response['sizes'] = array(
                'full' => array(
                    'url'         => $response['url'],
                    'width'       => $response['width'],
                    'height'      => $response['height'],
                    'orientation' => $response['orientation']
                )
            );
        }
        return $response;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.'));
        }
        ?>
        <div class="alvobot-admin-wrap">
            <div class="alvobot-admin-container">
                <div class="alvobot-admin-header">
                    <h1>Gerador de Logo</h1>
                    <p>Crie um logo profissional para seu site em segundos</p>
                </div>

                <?php settings_errors('alvobot_pro_logo'); ?>

                <div class="alvobot-card">
                
                    <div class="preview-container">
                        <div class="logo-preview-section">
                            <h3>Preview do Logo</h3>
                            <div id="logo-preview" class="logo-preview">
                                <div id="logo-preview-content"></div>
                            </div>
                            
                        </div>
                        
                        <div class="favicon-preview-section">
                            <h3>Preview do Favicon</h3>
                            <div id="favicon-preview" class="favicon-preview">
                                <div id="favicon-preview-content"></div>
                            </div>
                        </div>
                    </div>

                    <div class="logo-generator-form">
                        <form method="post" id="logo-generator-form">
                            <?php wp_nonce_field('logo_generator_nonce', '_wpnonce'); ?>
                            <div class="form-fields">
                                <div class="form-field">
                                    <label for="blog_name">Nome do Blog</label>
                                    <input type="text" id="blog_name" name="blog_name" value="<?php echo esc_attr(get_bloginfo('name')); ?>" class="alvobot-input" required>
                                </div>

                                <div class="color-fields">
                                    <div class="form-field">
                                        <label for="font_color">Cor da Fonte</label>
                                        <input type="text" id="font_color" name="font_color" value="#000000" class="color-picker">
                                    </div>

                                    <div class="form-field">
                                        <label for="background_color">Cor do Fundo</label>
                                        <input type="text" id="background_color" name="background_color" value="#FFFFFF" class="color-picker">
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label>Escolha o Ícone</label>
                                    <div class="icon-grid-container">
                                        <?php $this->render_icon_grid(); ?>
                                        <input type="hidden" id="selected_icon" name="selected_icon" value="">
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label for="font_choice">Fonte</label>
                                    <select id="font_choice" name="font_choice" class="alvobot-select font-preview-select">
                                        <?php
                                        $fonts = $this->get_available_fonts();
                                        $current_font = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'montserrat';
                                        foreach ($fonts as $key => $font) {
                                            echo sprintf(
                                                '<option value="%s" style="font-family: %s" %s>%s - %s</option>',
                                                esc_attr($key),
                                                esc_attr($font['family']),
                                                selected($current_font, $key, false),
                                                esc_html($font['name']),
                                                esc_html($font['description'])
                                            );
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-field options-field">
                                    <label>Opções</label>
                                    <div class="checkbox-options">
                                        <label class="option-label">
                                            <input type="checkbox" id="set_as_logo" name="set_as_logo" value="1" checked>
                                            <span>Definir automaticamente como logo do site</span>
                                        </label>
                                        <label class="option-label">
                                            <input type="checkbox" id="set_as_favicon" name="set_as_favicon" value="1" checked>
                                            <span>Definir como favicon do site</span>
                                        </label>
                                        <div class="alvobot-btn-group alvobot-btn-group-right">
                                <button type="submit" name="generate_logo" id="generate_logo" class="alvobot-btn alvobot-btn-primary alvobot-btn-lg">Gerar Logo</button>
                            </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                
            </div>
        </div>

        <?php
    }

    public function render_icon_grid() {
        $icons = $this->get_available_icons();
        
        // Agrupa ícones por categoria
        $categorized_icons = array();
        foreach ($icons as $icon) {
            $category = $icon['category'];
            if (!isset($categorized_icons[$category])) {
                $categorized_icons[$category] = array();
            }
            $categorized_icons[$category][] = $icon;
        }
        
        // Container principal
        echo '<div class="icon-selector">';
        
        // Barra de busca
        echo '<div class="icon-search">
            <input type="text" id="icon-search" placeholder="Buscar ícones..." class="regular-text">
        </div>';
        
        // Categorias
        echo '<div class="icon-categories">';
        echo '<span class="icon-category active" data-category="all">Todos</span>';
        foreach ($categorized_icons as $category => $cat_icons) {
            printf(
                '<span class="icon-category" data-category="%s">%s <span class="count">%d</span></span>',
                esc_attr($category),
                esc_html(ucfirst($category)),
                count($cat_icons)
            );
        }
        echo '</div>';
        
        // Grid de ícones com container de scroll
        echo '<div class="icon-grid-scroll">';
        echo '<div class="icon-grid">';
        foreach ($categorized_icons as $category => $cat_icons) {
            foreach ($cat_icons as $icon) {
                $svg_content = file_get_contents($this->icons_dir . $icon['path']);
                if ($svg_content) {
                    printf(
                        '<div class="icon-option" data-category="%s" data-name="%s" data-keywords="%s">
                            <input type="radio" name="icon_choice" id="icon_%s" value="%s">
                            <label for="icon_%s">
                                <div class="icon-preview">%s</div>
                                <span class="icon-name">%s</span>
                            </label>
                        </div>',
                        esc_attr($category),
                        esc_attr($icon['name']),
                        esc_attr(implode(' ', $icon['keywords'])),
                        esc_attr($icon['name']),
                        esc_attr($icon['name']),
                        esc_attr($icon['name']),
                        $svg_content,
                        esc_html(ucfirst(str_replace('-', ' ', $icon['name'])))
                    );
                }
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Mensagem quando nenhum ícone é encontrado
        echo '<div class="no-icons-found">
            <span class="dashicons dashicons-info"></span>
            <p>Nenhum ícone encontrado. Tente outra busca.</p>
        </div>';
        
        echo '</div>'; // Fecha icon-selector
    }

    public function save_logo_as_attachment($svg_content, $title = '') {
        // Validação do conteúdo SVG
        if (empty($svg_content) || strpos($svg_content, '<svg') === false) {
            error_log('Logo Generator: Conteúdo SVG inválido');
            return false;
        }

        // Sanitiza o título
        $title = sanitize_text_field($title ?: 'Logo');
        
        // Configura o diretório de upload
        $upload_dir = wp_upload_dir();
        
        // Verifica se o diretório de upload existe e é gravável
        if (!wp_mkdir_p($upload_dir['path'])) {
            error_log('Logo Generator: Falha ao criar diretório: ' . $upload_dir['path']);
            return false;
        }

        $filename = sanitize_file_name($title . '-' . uniqid() . '.svg');
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Força a permissão correta do arquivo
        $file_permissions = 0644;
        
        // Salva o arquivo com verificação
        $saved = file_put_contents($filepath, $svg_content);
        if ($saved === false || filesize($filepath) === 0) {
            error_log('Logo Generator: Erro ao salvar arquivo SVG em: ' . $filepath);
            return false;
        }
        chmod($filepath, $file_permissions);

        // Cria o attachment com metadados completos
        $attachment = array(
            'post_mime_type' => 'image/svg+xml',
            'post_title'     => sanitize_file_name($title),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload_dir['url'] . '/' . $filename,
            'meta_input'     => array(
                '_wp_attachment_image_alt' => sanitize_text_field($title)
            )
        );

        // Insere e verifica o attachment
        $attach_id = wp_insert_attachment($attachment, $filepath);
        if (is_wp_error($attach_id)) {
            error_log('Logo Generator: Erro ao criar attachment - ' . $attach_id->get_error_message());
            @unlink($filepath);
            return false;
        }

        // Gera metadados do anexo
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $metadata = wp_generate_attachment_metadata($attach_id, $filepath);
        if (!empty($metadata)) {
            wp_update_attachment_metadata($attach_id, $metadata);
        }
        
        return array(
            'id'  => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
            'path' => $filepath
        );
    }

    public function ajax_save_logo() {
        check_ajax_referer('logo_generator_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        // Gera o logo
        $blog_name = isset($_POST['blog_name']) ? sanitize_text_field($_POST['blog_name']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        $icon_choice = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_choice = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'montserrat';
        
        if (empty($icon_choice)) {
            wp_send_json_error('Nenhum ícone selecionado');
            return;
        }

        // Verifica se o arquivo SVG existe
        $icon_file = $this->icons_dir . $icon_choice . '.svg';
        if (!file_exists($icon_file)) {
            wp_send_json_error('Arquivo de ícone não encontrado');
            return;
        }

        $svg_content = $this->generate_logo($blog_name, $font_color, $background_color, $icon_file, $font_choice);
        
        if (!$svg_content) {
            wp_send_json_error('Erro ao gerar o logo');
            return;
        }
        
        // Validação adicional do SVG
        if (strpos($svg_content, '<svg') === false || strpos($svg_content, '</svg>') === false) {
            wp_send_json_error('SVG gerado é inválido');
            return;
        }
        
        // Salva o logo
        $result = $this->save_logo_as_attachment($svg_content, $blog_name . ' Logo');
        
        if ($result) {
            // Define como logo do site se solicitado
            if (isset($_POST['set_as_logo']) && $_POST['set_as_logo'] == '1') {
                set_theme_mod('custom_logo', $result['id']);
            }
            
            // Gera e salva o favicon se solicitado
            if (isset($_POST['generate_favicon']) && $_POST['generate_favicon'] == '1') {
                $favicon_result = $this->generate_and_save_favicon(
                    str_replace('.svg', '', basename($icon_file)),
                    $font_color,
                    $background_color
                );
            }
            
            // Adiciona mensagem de sucesso
            $message = 'Logo gerado e salvo com sucesso!';
            if (isset($_POST['set_as_logo']) && $_POST['set_as_logo'] == '1') {
                $message .= ' O logo foi definido como logo do site.';
            }
            if (isset($_POST['generate_favicon']) && $_POST['generate_favicon'] == '1' && $favicon_result) {
                $message .= ' O favicon também foi gerado e aplicado.';
            }
            
            // Salva a mensagem na sessão para ser exibida após o redirecionamento
            add_settings_error(
                'alvobot_pro_logo',
                'logo_success',
                $message,
                'success'
            );
            
            wp_send_json_success(array(
                'id' => $result['id'],
                'url' => $result['url'],
                'message' => $message
            ));
        } else {
            wp_send_json_error('Erro ao salvar o logo');
        }
    }

    public function generate_and_save_favicon($icon_file, $font_color = '#000000', $background_color = '#FFFFFF') {
        // Gera o SVG do favicon
        $svg = $this->generate_square_svg($icon_file . '.svg', $font_color, $background_color);
        if (!$svg) {
            error_log('Logo Generator: Erro ao gerar SVG do favicon');
            return false;
        }
        
        // Salva o favicon como anexo
        $result = $this->save_logo_as_attachment($svg, 'Favicon');
        if (!$result) {
            error_log('Logo Generator: Erro ao salvar favicon como anexo');
            return false;
        }
        
        // Atualiza as opções do site
        update_option('site_icon', $result['id']);
        
        return $result;
    }

    public function ajax_save_favicon() {
        check_ajax_referer('logo_generator_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        $icon_choice = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        
        if (empty($icon_choice)) {
            wp_send_json_error('Nenhum ícone selecionado');
            return;
        }

        $result = $this->generate_and_save_favicon($icon_choice, $font_color, $background_color);
        
        if ($result) {
            $message = 'Favicon gerado e aplicado com sucesso!';
            
            // Adiciona mensagem de sucesso
            add_settings_error(
                'alvobot_pro_logo',
                'favicon_success',
                $message,
                'success'
            );

            wp_send_json_success(array(
                'favicon' => array(
                    'url' => $result['url']
                ),
                'message' => $message
            ));
        } else {
            wp_send_json_error('Erro ao gerar o favicon');
        }
    }
    
    public function ajax_generate_favicon() {
        // Verifica o nonce
        if (!check_ajax_referer('logo_generator_nonce', '_wpnonce', false)) {
            wp_send_json_error('Erro de segurança: nonce inválido');
            return;
        }

        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Você não tem permissão para executar esta ação');
            return;
        }

        // Obtém e valida os parâmetros
        $selected_icon = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';

        // Verifica se o ícone foi selecionado
        if (empty($selected_icon)) {
            wp_send_json_error('Por favor, selecione um ícone');
            return;
        }

        // Adiciona a extensão .svg ao nome do ícone
        $icon_file = $selected_icon . '.svg';

        // Gera o favicon
        $favicon_svg = $this->generate_square_svg($icon_file, $font_color, $background_color);
        if ($favicon_svg === false) {
            wp_send_json_error('Erro ao gerar o favicon');
            return;
        }

        wp_send_json_success($favicon_svg);
    }

    public function ajax_generate_logo() {
        // Verifica o nonce
        if (!check_ajax_referer('logo_generator_nonce', '_wpnonce', false)) {
            wp_send_json_error('Erro de segurança: nonce inválido');
            return;
        }

        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Você não tem permissão para executar esta ação');
            return;
        }

        // Obtém e valida os parâmetros
        $blog_name = isset($_POST['blog_name']) ? sanitize_text_field($_POST['blog_name']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        $icon_choice = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_choice = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'montserrat';

        // Verifica se o ícone foi selecionado
        if (empty($icon_choice)) {
            wp_send_json_error('Por favor, selecione um ícone');
            return;
        }

        // Verifica se o arquivo do ícone existe
        $icon_file = $this->icons_dir . $icon_choice . '.svg';
        if (!file_exists($icon_file)) {
            wp_send_json_error('Arquivo de ícone não encontrado: ' . $icon_file);
            return;
        }

        // Gera o logo
        $logo_svg = $this->generate_logo($blog_name, $font_color, $background_color, $icon_file, $font_choice);
        if ($logo_svg === false) {
            wp_send_json_error('Erro ao gerar o logo');
            return;
        }

        wp_send_json_success($logo_svg);
    }
    
    public function render_settings_page_styles() {
        ?>
        <style>
            .icon-selector {
                display: flex;
                flex-direction: column;
                gap: 15px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 15px;
            }

            .icon-search {
                position: relative;
            }

            .icon-search .dashicons {
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: #787c82;
            }

            .icon-search input {
                width: 100%;
                padding: 8px 12px 8px 35px;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                font-size: 14px;
            }

            .icon-categories {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding-bottom: 10px;
                border-bottom: 1px solid #dcdcde;
            }

            .icon-category {
                padding: 6px 12px;
                border-radius: 20px;
                background: #f0f0f1;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s ease;
                color: #3c434a;
            }

            .icon-category:hover {
                background: #e0e0e1;
            }

            .icon-category.active {
                background: #2271b1;
                color: #fff;
            }

            .icon-category .count {
                opacity: 0.8;
                font-size: 12px;
                margin-left: 4px;
            }

            .icon-grid-scroll {
                max-height: 300px;
                overflow-y: auto;
                padding: 10px 5px;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                background: #f6f7f7;
            }

            .icon-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 10px;
            }

            .icon-option {
                text-align: center;
            }

            .icon-option input[type="radio"] {
                display: none;
            }

            .icon-option label {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .icon-option label:hover {
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .icon-option input[type="radio"]:checked + label {
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.1);
                border: 1px solid #2271b1;
            }

            .icon-preview {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto;
            }

            .icon-preview svg {
                width: 100%;
                height: 100%;
                fill: currentColor;
            }

            .icon-name {
                font-size: 11px;
                color: #3c434a;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .no-icons-found {
                display: none;
                text-align: center;
                padding: 20px;
                color: #787c82;
            }

            .no-icons-found .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                margin-bottom: 8px;
            }

            .no-icons-found p {
                margin: 0;
                font-size: 13px;
            }
        </style>
        <?php
    }

    /**
     * Get the icons directory path
     *
     * @return string
     */
    public function get_icons_dir() {
        return $this->icons_dir;
    }
}