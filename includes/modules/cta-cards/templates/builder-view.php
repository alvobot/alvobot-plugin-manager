<?php
/**
 * CTA Cards Builder View
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Preview Ao Vivo (Topo) -->
<div class="cta-preview-section">
	<div class="cta-preview-header">
		<h3><?php esc_html_e( 'Preview', 'alvobot-pro' ); ?></h3>
		<div class="cta-preview-controls">
			<div class="preview-actions">
				<button type="button" class="btn btn--ghost btn--sm" id="preview-mobile" title="<?php esc_attr_e( 'Visualizar Mobile', 'alvobot-pro' ); ?>">
					<i data-lucide="smartphone" class="alvobot-icon"></i>
				</button>
				<button type="button" class="btn btn--ghost btn--sm is-active" id="preview-desktop" title="<?php esc_attr_e( 'Visualizar Desktop', 'alvobot-pro' ); ?>">
					<i data-lucide="monitor" class="alvobot-icon"></i>
				</button>
			</div>
			<div class="cta-stats">
				<span><i data-lucide="palette" class="alvobot-icon" style="width:14px;height:14px;"></i> <strong id="stats-template"><?php esc_html_e( 'Nenhum', 'alvobot-pro' ); ?></strong></span>
				<span><i data-lucide="link" class="alvobot-icon" style="width:14px;height:14px;"></i> <strong id="stats-url"><?php esc_html_e( 'Não definida', 'alvobot-pro' ); ?></strong></span>
			</div>
		</div>
	</div>

	<div class="preview-frame desktop-frame is-active" id="cta-preview-content">
		<div class="preview-placeholder">
			<i data-lucide="eye" class="alvobot-icon"></i>
			<p><?php esc_html_e( 'Escolha um template para ver o preview', 'alvobot-pro' ); ?></p>
		</div>
	</div>
</div>

<!-- Configurações (Caixa única) -->
<div class="cta-config-section">
	<div class="cta-config-header">
		<h3><?php esc_html_e( 'Configurações do CTA', 'alvobot-pro' ); ?></h3>
		<div class="cta-config-actions">
			<button type="button" class="btn btn--ghost btn--sm" id="btn--reset">
				<i data-lucide="refresh-cw" class="alvobot-icon"></i>
				<?php esc_html_e( 'Limpar', 'alvobot-pro' ); ?>
			</button>
			<button type="button" class="btn btn--ghost btn--sm" id="btn--examples">
				<i data-lucide="eye" class="alvobot-icon"></i>
				<?php esc_html_e( 'Ver Exemplos', 'alvobot-pro' ); ?>
			</button>
		</div>
	</div>

	<div class="cta-config-body">
		<!-- Sidebar Esquerda - Templates e Shortcode -->
		<div class="cta-builder-sidebar">
			<div class="sidebar-label"><?php esc_html_e( 'Templates', 'alvobot-pro' ); ?></div>

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
						<strong><?php esc_html_e( 'Vertical', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Centralizado com imagem', 'alvobot-pro' ); ?></small>
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
						<strong><?php esc_html_e( 'Horizontal', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Imagem + conteúdo', 'alvobot-pro' ); ?></small>
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
						<strong><?php esc_html_e( 'Minimalista', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Design limpo', 'alvobot-pro' ); ?></small>
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
						<strong><?php esc_html_e( 'Banner', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Fundo destacado', 'alvobot-pro' ); ?></small>
					</div>
				</div>

				<div class="template-item" data-template="simple">
					<div class="template-preview">
						<div class="mini-cta simple">
							<div class="mini-icon"><i data-lucide="star" class="alvobot-icon"></i></div>
							<div class="mini-title"></div>
							<div class="mini-arrow">&rarr;</div>
						</div>
					</div>
					<div class="template-info">
						<strong><?php esc_html_e( 'Simples', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Ícone + link', 'alvobot-pro' ); ?></small>
					</div>
				</div>

				<div class="template-item" data-template="pulse">
					<div class="template-preview">
						<div class="mini-cta pulse">
							<div class="mini-pulse-dot"></div>
							<div class="mini-icon"><i data-lucide="play" class="alvobot-icon"></i></div>
							<div class="mini-title"></div>
							<div class="mini-button"></div>
						</div>
					</div>
					<div class="template-info">
						<strong><?php esc_html_e( 'Pulse', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Animação pulsante', 'alvobot-pro' ); ?></small>
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
						<strong><?php esc_html_e( 'Multi-Botão', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Múltiplas ações', 'alvobot-pro' ); ?></small>
					</div>
				</div>

				<div class="template-item" data-template="led-border">
					<div class="template-preview">
						<div class="mini-cta led">
							<div class="mini-icon"><i data-lucide="zap" class="alvobot-icon"></i></div>
							<div class="mini-title"></div>
							<div class="mini-led-button"></div>
						</div>
					</div>
					<div class="template-info">
						<strong><?php esc_html_e( 'LED Border', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Efeito futurista', 'alvobot-pro' ); ?></small>
					</div>
				</div>
			</div>

			<!-- Shortcode Output -->
			<div class="shortcode-section">
				<div class="sidebar-label">
					<i data-lucide="code" class="alvobot-icon"></i>
					<?php esc_html_e( 'Shortcode', 'alvobot-pro' ); ?>
				</div>
				<textarea id="generated-shortcode" class="input-modern" rows="3" readonly
							placeholder="<?php esc_attr_e( 'Configure seu CTA para gerar o shortcode...', 'alvobot-pro' ); ?>"></textarea>
				<button type="button" class="btn btn--primary btn--sm" id="btn--copy" style="margin-top: var(--alvobot-space-sm);">
					<i data-lucide="clipboard" class="alvobot-icon"></i>
					<span class="copy-text"><?php esc_html_e( 'Copiar', 'alvobot-pro' ); ?></span>
				</button>
			</div>
		</div>

		<!-- Formulário de Configuração -->
		<div class="cta-builder-canvas">
			<!-- Estado inicial -->
			<div class="cta-empty-state" id="cta-empty-state">
				<div class="empty-state">
					<i data-lucide="image" class="alvobot-icon empty-icon"></i>
					<h4><?php esc_html_e( 'Escolha um template', 'alvobot-pro' ); ?></h4>
					<p><?php esc_html_e( 'Selecione um dos templates na barra lateral para começar', 'alvobot-pro' ); ?></p>
				</div>
			</div>

			<!-- Form de configuração (será preenchido dinamicamente) -->
			<form id="cta-config-form" class="cta-config-form" style="display: none;">
				<div id="form-content"></div>
			</form>
		</div>
	</div>
</div>

<style>
/* ==========================================================================
   CTA BUILDER — Preview Section (Top)
   ========================================================================== */

