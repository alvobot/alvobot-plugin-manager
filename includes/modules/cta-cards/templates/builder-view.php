<?php
/**
 * CTA Cards Builder View
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cta-builder-layout">
    <!-- Sidebar Esquerda - Templates e Configurações -->
    <div class="cta-builder-sidebar">
        <div class="sidebar-header">
            <h3><?php _e('Templates', 'alvobot-pro'); ?></h3>
        </div>
        
        <!-- Templates Gallery -->
        <div class="templates-gallery">
            <div class="template-item" data-template="vertical">
                <div class="template-preview">
                    <div class="mini-cta vertical">
                        <div class="mini-title"></div>
                        <div class="mini-subtitle"></div>
                        <div class="mini-description"></div>
                        <div class="mini-button"></div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Vertical', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Centralizado com imagem', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="horizontal">
                <div class="template-preview">
                    <div class="mini-cta horizontal">
                        <div class="mini-image"></div>
                        <div class="mini-content">
                            <div class="mini-title"></div>
                            <div class="mini-description"></div>
                            <div class="mini-button"></div>
                        </div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Horizontal', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Imagem + conteúdo', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="minimal">
                <div class="template-preview">
                    <div class="mini-cta minimal">
                        <div class="mini-tag"></div>
                        <div class="mini-title"></div>
                        <div class="mini-link"></div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Minimalista', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Design limpo', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="banner">
                <div class="template-preview">
                    <div class="mini-cta banner">
                        <div class="mini-title"></div>
                        <div class="mini-description"></div>
                        <div class="mini-button"></div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Banner', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Fundo destacado', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="simple">
                <div class="template-preview">
                    <div class="mini-cta simple">
                        <div class="mini-icon">🌟</div>
                        <div class="mini-title"></div>
                        <div class="mini-arrow">→</div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Simples', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Ícone + link', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="pulse">
                <div class="template-preview">
                    <div class="mini-cta pulse">
                        <div class="mini-pulse-dot"></div>
                        <div class="mini-icon">▶️</div>
                        <div class="mini-title"></div>
                        <div class="mini-button"></div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Pulse', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Animação pulsante', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="multi-button">
                <div class="template-preview">
                    <div class="mini-cta multi">
                        <div class="mini-title"></div>
                        <div class="mini-buttons">
                            <div class="mini-button"></div>
                            <div class="mini-button"></div>
                            <div class="mini-button"></div>
                        </div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('Multi-Botão', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Múltiplas ações', 'alvobot-pro'); ?></small>
                </div>
            </div>

            <div class="template-item" data-template="led-border">
                <div class="template-preview">
                    <div class="mini-cta led">
                        <div class="mini-icon">⚡</div>
                        <div class="mini-title"></div>
                        <div class="mini-led-button"></div>
                    </div>
                </div>
                <div class="template-info">
                    <strong><?php _e('LED Border', 'alvobot-pro'); ?></strong>
                    <small><?php _e('Efeito futurista', 'alvobot-pro'); ?></small>
                </div>
            </div>
        </div>
        
        <!-- Shortcode Output -->
        <div class="shortcode-section">
            <h4>
                <i class="dashicons dashicons-editor-code"></i>
                <?php _e('Shortcode:', 'alvobot-pro'); ?>
            </h4>
            <textarea id="generated-shortcode" class="input-modern" rows="4" readonly 
                      placeholder="<?php _e('Configure seu CTA para gerar o shortcode...', 'alvobot-pro'); ?>"></textarea>
            <button type="button" class="btn btn--primary btn--sm" id="btn--copy" style="margin-top: 8px;">
                <i class="dashicons dashicons-clipboard"></i>
                <span class="copy-text"><?php _e('Copiar', 'alvobot-pro'); ?></span>
            </button>
        </div>
    </div>
    
    <!-- Canvas Central - Configurações -->
    <div class="cta-builder-canvas">
        <div class="canvas-header">
            <h3><?php _e('Configurações do CTA', 'alvobot-pro'); ?></h3>
            <div class="canvas-actions">
                <button type="button" class="btn btn--secondary" id="btn--reset">
                    <i class="dashicons dashicons-update"></i>
                    <?php _e('Limpar', 'alvobot-pro'); ?>
                </button>
                <button type="button" class="btn btn--ghost" id="btn--examples">
                    <i class="dashicons dashicons-visibility"></i>
                    <?php _e('Ver Exemplos', 'alvobot-pro'); ?>
                </button>
            </div>
        </div>
        
        <!-- Configuração Form -->
        <div class="cta-form-container" id="cta-form-container">
            <!-- Estado inicial -->
            <div class="cta-empty-state" id="cta-empty-state">
                <div class="empty-state">
                    <i class="dashicons dashicons-format-image empty-icon"></i>
                    <h4><?php _e('Escolha um template', 'alvobot-pro'); ?></h4>
                    <p><?php _e('Selecione um dos templates na barra lateral para começar', 'alvobot-pro'); ?></p>
                </div>
            </div>

            <!-- Form de configuração (será preenchido dinamicamente) -->
            <form id="cta-config-form" class="cta-config-form" style="display: none;">
                <div id="form-content"></div>
            </form>
        </div>
    </div>
    
    <!-- Preview Direita - Visualização Ao Vivo -->
    <div class="cta-builder-preview">
        <div class="preview-header">
            <h3><?php _e('Preview Ao Vivo', 'alvobot-pro'); ?></h3>
            <div class="preview-actions">
                <button type="button" class="btn btn--ghost btn--sm" id="preview-mobile" title="<?php _e('Visualizar Mobile', 'alvobot-pro'); ?>">
                    <i class="dashicons dashicons-smartphone"></i>
                </button>
                <button type="button" class="btn btn--ghost btn--sm is-active" id="preview-desktop" title="<?php _e('Visualizar Desktop', 'alvobot-pro'); ?>">
                    <i class="dashicons dashicons-desktop"></i>
                </button>
            </div>
        </div>
        
        <div class="preview-viewport">
            <div class="preview-frame desktop-frame is-active" id="cta-preview-content">
                <div class="preview-placeholder">
                    <i class="dashicons dashicons-visibility"></i>
                    <p><?php _e('Escolha um template para ver o preview', 'alvobot-pro'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="preview-footer">
            <div class="cta-stats">
                <span>🎨 <strong id="stats-template"><?php _e('Nenhum', 'alvobot-pro'); ?></strong></span>
                <span>🔗 <strong id="stats-url"><?php _e('Não definida', 'alvobot-pro'); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<style>
/* CTA Builder Layout */
.cta-builder-layout {
    display: grid;
    grid-template-columns: 300px 1fr 350px;
    gap: 20px;
    min-height: 70vh;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}

