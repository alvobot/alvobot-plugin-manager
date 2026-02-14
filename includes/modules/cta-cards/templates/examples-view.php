<?php
/**
 * CTA Cards Examples View
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="examples-container">
	<div class="examples-header">
		<h2><?php _e( 'Templates Disponíveis', 'alvobot-pro' ); ?></h2>
		<p><?php _e( 'Escolha entre os templates abaixo e personalize com seus próprios conteúdos:', 'alvobot-pro' ); ?></p>
	</div>

	<div class="examples-grid">
		<!-- Vertical Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '1. Template Vertical', 'alvobot-pro' ); ?></h3>
				<span class="example-badge"><?php _e( 'Popular', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Ideal para destaque no meio do conteúdo, com layout vertical centralizado.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="vertical" title="Confira nosso novo produto" subtitle="Lançamento especial com desconto" description="Uma solução completa para suas necessidades. Não perca esta oportunidade única!" button="Ver Mais" url="#"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="vertical-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="vertical-code" readonly>[cta_card template="vertical" title="Confira nosso novo produto" subtitle="Lançamento especial com desconto" description="Uma solução completa para suas necessidades. Não perca esta oportunidade única!" button="Ver Mais" url="/produto"]</textarea>
			</div>
		</div>

		<!-- Horizontal Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '2. Template Horizontal', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--secondary"><?php _e( 'Versátil', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Perfeito para artigos relacionados, com imagem à esquerda e conteúdo à direita.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="horizontal" title="Artigo Relacionado" description="Leia também este conteúdo que pode ser do seu interesse." image="https://images.unsplash.com/photo-1754220820888-e6f7e610a7a2?q=80&w=1472&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" button="Ler Artigo" url="#"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="horizontal-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="horizontal-code" readonly>[cta_card template="horizontal" title="Artigo Relacionado" description="Leia também este conteúdo que pode ser do seu interesse." image="/wp-content/uploads/sua-imagem.jpg" button="Ler Artigo" url="/artigo-relacionado"]</textarea>
			</div>
		</div>

		<!-- Minimal Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '3. Template Minimalista', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--success"><?php _e( 'Rápido', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Design limpo e simples, ideal para downloads ou links rápidos.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="minimal" title="Download Gratuito" subtitle="Guia completo em PDF" tag="PDF" button="Baixar Agora" url="#"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="minimal-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="minimal-code" readonly>[cta_card template="minimal" title="Download Gratuito" subtitle="Guia completo em PDF" tag="PDF" button="Baixar Agora" url="/download.pdf" target="_blank"]</textarea>
			</div>
		</div>

		<!-- Banner Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '4. Template Banner', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--warning"><?php _e( 'Destaque', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Banner destacado com imagem de fundo, ideal para promoções especiais.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="banner" title="Oferta Especial!" description="Aproveite nossa promoção exclusiva com até 50% de desconto." button="Aproveitar Oferta" url="#" color_button="#ff6b6b"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="banner-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="banner-code" readonly>[cta_card template="banner" title="Oferta Especial!" description="Aproveite nossa promoção exclusiva com até 50% de desconto." button="Aproveitar Oferta" url="/promocao" background="/wp-content/uploads/banner-bg.jpg" color_button="#ff6b6b"]</textarea>
			</div>
		</div>

		<!-- Simple Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '5. Template Simples', 'alvobot-pro' ); ?></h3>
			</div>
			<p class="example-description"><?php _e( 'Card simples com ícone/emoji, título e link, perfeito para listas de recursos.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="simple" title="Recursos Premium" icon="🌟" url="#"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="simple-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="simple-code" readonly>[cta_card template="simple" title="Recursos Premium" icon="🌟" url="/recursos"]</textarea>
			</div>
		</div>

		<!-- Pulse Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '6. Template Pulse Animado', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--danger"><?php _e( 'Novo', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Template com animações pulsantes e indicador "ao vivo", perfeito para transmissões ou ofertas urgentes.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="pulse" title="Transmissão Ao Vivo" subtitle="Webinar Gratuito" description="Aprenda as melhores estratégias de marketing digital direto com os especialistas!" button="Participar Agora" url="#" icon="▶️" pulse_text="AO VIVO" pulse_color="#ff6b6b"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="pulse-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="pulse-code" readonly>[cta_card template="pulse" title="Transmissão Ao Vivo" subtitle="Webinar Gratuito" description="Aprenda as melhores estratégias!" button="Participar Agora" url="/webinar" icon="▶️" pulse_text="AO VIVO" pulse_color="#ff6b6b"]</textarea>
			</div>
		</div>

		<!-- Multi-Button Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '7. Template Múltiplos Botões', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--info"><?php _e( 'Flexível', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Template com até 3 botões de ação diferentes, ideal para oferecer múltiplas opções aos usuários.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="multi-button" title="Escolha Seu Plano" subtitle="Opções flexíveis para todos os perfis" description="Encontre o plano perfeito para suas necessidades e comece hoje mesmo!" button="Plano Básico" url="#" button2="Plano Pro" url2="#" button3="Saiba Mais" url3="#" color_button="#2271b1" color_button2="#28a745" color_button3="#6c757d"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="multi-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="multi-code" readonly>[cta_card template="multi-button" title="Escolha Seu Plano" subtitle="Opções flexíveis" description="Encontre o plano perfeito!" button="Plano Básico" url="/basico" button2="Plano Pro" url2="/pro" button3="Saiba Mais" url3="/planos" color_button="#2271b1" color_button2="#28a745" color_button3="#6c757d"]</textarea>
			</div>
		</div>

		<!-- LED Border Template -->
		<div class="example-card">
			<div class="example-header">
				<h3><?php _e( '8. Template LED Border', 'alvobot-pro' ); ?></h3>
				<span class="example-badge example-badge--purple"><?php _e( 'Futurista', 'alvobot-pro' ); ?></span>
			</div>
			<p class="example-description"><?php _e( 'Template futurista com efeito LED colorido na borda do botão, perfeito para promoções especiais ou produtos tecnológicos.', 'alvobot-pro' ); ?></p>
			
			<div class="example-preview">
				<?php echo do_shortcode( '[cta_card template="led-border" title="Oferta Limitada" subtitle="Tecnologia de Ponta" description="Produto revolucionário com desconto especial por tempo limitado!" button="Comprar Agora" url="#" icon="⚡" led_colors="#ff0080,#00ff80,#8000ff,#ff8000" led_speed="2s"]' ); ?>
			</div>

			<div class="example-code">
				<div class="code-header">
					<h4><?php _e( 'Código do Shortcode:', 'alvobot-pro' ); ?></h4>
					<button class="copy-btn" data-copy="led-code"><?php _e( 'Copiar', 'alvobot-pro' ); ?></button>
				</div>
				<textarea id="led-code" readonly>[cta_card template="led-border" title="Oferta Limitada" subtitle="Tecnologia de Ponta" description="Produto revolucionário com desconto especial!" button="Comprar Agora" url="/produto" icon="⚡" led_colors="#ff0080,#00ff80,#8000ff,#ff8000" led_speed="2s"]</textarea>
			</div>
		</div>
	</div>
</div>

<style>
.examples-container {
	padding: 20px 0;
}

.examples-header {
	text-align: center;
	margin-bottom: 40px;
}

.examples-header h2 {
	margin: 0 0 10px 0;
	font-size: 28px;
	font-weight: 600;
	color: #2c3e50;
}

.examples-header p {
	margin: 0;
	font-size: 16px;
	color: #718096;
	max-width: 600px;
	margin-left: auto;
	margin-right: auto;
}

.examples-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
	gap: 30px;
	max-width: 1400px;
	margin: 0 auto;
}

.example-card {
	background: #ffffff;
	border-radius: 12px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
	overflow: hidden;
	transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.example-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.example-header {
	padding: 20px 20px 0 20px;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}

.example-header h3 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	color: #2c3e50;
	flex: 1;
}

.example-badge {
	padding: 4px 12px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	background: #3498db;
	color: #ffffff;
	margin-left: 10px;
}

.example-badge--secondary {
	background: #95a5a6;
}

.example-badge--success {
	background: #27ae60;
}

.example-badge--warning {
	background: #f39c12;
}

.example-badge--danger {
	background: #e74c3c;
}

.example-badge--info {
	background: #3498db;
}

.example-badge--purple {
	background: #9b59b6;
}

.example-description {
	padding: 0 20px;
	margin: 10px 0 20px 0;
	font-size: 14px;
	color: #718096;
	line-height: 1.5;
}

.example-preview {
	margin: 0 20px 20px 20px;
	padding: 25px;
	background: #f8f9fa;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
}

.example-preview .alvobot-cta-card {
	margin: 0 auto;
}

.example-code {
	background: #f8f9fa;
	border-top: 1px solid #e0e0e0;
	padding: 20px;
}

.code-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
}

.code-header h4 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: #4a5568;
}

.copy-btn {
	padding: 6px 12px;
	background: #3498db;
	color: #ffffff;
	border: none;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 500;
	cursor: pointer;
	transition: background 0.3s ease;
}

.copy-btn:hover {
	background: #2980b9;
}

.copy-btn.copied {
	background: #27ae60;
}

.example-code textarea {
	width: 100%;
	height: 80px;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 12px;
	padding: 12px;
	border: 1px solid #e0e0e0;
	border-radius: 6px;
	background: #ffffff;
	resize: vertical;
	color: #2d3748;
	line-height: 1.4;
}

.example-code textarea:focus {
	outline: none;
	border-color: #3498db;
	box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

@media (max-width: 768px) {
	.examples-grid {
		grid-template-columns: 1fr;
		gap: 20px;
		padding: 0 15px;
	}
	
	.example-header {
		flex-direction: column;
		gap: 10px;
		align-items: flex-start;
	}
	
	.example-badge {
		margin-left: 0;
	}
	
	.code-header {
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
	}
	
	.example-code textarea {
		height: 100px;
		font-size: 11px;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Copy button functionality
	$('.copy-btn').on('click', function() {
		var targetId = $(this).data('copy');
		var $textarea = $('#' + targetId);
		var $button = $(this);
		
		$textarea.select();
		
		try {
			var successful = document.execCommand('copy');
			if (successful) {
				$button.addClass('copied').text('<?php _e( 'Copiado!', 'alvobot-pro' ); ?>');
				setTimeout(function() {
					$button.removeClass('copied').text('<?php _e( 'Copiar', 'alvobot-pro' ); ?>');
				}, 2000);
			}
		} catch (err) {
			console.log('Fallback: Copy failed');
		}
	});
	
	// Click to select textarea content
	$('.example-code textarea').on('click', function() {
		$(this).select();
	});
});
</script>