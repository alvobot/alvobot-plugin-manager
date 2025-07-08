<?php
/**
 * Classe do módulo Author Box
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_AuthorBox {
    private $version;
    private $plugin_name = 'alvobot-pro';
    private $option_name = 'alvobot_pro_author_box';

    public function __construct() {
        AlvoBotPro::debug_log('author_box', 'Inicializando módulo Author Box');
        $this->version = ALVOBOT_PRO_VERSION;
        $this->init();
        AlvoBotPro::debug_log('author_box', 'Módulo Author Box inicializado com sucesso');
    }

    public function init() {
        // Carrega o arquivo da API
        require_once plugin_dir_path(__FILE__) . 'php/api.php';
        
        // Garante que as opções padrão existam
        $options = get_option($this->option_name);
        if ($options === false) {
            update_option($this->option_name, $this->get_default_options());
        }
        
        // Registra configurações
        add_action('admin_init', array($this, 'register_settings'));

        // Adiciona campos ao perfil do usuário
        add_action('show_user_profile', array($this, 'add_social_fields'));
        add_action('edit_user_profile', array($this, 'add_social_fields'));
        add_action('personal_options_update', array($this, 'save_social_fields'));
        add_action('edit_user_profile_update', array($this, 'save_social_fields'));

        // Adiciona o Author Box ao conteúdo (prioridade mais alta para garantir que execute por último)
        remove_filter('the_content', array($this, 'append_author_box')); // Remove primeiro para evitar duplicatas
        add_filter('the_content', array($this, 'append_author_box'), 999);

        // Registra scripts e estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    private function get_default_options() {
        return array(
            'display_on_posts' => 1,
            'display_on_pages' => 0,
            'show_description' => 1,
            'avatar_size' => 96,
            'title_text' => __('Sobre o Autor', 'alvobot-pro')
        );
    }

    public function register_settings() {
        register_setting(
            $this->option_name,
            $this->option_name,
            array($this, 'sanitize_options')
        );

        add_settings_section(
            'author_box_section',
            __('Configurações do Author Box', 'alvobot-pro'),
            null,
            $this->option_name
        );

        $this->add_settings_fields();
    }

    private function add_settings_fields() {
        // Título do Author Box
        add_settings_field(
            'title_text',
            __('Título', 'alvobot-pro'),
            array($this, 'render_text_field'),
            $this->option_name,
            'author_box_section',
            array(
                'label_for' => 'title_text',
                'name' => 'title_text',
                'description' => __('Título que será exibido acima do Author Box.', 'alvobot-pro')
            )
        );


        // Exibir em Posts
        add_settings_field(
            'display_on_posts',
            __('Exibição', 'alvobot-pro'),
            array($this, 'render_display_options'),
            $this->option_name,
            'author_box_section',
            array(
                'label_for' => 'display_on_posts'
            )
        );

        // Exibir Descrição
        add_settings_field(
            'show_description',
            __('Biografia', 'alvobot-pro'),
            array($this, 'render_checkbox_field'),
            $this->option_name,
            'author_box_section',
            array(
                'label_for' => 'show_description',
                'name' => 'show_description',
                'description' => __('Exibir a biografia do autor.', 'alvobot-pro')
            )
        );
    }

    public function render_settings_section() {
        echo '<p>' . __('Configure as opções do Author Box abaixo.', 'alvobot-pro') . '</p>';
    }

    public function render_text_field($args) {
        $options = get_option($this->option_name);
        $value = isset($options[$args['name']]) ? $options[$args['name']] : '';
        ?>
        <input type="text" 
               id="<?php echo esc_attr($args['label_for']); ?>" 
               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($args['name']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_number_field($args) {
        $options = get_option($this->option_name);
        $value = isset($options[$args['name']]) ? $options[$args['name']] : '';
        ?>
        <input type="number" 
               id="<?php echo esc_attr($args['label_for']); ?>" 
               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($args['name']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text" 
               min="<?php echo esc_attr($args['min']); ?>" 
               max="<?php echo esc_attr($args['max']); ?>">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_checkbox_field($args) {
        $options = get_option($this->option_name);
        $value = isset($options[$args['name']]) ? $options[$args['name']] : '';
        ?>
        <label>
            <input type="checkbox" 
                   id="<?php echo esc_attr($args['label_for']); ?>" 
                   name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($args['name']); ?>]" 
                   <?php checked(!empty($value)); ?>>
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    public function render_display_options($args) {
        $options = get_option($this->option_name);
        ?>
        <div class="display-options">
            <label>
                <input type="checkbox" 
                       id="display_on_posts" 
                       name="<?php echo esc_attr($this->option_name); ?>[display_on_posts]" 
                       <?php checked(!empty($options['display_on_posts'])); ?>>
                <?php _e('Exibir em Posts', 'alvobot-pro'); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       id="display_on_pages" 
                       name="<?php echo esc_attr($this->option_name); ?>[display_on_pages]" 
                       <?php checked(!empty($options['display_on_pages'])); ?>>
                <?php _e('Exibir em Páginas', 'alvobot-pro'); ?>
            </label>
            <p class="description">
                <?php _e('Selecione onde o Author Box será exibido.', 'alvobot-pro'); ?>
            </p>
        </div>
        <?php
    }

    public function sanitize_options($input) {
        $defaults = $this->get_default_options();
        $sanitized = array();

        // Display on posts
        $sanitized['display_on_posts'] = isset($input['display_on_posts']) ? 1 : 0;

        // Display on pages
        $sanitized['display_on_pages'] = isset($input['display_on_pages']) ? 1 : 0;

        // Show description
        $sanitized['show_description'] = isset($input['show_description']) ? 1 : 0;

        // Avatar size
        $sanitized['avatar_size'] = isset($input['avatar_size']) ? 
            absint($input['avatar_size']) : $defaults['avatar_size'];

        // Title text
        $sanitized['title_text'] = isset($input['title_text']) ? 
            sanitize_text_field($input['title_text']) : $defaults['title_text'];

        return $sanitized;
    }

    public function add_social_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        ?>
        <div class="alvobot-pro-wrap">
            <div class="alvobot-pro-header">
                <h1><?php _e('Configurações do Author Box', 'alvobot-pro'); ?></h1>
                <p><?php _e('Personalize como suas informações de autor aparecem no Author Box.', 'alvobot-pro'); ?></p>
            </div>

            <!-- Preview Card -->
            <div class="alvobot-pro-preview-card">
                <h3><?php _e('Preview em Tempo Real', 'alvobot-pro'); ?></h3>
                <div class="ab-avatar-preview" style="margin-bottom: 10px;">
                    <?php 
                    $custom_avatar_id = get_user_meta($user->ID, 'ab_custom_avatar_id', true);
                    $avatar_url = $custom_avatar_id ? wp_get_attachment_image_url($custom_avatar_id, 'thumbnail') : get_avatar_url($user->ID);
                    ?>
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" />
                </div>
            </div>

            <!-- Settings Card -->
            <div class="alvobot-pro-card">
                <div class="alvobot-pro-card-header">
                    <h2><?php _e('Configurações do Avatar', 'alvobot-pro'); ?></h2>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th>
                            <label for="ab_custom_avatar"><?php _e('Avatar Personalizado', 'alvobot-pro'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" name="ab_custom_avatar_id" id="ab_custom_avatar_id" 
                                   value="<?php echo esc_attr($custom_avatar_id); ?>" />
                            <input type="button" class="button" id="ab_upload_avatar_button" 
                                   value="<?php _e('Selecionar Imagem', 'alvobot-pro'); ?>" />
                            <?php if ($custom_avatar_id) : ?>
                                <input type="button" class="button" id="ab_remove_avatar_button" 
                                       value="<?php _e('Remover Imagem', 'alvobot-pro'); ?>" />
                            <?php endif; ?>
                            <p class="description">
                                <?php _e('Esta imagem será usada no Author Box em vez do seu Gravatar.', 'alvobot-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    public function save_social_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        // Salva o avatar personalizado
        if (isset($_POST['ab_custom_avatar_id'])) {
            update_user_meta($user_id, 'ab_custom_avatar_id', sanitize_text_field($_POST['ab_custom_avatar_id']));
        }
    }

    public function append_author_box($content) {
        AlvoBotPro::debug_log('author_box', 'Verificando se deve exibir author box para post ID: ' . get_the_ID());
        
        // Obtém as opções
        $options = get_option($this->option_name);

        // Verifica se deve exibir o author box
        if (!is_singular()) {
            AlvoBotPro::debug_log('author_box', 'Não é página singular - author box não será exibido');
            return $content;
        }

        // Verifica o tipo de post atual
        $is_single = is_single();
        $is_page = is_page();

        // Verifica se deve exibir baseado nas configurações
        $show_on_posts = !empty($options['display_on_posts']);
        $show_on_pages = !empty($options['display_on_pages']);

        AlvoBotPro::debug_log('author_box', "Configurações: Posts={$show_on_posts}, Pages={$show_on_pages}, Current: Single={$is_single}, Page={$is_page}");

        // Retorna apenas o conteúdo se não deve exibir
        if (($is_single && !$show_on_posts) || ($is_page && !$show_on_pages)) {
            AlvoBotPro::debug_log('author_box', 'Author box desabilitado para este tipo de conteúdo');
            return $content;
        }

        // Obtém o ID do autor
        $author_id = get_the_author_meta('ID');
        
        if (!$author_id) {
            AlvoBotPro::debug_log('author_box', 'Autor não encontrado - author box não será exibido');
            return $content;
        }

        AlvoBotPro::debug_log('author_box', "Exibindo author box para autor ID: {$author_id}");

        // Gera o HTML do author box
        $author_box = $this->get_author_box_html();

        // Retorna o conteúdo com o author box
        return $content . $author_box;
    }

    private function get_author_box_html() {
        $options = get_option($this->option_name);
        
        // Se estiver na página de admin, usa o usuário atual
        if (is_admin()) {
            $author = wp_get_current_user();
            $author_id = $author->ID;
            $author_name = $author->display_name;
            $author_description = $author->description;
            $author_url = get_author_posts_url($author_id);
        } else {
            $author_id = get_the_author_meta('ID');
            $author_name = get_the_author();
            $author_description = get_the_author_meta('description');
            $author_url = get_author_posts_url($author_id);
        }

        // Verifica se existe um avatar personalizado
        $custom_avatar_id = get_user_meta($author_id, 'ab_custom_avatar_id', true);
        $avatar_html = '';
        
        if ($custom_avatar_id) {
            $avatar_url = wp_get_attachment_image_url($custom_avatar_id, 'thumbnail');
            if ($avatar_url) {
                $avatar_html = sprintf(
                    '<img src="%s" alt="%s" class="avatar" width="%d" height="%d" />',
                    esc_url($avatar_url),
                    esc_attr($author_name),
                    $options['avatar_size'],
                    $options['avatar_size']
                );
            }
        }
        
        // Se não houver avatar personalizado, usa o gravatar padrão
        if (empty($avatar_html)) {
            $avatar_html = get_avatar($author_id, $options['avatar_size']);
        }
        
        ob_start();
        ?>
        <div class="alvobot-author-box">
            <?php if (!empty($options['title_text'])) : ?>
                <h3 class="author-box-title"><?php echo esc_html($options['title_text']); ?></h3>
            <?php endif; ?>
            
            <div class="author-content">
                <div class="author-avatar">
                    <?php echo $avatar_html; ?>
                </div>
                
                <div class="author-info">
                    <h4 class="author-name">
                        <a href="<?php echo esc_url($author_url); ?>">
                            <?php echo esc_html($author_name); ?>
                        </a>
                    </h4>

                    <?php if (!empty($options['show_description']) && !empty($author_description)) : ?>
                        <div class="author-description">
                            <?php echo wp_kses_post($author_description); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="alvobot-admin-wrap">
            <div class="alvobot-admin-container">
                <div class="alvobot-admin-header">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p><?php _e('Configure as opções de exibição do Author Box em seus posts e páginas.', 'alvobot-pro'); ?></p>
                </div>

                <div class="alvobot-notice-container">
                    <?php settings_errors(); ?>
                </div>

                <!-- Settings Card -->
                <div class="alvobot-card">
                    <div class="alvobot-card-header">
                        <div>
                            <h2 class="alvobot-card-title"><?php _e('Configurações', 'alvobot-pro'); ?></h2>
                        </div>
                    </div>
                    <div class="alvobot-card-body">
                        <form action="options.php" method="post">
                            <?php
                            settings_fields($this->option_name);
                            do_settings_sections($this->option_name);
                            submit_button(__('Salvar Alterações', 'alvobot-pro'), 'alvobot-btn alvobot-btn-primary');
                            ?>
                        </form>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="alvobot-card">
                    <div class="alvobot-card-header">
                        <div>
                            <h2 class="alvobot-card-title"><?php _e('Preview do Author Box', 'alvobot-pro'); ?></h2>
                        </div>
                    </div>
                    <div class="alvobot-card-body">
                        <?php echo $this->get_author_box_html(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_public_assets() {
        if (!is_singular()) {
            return;
        }

        $options = get_option($this->option_name);
        $is_single = is_single();
        $is_page = is_page();
        $show_on_posts = !empty($options['display_on_posts']);
        $show_on_pages = !empty($options['display_on_pages']);

        // Só carrega se deve exibir o Author Box
        if (($is_single && !$show_on_posts) || ($is_page && !$show_on_pages)) {
            return;
        }

        // Carrega estilos principais do AlvoBot
        $main_styles_url = plugins_url('assets/css/styles.css', dirname(dirname(dirname(__FILE__))));
        wp_enqueue_style(
            'alvobot-pro-main-styles',
            $main_styles_url,
            array(),
            $this->version
        );

        // Carrega estilos específicos do Author Box
        wp_enqueue_style(
            'alvobot-author-box-public',
            plugin_dir_url(__FILE__) . 'css/public.css',
            array('alvobot-pro-main-styles'),
            $this->version
        );
    }

    public function enqueue_admin_assets($hook) {
        // Enqueue the main AlvoBot styles for admin - standard across all modules
        wp_enqueue_style(
            'alvobot-admin',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/css/styles.css',
            array(),
            $this->version
        );
        
        // Carrega os scripts necessários para o media uploader em páginas de perfil
        if ('profile.php' === $hook || 'user-edit.php' === $hook) {
            wp_enqueue_media();
            wp_enqueue_script(
                'ab-custom-avatar',
                plugin_dir_url(__FILE__) . 'js/custom-avatar.js',
                array('jquery'),
                $this->version,
                true
            );
        }
        
        // Enfileira o Color Picker e seus scripts específicos somente na página de configurações do módulo
        if (isset($_GET['page']) && $_GET['page'] === 'alvobot-pro-author-box') {
            // Enqueue module-specific CSS with dependency on main styles
            wp_enqueue_style(
                'alvobot-author-box-admin',
                plugin_dir_url(__FILE__) . 'css/admin.css',
                array('alvobot-admin'),  // Ensure module CSS depends on main styles
                $this->version
            );
            
            // Enqueue public CSS for preview in admin
            wp_enqueue_style(
                'alvobot-author-box-public-preview',
                plugin_dir_url(__FILE__) . 'css/public.css',
                array('alvobot-author-box-admin'),
                $this->version
            );
            
            // Scripts de administração
            wp_enqueue_script(
                'alvobot-author-box-admin-js',
                plugin_dir_url(__FILE__) . 'js/admin.js',
                array('jquery'),
                $this->version,
                true
            );
        }
    }
}
