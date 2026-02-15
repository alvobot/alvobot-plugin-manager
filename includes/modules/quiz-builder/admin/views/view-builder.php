<?php
/**
 * Builder tab view
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="quiz-builder-layout">
	<!-- Sidebar Esquerda - Componentes -->
	<div class="quiz-builder-sidebar">
		<div class="sidebar-header">
			<h3><?php esc_html_e( 'Componentes', 'alvobot-pro' ); ?></h3>
		</div>
		
		<!-- Biblioteca de Componentes -->
		<div class="component-library">
			<div class="component-item component-card" draggable="true" data-type="question">
				<div class="component-icon"><i data-lucide="help-circle" class="alvobot-icon"></i></div>
				<div class="component-info">
					<strong><?php esc_html_e( 'Nova Questão', 'alvobot-pro' ); ?></strong>
					<small><?php esc_html_e( 'Pergunta com múltiplas opções', 'alvobot-pro' ); ?></small>
				</div>
				<div class="drag-indicator">
					<i data-lucide="grip-vertical" class="alvobot-icon"></i>
				</div>
			</div>

			<div class="component-item component-card" draggable="true" data-type="lead-capture">
				<div class="component-icon"><i data-lucide="clipboard-list" class="alvobot-icon"></i></div>
				<div class="component-info">
					<strong><?php esc_html_e( 'Captura de Leads', 'alvobot-pro' ); ?></strong>
					<small><?php esc_html_e( 'Formulário de contato (Nome, Email, Tel)', 'alvobot-pro' ); ?></small>
				</div>
				<div class="drag-indicator">
					<i data-lucide="grip-vertical" class="alvobot-icon"></i>
				</div>
			</div>
		</div>
		
		<!-- Configurações Globais -->
		<div class="global-settings">
			<h4><i data-lucide="settings" class="alvobot-icon" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"></i> <?php esc_html_e( 'Configurações', 'alvobot-pro' ); ?></h4>
			
			<div class="setting-group">
				<label><?php esc_html_e( 'URL de Redirecionamento:', 'alvobot-pro' ); ?></label>
				<input type="url" id="redirect_url" class="input-modern" placeholder="/obrigado">
			</div>
			
			<div class="setting-group">
				<label><?php esc_html_e( 'Estilo Visual:', 'alvobot-pro' ); ?></label>
				<select id="quiz_style" class="select-modern">
					<option value="default"><?php esc_html_e( 'Padrão', 'alvobot-pro' ); ?></option>
					<option value="modern"><?php esc_html_e( 'Moderno', 'alvobot-pro' ); ?></option>
					<option value="minimal"><?php esc_html_e( 'Minimalista', 'alvobot-pro' ); ?></option>
				</select>
			</div>
			
			<div class="setting-group">
				<label class="checkbox-modern">
					<input type="checkbox" id="show_progress" checked>
					<span class="checkmark"></span>
					<?php esc_html_e( 'Mostrar barra de progresso', 'alvobot-pro' ); ?>
				</label>
			</div>
			
			<div class="setting-group">
				<label class="checkbox-modern">
					<input type="checkbox" id="randomize">
					<span class="checkmark"></span>
					<?php esc_html_e( 'Ordem aleatória das questões', 'alvobot-pro' ); ?>
				</label>
			</div>
			
			<div class="setting-group">
				<label class="checkbox-modern">
					<input type="checkbox" id="show_score" checked>
					<span class="checkmark"></span>
					<?php esc_html_e( 'Exibir pontuação final (modo quiz)', 'alvobot-pro' ); ?>
				</label>
			</div>
			
			<!-- Seção do Shortcode -->
			<div class="setting-group">
				<label>
					<i data-lucide="code" class="alvobot-icon"></i>
					<?php esc_html_e( 'Shortcode:', 'alvobot-pro' ); ?>
				</label>
				<textarea id="generated-shortcode" class="input-modern" rows="4" readonly 
							placeholder="<?php esc_attr_e( 'Crie questões para gerar o shortcode...', 'alvobot-pro' ); ?>"></textarea>
				<button type="button" class="btn btn--primary btn--sm" id="btn--copy" style="margin-top: 8px;">
					<i data-lucide="clipboard" class="alvobot-icon"></i>
					<span class="copy-text"><?php esc_html_e( 'Copiar', 'alvobot-pro' ); ?></span>
				</button>
			</div>
		</div>
	</div>
	
	<!-- Canvas Central - Área de Trabalho -->
	<div class="quiz-builder-canvas">
		<div class="canvas-header">
			<h3><?php esc_html_e( 'Construtor de Quiz', 'alvobot-pro' ); ?></h3>
			<div class="canvas-actions">
				<button type="button" class="btn btn--secondary" id="btn--template">
					<i data-lucide="layout" class="alvobot-icon"></i>
					<?php esc_html_e( 'Templates', 'alvobot-pro' ); ?>
				</button>
				<button type="button" class="btn btn--ghost" id="btn--import">
					<i data-lucide="upload" class="alvobot-icon"></i>
					<?php esc_html_e( 'Importar', 'alvobot-pro' ); ?>
				</button>
			</div>
		</div>
		
		<!-- Drop Zone Principal -->
		<div class="questions-container" id="questions-container">
			<!-- Estado Vazio -->
			<div class="drop-zone-empty">
				<div class="empty-state">
					<i data-lucide="grip-vertical" class="alvobot-icon empty-icon"></i>
					<h4>Arraste componentes aqui</h4>
					<p>Comece arrastando "Nova Questão" da barra lateral</p>
					<div class="empty-actions">
						<button type="button" class="btn btn--primary" id="btn--add-question">
							<i data-lucide="plus" class="alvobot-icon"></i>
							<?php esc_html_e( 'Adicionar Questão', 'alvobot-pro' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Preview Direita - Visualização Ao Vivo -->
	<div class="quiz-builder-preview">
		<div class="preview-header">
			<h3><?php esc_html_e( 'Preview Ao Vivo', 'alvobot-pro' ); ?></h3>
		</div>
		
		<div class="preview-viewport">
			<div class="preview-frame desktop-frame is-active" id="preview-content">
				<div class="preview-placeholder">
					<i data-lucide="eye" class="alvobot-icon"></i>
					<p>Crie uma questão para ver o preview aqui</p>
				</div>
			</div>
		</div>
		
		<div class="preview-footer">
			<div class="quiz-stats">
				<span><i data-lucide="bar-chart-3" class="alvobot-icon" style="width:14px;height:14px;"></i> <strong id="stats-questions">0</strong> <?php esc_html_e( 'questões', 'alvobot-pro' ); ?></span>
				<span><i data-lucide="clock" class="alvobot-icon" style="width:14px;height:14px;"></i> <strong id="stats-time">~0min</strong> <?php esc_html_e( 'para completar', 'alvobot-pro' ); ?></span>
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
					<i data-lucide="menu" class="alvobot-icon"></i>
				</div>
			</div>
			
			<div class="question-main">
				<input type="text" 
						class="question-text" 
						placeholder="<?php esc_attr_e( 'Digite sua pergunta aqui...', 'alvobot-pro' ); ?>"
						value="">
			</div>
			
			<div class="question-actions">
				<button type="button" class="btn btn--ghost btn--sm" title="<?php esc_attr_e( 'Alterar visual', 'alvobot-pro' ); ?>">
					<i data-lucide="palette" class="alvobot-icon"></i>
				</button>
				<button type="button" class="btn btn--ghost btn--sm" title="<?php esc_attr_e( 'Duplicar', 'alvobot-pro' ); ?>">
					<i data-lucide="file" class="alvobot-icon"></i>
				</button>
				<button type="button" class="btn btn--ghost btn--sm btn--danger" title="<?php esc_attr_e( 'Excluir', 'alvobot-pro' ); ?>">
					<i data-lucide="trash-2" class="alvobot-icon"></i>
				</button>
			</div>
		</div>
		
		<div class="question-content">
			<div class="answers-section">
				<div class="answers-header">
					<label><?php esc_html_e( 'Opções de resposta:', 'alvobot-pro' ); ?></label>
					<button type="button" class="btn btn--primary btn--sm">
						<i data-lucide="plus" class="alvobot-icon"></i>
						<?php esc_html_e( 'Adicionar opção', 'alvobot-pro' ); ?>
					</button>
				</div>
				
				<div class="answers-list"></div>
			</div>
			
			<div class="quiz-mode-toggle">
				<label>
					<input type="checkbox" class="has-correct-answer">
					<?php esc_html_e( 'Esta questão tem resposta correta (modo quiz)', 'alvobot-pro' ); ?>
				</label>
			</div>
			
			<div class="settings-advanced" style="display: none;">
				<div class="setting-row">
					<label><?php esc_html_e( 'Explicação da resposta:', 'alvobot-pro' ); ?></label>
					<textarea class="explanation" 
								placeholder="<?php esc_attr_e( 'Explicação opcional que aparece após responder...', 'alvobot-pro' ); ?>"></textarea>
				</div>
			</div>
		</div>
	</div>
</template>

<template id="answer-template">
	<div class="answer-item" data-answer-id="">
		<div class="answer-content">
			<input type="text" class="answer-text" placeholder="<?php esc_attr_e( 'Digite a opção de resposta', 'alvobot-pro' ); ?>">
		</div>
		<div class="answer-controls">
			<div class="correct-indicator" style="display: none;">
				<input type="radio" class="correct-answer" name="correct-answer-">
				<i data-lucide="check" class="alvobot-icon" style="width:16px;height:16px;"></i>
			</div>
			<button type="button" class="btn btn--ghost btn--sm" title="<?php esc_attr_e( 'Alterar visual', 'alvobot-pro' ); ?>">
				<i data-lucide="palette" class="alvobot-icon"></i>
			</button>
			<button type="button" class="btn btn--ghost btn--sm" title="<?php esc_attr_e( 'Duplicar', 'alvobot-pro' ); ?>">
				<i data-lucide="file" class="alvobot-icon"></i>
			</button>
			<button type="button" class="btn btn--ghost btn--sm btn--danger" title="<?php esc_attr_e( 'Remover', 'alvobot-pro' ); ?>">×</button>
		</div>
	</div>
</template>

<template id="lead-capture-template">
	<div class="question-item lead-capture-item" data-question-id="">
		<div class="question-header">
			<div class="question-number">
				<span class="message message--warning">Lead</span>
				<div class="drag-handle">
					<i data-lucide="menu" class="alvobot-icon"></i>
				</div>
			</div>
			
			<div class="question-main">
				<input type="text" 
						class="question-text" 
						placeholder="<?php esc_attr_e( 'Título do formulário (ex: Preencha seus dados)', 'alvobot-pro' ); ?>"
						value="<?php esc_attr_e( 'Preencha seus dados para continuar', 'alvobot-pro' ); ?>">
			</div>
			
			<div class="question-actions">
				<button type="button" class="btn btn--ghost btn--sm" title="<?php esc_attr_e( 'Alterar visual', 'alvobot-pro' ); ?>">
					<i data-lucide="palette" class="alvobot-icon"></i>
				</button>
				<button type="button" class="btn btn--ghost btn--sm btn--danger" title="<?php esc_attr_e( 'Excluir', 'alvobot-pro' ); ?>">
					<i data-lucide="trash-2" class="alvobot-icon"></i>
				</button>
			</div>
		</div>
		
		<div class="question-content">
			<div class="lead-capture-settings">
				<div class="setting-row">
					<label><strong><?php esc_html_e( 'Campos do formulário:', 'alvobot-pro' ); ?></strong></label>
					<div class="checkbox-group">
						<label class="checkbox-modern">
							<input type="checkbox" class="field-name" checked>
							<span class="checkmark"></span>
							<?php esc_html_e( 'Nome', 'alvobot-pro' ); ?>
						</label>
						<label class="checkbox-modern">
							<input type="checkbox" class="field-email" checked>
							<span class="checkmark"></span>
							<?php esc_html_e( 'Email', 'alvobot-pro' ); ?>
						</label>
						<label class="checkbox-modern">
							<input type="checkbox" class="field-phone">
							<span class="checkmark"></span>
							<?php esc_html_e( 'Telefone / WhatsApp', 'alvobot-pro' ); ?>
						</label>
					</div>
				</div>

				<div class="setting-row">
					<label><strong><?php esc_html_e( 'Plataforma de Integração:', 'alvobot-pro' ); ?></strong></label>
					<select class="webhook-platform select-modern" style="width: 100%;">
						<option value="generic"><?php esc_html_e( 'Webhook Genérico', 'alvobot-pro' ); ?></option>
						<option value="gohighlevel"><?php esc_html_e( 'GoHighLevel (GHL)', 'alvobot-pro' ); ?></option>
						<option value="sendpulse"><?php esc_html_e( 'SendPulse', 'alvobot-pro' ); ?></option>
					</select>
					<small><?php esc_html_e( 'Selecione a plataforma para formatação correta dos dados', 'alvobot-pro' ); ?></small>
				</div>

				<div class="setting-row">
					<label><strong><?php esc_html_e( 'URL do Webhook:', 'alvobot-pro' ); ?></strong></label>
					<input type="url" class="webhook-url input-modern" placeholder="https://services.leadconnectorhq.com/hooks/..." style="width: 100%;">
					<small class="webhook-help-text"><?php esc_html_e( 'URL do Webhook para enviar os dados (POST JSON)', 'alvobot-pro' ); ?></small>
				</div>

				<div class="setting-row">
					<label><strong><?php esc_html_e( 'URL de Redirecionamento:', 'alvobot-pro' ); ?></strong></label>
					<input type="url" class="redirect-after-submit input-modern" placeholder="/obrigado ou https://..." style="width: 100%;">
					<small><?php esc_html_e( 'Para onde redirecionar após enviar. Vazio = continua no quiz.', 'alvobot-pro' ); ?></small>
				</div>

				<div class="setting-row">
					<label><strong><?php esc_html_e( 'Botão de Envio:', 'alvobot-pro' ); ?></strong></label>
					<input type="text" class="submit-text input-modern" value="<?php esc_attr_e( 'Continuar', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'Texto do botão', 'alvobot-pro' ); ?>">
				</div>

				<hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">

				<div class="setting-row">
					<label><strong><?php esc_html_e( 'Textos dos Campos (i18n):', 'alvobot-pro' ); ?></strong></label>
					<small style="display: block; margin-bottom: 10px; color: #666;"><?php esc_html_e( 'Personalize os textos para diferentes idiomas', 'alvobot-pro' ); ?></small>
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Label do Nome:', 'alvobot-pro' ); ?></label>
					<input type="text" class="label-name input-modern" value="<?php esc_attr_e( 'Nome completo', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'Nome completo', 'alvobot-pro' ); ?>">
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Placeholder do Nome:', 'alvobot-pro' ); ?></label>
					<input type="text" class="placeholder-name input-modern" value="<?php esc_attr_e( 'Digite seu nome', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'Digite seu nome', 'alvobot-pro' ); ?>">
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Label do Email:', 'alvobot-pro' ); ?></label>
					<input type="text" class="label-email input-modern" value="<?php esc_attr_e( 'E-mail', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'E-mail', 'alvobot-pro' ); ?>">
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Placeholder do Email:', 'alvobot-pro' ); ?></label>
					<input type="text" class="placeholder-email input-modern" value="<?php esc_attr_e( 'seu@email.com', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'seu@email.com', 'alvobot-pro' ); ?>">
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Label do Telefone:', 'alvobot-pro' ); ?></label>
					<input type="text" class="label-phone input-modern" value="<?php esc_attr_e( 'Telefone / WhatsApp', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'Telefone / WhatsApp', 'alvobot-pro' ); ?>">
				</div>

				<div class="setting-row">
					<label><?php esc_html_e( 'Placeholder do Telefone:', 'alvobot-pro' ); ?></label>
					<input type="text" class="placeholder-phone input-modern" value="<?php esc_attr_e( 'Seu número', 'alvobot-pro' ); ?>" placeholder="<?php esc_attr_e( 'Seu número', 'alvobot-pro' ); ?>">
				</div>
			</div>
		</div>
	</div>
</template>