.cta-preview-section {
	display: flex;
	flex-direction: column;
	background: var(--alvobot-white);
	padding: var(--alvobot-space-lg);
	border: 1px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-lg);
}

.cta-preview-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: var(--alvobot-space-lg);
}

.cta-preview-header h3 {
	margin: 0;
	font-size: var(--alvobot-font-size-xs);
	font-weight: 600;
	color: var(--alvobot-gray-400);
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.cta-preview-controls {
	display: flex;
	align-items: center;
	gap: var(--alvobot-space-xl);
}

.preview-actions {
	display: flex;
	gap: var(--alvobot-space-xs);
}

.cta-stats {
	display: flex;
	gap: var(--alvobot-space-lg);
	font-size: var(--alvobot-font-size-xs);
	color: var(--alvobot-gray-700);
}

.cta-stats span {
	display: flex;
	align-items: center;
	gap: var(--alvobot-space-xs);
}

/* Preview Frame — renders directly, no inner box */
.cta-preview-section .preview-frame {
	width: 100%;
	min-height: 120px;
	transition: max-width var(--alvobot-transition-normal);
}

.cta-preview-section .preview-frame.mobile-frame {
	max-width: 375px;
	margin: 0 auto;
}

.cta-preview-section .preview-frame.desktop-frame {
	max-width: 100%;
}

/* Reset frontend CTA styles inside builder preview */
.cta-preview-section .preview-frame .alvobot-cta-card {
	width: 100%;
	max-width: 100% !important;
	box-sizing: border-box;
	box-shadow: none;
	border-radius: 0;
	margin: 0;
	animation: none;
}

.cta-preview-section .preview-frame .alvobot-cta-card:hover {
	transform: none;
	box-shadow: none;
}

.preview-placeholder {
	text-align: center;
	color: var(--alvobot-gray-500);
	padding: var(--alvobot-space-xl) 0;
}

.preview-placeholder i {
	font-size: 48px;
	margin-bottom: var(--alvobot-space-lg);
	opacity: 0.4;
}

.preview-placeholder p {
	margin: 0;
	font-size: var(--alvobot-font-size-sm);
}

/* ==========================================================================
   CTA CONFIG — Single container for everything below preview
   ========================================================================== */

.cta-config-section {
	background: var(--alvobot-white);
	border: 1px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-lg);
}

