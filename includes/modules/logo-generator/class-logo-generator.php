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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_head', array($this, 'add_preview_styles'));
        add_action('wp_ajax_generate_logo', array($this, 'ajax_generate_logo'));
        add_action('wp_ajax_save_logo', array($this, 'ajax_save_logo'));
        add_action('wp_ajax_generate_favicon', array($this, 'ajax_generate_favicon'));
        add_action('wp_ajax_save_favicon', array($this, 'ajax_save_favicon'));
        add_action('wp_ajax_preview_logo', array($this, 'ajax_generate_logo')); // Adicionado o método para preview em tempo real
        
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
        if ($hook != 'alvobot-pro_page_alvobot-pro-logo') {
            return;
        }
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'alvobot-pro-logo') === false) {
            return;
        }
        
        wp_enqueue_script('alvobot-pro-logo-generator', $this->plugin_url . 'includes/modules/logo-generator/js/logo-generator.js', array('jquery', 'wp-color-picker'), ALVOBOT_PRO_VERSION, true);
        wp_localize_script('alvobot-pro-logo-generator', 'logoGeneratorParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('logo_generator_nonce')
        ));
        
        $this->enqueue_google_fonts();
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
                'family' => implode('|', $fonts),
                'display' => 'swap',
            );
            wp_enqueue_style('blg-google-fonts', add_query_arg($query_args, "https://fonts.googleapis.com/css"), array(), null);
            
            // Add CSS for font preview
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
            
            .icon-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            
            .icon-grid label {
                display: block;
                cursor: pointer;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                text-align: center;
            }
            
            .icon-grid label:hover {
                border-color: #2271b1;
            }
            
            .icon-grid input[type="radio"] {
                display: none;
            }
            
            .icon-grid input[type="radio"]:checked + .icon-preview {
                background: #f0f6fc;
                border-color: #2271b1;
            }
            
            .icon-preview {
                padding: 10px;
                border: 1px solid transparent;
                border-radius: 4px;
            }
            
            .icon-preview svg {
                width: 32px;
                height: 32px;
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
                'name' => 'Montserrat',
                'family' => "'Montserrat', sans-serif",
                'description' => 'Moderna e limpa'
            ),
            'playfair' => array(
                'name' => 'Playfair Display',
                'family' => "'Playfair Display', serif",
                'description' => 'Elegante e clássica'
            ),
            'raleway' => array(
                'name' => 'Raleway',
                'family' => "'Raleway', sans-serif",
                'description' => 'Minimalista e contemporânea'
            ),
            'abril' => array(
                'name' => 'Abril Fatface',
                'family' => "'Abril Fatface', cursive",
                'description' => 'Ousada e decorativa'
            ),
            'roboto' => array(
                'name' => 'Roboto',
                'family' => "'Roboto', sans-serif",
                'description' => 'Versátil e profissional'
            ),
            'lora' => array(
                'name' => 'Lora',
                'family' => "'Lora', serif",
                'description' => 'Elegante e legível'
            ),
            'oswald' => array(
                'name' => 'Oswald',
                'family' => "'Oswald', sans-serif",
                'description' => 'Forte e condensada'
            ),
            'pacifico' => array(
                'name' => 'Pacifico',
                'family' => "'Pacifico', cursive",
                'description' => 'Manuscrita moderna'
            ),
            'quicksand' => array(
                'name' => 'Quicksand',
                'family' => "'Quicksand', sans-serif",
                'description' => 'Geométrica e amigável'
            ),
            'cinzel' => array(
                'name' => 'Cinzel',
                'family' => "'Cinzel', serif",
                'description' => 'Clássica e luxuosa'
            )
        );
    }

    public function get_available_icons() {
        error_log('[Logo Generator] Getting available icons from: ' . $this->icons_dir);
        $icons = array();
        
        if (!is_dir($this->icons_dir)) {
            error_log('[Logo Generator] Error: Icons directory does not exist: ' . $this->icons_dir);
            return $icons;
        }

        $files = scandir($this->icons_dir);
        error_log('[Logo Generator] Found files in icons directory: ' . print_r($files, true));
        
        if ($files === false) {
            error_log('[Logo Generator] Error: Failed to scan icons directory');
            return $icons;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $icons[] = array(
                    'name' => $name,
                    'path' => $file,
                    'url' => $this->plugin_url . 'includes/modules/logo-generator/assets/icons/' . $file
                );
            }
        }

        error_log('[Logo Generator] Available icons: ' . print_r($icons, true));
        return $icons;
    }

    public function generate_logo($blog_name, $font_color, $background_color, $icon_file, $font_choice) {
        error_log('[Logo Generator] Starting logo generation with:');
        error_log('[Logo Generator] - blog_name: ' . $blog_name);
        error_log('[Logo Generator] - font_color: ' . $font_color);
        error_log('[Logo Generator] - background_color: ' . $background_color);
        error_log('[Logo Generator] - icon_file: ' . $icon_file);
        error_log('[Logo Generator] - font_choice: ' . $font_choice);
        error_log('[Logo Generator] - icons_dir: ' . $this->icons_dir);

        if (empty($blog_name) || empty($icon_file)) {
            error_log('[Logo Generator] Error: Blog name or icon file is empty');
            return false;
        }

        // Dimensões finais do SVG
        $width  = 500;  // Largura total
        $height = 100;  // Altura total do SVG
        
        // Configurações de texto
        $text_size = 42;   // Tamanho da fonte
        $padding   = 20;   // Espaço entre o ícone e o texto
        
        // Altura máxima que o ícone pode ter dentro do nosso SVG de 100px
        $max_icon_height = 60; // Pode ajustar conforme a necessidade
        
        // Carrega o arquivo SVG do ícone
        $icon_path = $this->icons_dir . $icon_file;
        error_log('[Logo Generator] Attempting to load SVG from: ' . $icon_path);
        
        if (!file_exists($icon_path)) {
            error_log('[Logo Generator] Error: Icon file does not exist at ' . $icon_path);
            return false;
        }
        
        $svg_content = @file_get_contents($icon_path);
        
        if (!$svg_content) {
            error_log('[Logo Generator] Error: Failed to load SVG file: ' . $icon_path);
            return false;
        }
        
        error_log('[Logo Generator] Successfully loaded SVG icon');
        
        // Remove declarações XML e DOCTYPE
        $svg_content_clean = (string)preg_replace('/<\?xml.*?\?>/', '', $svg_content);
        $svg_content_clean = (string)preg_replace('/<!DOCTYPE.*?\?>/', '', $svg_content_clean);
        
        // Extrai a viewBox do SVG (ex: viewBox="0 0 256 512")
        preg_match('/viewBox=["\']([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)["\']/', $svg_content_clean, $vb_matches);
        
        if (count($vb_matches) === 5) {
            // minX, minY, vbWidth, vbHeight
            $minX     = floatval($vb_matches[1]);
            $minY     = floatval($vb_matches[2]);
            $vbWidth  = floatval($vb_matches[3]);
            $vbHeight = floatval($vb_matches[4]);
            error_log('[Logo Generator] Extracted viewBox: ' . implode(', ', [$minX, $minY, $vbWidth, $vbHeight]));
        } else {
            // Se não houver viewBox, assumimos algo padrão
            $minX = 0; 
            $minY = 0;
            $vbWidth = 100;
            $vbHeight = 100;
            error_log('[Logo Generator] No viewBox found, using defaults');
        }
        
        // Calcula a escala para que a altura final do ícone seja $max_icon_height
        // Se $vbHeight for 0, evitamos divisão por zero
        $icon_scale = ($vbHeight > 0) ? ($max_icon_height / $vbHeight) : 1.0;
        
        // Largura do ícone após aplicar a escala
        $drawn_icon_width = $vbWidth * $icon_scale;
        
        // Remover tags <svg> de abertura e </svg> de fechamento
        $svg_content_clean = preg_replace('/<svg[^>]*>/', '', $svg_content_clean);
        $svg_content_clean = preg_replace('/<\/svg>/', '', $svg_content_clean);
        
        // Ajusta fills e strokes para a cor da fonte
        $svg_content_clean = preg_replace('/fill="[^"]*"/', 'fill="' . $font_color . '"', $svg_content_clean);
        $svg_content_clean = preg_replace('/stroke="[^"]*"/', 'stroke="' . $font_color . '"', $svg_content_clean);
        
        // Calcula largura aproximada do texto
        $text_width = strlen($blog_name) * ($text_size * 0.6);
        
        // Largura total ocupada (ícone escalado + padding + texto)
        $total_content_width = $drawn_icon_width + $padding + $text_width;
        
        // Posição inicial com padding à esquerda (em vez de centralizar)
        $start_x = 0; // Padding fixo de 20px à esquerda
        
        // Posição do ícone
        $icon_x = $start_x;
        // Centralização vertical do ícone em 100px de altura
        $icon_y = ($height - $max_icon_height) / 2;
        
        // Posição do texto (lado a lado)
        $text_start_x = $icon_x + $drawn_icon_width + $padding;
        // Centralizado verticalmente
        $text_y = $height / 2;
        
        // Monta o SVG final
        $fonts = $this->get_available_fonts();
        $font_family = isset($fonts[$font_choice]) ? $fonts[$font_choice]['family'] : "'Montserrat', sans-serif";
        
        $svg = <<<SVG
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg xmlns="http://www.w3.org/2000/svg"
             width="{$width}"
             height="{$height}"
             viewBox="0 0 {$width} {$height}"
             style="background-color: {$background_color};">
             
            <!-- Ícone importado e escalado dinamicamente -->
            <g transform="translate({$icon_x}, {$icon_y}) 
                         scale({$icon_scale}) 
                         translate(-{$minX}, -{$minY})"
               fill="{$font_color}">
                {$svg_content_clean}
            </g>
            
            <!-- Texto -->
            <text x="{$text_start_x}"
                  y="{$text_y}"
                  font-family="{$font_family}"
                  font-size="{$text_size}px"
                  font-weight="bold"
                  dominant-baseline="middle"
                  fill="{$font_color}">
                {$blog_name}
            </text>
        </svg>
        SVG;
        
        return $svg;
    }

    public function ajax_generate_logo() {
        check_ajax_referer('logo_generator_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }

        $blog_name = isset($_POST['blog_name']) ? sanitize_text_field($_POST['blog_name']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        $selected_icon = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_choice = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'montserrat';

        // Validate icon exists
        $available_icons = $this->get_available_icons();
        $icon_found = false;
        
        foreach ($available_icons as $icon) {
            if ($icon['path'] === $selected_icon) {
                $icon_to_use = $selected_icon;
                $icon_found = true;
                break;
            }
        }
        
        if (!$icon_found) {
            $random_icon = $available_icons[array_rand($available_icons)];
            $icon_to_use = $random_icon['path'];
        }

        // Generate logo
        $logo_svg = $this->generate_logo($blog_name, $font_color, $background_color, $icon_to_use, $font_choice);
        
        if ($logo_svg) {
            wp_send_json_success($logo_svg);
        } else {
            wp_send_json_error('Erro ao gerar o logo');
        }
    }

    /**
     * Save logo as media attachment and optionally set as site logo
     */
    public function save_logo_as_attachment($svg_content, $title = '') {
        $upload_dir = wp_upload_dir();
        $logo_dir = $upload_dir['basedir'] . '/logos';
        
        // Criar diretório se não existir
        if (!file_exists($logo_dir)) {
            wp_mkdir_p($logo_dir);
        }

        // Gerar nome único para o arquivo
        $filename = sanitize_title($title ?: 'logo') . '-' . uniqid() . '.svg';
        $filepath = $logo_dir . '/' . $filename;
        
        // Salvar o arquivo SVG
        file_put_contents($filepath, $svg_content);
        
        // Criar o anexo
        $attachment = array(
            'post_mime_type' => 'image/svg+xml',
            'post_title'     => $title ?: 'Logo',
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filepath);
        
        if (is_wp_error($attach_id)) {
            return false;
        }
        
        // Gerar metadados do anexo
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return array(
            'id'  => $attach_id,
            'url' => wp_get_attachment_url($attach_id)
        );
    }

    public function ajax_save_logo() {
        check_ajax_referer('logo_generator_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $svg_content = isset($_POST['svg']) ? stripslashes($_POST['svg']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        
        if (empty($svg_content)) {
            wp_send_json_error('Conteúdo SVG não fornecido');
        }
        
        $result = $this->save_logo_as_attachment($svg_content, $title);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Logo salvo e aplicado com sucesso!',
                'logo'    => $result
            ));
        } else {
            wp_send_json_error('Erro ao salvar o logo');
        }
    }

    public function ajax_generate_favicon() {
        check_ajax_referer('logo_generator_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $icon_choice = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        
        // Gera o SVG do favicon (mesmo método do logo, mas quadrado)
        $svg = $this->generate_square_svg($icon_choice, $font_color, $background_color);
        if (!$svg) {
            wp_send_json_error('Erro ao gerar o favicon');
        }
        
        wp_send_json_success($svg);
    }
    
    public function ajax_save_favicon() {
        check_ajax_referer('logo_generator_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $icon_choice = isset($_POST['icon_choice']) ? sanitize_text_field($_POST['icon_choice']) : '';
        $font_color = isset($_POST['font_color']) ? sanitize_hex_color($_POST['font_color']) : '#000000';
        $background_color = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#FFFFFF';
        
        // Gera o SVG do favicon
        $svg = $this->generate_square_svg($icon_choice, $font_color, $background_color);
        if (!$svg) {
            wp_send_json_error('Erro ao gerar o favicon');
        }
        
        // Salva o favicon usando o mesmo método do logo
        $upload_dir = wp_upload_dir();
        $filename = 'favicon-' . uniqid() . '.svg';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Salva o arquivo
        if (!file_put_contents($filepath, $svg)) {
            wp_send_json_error('Erro ao salvar o arquivo');
        }
        
        // Cria o anexo
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'image/svg+xml',
            'post_title'     => 'Favicon',
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filepath);
        if (is_wp_error($attach_id)) {
            wp_send_json_error('Erro ao criar anexo');
        }
        
        // Remove o favicon anterior
        $old_icon_id = get_option('site_icon');
        if ($old_icon_id) {
            wp_delete_attachment($old_icon_id, true);
        }
        
        // Define o novo favicon
        update_option('site_icon', $attach_id);
        
        wp_send_json_success(array(
            'message' => 'Favicon salvo com sucesso!',
            'favicon' => array(
                'id'  => $attach_id,
                'url' => wp_get_attachment_url($attach_id)
            )
        ));
    }
    
    public function generate_and_save_favicon($icon_file, $font_color = '#000000', $background_color = '#FFFFFF') {
        // Gera o SVG do favicon
        $svg = $this->generate_square_svg($icon_file, $font_color, $background_color);
        if (!$svg) {
            throw new Exception('Erro ao gerar o favicon');
        }
        
        // Salva o favicon
        $upload_dir = wp_upload_dir();
        $filename = 'favicon-' . uniqid() . '.svg';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Salva o arquivo
        if (!file_put_contents($filepath, $svg)) {
            throw new Exception('Erro ao salvar o arquivo do favicon');
        }
        
        // Cria o anexo
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'image/svg+xml',
            'post_title'     => 'Favicon',
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filepath);
        if (is_wp_error($attach_id)) {
            throw new Exception('Erro ao criar anexo do favicon');
        }
        
        // Remove o favicon anterior
        $old_icon_id = get_option('site_icon');
        if ($old_icon_id) {
            wp_delete_attachment($old_icon_id, true);
        }
        
        // Define o novo favicon
        update_option('site_icon', $attach_id);
        
        return array(
            'id'  => $attach_id,
            'url' => wp_get_attachment_url($attach_id)
        );
    }

    public function generate_square_svg($icon_file, $font_color = '#000000', $background_color = '#FFFFFF') {
        // Carrega o arquivo SVG do ícone
        $svg_content = file_get_contents($this->icons_dir . $icon_file);
        if (!$svg_content) {
            return false;
        }
        
        // Remove declarações XML e DOCTYPE
        $svg_content = preg_replace('/<\?xml.*?\?>/', '', $svg_content);
        $svg_content = preg_replace('/<!DOCTYPE.*?>/', '', $svg_content);
        
        // Extrai o viewBox original para calcular as proporções
        if (preg_match('/viewBox=["\']([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)["\']/', $svg_content, $matches)) {
            $original_width = floatval($matches[3]);
            $original_height = floatval($matches[4]);
        } else {
            $original_width = 24;
            $original_height = 24;
        }
        
        // Remove a tag svg externa mantendo o conteúdo interno
        $svg_content = preg_replace('/<svg[^>]*>/', '', $svg_content);
        $svg_content = preg_replace('/<\/svg>/', '', $svg_content);
        
        // Remove cores existentes
        $svg_content = preg_replace('/fill="[^"]*"/', '', $svg_content);
        $svg_content = preg_replace('/stroke="[^"]*"/', '', $svg_content);
        
        // Monta o SVG quadrado
        $size = 128; // Tamanho do favicon
        $padding = $size * 0.15; // 20% de padding
        $content_size = $size - (2 * $padding);
        
        // Calcula a escala mantendo a proporção
        $scale = $content_size / max($original_width, $original_height);
        
        // Calcula o centro
        $center_x = ($size - ($original_width * $scale)) / 2;
        $center_y = ($size - ($original_height * $scale)) / 2;
        
        $svg = <<<SVG
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
            <rect x="0" y="0" width="{$size}" height="{$size}" fill="{$background_color}"/>
            <g transform="translate({$center_x}, {$center_y}) scale({$scale})" fill="{$font_color}">
                {$svg_content}
            </g>
        </svg>
        SVG;
        
        return $svg;
    }

    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    public function fix_svg_display($response, $attachment, $meta) {
        if ($response['mime'] === 'image/svg+xml') {
            $response['sizes'] = array(
                'full' => array(
                    'url' => $response['url'],
                    'width' => $response['width'],
                    'height' => $response['height'],
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
        <div class="alvobot-pro-wrap">
            <div class="alvobot-pro-header">
                <h1>Gerador de Logo</h1>
                <p>Crie um logo profissional para seu site em segundos</p>
            </div>

            <div class="alvobot-pro-module-card">
                <div class="logo-generator-container">
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
                            <div class="favicon-sizes">
                                <span class="size-16">16px</span>
                                <span class="size-32">32px</span>
                                <span class="size-180">180px</span>
                            </div>
                            <p class="submit">
                                <button type="button" id="save-favicon-button" class="button button-secondary">Salvar como Favicon do Site</button>
                            </p>
                        </div>
                    </div>

                    <div class="logo-generator-form">
                        <form method="post" id="logo-generator-form">
                            <?php wp_nonce_field('logo_generator_nonce', '_wpnonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="blog_name">Nome do Blog</label></th>
                                    <td>
                                        <input type="text" id="blog_name" name="blog_name" value="<?php echo esc_attr(get_bloginfo('name')); ?>" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="font_color">Cor da Fonte</label></th>
                                    <td>
                                        <input type="text" id="font_color" name="font_color" value="#000000" class="color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="background_color">Cor do Fundo</label></th>
                                    <td>
                                        <input type="text" id="background_color" name="background_color" value="#FFFFFF" class="color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Escolha o Ícone</th>
                                    <td>
                                        <div class="icon-grid">
                                            <?php
                                            $icons = $this->get_available_icons();
                                            foreach ($icons as $index => $icon) {
                                                $svg_content = file_get_contents($this->icons_dir . $icon['path']);
                                                if ($svg_content) {
                                                    ?>
                                                    <label>
                                                        <input type="radio" name="icon_choice" value="<?php echo esc_attr($icon['path']); ?>" 
                                                               <?php echo $index === 0 ? 'checked' : ''; ?>>
                                                        <div class="icon-preview">
                                                            <?php echo $svg_content; ?>
                                                        </div>
                                                    </label>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="font_choice">Fonte</label></th>
                                    <td>
                                        <select id="font_choice" name="font_choice" class="font-preview-select">
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
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Opções</th>
                                    <td>
                                        <label class="option-label">
                                            <input type="checkbox" name="set_as_logo" value="1" checked>
                                            Definir automaticamente como logo do site
                                        </label>
                                        <br>
                                        <label class="option-label">
                                            <input type="checkbox" name="set_as_favicon" value="1" checked>
                                            Definir como favicon do site
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button('Gerar Logo', 'primary', 'generate_logo'); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .logo-generator-container {
                display: flex;
                flex-direction: column;
                gap: 30px;
                margin-top: 20px;
            }

            .preview-container {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }

            .logo-preview-section,
            .favicon-preview-section {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .logo-preview {
                background: #f8f9fa;
                padding: 30px;
                border-radius: 8px;
                text-align: center;
                min-height: 200px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 20px;
            }

            .favicon-preview {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                min-height: 100px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 20px;
            }

            .favicon-sizes {
                display: flex;
                justify-content: space-around;
                margin-top: 10px;
            }

            .favicon-sizes span {
                background: #e9ecef;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
            }

            .logo-preview svg,
            .favicon-preview svg {
                max-width: 100%;
                height: auto;
            }

            .icon-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 10px;
                max-height: 200px;
                overflow-y: auto;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .icon-grid label {
                cursor: pointer;
                text-align: center;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .icon-grid label:hover {
                background: #f0f0f0;
            }

            .icon-grid input[type="radio"] {
                display: none;
            }

            .icon-grid input[type="radio"]:checked + .icon-preview {
                background: #e0e0e0;
            }

            .icon-preview {
                width: 40px;
                height: 40px;
                margin: 0 auto;
                padding: 5px;
                border-radius: 4px;
            }

            .icon-preview svg {
                width: 100%;
                height: 100%;
            }

            .font-preview-select {
                max-width: 400px;
                width: 100%;
                padding: 8px;
                border-radius: 4px;
            }

            .option-label {
                display: block;
                margin: 5px 0;
            }

            .notice {
                margin: 10px 0;
            }

            @media screen and (max-width: 782px) {
                .preview-container {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
}
