<?php
/**
 * Template for the AlvoBot Pro Onboarding Wizard
 *
 * Variables available:
 * $onboarding_data (array) - Data passed from AlvoBotPro::render_onboarding_page()
 *   'ajax_url'
 *   'nonce'
 *   'steps' (array of step info)
 */

if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit; // Exit if accessed directly or insufficient permissions
}

// Get data passed from PHP
global $alvobot_pro; // Assuming $alvobot_pro is the global instance

$system_info_for_onboarding = array();
$all_modules_info_for_template = array();
$active_modules_for_template = array();

if ($alvobot_pro) {
    if (method_exists($alvobot_pro, 'get_module_info')) {
        $all_modules_info_for_template = $alvobot_pro->get_module_info();
    }
    if (method_exists($alvobot_pro, 'get_active_modules')) {
        $active_modules_for_template = $alvobot_pro->get_active_modules();
    }
    
    // Simplified system info for step 2
    $alvobot_user_obj = get_user_by('login', 'alvobot');
    $system_info_for_onboarding['alvobot_user_exists'] = (bool) $alvobot_user_obj;
    $system_info_for_onboarding['alvobot_user_is_admin'] = $alvobot_user_obj ? in_array('administrator', $alvobot_user_obj->roles) : false;
    $system_info_for_onboarding['site_token_exists'] = (bool) get_option('grp_site_token');
}

