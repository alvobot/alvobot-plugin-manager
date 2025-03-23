<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Multi Languages', 'alvobot-pro'); ?></h1>
    
    <?php if (!function_exists('pll_languages_list')): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('O plugin Polylang não está ativo. Este módulo requer o Polylang para funcionar corretamente.', 'alvobot-pro'); ?></p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2><?php echo esc_html__('Idiomas Configurados', 'alvobot-pro'); ?></h2>
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
                <p><?php echo esc_html__('Nenhum idioma configurado. Configure os idiomas no Polylang.', 'alvobot-pro'); ?></p>
            <?php else: ?>
                <table class="widefat fixed striped">
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
                
                <p class="description" style="margin-top: 10px;">
                    <?php echo esc_html__('Para adicionar ou configurar idiomas, acesse as configurações do Polylang em', 'alvobot-pro'); ?> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mlang')); ?>"><?php echo esc_html__('Idiomas', 'alvobot-pro'); ?></a>.
                </p>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2><?php echo esc_html__('Estatísticas de Tradução', 'alvobot-pro'); ?></h2>
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
            
            <table class="widefat fixed striped">
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
        
        <div class="card" style="margin-top: 20px;">
            <h2><?php echo esc_html__('Sobre o Módulo', 'alvobot-pro'); ?></h2>
            <p>
                <?php echo esc_html__('Este módulo permite gerenciar conteúdo multilíngue usando o plugin Polylang.', 'alvobot-pro'); ?>
            </p>
        </div>
        
        <style>
            .card {
                padding: 20px;
                background: #fff;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                border: 1px solid #e5e5e5;
                margin-bottom: 20px;
            }
            
            .progress-bar {
                background-color: #f0f0f0;
                border-radius: 3px;
                height: 20px;
                position: relative;
                width: 100%;
            }
            
            .progress {
                background-color: #0073aa;
                border-radius: 3px;
                height: 20px;
                position: absolute;
                left: 0;
                top: 0;
            }
            
            .progress-text {
                position: absolute;
                width: 100%;
                text-align: center;
                color: #000;
                font-weight: bold;
                line-height: 20px;
            }
        </style>
    <?php endif; ?>
</div>
