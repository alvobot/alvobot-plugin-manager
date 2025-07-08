<?php
/**
 * Builder tab view
 *
 * @package Alvobot_Quiz
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quiz-builder-layout">
    <!-- Sidebar Esquerda - Componentes -->
    <div class="quiz-builder-sidebar">
        <div class="sidebar-header">
            <h3><?php _e('Componentes', 'alvobot-quiz'); ?></h3>
        </div>
        
        <!-- Biblioteca de Componentes -->
        <div class="component-library">
            <div class="component-item component-card" draggable="true" data-type="question">
                <div class="component-icon">❓</div>
                <div class="component-info">
                    <strong><?php _e('Nova Questão', 'alvobot-quiz'); ?></strong>
                    <small><?php _e('Pergunta com múltiplas opções', 'alvobot-quiz'); ?></small>
                </div>
                <div class="drag-indicator">
                    <i class="dashicons dashicons-move"></i>
                </div>
            </div>
        </div>
        
        <!-- Configurações Globais -->
        <div class="global-settings">
            <h4>⚙️ <?php _e('Configurações', 'alvobot-quiz'); ?></h4>
            
            <div class="setting-group">
                <label><?php _e('URL de Redirecionamento:', 'alvobot-quiz'); ?></label>
                <input type="url" id="redirect_url" class="input-modern" placeholder="/obrigado">
            </div>
            
            <div class="setting-group">
                <label><?php _e('Estilo Visual:', 'alvobot-quiz'); ?></label>
                <select id="quiz_style" class="select-modern">
                    <option value="default"><?php _e('Padrão', 'alvobot-quiz'); ?></option>
                    <option value="modern"><?php _e('Moderno', 'alvobot-quiz'); ?></option>
                    <option value="minimal"><?php _e('Minimalista', 'alvobot-quiz'); ?></option>
                </select>
            </div>
            
            <div class="setting-group">
                <label class="checkbox-modern">
                    <input type="checkbox" id="show_progress" checked>
                    <span class="checkmark"></span>
                    <?php _e('Mostrar barra de progresso', 'alvobot-quiz'); ?>
                </label>
            </div>
            
            <div class="setting-group">
                <label class="checkbox-modern">
                    <input type="checkbox" id="randomize">
                    <span class="checkmark"></span>
                    <?php _e('Ordem aleatória das questões', 'alvobot-quiz'); ?>
                </label>
            </div>
            
            <div class="setting-group">
                <label class="checkbox-modern">
                    <input type="checkbox" id="show_score" checked>
                    <span class="checkmark"></span>
                    <?php _e('Exibir pontuação final (modo quiz)', 'alvobot-quiz'); ?>
                </label>
            </div>
            
            <!-- Seção do Shortcode -->
            <div class="setting-group">
                <label>
                    <i class="dashicons dashicons-editor-code"></i>
                    <?php _e('Shortcode:', 'alvobot-quiz'); ?>
                </label>
                <textarea id="generated-shortcode" class="input-modern" rows="4" readonly 
                          placeholder="<?php _e('Crie questões para gerar o shortcode...', 'alvobot-quiz'); ?>"></textarea>
                <button type="button" class="btn btn--primary btn--sm" id="btn--copy" style="margin-top: 8px;">
                    <i class="dashicons dashicons-clipboard"></i>
                    <span class="copy-text"><?php _e('Copiar', 'alvobot-quiz'); ?></span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Canvas Central - Área de Trabalho -->
    <div class="quiz-builder-canvas">
        <div class="canvas-header">
            <h3><?php _e('Construtor de Quiz', 'alvobot-quiz'); ?></h3>
            <div class="canvas-actions">
                <button type="button" class="btn btn--secondary" id="btn--template">
                    <i class="dashicons dashicons-layout"></i>
                    <?php _e('Templates', 'alvobot-quiz'); ?>
                </button>
                <button type="button" class="btn btn--ghost" id="btn--import">
                    <i class="dashicons dashicons-upload"></i>
                    <?php _e('Importar', 'alvobot-quiz'); ?>
                </button>
            </div>
        </div>
        
        <!-- Drop Zone Principal -->
        <div class="questions-container" id="questions-container">
            <!-- Estado Vazio -->
            <div class="drop-zone-empty">
                <div class="empty-state">
                    <i class="dashicons dashicons-move empty-icon"></i>
                    <h4>Arraste componentes aqui</h4>
                    <p>Comece arrastando "Nova Questão" da barra lateral</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn--primary" id="btn--add-question">
                            <i class="dashicons dashicons-plus"></i>
                            <?php _e('Adicionar Questão', 'alvobot-quiz'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Preview Direita - Visualização Ao Vivo -->
    <div class="quiz-builder-preview">
        <div class="preview-header">
            <h3><?php _e('Preview Ao Vivo', 'alvobot-quiz'); ?></h3>
        </div>
        
        <div class="preview-viewport">
            <div class="preview-frame desktop-frame is-active" id="preview-content">
                <div class="preview-placeholder">
                    <i class="dashicons dashicons-visibility"></i>
                    <p>Crie uma questão para ver o preview aqui</p>
                </div>
            </div>
        </div>
        
        <div class="preview-footer">
            <div class="quiz-stats">
                <span>📊 <strong id="stats-questions">0</strong> <?php _e('questões', 'alvobot-quiz'); ?></span>
                <span>⏱️ <strong id="stats-time">~0min</strong> <?php _e('para completar', 'alvobot-quiz'); ?></span>
            </div>
        </div>
    </div>
</div>


<!-- Templates HTML -->
<template id="question-template">
    <div class="question-item" data-question-id="">
        <div class="question-header">
            <div class="question-number">
                <span class="message message--success">1</span>
                <div class="drag-handle">
                    <i class="dashicons dashicons-menu"></i>
                </div>
            </div>
            
            <div class="question-main">
                <input type="text" 
                       class="question-text" 
                       placeholder="<?php _e('Digite sua pergunta aqui...', 'alvobot-quiz'); ?>"
                       value="">
            </div>
            
            <div class="question-actions">
                <button type="button" class="btn btn--ghost btn--sm" title="<?php _e('Alterar visual', 'alvobot-quiz'); ?>">
                    <i class="dashicons dashicons-admin-appearance"></i>
                </button>
                <button type="button" class="btn btn--ghost btn--sm" title="<?php _e('Duplicar', 'alvobot-quiz'); ?>">
                    <i class="dashicons dashicons-admin-page"></i>
                </button>
                <button type="button" class="btn btn--ghost btn--sm btn--danger" title="<?php _e('Excluir', 'alvobot-quiz'); ?>">
                    <i class="dashicons dashicons-trash"></i>
                </button>
            </div>
        </div>
        
        <div class="question-content">
            <div class="answers-section">
                <div class="answers-header">
                    <label><?php _e('Opções de resposta:', 'alvobot-quiz'); ?></label>
                    <button type="button" class="btn btn--primary btn--sm">
                        <i class="dashicons dashicons-plus"></i>
                        <?php _e('Adicionar opção', 'alvobot-quiz'); ?>
                    </button>
                </div>
                
                <div class="answers-list"></div>
            </div>
            
            <div class="quiz-mode-toggle">
                <label>
                    <input type="checkbox" class="has-correct-answer">
                    <?php _e('Esta questão tem resposta correta (modo quiz)', 'alvobot-quiz'); ?>
                </label>
            </div>
            
            <div class="settings-advanced" style="display: none;">
                <div class="setting-row">
                    <label><?php _e('Explicação da resposta:', 'alvobot-quiz'); ?></label>
                    <textarea class="explanation" 
                              placeholder="<?php _e('Explicação opcional que aparece após responder...', 'alvobot-quiz'); ?>"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="answer-template">
    <div class="answer-item" data-answer-id="">
        <div class="answer-content">
            <input type="text" class="answer-text" placeholder="<?php _e('Digite a opção de resposta', 'alvobot-quiz'); ?>">
        </div>
        <div class="answer-controls">
            <div class="correct-indicator" style="display: none;">
                <input type="radio" class="correct-answer" name="correct-answer-">
                <span>✓</span>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" title="<?php _e('Alterar visual', 'alvobot-quiz'); ?>">
                <i class="dashicons dashicons-admin-appearance"></i>
            </button>
            <button type="button" class="btn btn--ghost btn--sm" title="<?php _e('Duplicar', 'alvobot-quiz'); ?>">
                <i class="dashicons dashicons-admin-page"></i>
            </button>
            <button type="button" class="btn btn--ghost btn--sm btn--danger" title="<?php _e('Remover', 'alvobot-quiz'); ?>">×</button>
        </div>
    </div>
</template>