/* Sidebar */
.cta-builder-sidebar {
    background: #ffffff;
    border-right: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

/* Templates Gallery */
.templates-gallery {
    padding: 15px;
    flex: 1;
}

.template-item {
    background: #ffffff;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.template-item:hover {
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.1);
}

.template-item.active {
    border-color: #3498db;
    background: #f8fafc;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
}

.template-preview {
    height: 50px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 4px;
    overflow: hidden;
}

/* Mini CTA Previews */
.mini-cta {
    width: 100%;
    height: 100%;
    padding: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
}

.mini-cta.horizontal {
    flex-direction: row;
    gap: 6px;
}

.mini-cta.simple {
    flex-direction: row;
    align-items: center;
    gap: 4px;
}

.mini-title, .mini-subtitle, .mini-description {
    height: 3px;
    background: #cbd5e0;
    border-radius: 2px;
    margin: 1px 0;
}

.mini-title {
    width: 80%;
    background: #4a5568;
}

.mini-subtitle {
    width: 60%;
}

.mini-description {
    width: 90%;
}

.mini-button {
    height: 8px;
    width: 40px;
    background: #3498db;
    border-radius: 4px;
    margin-top: 2px;
}

.mini-image {
    width: 20px;
    height: 15px;
    background: #e2e8f0;
    border-radius: 2px;
    flex-shrink: 0;
}

