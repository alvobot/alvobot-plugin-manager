<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="alvobot-pro-wrap">
    <div class="alvobot-pro-header">
        <h1>AlvoBot Pro</h1>
        <p>Gerencie todos os módulos do AlvoBot Pro em um só lugar.</p>
    </div>

    <div class="alvobot-pro-notices"></div>

    <div class="alvobot-pro-modules">
        <!-- Módulo Gerador de Logo -->
        <div class="alvobot-pro-module-card <?php echo isset($active_modules['logo_generator']) && $active_modules['logo_generator'] ? 'module-enabled' : ''; ?>">
            <div class="alvobot-pro-module-header">
                <h2 class="alvobot-pro-module-title">Gerador de Logo</h2>
                <label class="alvobot-pro-module-toggle">
                    <input type="checkbox" 
                           data-module="logo_generator" 
                           <?php echo isset($active_modules['logo_generator']) && $active_modules['logo_generator'] ? 'checked="checked"' : ''; ?>>
                    <span class="alvobot-pro-module-slider"></span>
                </label>
            </div>
            <p class="alvobot-pro-module-description">
                Crie logos profissionais para seus sites WordPress automaticamente.
            </p>
            <div class="alvobot-pro-module-actions">
                <?php if (isset($active_modules['logo_generator']) && $active_modules['logo_generator']): ?>
                    <a href="<?php echo admin_url('admin.php?page=alvobot-pro-logo'); ?>">Configurações</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Módulo Author Box -->
        <div class="alvobot-pro-module-card <?php echo isset($active_modules['author_box']) && $active_modules['author_box'] ? 'module-enabled' : ''; ?>">
            <div class="alvobot-pro-module-header">
                <h2 class="alvobot-pro-module-title">Author Box</h2>
                <label class="alvobot-pro-module-toggle">
                    <input type="checkbox" 
                           data-module="author_box" 
                           <?php echo isset($active_modules['author_box']) && $active_modules['author_box'] ? 'checked="checked"' : ''; ?>>
                    <span class="alvobot-pro-module-slider"></span>
                </label>
            </div>
            <p class="alvobot-pro-module-description">
                Exiba uma elegante caixa de autor no final dos seus posts.
            </p>
            <div class="alvobot-pro-module-actions">
                <?php if (isset($active_modules['author_box']) && $active_modules['author_box']): ?>
                    <a href="<?php echo admin_url('admin.php?page=alvobot-pro-author'); ?>">Configurações</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Módulo Pre Article -->
        <div class="alvobot-pro-module-card <?php echo isset($active_modules['pre_article']) && $active_modules['pre_article'] ? 'module-enabled' : ''; ?>">
            <div class="alvobot-pro-module-header">
                <h2 class="alvobot-pro-module-title">Pre Article</h2>
                <label class="alvobot-pro-module-toggle">
                    <input type="checkbox" 
                           data-module="pre_article" 
                           <?php echo isset($active_modules['pre_article']) && $active_modules['pre_article'] ? 'checked="checked"' : ''; ?>>
                    <span class="alvobot-pro-module-slider"></span>
                </label>
            </div>
            <p class="alvobot-pro-module-description">
                Gere páginas de pré-artigo automaticamente para seus posts existentes.
            </p>
            <div class="alvobot-pro-module-actions">
                <?php if (isset($active_modules['pre_article']) && $active_modules['pre_article']): ?>
                    <a href="<?php echo admin_url('admin.php?page=alvobot-pro-pre-article'); ?>">Configurações</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Módulo Plugin Manager -->
        <div class="alvobot-pro-module-card module-enabled">
            <div class="alvobot-pro-module-header">
                <h2 class="alvobot-pro-module-title">Plugin Manager</h2>
                <label class="alvobot-pro-module-toggle">
                    <input type="checkbox" 
                           data-module="plugin_manager" 
                           checked="checked"
                           disabled="disabled">
                    <span class="alvobot-pro-module-slider"></span>
                </label>
            </div>
            <p class="alvobot-pro-module-description">
                Gerencie plugins remotamente através da plataforma AlvoBot.
                <br>
                <small><em>Este módulo é essencial e não pode ser desativado.</em></small>
            </p>
            <div class="alvobot-pro-module-actions">
                <a href="<?php echo admin_url('admin.php?page=alvobot-pro-plugins'); ?>">Configurações</a>
            </div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Verifica se há algum erro na URL
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        if (error) {
            showNotice(decodeURIComponent(error), 'error');
        }

        // Verifica se há alguma mensagem de sucesso na URL
        const success = urlParams.get('success');
        if (success) {
            showNotice(decodeURIComponent(success));
        }
    });
    </script>
</div>
