<?php

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_PreArticle extends Alvobot_Pre_Article {
    private $version;
    private $plugin_name = 'alvobot-pro';
    private $option_name = 'alvobot_pre_artigo_options';

    public function __construct() {
        $this->version = ALVOBOT_PRO_VERSION;
        
        // Remove o hook do menu original para evitar duplicação
        remove_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Adiciona o CSS admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Inicia o módulo
        $this->init();
    }

    public function render_settings_page() {
        // Usa o método da classe pai para renderizar a página de configurações
        $this->create_admin_page();
    }

    public function init() {
        // Garante que as opções padrão existam
        $options = get_option($this->option_name);
        if ($options === false) {
            $default_options = array(
                'num_ctas' => 2,
                'footer_text' => 'Aviso Legal: As informações deste site são meramente informativas e não substituem orientação profissional. Os resultados apresentados são ilustrativos, sem garantia de sucesso específico. Somos um site independente, não afiliado a outras marcas, que preza pela privacidade do usuário e protege suas informações pessoais, utilizando apenas para comunicações relacionadas aos nossos serviços.'
            );
            update_option($this->option_name, $default_options);
        }
        
        // Inicia o módulo base
        $this->run();
    }

    // Sobrescreve o método add_admin_menu para evitar que o menu seja criado
    public function add_admin_menu() {
        // Não faz nada, pois o menu é gerenciado pelo Alvobot Pro
        return;
    }

    public function enqueue_admin_assets($hook) {
        // Carrega os assets apenas na página de configurações do módulo
        if ('alvobot-pro_page_alvobot-pro-pre-article' === $hook) {
            // CSS
            wp_enqueue_style(
                'alvobot-pre-article-admin',
                plugin_dir_url(dirname(__FILE__)) . 'alvobot-pre-article/assets/css/alvobot-pre-article-admin.css',
                array(),
                $this->version
            );
            
            // Color Picker
            wp_enqueue_style('wp-color-picker');
            
            // JavaScript
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script(
                'alvobot-pre-article-admin-settings',
                plugin_dir_url(dirname(__FILE__)) . 'alvobot-pre-article/assets/js/admin-settings.js',
                array('jquery', 'wp-color-picker'),
                $this->version,
                true
            );

            // Traduções para o JavaScript
            wp_localize_script('alvobot-pre-article-admin-settings', 'alvobotTranslations', array(
                'cta' => __('CTA', 'alvobot-pre-artigo'),
                'buttonText' => __('Texto do Botão:', 'alvobot-pre-artigo'),
                'buttonColor' => __('Cor do Botão:', 'alvobot-pre-artigo')
            ));
        }
    }
}