.mini-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.mini-tag {
    height: 4px;
    width: 25px;
    background: #e53e3e;
    border-radius: 2px;
    margin-bottom: 2px;
}

.mini-link {
    height: 2px;
    width: 35px;
    background: #3182ce;
    border-radius: 1px;
}

.mini-icon {
    font-size: 12px;
    margin-right: 2px;
}

.mini-arrow {
    font-size: 8px;
    color: #3182ce;
}

.mini-pulse-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 4px;
    height: 4px;
    background: #e53e3e;
    border-radius: 50%;
    animation: pulse 1s infinite;
}

.mini-buttons {
    display: flex;
    gap: 2px;
    margin-top: 2px;
}

.mini-buttons .mini-button {
    width: 20px;
    height: 6px;
}

.mini-led-button {
    height: 8px;
    width: 35px;
    background: linear-gradient(45deg, #ff0080, #00ff80, #8000ff);
    border-radius: 4px;
    margin-top: 2px;
}

.template-info strong {
    font-size: 12px;
    color: #2d3748;
    font-weight: 600;
    display: block;
}

.template-info small {
    font-size: 10px;
    color: #718096;
    line-height: 1.2;
}

/* Shortcode Section */
.shortcode-section {
    padding: 15px;
    border-top: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.shortcode-section h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Canvas */
.cta-builder-canvas {
    background: #ffffff;
    border-left: 1px solid #e0e0e0;
    border-right: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.canvas-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.canvas-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.canvas-actions {
    display: flex;
    gap: 8px;
}

.cta-form-container {
    flex: 1;
    padding: 20px;
}

.cta-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 300px;
}

.empty-state {
    text-align: center;
    color: #718096;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #4a5568;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

/* Form Styling */
.cta-config-form {
    max-width: 100%;
}

.form-section {
    margin-bottom: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.form-section h4 {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
}

.form-group.full-width {
    flex: none;
    width: 100%;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 13px;
    color: #4a5568;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    background: #ffffff;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.form-group input[type="color"] {
    width: 60px;
    height: 40px;
    padding: 2px;
    border-radius: 6px;
}

/* Preview */
.cta-builder-preview {
    background: #ffffff;
    border-left: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.preview-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preview-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.preview-actions {
    display: flex;
    gap: 4px;
}

.preview-viewport {
    flex: 1;
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-frame {
    width: 100%;
    min-height: 300px;
    background: #ffffff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-frame.mobile-frame {
    max-width: 320px;
}

.preview-placeholder {
    text-align: center;
    color: #718096;
}

.preview-placeholder i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.preview-placeholder p {
    margin: 0;
    font-size: 14px;
}

.preview-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.cta-stats {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #4a5568;
}

.cta-stats span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn--primary {
    background: #3498db;
    color: #ffffff;
}

.btn--primary:hover {
    background: #2980b9;
}

.btn--secondary {
    background: #95a5a6;
    color: #ffffff;
}

.btn--secondary:hover {
    background: #7f8c8d;
}

.btn--ghost {
    background: transparent;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn--ghost:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.btn--ghost.is-active {
    background: #edf2f7;
    border-color: #3498db;
    color: #3498db;
}

.btn--sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Input Modern */
.input-modern {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    background: #ffffff;
    font-family: monospace;
    resize: vertical;
}

.input-modern:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

/* Responsive */
@media (max-width: 1200px) {
    .cta-builder-layout {
        grid-template-columns: 250px 1fr 300px;
    }
}

@media (max-width: 968px) {
    .cta-builder-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto auto;
    }
    
    .cta-builder-sidebar,
    .cta-builder-canvas,
    .cta-builder-preview {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .canvas-actions {
        flex-direction: column;
        gap: 5px;
    }
    
    .preview-actions {
        flex-direction: column;
        gap: 2px;
    }
}
</style>