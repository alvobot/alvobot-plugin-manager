<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="alvobot-admin-wrap">
  <div class="alvobot-admin-container">
    <div class="alvobot-admin-header">
      <h1>AlvoBot Pro</h1>
      <p>Gerencie todos os módulos do AlvoBot Pro em um só lugar.</p>
    </div>

    <div class="alvobot-notice-container">
      <!-- Mensagens de sucesso/erro, se necessário -->
    </div>

    <div class="alvobot-grid alvobot-grid-auto">
      <!-- Cartões dos Módulos -->
      <!-- Módulo Gerador de Logo -->
      <div class="alvobot-card <?php echo isset($active_modules['logo_generator']) && $active_modules['logo_generator'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Gerador de Logo</h2>
            <p class="alvobot-card-subtitle">Crie logos profissionais para seus sites WordPress automaticamente.</p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="logo_generator" 
                   <?php echo isset($active_modules['logo_generator']) && $active_modules['logo_generator'] ? 'checked="checked"' : ''; ?>>
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <?php if (isset($active_modules['logo_generator']) && $active_modules['logo_generator']): ?>
            <a href="<?php echo admin_url('admin.php?page=alvobot-pro-logo'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Módulo Author Box -->
      <div class="alvobot-card <?php echo isset($active_modules['author_box']) && $active_modules['author_box'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Author Box</h2>
            <p class="alvobot-card-subtitle">Exiba uma elegante caixa de autor no final dos seus posts.</p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="author_box" 
                   <?php echo isset($active_modules['author_box']) && $active_modules['author_box'] ? 'checked="checked"' : ''; ?>>
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <?php if (isset($active_modules['author_box']) && $active_modules['author_box']): ?>
            <a href="<?php echo admin_url('admin.php?page=alvobot-pro-author-box'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Módulo Pre Article -->
      <div class="alvobot-card <?php echo isset($active_modules['pre-article']) && $active_modules['pre-article'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Pre Article</h2>
            <p class="alvobot-card-subtitle">Gere páginas de pré-artigo automaticamente para seus posts existentes.</p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="pre-article" 
                   <?php echo isset($active_modules['pre-article']) && $active_modules['pre-article'] ? 'checked="checked"' : ''; ?>>
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <?php if (isset($active_modules['pre-article']) && $active_modules['pre-article']): ?>
            <a href="<?php echo admin_url('admin.php?page=alvobot-pro-pre-article'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Módulo Plugin Manager (sempre ativo) -->
      <div class="alvobot-card module-enabled">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Plugin Manager</h2>
            <p class="alvobot-card-subtitle">Gerencie plugins remotamente através da plataforma AlvoBot.<br><small><em>Este módulo é essencial e não pode ser desativado.</em></small></p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="plugin-manager" 
                   checked="checked"
                   disabled="disabled">
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <a href="<?php echo admin_url('admin.php?page=alvobot-pro-plugins'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
        </div>
      </div>

      <!-- Módulo Essential Pages -->
      <div class="alvobot-card <?php echo isset($active_modules['essential_pages']) && $active_modules['essential_pages'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Páginas Essenciais</h2>
            <p class="alvobot-card-subtitle">Crie e gerencie páginas essenciais como Termos de Uso, Política de Privacidade e Contato.</p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="essential_pages" 
                   <?php echo isset($active_modules['essential_pages']) && $active_modules['essential_pages'] ? 'checked="checked"' : ''; ?>>
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <?php if (isset($active_modules['essential_pages']) && $active_modules['essential_pages']): ?>
            <a href="<?php echo admin_url('admin.php?page=alvobot-pro-essential-pages'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Módulo Multi Languages -->
      <div class="alvobot-card <?php echo isset($active_modules['multi-languages']) && $active_modules['multi-languages'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-card-header">
          <div>
            <h2 class="alvobot-card-title">Multi Languages</h2>
            <p class="alvobot-card-subtitle">Gerencie traduções e conteúdo multilíngue para seu site WordPress com Polylang.</p>
          </div>
          <label class="alvobot-toggle">
            <input type="checkbox" 
                   data-module="multi-languages" 
                   <?php echo isset($active_modules['multi-languages']) && $active_modules['multi-languages'] ? 'checked="checked"' : ''; ?>>
            <span class="alvobot-toggle-slider"></span>
          </label>
        </div>
        <div class="alvobot-card-footer">
          <?php if (isset($active_modules['multi-languages']) && $active_modules['multi-languages']): ?>
            <a href="<?php echo admin_url('admin.php?page=alvobot-pro-multi-languages'); ?>" class="alvobot-btn alvobot-btn-secondary">Configurações</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Cartão extra do Plugin Manager (status e formulários) -->
    <div class="alvobot-card">
      <div class="plugin-manager-container">
      <h2><?php _e('Status do Gerenciador de Plugins', 'alvobot-pro'); ?></h2>
      
      <table class="alvobot-form-table" role="presentation">
        <tr>
          <th scope="row"><?php _e('AlvoBot User', 'alvobot-pro'); ?></th>
          <td>
            <?php if ($alvobot_user): ?>
              <span style="color: green;">✓</span> <?php _e('Created', 'alvobot-pro'); ?>
            <?php else: ?>
              <span style="color: red;">×</span> <?php _e('Not Created', 'alvobot-pro'); ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th scope="row"><?php _e('Site Token', 'alvobot-pro'); ?></th>
          <td>
            <?php if ($site_token): ?>
              <span style="color: green;">✓</span> <?php _e('Generated', 'alvobot-pro'); ?>
              <br>
              <div class="alvobot-token-field">
                  <code class="alvobot-token-value" data-token="<?php echo esc_attr($site_token); ?>">••••••••••••••••••••••••••••••••</code>
                  <button type="button" class="alvobot-token-toggle" title="<?php esc_attr_e('Mostrar/Ocultar Token', 'alvobot-pro'); ?>">
                      <svg class="eye-show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      <svg class="eye-hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                  </button>
              </div>
            <?php else: ?>
              <span style="color: red;">×</span> <?php _e('Not Generated', 'alvobot-pro'); ?>
            <?php endif; ?>
          </td>
        </tr>
      </table>
      
      <?php if (!$alvobot_user || !$site_token): ?>
        <form method="post" action="">
          <?php wp_nonce_field('activate_plugin_manager'); ?>
          <input type="hidden" name="action" value="activate_plugin_manager">
          <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Initialize Plugin Manager', 'alvobot-pro'); ?>">
          </p>
        </form>
      <?php else: ?>
        <form method="post" action="">
          <?php wp_nonce_field('retry_registration'); ?>
          <input type="hidden" name="action" value="retry_registration">
          <p class="submit">
            <input type="submit" name="submit" id="retry_registration" class="alvobot-btn alvobot-btn-secondary" value="<?php esc_attr_e('Refazer Registro', 'alvobot-pro'); ?>">
          </p>
        </form>
      <?php endif; ?>
      </div>
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
