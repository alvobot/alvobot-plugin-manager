<?php
/**
 * Smart Internal Links - Admin Page Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$positions_options = array(
	'after_first' => 'Após 1° parágrafo',
	'middle'      => 'No meio do artigo',
	'before_last' => 'Antes do último parágrafo',
);

$post_types_available = get_post_types( array( 'public' => true ), 'objects' );

$languages = array();
if ( function_exists( 'PLL' ) && PLL()->model ) {
	$languages = PLL()->model->get_languages_list();
}

$categories = get_categories( array( 'hide_empty' => true ) );

$btn_border_color = ! empty( $settings['button_border_color'] ) ? $settings['button_border_color'] : ( ! empty( $settings['border_color'] ) ? $settings['border_color'] : '#D4A843' );
$btn_border_size  = isset( $settings['button_border_size'] ) ? absint( $settings['button_border_size'] ) : 2;
$btn_border_style = $btn_border_size > 0 ? $btn_border_size . 'px solid ' . esc_attr( $btn_border_color ) : 'none';
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="link" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1>Smart Internal Links</h1>
				<p>Links internos inteligentes com copy gerada por IA para aumentar o engajamento</p>
			</div>
		</div>

		<!-- Tabs -->
		<nav class="nav-tab-wrapper">
			<button class="nav-tab nav-tab-active" data-tab="sil-tab-settings">
				<i data-lucide="settings" class="alvobot-icon"></i>
				Estilos e Configurações
			</button>
			<button class="nav-tab" data-tab="sil-tab-bulk">
				<i data-lucide="zap" class="alvobot-icon"></i>
				Geração em Massa
			</button>
		</nav>

		<!-- Tab: Estilos e Configurações -->
		<div id="sil-tab-settings" class="sil-tab-content alvobot-tab-content">
			<div id="alvobot-sil-settings-message"></div>

			<!-- Preview -->
			<div class="sil-preview-section">
				<h3>Preview</h3>
				<div class="sil-preview-area">
					<div class="alvobot-sil" id="sil-preview">
						<div class="alvobot-sil__list">
							<a href="#" class="alvobot-sil__btn" onclick="return false"
								style="background:<?php echo esc_attr( $settings['button_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['button_text_color'] ); ?>;border:<?php echo esc_attr( $btn_border_style ); ?>">
								<span class="alvobot-sil__text">Exemplo de Link Interno Gerado por IA</span>
								<span class="alvobot-sil__arrow" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
								</span>
							</a>
							<a href="#" class="alvobot-sil__btn" onclick="return false"
								style="background:<?php echo esc_attr( $settings['button_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['button_text_color'] ); ?>;border:<?php echo esc_attr( $btn_border_style ); ?>">
								<span class="alvobot-sil__text">Descubra Mais Sobre Este Tema</span>
								<span class="alvobot-sil__arrow" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
								</span>
							</a>
							<a href="#" class="alvobot-sil__btn" onclick="return false"
								style="background:<?php echo esc_attr( $settings['button_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['button_text_color'] ); ?>;border:<?php echo esc_attr( $btn_border_style ); ?>">
								<span class="alvobot-sil__text">Qual a Melhor Opção Para Você?</span>
								<span class="alvobot-sil__arrow" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
								</span>
							</a>
						</div>
						<p class="alvobot-sil__disclaimer">*Você permanecerá neste site.</p>
					</div>
				</div>
			</div>

			<!-- Estilos -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">
						<i data-lucide="palette" class="alvobot-icon"></i>
						Estilos
					</h2>
				</div>
				<div class="alvobot-card-content">
					<table class="alvobot-form-table">
						<tr>
							<th><label>Cor do botão</label></th>
							<td>
								<input type="text" id="sil_button_bg_color" class="alvobot-sil-color-picker"
									value="<?php echo esc_attr( $settings['button_bg_color'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label>Cor do texto</label></th>
							<td>
								<input type="text" id="sil_button_text_color" class="alvobot-sil-color-picker"
									value="<?php echo esc_attr( $settings['button_text_color'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label>Cor da borda</label></th>
							<td>
								<input type="text" id="sil_button_border_color" class="alvobot-sil-color-picker"
									value="<?php echo esc_attr( $btn_border_color ); ?>">
							</td>
						</tr>
						<tr>
							<th><label for="sil_button_border_size">Tamanho da borda</label></th>
							<td>
								<select id="sil_button_border_size" class="alvobot-select">
									<?php for ( $i = 0; $i <= 6; $i++ ) : ?>
										<option value="<?php echo intval( $i ); ?>" <?php selected( $btn_border_size, $i ); ?>>
											<?php echo $i === 0 ? esc_html__( 'Sem borda', 'alvobot-pro' ) : intval( $i ) . 'px'; ?>
										</option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Configurações -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">
						<i data-lucide="sliders-horizontal" class="alvobot-icon"></i>
						Configurações
					</h2>
				</div>
				<div class="alvobot-card-content">
					<table class="alvobot-form-table">
						<tr>
							<th><label for="sil_links_per_block">Links por bloco</label></th>
							<td>
								<select id="sil_links_per_block" class="alvobot-select">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<option value="<?php echo intval( $i ); ?>" <?php selected( $settings['links_per_block'], $i ); ?>>
											<?php echo intval( $i ); ?>
										</option>
									<?php endfor; ?>
								</select>
								<p class="alvobot-description">Quantidade de links em cada bloco inserido no post.</p>
							</td>
						</tr>
						<tr>
							<th><label for="sil_num_blocks">Número de blocos</label></th>
							<td>
								<select id="sil_num_blocks" class="alvobot-select">
									<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
										<option value="<?php echo intval( $i ); ?>" <?php selected( $settings['num_blocks'], $i ); ?>>
											<?php echo intval( $i ); ?>
										</option>
									<?php endfor; ?>
								</select>
								<p class="alvobot-description">Quantidade de blocos inseridos no post (máx. 3).</p>
							</td>
						</tr>
						<tr>
							<th><label>Posições</label></th>
							<td>
								<?php foreach ( $positions_options as $key => $label ) : ?>
									<label class="alvobot-checkbox-label">
										<input type="checkbox" name="sil_positions[]" value="<?php echo esc_attr( $key ); ?>"
											<?php echo in_array( $key, $settings['positions'] ?? array(), true ) ? 'checked' : ''; ?>>
										<?php echo esc_html( $label ); ?>
									</label>
									<br>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th><label>Post types</label></th>
							<td>
								<?php
								foreach ( $post_types_available as $pt ) :
									if ( in_array( $pt->name, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face' ), true ) ) {
										continue;
									}
									?>
									<label class="alvobot-checkbox-label">
										<input type="checkbox" name="sil_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>"
											<?php echo in_array( $pt->name, $settings['post_types'] ?? array(), true ) ? 'checked' : ''; ?>>
										<?php echo esc_html( $pt->label ); ?>
									</label>
									<br>
								<?php endforeach; ?>
							</td>
						</tr>
					</table>
				</div>
				<div class="alvobot-card-footer" style="justify-content:flex-end;">
					<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-save-settings">
						Salvar Configurações
					</button>
				</div>
			</div>
		</div>

		<!-- Tab: Bulk Generation -->
		<div id="sil-tab-bulk" class="sil-tab-content" style="display:none;">
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<div>
						<h2 class="alvobot-card-title">Geração em Massa</h2>
						<p class="alvobot-card-subtitle">Selecione os posts e gere links internos automaticamente. Cada post consome 2 créditos.</p>
					</div>
				</div>
				<div class="alvobot-card-content">
					<table class="alvobot-form-table">
						<tr>
							<th><label for="sil_bulk_category">Categoria</label></th>
							<td>
								<select id="sil_bulk_category" class="alvobot-select">
									<option value="">Todas</option>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->term_id ); ?>">
											<?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php if ( ! empty( $languages ) ) : ?>
						<tr>
							<th><label for="sil_bulk_language">Língua</label></th>
							<td>
								<select id="sil_bulk_language" class="alvobot-select">
									<option value="">Todas</option>
									<?php foreach ( $languages as $lang ) : ?>
										<option value="<?php echo esc_attr( $lang->slug ); ?>">
											<?php echo esc_html( $lang->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><label for="sil_bulk_status">Status</label></th>
							<td>
								<select id="sil_bulk_status" class="alvobot-select">
									<option value="all">Todos</option>
									<option value="missing">Não gerado</option>
									<option value="generated">Gerado</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="sil_bulk_sort">Ordenar por</label></th>
							<td>
								<select id="sil_bulk_sort" class="alvobot-select">
									<option value="title_asc">Título (A → Z)</option>
									<option value="title_desc">Título (Z → A)</option>
									<option value="date_desc">Mais recentes</option>
									<option value="date_asc">Mais antigos</option>
								</select>
							</td>
						</tr>
					</table>

					<div class="alvobot-btn-group" style="margin-top:var(--alvobot-space-lg);">
						<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-load-posts">
							<i data-lucide="download" class="alvobot-icon"></i>
							Carregar Posts
						</button>
						<button type="button" class="alvobot-btn alvobot-btn-secondary" id="alvobot-sil-generate-missing">
							<i data-lucide="zap" class="alvobot-icon"></i>
							Gerar Faltantes
						</button>
						<button type="button" class="alvobot-btn alvobot-btn-secondary" id="alvobot-sil-generate-all">
							<i data-lucide="refresh-cw" class="alvobot-icon"></i>
							Gerar Tudo Novamente
						</button>
					</div>

					<div id="alvobot-sil-post-list" style="margin-top:var(--alvobot-space-xl);"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Load frontend CSS for preview -->
<link rel="stylesheet" href="<?php echo esc_url( ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/smart-internal-links/assets/css/smart-internal-links.css' ); ?>">

<style>
.sil-preview-section {
	display: flex;
	flex-direction: column;
	background: var(--alvobot-white);
	padding: var(--alvobot-space-lg);
	border: 1px solid var(--alvobot-gray-300);
	border-radius: var(--alvobot-radius-lg);
}
.sil-preview-section h3 {
	margin: 0 0 var(--alvobot-space-xs);
	font-size: var(--alvobot-font-size-xs);
	font-weight: 600;
	color: var(--alvobot-gray-400);
	text-transform: uppercase;
	letter-spacing: 0.05em;
}
.sil-preview-area {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	max-width: 700px;
	margin: 0 auto;
	width: 100%;
}
.sil-preview-area .alvobot-sil {
	width: 100%;
	margin: 0;
}

/* Edit Modal */
.sil-edit-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 999999;
	display: flex;
	align-items: center;
	justify-content: center;
	animation: silModalFadeIn 0.15s ease-out;
}
.sil-modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.5);
}
.sil-modal-container {
	position: relative;
	background: var(--alvobot-white, #fff);
	border-radius: var(--alvobot-radius-lg, 12px);
	box-shadow: var(--alvobot-shadow-xl, 0 20px 25px -5px rgba(0,0,0,0.1));
	width: 90vw;
	max-width: 680px;
	max-height: 85vh;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	animation: silModalSlideIn 0.2s ease-out;
}
.sil-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px 20px;
	border-bottom: 1px solid var(--alvobot-gray-200, #E5E7EB);
	background: var(--alvobot-gray-50, #F9FAFB);
}
.sil-modal-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	color: var(--alvobot-gray-900, #18181B);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.sil-modal-close-btn {
	background: none;
	border: none;
	font-size: 22px;
	cursor: pointer;
	color: var(--alvobot-gray-500, #A1A1AA);
	padding: 4px 8px;
	border-radius: 4px;
	line-height: 1;
	transition: background 0.15s, color 0.15s;
}
.sil-modal-close-btn:hover {
	background: var(--alvobot-gray-200, #E5E7EB);
	color: var(--alvobot-gray-800, #344054);
}
.sil-modal-body {
	padding: 20px;
	overflow-y: auto;
	flex: 1;
}
.sil-modal-footer {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	padding: 12px 20px;
	border-top: 1px solid var(--alvobot-gray-200, #E5E7EB);
	background: var(--alvobot-gray-50, #F9FAFB);
}

/* Edit Block */
.sil-edit-block {
	background: var(--alvobot-gray-50, #F9FAFB);
	border: 1px solid var(--alvobot-gray-200, #E5E7EB);
	border-radius: 8px;
	padding: 12px;
	margin-bottom: 12px;
}
.sil-edit-block-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 10px;
}
.sil-edit-block-title {
	font-size: 13px;
	font-weight: 600;
	color: var(--alvobot-gray-700, #52525B);
}
.sil-edit-position {
	border: 1px solid var(--alvobot-gray-300, #D4D4D8);
	border-radius: 4px;
	background: var(--alvobot-white, #fff);
	color: var(--alvobot-gray-700, #52525B);
}

/* Link Row */
.sil-edit-link-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 0;
	border-bottom: 1px solid var(--alvobot-gray-200, #E5E7EB);
}
.sil-edit-link-row:last-child {
	border-bottom: none;
}
.sil-edit-link-target {
	flex: 0 0 auto;
	max-width: 180px;
	overflow: hidden;
}
.sil-link-post-label {
	font-size: 11px;
	color: var(--alvobot-gray-500, #A1A1AA);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	display: block;
}
.sil-edit-link-text {
	flex: 1;
	min-width: 0;
}
.sil-link-text-input {
	width: 100%;
	padding: 6px 10px;
	border: 1px solid var(--alvobot-gray-300, #D4D4D8);
	border-radius: 6px;
	font-size: 13px;
	color: var(--alvobot-gray-800, #344054);
	background: var(--alvobot-white, #fff);
	transition: border-color 0.15s;
	box-sizing: border-box;
}
.sil-link-text-input:focus {
	border-color: var(--alvobot-primary, #fbbf24);
	outline: none;
	box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
}
.sil-remove-link-btn {
	flex-shrink: 0;
	background: none;
	border: none;
	cursor: pointer;
	color: var(--alvobot-gray-400, #D4D4D8);
	padding: 4px;
	border-radius: 4px;
	display: flex;
	align-items: center;
	transition: color 0.15s, background 0.15s;
}
.sil-remove-link-btn:hover {
	color: var(--alvobot-error, #F63D68);
	background: var(--alvobot-error-bg, #FEE2E2);
}

/* Add Link Button */
.sil-add-link-btn {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	margin-top: 8px;
	padding: 6px 12px;
	background: none;
	border: 1px dashed var(--alvobot-gray-300, #D4D4D8);
	border-radius: 6px;
	color: var(--alvobot-gray-600, #6B7280);
	font-size: 12px;
	cursor: pointer;
	transition: border-color 0.15s, color 0.15s;
}
.sil-add-link-btn:hover {
	border-color: var(--alvobot-primary, #fbbf24);
	color: var(--alvobot-gray-800, #344054);
}

/* Post Search */
.sil-post-search-wrap {
	position: relative;
}
.sil-post-search-results {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
	background: var(--alvobot-white, #fff);
	border: 1px solid var(--alvobot-gray-300, #D4D4D8);
	border-radius: 6px;
	box-shadow: var(--alvobot-shadow-md, 0 4px 6px -1px rgba(0,0,0,0.1));
	z-index: 10;
	max-height: 200px;
	overflow-y: auto;
	margin-top: 4px;
}
.sil-search-result {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 12px;
	cursor: pointer;
	transition: background 0.1s;
}
.sil-search-result:hover {
	background: var(--alvobot-gray-100, #F9FAFB);
}
.sil-search-result-title {
	font-size: 13px;
	color: var(--alvobot-gray-800, #344054);
	flex: 1;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	margin-right: 8px;
}
.sil-search-result-type {
	font-size: 11px;
	color: var(--alvobot-gray-400, #D4D4D8);
	flex-shrink: 0;
	background: var(--alvobot-gray-100, #F3F4F6);
	padding: 1px 6px;
	border-radius: 3px;
}

/* Animations */
@keyframes silModalFadeIn {
	from { opacity: 0; }
	to { opacity: 1; }
}
@keyframes silModalSlideIn {
	from { transform: translateY(-12px); opacity: 0; }
	to { transform: translateY(0); opacity: 1; }
}

/* Mobile */
@media (max-width: 600px) {
	.sil-modal-container {
		width: 96vw;
		max-height: 92vh;
	}
	.sil-edit-link-row {
		flex-wrap: wrap;
	}
	.sil-edit-link-target {
		max-width: 100%;
		flex: 0 0 100%;
	}
	.sil-edit-link-text {
		flex: 1;
	}
}
</style>

<script>
jQuery(function($) {
	// Tab navigation
	$('.nav-tab-wrapper .nav-tab').on('click', function(e) {
		e.preventDefault();
		var tabId = $(this).data('tab');
		$('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.sil-tab-content').hide();
		$('#' + tabId).show();
	});

	// Helper to rebuild button border style
	function updatePreviewBorder() {
		var size = parseInt($('#sil_button_border_size').val()) || 0;
		var color = $('#sil_button_border_color').val();
		var border = size > 0 ? size + 'px solid ' + color : 'none';
		$('#sil-preview .alvobot-sil__btn').css('border', border);
	}

	// Live preview update for color pickers
	if ($.fn.wpColorPicker) {
		$('.alvobot-sil-color-picker').wpColorPicker({
			change: function(event, ui) {
				var id = $(event.target).attr('id');
				var color = ui.color.toString();

				if (id === 'sil_button_bg_color') {
					$('#sil-preview .alvobot-sil__btn').css('background', color);
				} else if (id === 'sil_button_text_color') {
					$('#sil-preview .alvobot-sil__btn').css('color', color);
				} else if (id === 'sil_button_border_color') {
					updatePreviewBorder();
				}
			}
		});
	}

	// Live preview for border size select
	$('#sil_button_border_size').on('change', function() {
		updatePreviewBorder();
	});
});
</script>