.cta-config-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: var(--alvobot-space-lg) var(--alvobot-space-xl);
	border-bottom: 1px solid var(--alvobot-gray-300);
}

.cta-config-header h3 {
	margin: 0;
	font-size: var(--alvobot-font-size-lg);
	font-weight: 600;
	color: var(--alvobot-gray-900);
}

.cta-config-actions {
	display: flex;
	gap: var(--alvobot-space-sm);
}

.cta-config-body {
	display: grid;
	grid-template-columns: 280px 1fr;
}

/* ==========================================================================
   SIDEBAR (inside config body, no extra border)
   ========================================================================== */

.cta-builder-sidebar {
	border-right: 1px solid var(--alvobot-gray-300);
	display: flex;
	flex-direction: column;
	overflow-y: auto;
	max-height: calc(100vh - 200px);
}

.sidebar-label {
	padding: var(--alvobot-space-lg);
	font-size: var(--alvobot-font-size-xs);
	font-weight: 600;
	color: var(--alvobot-gray-400);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	display: flex;
	align-items: center;
	gap: var(--alvobot-space-xs);
}

/* Templates Gallery */
.templates-gallery {
	padding: 0 var(--alvobot-space-lg);
	flex: 1;
	overflow-y: auto;
}

.template-item {
	background: var(--alvobot-white);
	border: 2px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-md);
	padding: var(--alvobot-space-md);
	margin-bottom: var(--alvobot-space-md);
	cursor: pointer;
	transition: all var(--alvobot-transition-normal);
}

.template-item:hover {
	border-color: var(--alvobot-primary);
	background: var(--alvobot-primary-light);
	transform: translateY(-1px);
	box-shadow: var(--alvobot-shadow-sm);
}

.template-item.active {
	border-color: var(--alvobot-primary);
	background: var(--alvobot-primary-light);
}

.template-preview {
	height: 48px;
	margin-bottom: var(--alvobot-space-sm);
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--alvobot-gray-50);
	border-radius: var(--alvobot-radius-sm);
	overflow: hidden;
}

/* Mini CTA Previews */
.mini-cta {
	width: 100%;
	height: 100%;
	padding: var(--alvobot-space-sm);
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	position: relative;
}

.mini-cta.horizontal { flex-direction: row; gap: 6px; }
.mini-cta.simple { flex-direction: row; align-items: center; gap: 4px; }

.mini-title, .mini-subtitle, .mini-description {
	height: 3px;
	background: var(--alvobot-gray-400);
	border-radius: 2px;
	margin: 1px 0;
}
.mini-title { width: 80%; background: var(--alvobot-gray-700); }
.mini-subtitle { width: 60%; }
.mini-description { width: 90%; }