?>
<div class="alvobot-pro-onboarding-wrap">
    <div class="onboarding-header">
        <img src="<?php echo esc_url(ALVOBOT_PRO_PLUGIN_URL . 'assets/images/icon-alvobot-app.svg'); ?>" alt="<?php esc_attr_e('AlvoBot Pro Logo', 'alvobot-pro'); ?>" class="alvobot-logo">
        <h1><?php esc_html_e('Bem-vindo ao AlvoBot Pro!', 'alvobot-pro'); ?></h1>
    </div>

    <div class="onboarding-content">
        <div class="onboarding-steps-indicator">
            <span class="step-indicator active" data-step-target="1">1. <?php esc_html_e('Boas-vindas', 'alvobot-pro'); ?></span>
            <span class="step-indicator" data-step-target="2">2. <?php esc_html_e('Configuração Essencial', 'alvobot-pro'); ?></span>
            <span class="step-indicator" data-step-target="3">3. <?php esc_html_e('Módulos', 'alvobot-pro'); ?></span>
            <span class="step-indicator" data-step-target="4">4. <?php esc_html_e('Links Úteis', 'alvobot-pro'); ?></span>
            <span class="step-indicator" data-step-target="5">5. <?php esc_html_e('Concluído', 'alvobot-pro'); ?></span>
        </div>

        <div class="onboarding-step-content active" data-step-id="1">
            <h2><?php esc_html_e('Bem-vindo ao AlvoBot Pro!', 'alvobot-pro'); ?></h2>
            <p><?php esc_html_e('Obrigado por escolher o AlvoBot Pro! Estamos aqui para ajudar você a turbinar seu site WordPress com ferramentas poderosas e gerenciamento simplificado.', 'alvobot-pro'); ?></p>
            <p><?php esc_html_e('Este rápido assistente de configuração ajudará você a começar.', 'alvobot-pro'); ?></p>
        </div>

        <div class="onboarding-step-content" data-step-id="2">
            <h2><?php esc_html_e('Configuração Essencial de Conexão', 'alvobot-pro'); ?></h2>
            <p><?php esc_html_e('Para recursos de gerenciamento remoto e integrações, o AlvoBot Pro realiza algumas configurações iniciais:', 'alvobot-pro'); ?></p>
            <ul>
                <li>
                    <strong><?php esc_html_e('Usuário `alvobot`:', 'alvobot-pro'); ?></strong>
                    <span>
                        <?php if ($system_info_for_onboarding['alvobot_user_exists']) : ?>
                            <span class="status-ok">✓ <?php esc_html_e('Criado', 'alvobot-pro'); ?></span>
                            (<?php echo $system_info_for_onboarding['alvobot_user_is_admin'] ? esc_html__('Admin', 'alvobot-pro') : esc_html__('Papel incorreto', 'alvobot-pro'); ?>)
                        <?php else : ?>
                            <span class="status-error">⚠ <?php esc_html_e('Não criado', 'alvobot-pro'); ?></span>
                        <?php endif; ?>
                    </span>
                    <br>
                    <small><?php esc_html_e('Este usuário é usado para operações seguras realizadas pelo AlvoBot Pro e para a conexão com nossa plataforma central, se você optar por usá-la.', 'alvobot-pro'); ?></small>
                </li>
                <li>
                    <strong><?php esc_html_e('Token do Site (grp_site_token):', 'alvobot-pro'); ?></strong>
                     <span>
                        <?php if ($system_info_for_onboarding['site_token_exists']) : ?>
                            <span class="status-ok">✓ <?php esc_html_e('Gerado', 'alvobot-pro'); ?></span>
                        <?php else : ?>
                            <span class="status-error">⚠ <?php esc_html_e('Não gerado', 'alvobot-pro'); ?></span>
                        <?php endif; ?>
                    </span>
                    <br>
                    <small><?php esc_html_e('Este token único autentica seu site com as APIs do AlvoBot Pro.', 'alvobot-pro'); ?></small>
                </li>
                <li>
                     <strong><?php esc_html_e('Senha de Aplicativo:', 'alvobot-pro'); ?></strong>
                    <br>
                    <small><?php esc_html_e('Uma senha de aplicativo é gerada para o usuário `alvobot` para o registro inicial seguro do seu site com a plataforma AlvoBot.', 'alvobot-pro'); ?></small>
                </li>
            </ul>
            <p><?php esc_html_e('Estas configurações são essenciais para a funcionalidade completa do Gerenciador de Plugins e outras integrações remotas.', 'alvobot-pro'); ?></p>
             <p><?php printf(
                wp_kses(
                    __('Você pode gerenciar ou refazer o registro a qualquer momento na página de configurações do <a href="%s">Gerenciador de Plugins</a>.', 'alvobot-pro'),
                    array('a' => array('href' => array()))
                ),
                esc_url(admin_url('admin.php?page=alvobot-pro-plugins'))
            ); ?>
            </p>
        </div>

        <div class="onboarding-step-content" data-step-id="3">
            <h2><?php esc_html_e('Ativar Módulos', 'alvobot-pro'); ?></h2>
            <p><?php esc_html_e('Selecione os módulos que você deseja ativar. Você pode alterar isso a qualquer momento no painel principal do AlvoBot Pro.', 'alvobot-pro'); ?></p>
            <div class="onboarding-module-list">
                <?php if (!empty($all_modules_info_for_template)) : ?>
                    <?php foreach ($all_modules_info_for_template as $slug => $module) : ?>
                        <div class="onboarding-module-item">
                            <label class="alvobot-pro-module-toggle onboarding-style-toggle">
                                <input type="checkbox"
                                       class="alvobot-onboarding-module-toggle"
                                       data-module-slug="<?php echo esc_attr($slug); ?>"
                                       <?php checked(isset($active_modules_for_template[$slug]) && $active_modules_for_template[$slug]); ?>
                                       <?php disabled($module['is_core']); ?>>
                                <span class="alvobot-pro-module-slider"></span>
                            </label>
                            <div class="module-info">
                                <strong class="module-name"><?php echo esc_html($module['name']); ?></strong>
                                <p class="module-description"><?php echo esc_html($module['description']); ?></p>
                                <?php if ($slug === 'multi-languages' && !function_exists('pll_languages_list')) : ?>
                                    <p class="module-notice notice-warning"><?php printf(wp_kses(__('Este módulo requer o plugin <a href="%s" target="_blank">Polylang</a>. Por favor, instale e ative o Polylang.', 'alvobot-pro'), array('a' => array('href' => array(), 'target'=>array())) ), esc_url('https://wordpress.org/plugins/polylang/')); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php esc_html_e('Não foi possível carregar a lista de módulos.', 'alvobot-pro'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="onboarding-step-content" data-step-id="4">
            <h2><?php esc_html_e('Principais Configurações Iniciais', 'alvobot-pro'); ?></h2>
            <p><?php esc_html_e('Aqui estão alguns links rápidos para as configurações que você pode querer ajustar inicialmente. Você pode acessar todas as configurações a qualquer momento através do menu AlvoBot Pro.', 'alvobot-pro'); ?></p>
            <ul class="quick-links-list">
                <?php
                if (!empty($all_modules_info_for_template)) {
                    foreach ($all_modules_info_for_template as $slug => $module) {
                        if (!empty($module['settings_slug']) && $slug !== 'plugin-manager') { // Plugin Manager already mentioned
                            echo '<li><a href="' . esc_url(admin_url('admin.php?page=' . $module['settings_slug'])) . '" target="_blank">' . sprintf(esc_html__('Configurar %s', 'alvobot-pro'), esc_html($module['name'])) . ' <span class="dashicons dashicons-external"></span></a></li>';
                        }
                    }
                }
                ?>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=alvobot-pro-plugins')); ?>" target="_blank"><?php esc_html_e('Verificar Status da Conexão (Gerenciador de Plugins)', 'alvobot-pro'); ?> <span class="dashicons dashicons-external"></span></a></li>
            </ul>
             <p><?php esc_html_e('Lembre-se de que alguns módulos, como o Multi Idiomas, podem exigir a instalação de plugins adicionais (ex: Polylang) para funcionar corretamente.', 'alvobot-pro'); ?></p>
        </div>
        
        <div class="onboarding-step-content" data-step-id="5">
            <h2><?php esc_html_e('Configuração Concluída!', 'alvobot-pro'); ?></h2>
            <p><?php esc_html_e('Parabéns! O AlvoBot Pro está pronto para ser usado. Explore os módulos e aproveite ao máximo os recursos.', 'alvobot-pro'); ?></p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=alvobot-pro')); ?>" class="button button-primary button-hero"><?php esc_html_e('Ir para o Painel AlvoBot Pro', 'alvobot-pro'); ?></a></p>
            <p>
                <?php printf(
                    wp_kses(
                        __('Precisa de ajuda? Confira nossa <a href="%s" target="_blank">documentação completa</a>.', 'alvobot-pro'),
                        array('a' => array('href' => array(), 'target' => array()))
                    ),
                    esc_url('https://github.com/alvobot/alvobot-plugin-manager/blob/main/README.md') // Placeholder
                ); ?>
            </p>
        </div>

    </div>

    <div class="onboarding-footer">
        <button id="alvobot-onboarding-skip" class="button button-secondary"><?php esc_html_e('Pular Configuração', 'alvobot-pro'); ?></button>
        <div class="step-navigation">
            <button id="alvobot-onboarding-prev" class="button button-secondary" style="display: none;"><?php esc_html_e('Anterior', 'alvobot-pro'); ?></button>
            <button id="alvobot-onboarding-next" class="button button-primary"><?php esc_html_e('Próximo Passo', 'alvobot-pro'); ?></button>
            <button id="alvobot-onboarding-finish" class="button button-primary" style="display: none;"><?php esc_html_e('Concluir', 'alvobot-pro'); ?></button>
        </div>
    </div>
</div>
