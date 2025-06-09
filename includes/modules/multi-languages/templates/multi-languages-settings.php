<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="alvobot-admin-wrap">
    <div class="alvobot-admin-container">
        <div class="alvobot-admin-header">
            <h1><?php echo esc_html__('Gerenciamento Multilíngue', 'alvobot-pro'); ?></h1>
            <p><?php echo esc_html__('Gerencie conteúdo multilíngue usando o plugin Polylang e tradução automática avançada.', 'alvobot-pro'); ?></p>
        </div>
        
        <div class="alvobot-notice-container">
            <!-- Notificações serão inseridas aqui -->
        </div>
    
    <?php
    // Garantir que a função get_plugins esteja disponível
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $all_plugins = get_plugins();
    
    // Verificando se o Polylang está ativo
    $polylang_active = class_exists('Polylang') || function_exists('pll_the_languages');
    
    if (!$polylang_active): // Se o Polylang não estiver ativo
    ?>
        <!-- Estado: Polylang não instalado -->
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title">
                        <?php echo esc_html__('Plugin Necessário: Polylang', 'alvobot-pro'); ?>
                    </h2>
                    <p class="alvobot-card-subtitle">
                        <?php echo esc_html__('O módulo Multi Languages requer o plugin Polylang para funcionar', 'alvobot-pro'); ?>
                    </p>
                </div>
                <div>
                    <?php
                    // Verificar se o Polylang está instalado mas não ativo
                    $polylang_installed = false;
                    foreach ($all_plugins as $plugin_path => $plugin_data) {
                        if (strpos($plugin_path, 'polylang') !== false) {
                            $polylang_installed = true;
                            break;
                        }
                    }
                    
                    if ($polylang_active) {
                        echo '<span class="alvobot-badge alvobot-badge-success"><span class="alvobot-status-indicator success"></span>' . 
                            esc_html__('Ativo', 'alvobot-pro') . '</span>';
                    } elseif ($polylang_installed) {
                        echo '<span class="alvobot-badge alvobot-badge-warning"><span class="alvobot-status-indicator warning"></span>' . 
                            esc_html__('Instalado (Não Ativo)', 'alvobot-pro') . '</span>';
                    } else {
                        echo '<span class="alvobot-badge alvobot-badge-warning"><span class="alvobot-status-indicator warning"></span>' . 
                            esc_html__('Não Instalado', 'alvobot-pro') . '</span>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="alvobot-card-content">
            </div>
            
            <div class="alvobot-card-footer">
                <div class="alvobot-btn-group">
                    <?php 
                    // Verificar status novamente para mostrar os botões corretos
                    if ($polylang_active) {
                        // Se já estiver ativo, mostrar link para configurações
                        $settings_url = admin_url('admin.php?page=mlang');
                    ?>
                        <a href="<?php echo esc_url($settings_url); ?>" class="alvobot-btn alvobot-btn-primary">
                            <?php echo esc_html__('Configurar Polylang', 'alvobot-pro'); ?>
                        </a>
                    <?php
                    } elseif ($polylang_installed) {
                        // Se instalado mas não ativo, mostrar botão para ativar
                        // Encontrar o caminho correto do plugin
                        $polylang_plugin_path = '';
                        foreach ($all_plugins as $plugin_path => $plugin_data) {
                            if (strpos($plugin_path, 'polylang') !== false) {
                                $polylang_plugin_path = $plugin_path;
                                break;
                            }
                        }
                        
                        $activate_url = wp_nonce_url(
                            self_admin_url('plugins.php?action=activate&plugin=' . urlencode($polylang_plugin_path)),
                            'activate-plugin_' . $polylang_plugin_path
                        );
                    ?>
                        <a href="<?php echo esc_url($activate_url); ?>" class="alvobot-btn alvobot-btn-primary">
                            <?php echo esc_html__('Ativar Polylang', 'alvobot-pro'); ?>
                        </a>
                    <?php
                    } else {
                        // Se não instalado, mostrar botão para instalar
                        $install_url = wp_nonce_url(
                            self_admin_url('update.php?action=install-plugin&plugin=polylang'),
                            'install-plugin_polylang'
                        );
                    ?>
                        <a href="<?php echo esc_url($install_url); ?>" class="alvobot-btn alvobot-btn-primary">
                            <?php echo esc_html__('Instalar Polylang', 'alvobot-pro'); ?>
                        </a>
                    <?php
                    }
                    ?>
                    <a href="https://wordpress.org/plugins/polylang/" target="_blank" class="alvobot-btn alvobot-btn-outline">
                        <?php echo esc_html__('Ver no WordPress.org', 'alvobot-pro'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title"><?php echo esc_html__('Idiomas', 'alvobot-pro'); ?></h2>
                </div>
            </div>
            <div class="alvobot-card-content">
            <?php
            // Obter idiomas diretamente do banco de dados
            global $wpdb;
            
            // Verificar se a tabela de termos do Polylang existe
            $language_terms = $wpdb->get_results("
                SELECT t.term_id, t.name, t.slug, tm.meta_value
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id AND tm.meta_key = '_pll_language_code'
                WHERE tt.taxonomy = 'language'
                ORDER BY t.term_id ASC
            ");
            
            if (empty($language_terms)): ?>
                <div class="alvobot-empty-state">
                    <p><?php echo esc_html__('Nenhum idioma configurado. Configure os idiomas no Polylang para utilizar este módulo.', 'alvobot-pro'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mlang')); ?>" class="alvobot-btn alvobot-btn-primary"><?php echo esc_html__('Configurar Idiomas', 'alvobot-pro'); ?></a>
                </div>
            <?php else: ?>
                <table class="alvobot-table alvobot-table-striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Idioma', 'alvobot-pro'); ?></th>
                            <th><?php echo esc_html__('Posts', 'alvobot-pro'); ?></th>
                            <th><?php echo esc_html__('Páginas', 'alvobot-pro'); ?></th>
                            <th><?php echo esc_html__('Padrão', 'alvobot-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Obter idioma padrão
                        $default_lang = '';
                        if (function_exists('pll_default_language')) {
                            $default_lang = pll_default_language();
                        }
                        
                        // Verificar se temos o idioma padrão
                        if (empty($default_lang) && function_exists('PLL')) {
                            // Tentar obter de outra forma
                            $pll = PLL();
                            if (isset($pll->options['default_lang'])) {
                                $default_lang = $pll->options['default_lang'];
                            }
                        }
                        
                        foreach ($language_terms as $lang): 
                            // Obter código do idioma da meta
                            $lang_code = $lang->meta_value;
                            
                            // Contar posts para este idioma
                            $post_count = $wpdb->get_var($wpdb->prepare("
                                SELECT COUNT(*) 
                                FROM {$wpdb->posts} p
                                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                WHERE tt.term_id = %d
                                AND p.post_type = 'post'
                                AND p.post_status = 'publish'
                            ", $lang->term_id));
                            
                            // Contar páginas para este idioma
                            $page_count = $wpdb->get_var($wpdb->prepare("
                                SELECT COUNT(*) 
                                FROM {$wpdb->posts} p
                                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                WHERE tt.term_id = %d
                                AND p.post_type = 'page'
                                AND p.post_status = 'publish'
                            ", $lang->term_id));
                            
                            // Verificar se é o idioma padrão (várias verificações)
                            $is_default = false;
                            
                            // Método 1: comparar com o código do idioma padrão
                            if (!empty($default_lang) && !empty($lang_code) && $lang_code === $default_lang) {
                                $is_default = true;
                            }
                            
                            // Método 2: verificar na tabela de termmeta
                            if (!$is_default) {
                                $is_default_meta = $wpdb->get_var($wpdb->prepare(
                                    "SELECT meta_value FROM {$wpdb->termmeta} 
                                    WHERE term_id = %d AND meta_key = '_pll_is_default'", 
                                    $lang->term_id
                                ));
                                
                                if ($is_default_meta == '1') {
                                    $is_default = true;
                                }
                            }
                            
                            // Método 3: verificar no objeto PLL
                            if (!$is_default && function_exists('PLL')) {
                                $pll_languages = PLL()->model->get_languages_list();
                                foreach ($pll_languages as $pll_lang) {
                                    if ($pll_lang->term_id == $lang->term_id && isset($pll_lang->is_default) && $pll_lang->is_default) {
                                        $is_default = true;
                                        break;
                                    }
                                }
                            }
                            
                            // Obter URL da bandeira (se disponível)
                            $flag_url = '';
                            if (function_exists('PLL')) {
                                $pll_languages = PLL()->model->get_languages_list();
                                foreach ($pll_languages as $pll_lang) {
                                    if ($pll_lang->term_id == $lang->term_id && isset($pll_lang->flag_url)) {
                                        $flag_url = $pll_lang->flag_url;
                                        break;
                                    }
                                }
                            }
                        ?>
                            <tr>
                                <td>
                                    <?php if (!empty($flag_url)): ?>
                                    <span class="pll-flag" style="margin-right: 5px;">
                                        <img src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($lang->name); ?>" width="16" height="11" />
                                    </span>
                                    <?php endif; ?>
                                    <?php echo esc_html($lang->name); ?>
                                    <?php if ($is_default): ?>
                                        <span class="dashicons dashicons-flag" style="color: #2271b1; margin-left: 5px;" title="<?php echo esc_attr__('Idioma padrão', 'alvobot-pro'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($post_count); ?></td>
                                <td><?php echo esc_html($page_count); ?></td>
                                <td>
                                    <?php if ($is_default): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green; font-size: 20px;"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="alvobot-description alvobot-mt-lg">
                    <?php echo esc_html__('Para adicionar ou configurar idiomas, acesse as configurações do Polylang em', 'alvobot-pro'); ?> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mlang')); ?>"><?php echo esc_html__('Idiomas', 'alvobot-pro'); ?></a>.
                </p>
            <?php endif; ?>
            </div>
        </div>
        
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title"><?php echo esc_html__('Estatísticas', 'alvobot-pro'); ?></h2>
                </div>
            </div>
            <div class="alvobot-card-content">
            <?php
            // Obter estatísticas de tradução
            $post_types = array('post', 'page');
            $stats = array();
            
            foreach ($post_types as $post_type) {
                $total_posts = wp_count_posts($post_type)->publish;
                $untranslated = 0;
                
                // Contar posts sem traduções completas
                if (function_exists('pll_get_post_translations')) {
                    $args = array(
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                    );
                    $posts = get_posts($args);
                    
                    foreach ($posts as $post) {
                        $translations = pll_get_post_translations($post->ID);
                        if (count($translations) < count($language_terms)) {
                            $untranslated++;
                        }
                    }
                }
                
                $stats[$post_type] = array(
                    'total' => $total_posts,
                    'untranslated' => $untranslated,
                    'percentage' => ($total_posts > 0) ? round(100 - (($untranslated / $total_posts) * 100)) : 0
                );
            }
            ?>
            
            <table class="alvobot-table alvobot-table-striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Tipo de Conteúdo', 'alvobot-pro'); ?></th>
                        <th><?php echo esc_html__('Total', 'alvobot-pro'); ?></th>
                        <th><?php echo esc_html__('Sem Tradução Completa', 'alvobot-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $type => $data): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($type)); ?></td>
                            <td><?php echo esc_html($data['total']); ?></td>
                            <td><?php echo esc_html($data['untranslated']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        
    <?php else: // Se o Polylang está ativo ?>
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title">
                        <?php echo esc_html__('Status do Polylang', 'alvobot-pro'); ?>
                    </h2>
                    <p class="alvobot-card-subtitle">
                        <?php echo esc_html__('O plugin Polylang está ativo e operacional', 'alvobot-pro'); ?>
                    </p>
                </div>
                <div>
                    <span class="alvobot-badge alvobot-badge-success"><span class="alvobot-status-indicator success"></span>
                        <?php echo esc_html__('Ativo', 'alvobot-pro'); ?></span>
                </div>
            </div>
            
            <div class="alvobot-card-content">
                <p><?php echo esc_html__('O Polylang está configurado corretamente. Você pode gerenciar idiomas e traduções abaixo.', 'alvobot-pro'); ?></p>
            </div>
            
            <div class="alvobot-card-footer">
                <div class="alvobot-btn-group">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mlang')); ?>" class="alvobot-btn alvobot-btn-primary">
                        <?php echo esc_html__('Configurar Polylang', 'alvobot-pro'); ?>
                    </a>
                    <a href="https://polylang.pro/doc/" target="_blank" class="alvobot-btn alvobot-btn-outline">
                        <?php echo esc_html__('Documentação do Polylang', 'alvobot-pro'); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>