.mini-button {
	height: 8px;
	width: 40px;
	background: var(--alvobot-primary);
	border-radius: 4px;
	margin-top: 2px;
}

.mini-image { width: 20px; height: 15px; background: var(--alvobot-gray-300); border-radius: 2px; flex-shrink: 0; }
.mini-content { flex: 1; display: flex; flex-direction: column; gap: 1px; }
.mini-tag { height: 4px; width: 25px; background: var(--alvobot-error); border-radius: 2px; margin-bottom: 2px; }
.mini-link { height: 2px; width: 35px; background: var(--alvobot-accent); border-radius: 1px; }
.mini-icon { font-size: 12px; margin-right: 2px; }
.mini-arrow { font-size: 8px; color: var(--alvobot-accent); }

.mini-pulse-dot {
	position: absolute;
	top: 2px;
	right: 2px;
	width: 4px;
	height: 4px;
	background: var(--alvobot-error);
	border-radius: 50%;
	animation: cta-pulse 1s infinite;
}

.mini-buttons { display: flex; gap: 2px; margin-top: 2px; }
.mini-buttons .mini-button { width: 20px; height: 6px; }

.mini-led-button {
	height: 8px;
	width: 35px;
	background: linear-gradient(45deg, #ff0080, #00ff80, #8000ff);
	border-radius: 4px;
	margin-top: 2px;
}

.template-info strong {
	font-size: var(--alvobot-font-size-xs);
	color: var(--alvobot-gray-800);
	font-weight: 600;
	display: block;
}

.template-info small {
	font-size: 10px;
	color: var(--alvobot-gray-600);
	line-height: 1.2;
}

/* Shortcode Section */
.shortcode-section {
	padding: var(--alvobot-space-lg);
	border-top: 1px solid var(--alvobot-gray-300);
	background: var(--alvobot-gray-50);
}

/* ==========================================================================
   CANVAS (form area, no extra border)
   ========================================================================== */

.cta-builder-canvas {
	padding: var(--alvobot-space-xl);
	overflow: visible;
}

/* Empty State */
.cta-empty-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 300px;
}

.empty-state {
	text-align: center;
	color: var(--alvobot-gray-500);
}

.empty-icon {
	font-size: 48px;
	margin-bottom: var(--alvobot-space-lg);
	opacity: 0.4;
}

.empty-state h4 {
	margin: 0 0 var(--alvobot-space-sm) 0;
	font-size: var(--alvobot-font-size-xl);
	color: var(--alvobot-gray-700);
}

.empty-state p {
	margin: 0;
	font-size: var(--alvobot-font-size-base);
}

/* ==========================================================================
   FORM — Sections separated by borders, no nested cards
   ========================================================================== */

.cta-config-form {
	max-width: 100%;
}

.form-section {
	padding-bottom: var(--alvobot-space-xl);
	margin-bottom: var(--alvobot-space-xl);
	border-bottom: 1px solid var(--alvobot-gray-200);
}

.form-section:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}

.form-section-title {
	margin: 0 0 var(--alvobot-space-lg) 0;
	font-size: var(--alvobot-font-size-sm);
	font-weight: 600;
	color: var(--alvobot-gray-800);
}

.form-row {
	display: flex;
	gap: var(--alvobot-space-lg);
	margin-bottom: var(--alvobot-space-lg);
}

.form-row:last-child {
	margin-bottom: 0;
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
	margin-bottom: var(--alvobot-space-xs);
	font-weight: 500;
	font-size: var(--alvobot-font-size-sm);
	color: var(--alvobot-gray-700);
}

.form-group input,
.form-group select,
.form-group textarea {
	width: 100%;
	padding: var(--alvobot-space-sm) var(--alvobot-space-md);
	border: 1px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-md);
	font-size: var(--alvobot-font-size-sm);
	transition: all var(--alvobot-transition-normal);
	background: var(--alvobot-white);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
	outline: none;
	border-color: var(--alvobot-primary);
	box-shadow: 0 0 0 3px var(--alvobot-primary-light);
}

