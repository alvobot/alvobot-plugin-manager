<?php
/**
 * Smart Internal Links - Admin Page Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$positions_options = array(
	'after_first'  => 'Após 1° parágrafo',
	'middle'       => 'No meio do artigo',
	'before_last'  => 'Antes do último parágrafo',
);

$post_types_available = get_post_types( array( 'public' => true ), 'objects' );

$languages = array();
if ( function_exists( 'PLL' ) && PLL()->model ) {
	$languages = PLL()->model->get_languages_list();
}

$categories = get_categories( array( 'hide_empty' => true ) );
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

		<?php AlvoBotPro_AI_API::render_credit_badge(); ?>

		<div id="alvobot-sil-settings-message"></div>

		<!-- Tabs -->
		<nav class="nav-tab-wrapper">
			<button class="nav-tab nav-tab-active" data-tab="sil-tab-settings">
				<i data-lucide="settings" class="alvobot-icon"></i>
				Configurações
			</button>
			<button class="nav-tab" data-tab="sil-tab-appearance">
				<i data-lucide="palette" class="alvobot-icon"></i>
				Aparência
			</button>
			<button class="nav-tab" data-tab="sil-tab-bulk">
				<i data-lucide="zap" class="alvobot-icon"></i>
				Geração em Massa
			</button>
		</nav>

		<!-- Tab: Settings -->
		<div id="sil-tab-settings" class="sil-tab-content">
			<div class="alvobot-card">
				<div class="alvobot-card-content">
					<table class="alvobot-form-table">
						<tr>
							<th><label for="sil_links_per_block">Links por bloco</label></th>
							<td>
								<select id="sil_links_per_block" class="alvobot-select">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<option value="<?php echo $i; ?>" <?php selected( $settings['links_per_block'], $i ); ?>>
											<?php echo $i; ?>
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
										<option value="<?php echo $i; ?>" <?php selected( $settings['num_blocks'], $i ); ?>>
											<?php echo $i; ?>
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
								<?php foreach ( $post_types_available as $pt ) :
									if ( in_array( $pt->name, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face' ), true ) ) continue;
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

		<!-- Tab: Appearance -->
		<?php
		$btn_border_color = ! empty( $settings['button_border_color'] ) ? $settings['button_border_color'] : ( ! empty( $settings['border_color'] ) ? $settings['border_color'] : '#D4A843' );
		$btn_border_size  = isset( $settings['button_border_size'] ) ? absint( $settings['button_border_size'] ) : 2;
		$btn_border_style = $btn_border_size > 0 ? $btn_border_size . 'px solid ' . esc_attr( $btn_border_color ) : 'none';
		?>
		<div id="sil-tab-appearance" class="sil-tab-content" style="display:none;">
			<div class="alvobot-card">
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
										<option value="<?php echo $i; ?>" <?php selected( $btn_border_size, $i ); ?>>
											<?php echo $i === 0 ? 'Sem borda' : $i . 'px'; ?>
										</option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Preview -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Preview</h2>
				</div>
				<div class="alvobot-card-content">
					<div style="max-width:700px;">
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
				<div class="alvobot-card-footer" style="justify-content:flex-end;">
					<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-save-appearance">
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
											<?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)
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
					</table>

					<div class="alvobot-btn-group" style="margin-top:var(--alvobot-space-lg);">
						<button type="button" class="alvobot-btn alvobot-btn-primary" id="alvobot-sil-load-posts">
							<i data-lucide="download" class="alvobot-icon"></i>
							Carregar Posts
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

	// Save appearance tab also triggers the same save handler
	$('#alvobot-sil-save-appearance').on('click', function() {
		$('#alvobot-sil-save-settings').trigger('click');
	});
});
</script>
