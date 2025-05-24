<?php
if (!defined('ABSPATH')) {
    exit;
}

// Data passed from AlvoBotPro::render_dashboard_page()
// $system_info (array)
// $modules_for_template (array)

?>

<div class="alvobot-pro-wrap">
  <div class="alvobot-pro-header">
    <h1><?php esc_html_e('AlvoBot Pro', 'alvobot-pro'); ?></h1>
    <p><?php esc_html_e('Gerencie todos os módulos e configurações do AlvoBot Pro em um só lugar.', 'alvobot-pro'); ?></p>
  </div>

  <div class="alvobot-pro-notices">
    <!-- Notices will be injected here by JS or after form submissions -->
  </div>

  <h2 id="alvobot-pro-modules-section" style="margin-top: 2em;"><?php esc_html_e('Gerenciamento de Módulos', 'alvobot-pro'); ?></h2>
  <div class="alvobot-pro-modules">
    <?php foreach ($modules_for_template as $module_slug => $module_info) : ?>
      <div class="alvobot-pro-module-card <?php echo $module_info['active'] ? 'module-enabled' : 'module-disabled'; ?>">
        <div class="alvobot-pro-module-header">
          <h3 class="alvobot-pro-module-title"><?php echo esc_html($module_info['name']); ?></h3>
          <label class="alvobot-pro-module-toggle">
            <input type="checkbox" 
                   class="alvobot-module-toggle-checkbox"
                   data-module="<?php echo esc_attr($module_slug); ?>" 
                   <?php checked($module_info['active']); ?>
                   <?php disabled($module_info['is_core']); ?>>
            <span class="alvobot-pro-module-slider"></span>
          </label>
        </div>
        <p class="alvobot-pro-module-description">
          <?php echo esc_html($module_info['description']); ?>
          <?php if ($module_info['is_core']) : ?>
            <br><small><em><?php esc_html_e('Este módulo é essencial e não pode ser desativado.', 'alvobot-pro'); ?></em></small>
          <?php endif; ?>
        </p>
        <div class="alvobot-pro-module-actions">
          <?php if ($module_info['active'] && !empty($module_info['settings_slug'])) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $module_info['settings_slug'])); ?>" class="button button-secondary"><?php esc_html_e('Configurações', 'alvobot-pro'); ?></a>
          <?php elseif (empty($module_info['settings_slug'])): ?>
            <span><em><?php // esc_html_e('Nenhuma configuração específica para este módulo.', 'alvobot-pro'); ?></em></span>
          <?php else: ?>
             <button class="button button-secondary" disabled><?php esc_html_e('Configurações', 'alvobot-pro'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

    <script>
    // Basic notice display - can be enhanced later
    function showNotice(message, type = 'success') {
        const noticeDiv = document.createElement('div');
        noticeDiv.className = 'notice is-dismissible ' + (type === 'error' ? 'notice-error' : 'notice-success');
        const p = document.createElement('p');
        p.innerHTML = message; // Assuming message is already escaped or safe
        noticeDiv.appendChild(p);
        
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'notice-dismiss';
        button.innerHTML = '<span class="screen-reader-text">Descartar este aviso.</span>';
        button.addEventListener('click', function() {
            noticeDiv.remove();
        });
        noticeDiv.appendChild(button);

        const noticesContainer = document.querySelector('.alvobot-pro-notices');
        if (noticesContainer) {
            noticesContainer.appendChild(noticeDiv);
        } else {
            // Fallback if the specific container isn't there
            const headerEnd = document.querySelector('.alvobot-pro-header + *');
            if (headerEnd) {
                headerEnd.parentNode.insertBefore(noticeDiv, headerEnd);
            }
        }
    }

    jQuery(document).ready(function($) {
        // This script is primarily for module toggling, which is in alvobot-pro-admin.js
        // Token visibility is in token-visibility.js
        // Notices from URL params are handled above.
    });
    </script>
</div>