.form-group input[type="color"] {
	width: 60px;
	height: 40px;
	padding: 2px;
	border-radius: var(--alvobot-radius-md);
}

/* WordPress color picker needs space to render popup */
.form-group .wp-picker-container {
	position: relative;
}

.form-group .wp-picker-container .wp-picker-holder {
	position: absolute;
	z-index: 100;
}

.form-note {
	margin-top: calc(-1 * var(--alvobot-space-sm));
}

.form-note p {
	margin: 0;
	color: var(--alvobot-gray-500);
}

/* ==========================================================================
   BUTTONS
   ========================================================================== */

.btn {
	display: inline-flex;
	align-items: center;
	gap: var(--alvobot-space-xs);
	padding: var(--alvobot-space-sm) var(--alvobot-space-lg);
	border: none;
	border-radius: var(--alvobot-radius-md);
	font-size: var(--alvobot-font-size-sm);
	font-weight: 500;
	cursor: pointer;
	transition: all var(--alvobot-transition-normal);
	text-decoration: none;
}

.btn--primary {
	background: var(--alvobot-primary);
	color: var(--alvobot-white);
	border: 1px solid var(--alvobot-primary);
}

.btn--primary:hover {
	background: var(--alvobot-primary-dark);
	border-color: var(--alvobot-primary-dark);
}

.btn--ghost {
	background: transparent;
	color: var(--alvobot-gray-600);
	border: 1px solid var(--alvobot-gray-300);
}

.btn--ghost:hover {
	background: var(--alvobot-gray-50);
	border-color: var(--alvobot-gray-400);
}

.btn--ghost.is-active {
	background: var(--alvobot-primary-light);
	border-color: var(--alvobot-primary);
	color: var(--alvobot-primary-dark);
}

.btn--sm {
	padding: var(--alvobot-space-xs) var(--alvobot-space-sm);
	font-size: var(--alvobot-font-size-xs);
}

.btn--success {
	background: var(--alvobot-success) !important;
	border-color: var(--alvobot-success) !important;
	color: var(--alvobot-white) !important;
}

/* Input Modern (shortcode textarea) */
.input-modern {
	width: 100%;
	padding: var(--alvobot-space-sm) var(--alvobot-space-md);
	border: 1px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-md);
	font-size: var(--alvobot-font-size-sm);
	transition: all var(--alvobot-transition-normal);
	background: var(--alvobot-white);
	font-family: monospace;
	resize: vertical;
}

.input-modern:focus {
	outline: none;
	border-color: var(--alvobot-primary);
	box-shadow: 0 0 0 3px var(--alvobot-primary-light);
}

/* ==========================================================================
   ANIMATIONS
   ========================================================================== */

@keyframes cta-pulse {
	0%, 100% { transform: scale(1); opacity: 1; }
	50% { transform: scale(1.1); opacity: 0.7; }
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 968px) {
	.cta-config-body {
		grid-template-columns: 1fr;
	}

	.cta-builder-sidebar {
		border-right: none;
		border-bottom: 1px solid var(--alvobot-gray-300);
		max-height: none;
	}

	.cta-preview-header {
		flex-direction: column;
		align-items: flex-start;
		gap: var(--alvobot-space-sm);
	}

	.cta-preview-controls {
		width: 100%;
		justify-content: space-between;
	}

	.cta-config-header {
		flex-direction: column;
		align-items: flex-start;
		gap: var(--alvobot-space-sm);
	}
}

@media (max-width: 768px) {
	.form-row {
		flex-direction: column;
		gap: var(--alvobot-space-md);
	}

	.cta-stats {
		flex-direction: column;
		gap: var(--alvobot-space-xs);
	}
}
</style>
