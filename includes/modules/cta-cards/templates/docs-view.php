<?php
/**
 * CTA Cards Documentation View
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="docs-container">
	<div class="docs-sidebar">
		<nav class="docs-nav">
			<ul>
				<li><a href="#getting-started" class="nav-link active"><?php _e( 'Primeiros Passos', 'alvobot-pro' ); ?></a></li>
				<li><a href="#basic-usage" class="nav-link"><?php _e( 'Uso Básico', 'alvobot-pro' ); ?></a></li>
				<li><a href="#templates" class="nav-link"><?php _e( 'Templates', 'alvobot-pro' ); ?></a></li>
				<li><a href="#parameters" class="nav-link"><?php _e( 'Parâmetros', 'alvobot-pro' ); ?></a></li>
				<li><a href="#advanced" class="nav-link"><?php _e( 'Uso Avançado', 'alvobot-pro' ); ?></a></li>
				<li><a href="#tips" class="nav-link"><?php _e( 'Dicas e Truques', 'alvobot-pro' ); ?></a></li>
				<li><a href="#troubleshooting" class="nav-link"><?php _e( 'Solução de Problemas', 'alvobot-pro' ); ?></a></li>
			</ul>
		</nav>
	</div>

	<div class="docs-content">
		<!-- Getting Started -->
		<section id="getting-started" class="docs-section active">
			<div class="docs-header">
				<h2><?php _e( 'Primeiros Passos', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Aprenda a usar o CTA Cards para criar chamadas para ação atrativas em seus posts e páginas.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'O que são CTA Cards?', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'CTA Cards são elementos visuais interativos que você pode inserir em qualquer post ou página do WordPress usando shortcodes. Eles foram projetados para aumentar o engajamento e direcionar seus visitantes para ações específicas.', 'alvobot-pro' ); ?></p>
				
				<div class="feature-list">
					<div class="feature-item">
						<span class="feature-icon"><i data-lucide="palette" class="alvobot-icon"></i></span>
						<div>
							<strong><?php _e( '8 Templates Únicos', 'alvobot-pro' ); ?></strong>
							<p><?php _e( 'Escolha entre diferentes estilos visuais para cada situação', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="feature-item">
						<span class="feature-icon"><i data-lucide="paintbrush" class="alvobot-icon"></i></span>
						<div>
							<strong><?php _e( 'Totalmente Personalizável', 'alvobot-pro' ); ?></strong>
							<p><?php _e( 'Cores, textos, imagens e ícones podem ser ajustados', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="feature-item">
						<span class="feature-icon"><i data-lucide="smartphone" class="alvobot-icon"></i></span>
						<div>
							<strong><?php _e( 'Design Responsivo', 'alvobot-pro' ); ?></strong>
							<p><?php _e( 'Funciona perfeitamente em desktop, tablet e mobile', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="feature-item">
						<span class="feature-icon"><i data-lucide="zap" class="alvobot-icon"></i></span>
						<div>
							<strong><?php _e( 'Efeitos Avançados', 'alvobot-pro' ); ?></strong>
							<p><?php _e( 'Animações, pulsos e efeitos LED para chamar atenção', 'alvobot-pro' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Como Começar', 'alvobot-pro' ); ?></h3>
				<div class="step-list">
					<div class="step-item">
						<div class="step-number">1</div>
						<div class="step-content">
							<h4><?php _e( 'Use o Gerador', 'alvobot-pro' ); ?></h4>
							<p><?php _e( 'Acesse a aba "Gerador" para criar seu primeiro CTA de forma visual e interativa.', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="step-item">
						<div class="step-number">2</div>
						<div class="step-content">
							<h4><?php _e( 'Escolha um Template', 'alvobot-pro' ); ?></h4>
							<p><?php _e( 'Selecione o template que melhor se adequa ao seu objetivo na barra lateral.', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="step-item">
						<div class="step-number">3</div>
						<div class="step-content">
							<h4><?php _e( 'Configure e Personalize', 'alvobot-pro' ); ?></h4>
							<p><?php _e( 'Preencha os campos no centro e veja o resultado em tempo real no preview.', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="step-item">
						<div class="step-number">4</div>
						<div class="step-content">
							<h4><?php _e( 'Copie e Cole', 'alvobot-pro' ); ?></h4>
							<p><?php _e( 'Copie o shortcode gerado e cole em qualquer post ou página.', 'alvobot-pro' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Basic Usage -->
		<section id="basic-usage" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Uso Básico', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Aprenda a sintaxe básica dos shortcodes CTA Cards.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Sintaxe Básica', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'Para usar um CTA card, adicione o shortcode em qualquer post ou página:', 'alvobot-pro' ); ?></p>
				
				<div class="code-example">
					<pre><code>[cta_card template="vertical" title="Meu Título" button="Clique Aqui" url="/minha-pagina"]</code></pre>
				</div>

				<div class="alert alert-info">
					<strong><?php _e( 'Dica:', 'alvobot-pro' ); ?></strong>
					<?php _e( 'Os únicos parâmetros obrigatórios são template, title e url. Todos os outros são opcionais.', 'alvobot-pro' ); ?>
				</div>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Exemplo Prático', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'Vamos criar um CTA para promover um e-book gratuito:', 'alvobot-pro' ); ?></p>
				
				<div class="code-example">
					<pre><code>[cta_card 
	template="vertical" 
	title="E-book Gratuito: Marketing Digital 2024"
	subtitle="Guia Completo para Iniciantes"
	description="Aprenda as estratégias mais eficazes para divulgar seu negócio online e aumentar suas vendas."
	button="Baixar Grátis"
	url="/download-ebook"
	target="_blank"
	color_button="#27AE60"
	color_primary="#2ECC71"]</code></pre>
				</div>

				<div class="result-preview">
					<h4><?php _e( 'Resultado:', 'alvobot-pro' ); ?></h4>
					<?php echo do_shortcode( '[cta_card template="vertical" title="E-book Gratuito: Marketing Digital 2024" subtitle="Guia Completo para Iniciantes" description="Aprenda as estratégias mais eficazes para divulgar seu negócio online e aumentar suas vendas." button="Baixar Grátis" url="#" color_button="#27AE60" color_primary="#2ECC71"]' ); ?>
				</div>
			</div>
		</section>

		<!-- Templates -->
		<section id="templates" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Templates Disponíveis', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Conheça todos os templates e quando usar cada um.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="template-grid">
				<div class="template-doc-card">
					<h3>vertical</h3>
					<p><?php _e( 'Layout centralizado ideal para destaque no meio do conteúdo.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Promoções principais', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Downloads de lead magnets', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Inscrições em newsletters', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>horizontal</h3>
					<p><?php _e( 'Imagem à esquerda e conteúdo à direita.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Artigos relacionados', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Produtos com imagem', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Cursos e treinamentos', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>minimal</h3>
					<p><?php _e( 'Design limpo e discreto com tag opcional.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Downloads rápidos', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Links para ferramentas', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'CTAs secundários', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>banner</h3>
					<p><?php _e( 'Banner grande com imagem de fundo opcional.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Ofertas especiais', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Eventos e webinars', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Lançamentos importantes', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>simple</h3>
					<p><?php _e( 'Card simples com ícone/emoji e link.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Links em listas', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Recursos rápidos', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Navegação interna', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>pulse</h3>
					<p><?php _e( 'Template animado com indicador "ao vivo".', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Transmissões ao vivo', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Ofertas por tempo limitado', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Eventos urgentes', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>multi-button</h3>
					<p><?php _e( 'Template com até 3 botões diferentes.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Comparação de planos', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Múltiplas opções', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Call-to-actions complexos', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="template-doc-card">
					<h3>led-border</h3>
					<p><?php _e( 'Template futurista com efeito LED colorido.', 'alvobot-pro' ); ?></p>
					<div class="template-usage">
						<strong><?php _e( 'Melhor para:', 'alvobot-pro' ); ?></strong>
						<ul>
							<li><?php _e( 'Produtos tecnológicos', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Ofertas especiais', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'CTAs que precisam se destacar', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</section>

		<!-- Parameters -->
		<section id="parameters" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Referência de Parâmetros', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Lista completa de todos os parâmetros disponíveis.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="params-table-container">
				<table class="params-table">
					<thead>
						<tr>
							<th><?php _e( 'Parâmetro', 'alvobot-pro' ); ?></th>
							<th><?php _e( 'Tipo', 'alvobot-pro' ); ?></th>
							<th><?php _e( 'Padrão', 'alvobot-pro' ); ?></th>
							<th><?php _e( 'Descrição', 'alvobot-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>template</code></td>
							<td><span class="param-type">string</span></td>
							<td>vertical</td>
							<td><?php _e( 'Modelo do card (vertical, horizontal, minimal, banner, simple, pulse, multi-button, led-border)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>title</code></td>
							<td><span class="param-type">string</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Título principal do CTA', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>subtitle</code></td>
							<td><span class="param-type">string</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Subtítulo opcional', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>description</code></td>
							<td><span class="param-type">html</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Texto descritivo (HTML permitido)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>button</code></td>
							<td><span class="param-type">string</span></td>
							<td><?php _e( 'Saiba Mais', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Texto do botão principal', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>url</code></td>
							<td><span class="param-type">url</span></td>
							<td>#</td>
							<td><?php _e( 'URL de destino do CTA', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>target</code></td>
							<td><span class="param-type">string</span></td>
							<td>_self</td>
							<td><?php _e( 'Como abrir o link (_self, _blank)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>image</code></td>
							<td><span class="param-type">url</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'URL da imagem (templates horizontal, multi-button, led-border)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>icon</code></td>
							<td><span class="param-type">string</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Ícone Dashicons ou emoji (ex: dashicons-star-filled ou 🌟)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>tag</code></td>
							<td><span class="param-type">string</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Badge/tag (template minimal)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>background</code></td>
							<td><span class="param-type">url</span></td>
							<td><?php _e( 'vazio', 'alvobot-pro' ); ?></td>
							<td><?php _e( 'Imagem de fundo (template banner)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>color_primary</code></td>
							<td><span class="param-type">color</span></td>
							<td>#2271b1</td>
							<td><?php _e( 'Cor principal (hex)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>color_button</code></td>
							<td><span class="param-type">color</span></td>
							<td>#2271b1</td>
							<td><?php _e( 'Cor do botão (hex)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>color_text</code></td>
							<td><span class="param-type">color</span></td>
							<td>#333333</td>
							<td><?php _e( 'Cor do texto (hex)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>color_bg</code></td>
							<td><span class="param-type">color</span></td>
							<td>#ffffff</td>
							<td><?php _e( 'Cor de fundo (hex)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>align</code></td>
							<td><span class="param-type">string</span></td>
							<td>center</td>
							<td><?php _e( 'Alinhamento (left, center, right)', 'alvobot-pro' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Parâmetros Específicos por Template', 'alvobot-pro' ); ?></h3>
				
				<div class="template-params">
					<h4>Template Pulse</h4>
					<ul>
						<li><code>pulse_text</code> - <?php _e( 'Texto do indicador (padrão: "AO VIVO")', 'alvobot-pro' ); ?></li>
						<li><code>pulse_color</code> - <?php _e( 'Cor do pulse (padrão: "#ff6b6b")', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="template-params">
					<h4>Template Multi-Button</h4>
					<ul>
						<li><code>button2</code>, <code>url2</code>, <code>target2</code>, <code>color_button2</code> - <?php _e( 'Segundo botão', 'alvobot-pro' ); ?></li>
						<li><code>button3</code>, <code>url3</code>, <code>target3</code>, <code>color_button3</code> - <?php _e( 'Terceiro botão', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="template-params">
					<h4>Template LED Border</h4>
					<ul>
						<li><code>led_colors</code> - <?php _e( 'Cores do LED separadas por vírgula (padrão: "#ff0080,#00ff80,#8000ff,#ff8000")', 'alvobot-pro' ); ?></li>
						<li><code>led_speed</code> - <?php _e( 'Velocidade da animação (padrão: "2s")', 'alvobot-pro' ); ?></li>
					</ul>
				</div>
			</div>
		</section>

		<!-- Advanced -->
		<section id="advanced" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Uso Avançado', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Técnicas avançadas e exemplos complexos.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Ícones e Emojis', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'Você pode usar tanto ícones do WordPress (Dashicons) quanto emojis no parâmetro icon:', 'alvobot-pro' ); ?></p>
				
				<div class="icon-examples">
					<h4><?php _e( 'Exemplos com Dashicons:', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>icon="dashicons-star-filled"
icon="dashicons-download"
icon="dashicons-video-alt3"
icon="dashicons-cart"</code></pre>
					</div>

					<h4><?php _e( 'Exemplos com Emojis:', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>icon="🌟"
icon="📁" 
icon="🎥"
icon="🛒"</code></pre>
					</div>
				</div>

				<div class="alert alert-info">
					<strong><?php _e( 'Dica:', 'alvobot-pro' ); ?></strong>
					<?php _e( 'O sistema detecta automaticamente se você está usando um emoji ou um ícone Dashicons e aplica o estilo correto.', 'alvobot-pro' ); ?>
				</div>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Múltiplos Botões', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'O template multi-button permite até 3 botões com estilos diferentes:', 'alvobot-pro' ); ?></p>
				
				<div class="code-example">
					<pre><code>[cta_card 
	template="multi-button"
	title="Escolha Seu Plano"
	description="Encontre a opção perfeita para você"
	button="Básico - R$ 29"
	url="/plano-basico"
	color_button="#3498db"
	button2="Pro - R$ 59" 
	url2="/plano-pro"
	color_button2="#27ae60"
	button3="Enterprise"
	url3="/contato"
	color_button3="#95a5a6"]</code></pre>
				</div>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Efeito LED Personalizado', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'Personalize as cores e velocidade do efeito LED:', 'alvobot-pro' ); ?></p>
				
				<div class="code-example">
					<pre><code>[cta_card 
	template="led-border"
	title="Oferta Limitada"
	button="Comprar Agora"
	url="/comprar"
	led_colors="#ff0000,#00ff00,#0000ff,#ffff00,#ff00ff"
	led_speed="1s"
	icon="⚡"]</code></pre>
				</div>

				<p><?php _e( 'Velocidades disponíveis: 1s (rápida), 2s (normal), 3s (lenta), 4s (muito lenta)', 'alvobot-pro' ); ?></p>
			</div>

			<div class="docs-card">
				<h3><?php _e( 'Shortcodes Completos de Todos os Templates', 'alvobot-pro' ); ?></h3>
				<p><?php _e( 'Copie e cole estes shortcodes completos diretamente ou use como base para personalização:', 'alvobot-pro' ); ?></p>
				
				<div class="template-shortcodes">
					<h4><?php _e( '1. Template Vertical', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="vertical" 
	title="Confira nosso novo produto" 
	subtitle="Lançamento especial com desconto" 
	description="Uma solução completa para suas necessidades. Não perca esta oportunidade única!" 
	image="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?q=80&w=400&auto=format&fit=crop"
	button="Ver Mais" 
	url="/produto" 
	target="_self" 
	color_primary="#2271b1" 
	color_button="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff" 
	align="center"]</code></pre>
					</div>

					<h4><?php _e( '2. Template Horizontal', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="horizontal" 
	title="Artigo Relacionado" 
	description="Leia também este conteúdo que pode ser do seu interesse." 
	image="https://images.unsplash.com/photo-1754220820888-e6f7e610a7a2?q=80&w=400&auto=format&fit=crop" 
	button="Ler Artigo" 
	url="/artigo-relacionado" 
	target="_self" 
	color_button="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff"]</code></pre>
					</div>

					<h4><?php _e( '3. Template Minimalista', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="minimal" 
	title="Download Gratuito" 
	subtitle="Guia completo em PDF" 
	tag="PDF" 
	button="Baixar Agora" 
	url="/download.pdf" 
	target="_blank" 
	color_primary="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff"]</code></pre>
					</div>

					<h4><?php _e( '4. Template Banner', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="banner" 
	title="Oferta Especial!" 
	description="Aproveite nossa promoção exclusiva com até 50% de desconto." 
	button="Aproveitar Oferta" 
	url="/promocao" 
	target="_self" 
	background="/wp-content/uploads/banner-bg.jpg" 
	color_button="#ff6b6b" 
	align="center"]</code></pre>
					</div>

					<h4><?php _e( '5. Template Simples', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="simple" 
	title="Recursos Premium" 
	icon="🌟" 
	url="/recursos" 
	target="_self" 
	color_primary="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff"]</code></pre>
					</div>

					<h4><?php _e( '6. Template Pulse Animado', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="pulse" 
	title="Transmissão Ao Vivo" 
	subtitle="Webinar Gratuito" 
	description="Aprenda as melhores estratégias de marketing digital direto com os especialistas!" 
	button="Participar Agora" 
	url="/webinar" 
	target="_self" 
	icon="▶️" 
	pulse_text="AO VIVO" 
	pulse_color="#ff6b6b" 
	color_button="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff" 
	align="center"]</code></pre>
					</div>

					<h4><?php _e( '7. Template Múltiplos Botões', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="multi-button" 
	title="Escolha Seu Plano" 
	subtitle="Opções flexíveis para todos os perfis" 
	description="Encontre o plano perfeito para suas necessidades e comece hoje mesmo!" 
	image="https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=400&auto=format&fit=crop"
	button="Plano Básico" 
	url="/basico" 
	target="_self" 
	color_button="#2271b1" 
	button2="Plano Pro" 
	url2="/pro" 
	target2="_self" 
	color_button2="#28a745" 
	button3="Saiba Mais" 
	url3="/planos" 
	target3="_self" 
	color_button3="#6c757d" 
	color_text="#333333" 
	color_bg="#ffffff" 
	align="center"]</code></pre>
					</div>

					<h4><?php _e( '8. Template LED Border', 'alvobot-pro' ); ?></h4>
					<div class="code-example">
						<pre><code>[cta_card 
	template="led-border" 
	title="Oferta Limitada" 
	subtitle="Tecnologia de Ponta" 
	description="Produto revolucionário com desconto especial por tempo limitado!" 
	image="https://images.unsplash.com/photo-1518709268805-4e9042af2176?q=80&w=400&auto=format&fit=crop"
	button="Comprar Agora" 
	url="/produto" 
	target="_self" 
	icon="⚡" 
	led_colors="#ff0080,#00ff80,#8000ff,#ff8000" 
	led_speed="2s" 
	color_button="#2271b1" 
	color_text="#333333" 
	color_bg="#ffffff" 
	align="center"]</code></pre>
					</div>
				</div>

				<div class="alert alert-info">
					<i data-lucide="lightbulb" class="alvobot-icon" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"></i><strong><?php _e( 'Dica para IAs:', 'alvobot-pro' ); ?></strong>
					<?php _e( 'Estes shortcodes podem ser copiados e fornecidos para IAs como ChatGPT, Claude ou outras para personalização automática. Basta especificar o que você quer modificar (cores, textos, URLs, etc.) e a IA poderá ajustar os parâmetros apropriados.', 'alvobot-pro' ); ?>
				</div>
			</div>
		</section>

		<!-- Tips -->
		<section id="tips" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Dicas e Truques', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Maximize o impacto dos seus CTAs com essas dicas práticas.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="tips-grid">
				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="palette" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Escolha de Cores', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Use cores que contrastem com seu tema', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Vermelho/laranja para urgência', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Verde para ações positivas', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Azul para confiança', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="pencil" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Textos Eficazes', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Use verbos de ação no botão', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Mantenha títulos concisos', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Crie senso de urgência quando apropriado', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Destaque benefícios, não características', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="smartphone" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Design Responsivo', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Teste em dispositivos móveis', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Use imagens otimizadas', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Evite textos muito longos em mobile', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Considere o template horizontal para mobile', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="trending-up" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Conversão', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Posicione CTAs em pontos estratégicos', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Use apenas um CTA principal por página', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Teste diferentes templates', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Monitore métricas de clique', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="gauge" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Performance', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Use imagens comprimidas (WebP quando possível)', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Evite muitos CTAs animados na mesma página', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Teste a velocidade de carregamento', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Use CDN para imagens externas', 'alvobot-pro' ); ?></li>
					</ul>
				</div>

				<div class="tip-card">
					<div class="tip-icon"><i data-lucide="target" class="alvobot-icon"></i></div>
					<h3><?php _e( 'Estratégia', 'alvobot-pro' ); ?></h3>
					<ul>
						<li><?php _e( 'Alinhe CTA com o objetivo da página', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Use templates diferentes para públicos diferentes', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Crie sequências de CTAs (funil)', 'alvobot-pro' ); ?></li>
						<li><?php _e( 'Personalize por contexto do conteúdo', 'alvobot-pro' ); ?></li>
					</ul>
				</div>
			</div>
		</section>

		<!-- Troubleshooting -->
		<section id="troubleshooting" class="docs-section">
			<div class="docs-header">
				<h2><?php _e( 'Solução de Problemas', 'alvobot-pro' ); ?></h2>
				<p><?php _e( 'Resolva os problemas mais comuns rapidamente.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="faq-list">
				<div class="faq-item">
					<h3 class="faq-question"><?php _e( 'O CTA não aparece na página', 'alvobot-pro' ); ?></h3>
					<div class="faq-answer">
						<p><?php _e( 'Verifique se:', 'alvobot-pro' ); ?></p>
						<ul>
							<li><?php _e( 'O shortcode está escrito corretamente', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'O módulo CTA Cards está ativo no AlvoBot Pro', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Não há conflitos com outros plugins', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'O cache da página foi limpo', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="faq-item">
					<div class="faq-question"><?php _e( 'O design está quebrado ou diferente', 'alvobot-pro' ); ?></div>
					<div class="faq-answer">
						<p><?php _e( 'Possíveis causas:', 'alvobot-pro' ); ?></p>
						<ul>
							<li><?php _e( 'Conflito com CSS do tema - adicione !important nas cores', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Plugin de cache não atualizado', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'JavaScript do tema interferindo', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="faq-item">
					<div class="faq-question"><?php _e( 'Ícones não aparecem', 'alvobot-pro' ); ?></div>
					<div class="faq-answer">
						<p><?php _e( 'Soluções:', 'alvobot-pro' ); ?></p>
						<ul>
							<li><?php _e( 'Verifique se está usando o nome correto do Dashicon', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Teste com um emoji simples como "🌟"', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Confirme se o tema suporta Dashicons', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="faq-item">
					<div class="faq-question"><?php _e( 'Animações não funcionam', 'alvobot-pro' ); ?></div>
					<div class="faq-answer">
						<p><?php _e( 'Verifique:', 'alvobot-pro' ); ?></p>
						<ul>
							<li><?php _e( 'Se o usuário não tem preferência de movimento reduzido ativa', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Se não há CSS conflitante bloqueando animações', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Se o navegador suporta as animações CSS utilizadas', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="faq-item">
					<div class="faq-question"><?php _e( 'Template não responsivo no mobile', 'alvobot-pro' ); ?></div>
					<div class="faq-answer">
						<p><?php _e( 'Soluções:', 'alvobot-pro' ); ?></p>
						<ul>
							<li><?php _e( 'Limpe o cache e teste novamente', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Verifique se o tema tem viewport meta tag', 'alvobot-pro' ); ?></li>
							<li><?php _e( 'Teste com o template "vertical" que é mais mobile-friendly', 'alvobot-pro' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>

<style>
.docs-container {
	display: grid;
	grid-template-columns: 250px 1fr;
	gap: 30px;
	max-width: 1200px;
	margin: 0 auto;
}

.docs-sidebar {
	position: sticky;
	top: 32px;
	height: fit-content;
	background: #ffffff;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.docs-nav ul {
	list-style: none;
	margin: 0;
	padding: 0;
}

.docs-nav li {
	margin-bottom: 8px;
}

.docs-nav .nav-link {
	display: block;
	padding: 10px 15px;
	color: #4a5568;
	text-decoration: none;
	border-radius: 6px;
	font-size: 14px;
	font-weight: 500;
	transition: all 0.3s ease;
}

.docs-nav .nav-link:hover {
	background: #f7fafc;
	color: #3498db;
}

.docs-nav .nav-link.active {
	background: #3498db;
	color: #ffffff;
}

.docs-content {
	background: #ffffff;
	border-radius: 8px;
	padding: 40px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.docs-section {
	display: none;
}

.docs-section.active {
	display: block;
}

.docs-header {
	margin-bottom: 40px;
	text-align: center;
}

.docs-header h2 {
	margin: 0 0 15px 0;
	font-size: 32px;
	font-weight: 700;
	color: #2c3e50;
}

.docs-header p {
	margin: 0;
	font-size: 16px;
	color: #718096;
	max-width: 600px;
	margin-left: auto;
	margin-right: auto;
}

.docs-card {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 30px;
	margin-bottom: 30px;
	border: 1px solid #e0e0e0;
}

.docs-card h3 {
	margin: 0 0 20px 0;
	font-size: 24px;
	font-weight: 600;
	color: #2c3e50;
}

.docs-card p {
	margin: 0 0 20px 0;
	font-size: 16px;
	line-height: 1.6;
	color: #4a5568;
}

.feature-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 30px;
}

.feature-item {
	display: flex;
	align-items: flex-start;
	gap: 15px;
}

.feature-icon {
	font-size: 24px;
	flex-shrink: 0;
}

.feature-icon .alvobot-icon {
	width: 24px;
	height: 24px;
}

.feature-item strong {
	display: block;
	font-size: 16px;
	color: #2c3e50;
	margin-bottom: 5px;
}

.feature-item p {
	margin: 0;
	font-size: 14px;
	color: #718096;
}

.step-list {
	display: grid;
	gap: 25px;
	margin-top: 30px;
}

.step-item {
	display: flex;
	align-items: flex-start;
	gap: 20px;
}

.step-number {
	width: 40px;
	height: 40px;
	background: #3498db;
	color: #ffffff;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 700;
	font-size: 18px;
	flex-shrink: 0;
}

.step-content h4 {
	margin: 0 0 8px 0;
	font-size: 18px;
	font-weight: 600;
	color: #2c3e50;
}

.step-content p {
	margin: 0;
	font-size: 14px;
	color: #718096;
}

.code-example {
	background: #2d3748;
	color: #ffffff;
	padding: 20px;
	border-radius: 8px;
	margin: 20px 0;
	overflow-x: auto;
}

.code-example pre {
	margin: 0;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 14px;
	line-height: 1.5;
}

.code-example code {
	color: #e2e8f0;
}

.result-preview {
	margin-top: 30px;
	padding: 25px;
	background: #ffffff;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
}

.result-preview h4 {
	margin: 0 0 20px 0;
	font-size: 16px;
	font-weight: 600;
	color: #4a5568;
}

.alert {
	padding: 15px 20px;
	border-radius: 8px;
	margin: 20px 0;
	border-left: 4px solid;
}

.alert-info {
	background: #e6f3ff;
	border-color: #3498db;
	color: #2c5282;
}

.template-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 25px;
	margin-top: 30px;
}

.template-doc-card {
	background: #ffffff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 25px;
}

.template-doc-card h3 {
	margin: 0 0 15px 0;
	font-size: 20px;
	font-weight: 600;
	color: #3498db;
	font-family: monospace;
	background: #f7fafc;
	padding: 8px 12px;
	border-radius: 4px;
	display: inline-block;
}

.template-doc-card p {
	margin: 0 0 20px 0;
	font-size: 14px;
	color: #718096;
}

.template-usage strong {
	font-size: 14px;
	color: #2c3e50;
	display: block;
	margin-bottom: 10px;
}

.template-usage ul {
	margin: 0;
	padding-left: 20px;
}

.template-usage li {
	font-size: 13px;
	color: #4a5568;
	margin-bottom: 5px;
}

.params-table-container {
	overflow-x: auto;
	margin: 30px 0;
}

.params-table {
	width: 100%;
	border-collapse: collapse;
	background: #ffffff;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.params-table th,
.params-table td {
	padding: 15px 20px;
	text-align: left;
	border-bottom: 1px solid #e0e0e0;
}

.params-table th {
	background: #f8f9fa;
	font-weight: 600;
	font-size: 14px;
	color: #2c3e50;
}

.params-table td {
	font-size: 13px;
	color: #4a5568;
}

.params-table code {
	background: #f7fafc;
	color: #e53e3e;
	padding: 4px 8px;
	border-radius: 4px;
	font-family: monospace;
	font-size: 12px;
}

.param-type {
	background: #3498db;
	color: #ffffff;
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 500;
	text-transform: uppercase;
}

.template-params {
	margin: 25px 0;
	padding: 20px;
	background: #ffffff;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
}

.template-params h4 {
	margin: 0 0 15px 0;
	font-size: 16px;
	color: #3498db;
	font-family: monospace;
}

.template-params ul {
	margin: 0;
	padding-left: 20px;
}

.template-params li {
	font-size: 14px;
	margin-bottom: 8px;
	color: #4a5568;
}

.template-params code {
	background: #f7fafc;
	color: #e53e3e;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 12px;
}

.icon-examples h4 {
	margin: 25px 0 15px 0;
	font-size: 18px;
	color: #2c3e50;
}

.tips-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 25px;
	margin-top: 30px;
}

.tip-card {
	background: #ffffff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 25px;
	text-align: center;
}

.tip-icon {
	font-size: 48px;
	margin-bottom: 20px;
}

.tip-icon .alvobot-icon {
	width: 48px;
	height: 48px;
}

.tip-card h3 {
	margin: 0 0 20px 0;
	font-size: 18px;
	color: #2c3e50;
}

.tip-card ul {
	text-align: left;
	margin: 0;
	padding-left: 20px;
}

.tip-card li {
	font-size: 14px;
	color: #4a5568;
	margin-bottom: 8px;
	line-height: 1.5;
}

.faq-list {
	margin-top: 30px;
}

.faq-item {
	background: #ffffff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	margin-bottom: 20px;
	overflow: hidden;
}

.faq-question {
	margin: 0;
	padding: 20px 25px;
	background: #f8f9fa;
	font-size: 18px;
	font-weight: 600;
	color: #2c3e50;
	cursor: pointer;
	user-select: none;
	position: relative;
}

.faq-question:before {
	content: '+';
	position: absolute;
	right: 25px;
	top: 50%;
	transform: translateY(-50%);
	font-size: 24px;
	font-weight: 300;
	color: #3498db;
}

.faq-item.open .faq-question:before {
	content: '−';
}

.faq-answer {
	padding: 0 25px;
	max-height: 0;
	overflow: hidden;
	transition: all 0.3s ease;
}

.faq-item.open .faq-answer {
	padding: 25px;
	max-height: 500px;
}

.faq-answer p {
	margin: 0 0 15px 0;
	font-size: 16px;
	color: #4a5568;
}

.faq-answer ul {
	margin: 0;
	padding-left: 20px;
}

.faq-answer li {
	font-size: 14px;
	color: #718096;
	margin-bottom: 8px;
	line-height: 1.5;
}

@media (max-width: 968px) {
	.docs-container {
		grid-template-columns: 1fr;
		gap: 20px;
	}
	
	.docs-sidebar {
		position: static;
	}
	
	.docs-nav {
		display: flex;
		overflow-x: auto;
		gap: 10px;
		padding-bottom: 10px;
	}
	
	.docs-nav ul {
		display: flex;
		gap: 10px;
		white-space: nowrap;
	}
	
	.docs-nav li {
		margin-bottom: 0;
	}
	
	.docs-content {
		padding: 25px;
	}
	
	.template-grid,
	.tips-grid {
		grid-template-columns: 1fr;
	}
	
	.feature-list {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 768px) {
	.docs-content {
		padding: 20px;
	}
	
	.docs-card {
		padding: 20px;
	}
	
	.docs-header h2 {
		font-size: 24px;
	}
	
	.step-item {
		flex-direction: column;
		text-align: center;
		gap: 15px;
	}
	
	.params-table {
		font-size: 12px;
	}
	
	.params-table th,
	.params-table td {
		padding: 10px 12px;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Navigation
	$('.docs-nav .nav-link').on('click', function(e) {
		e.preventDefault();
		
		var target = $(this).attr('href');
		
		// Update navigation
		$('.docs-nav .nav-link').removeClass('active');
		$(this).addClass('active');
		
		// Show content
		$('.docs-section').removeClass('active');
		$(target).addClass('active');
		
		// Scroll to top
		$('.docs-content').scrollTop(0);
	});
	
	// FAQ Toggle
	$('.faq-question').on('click', function() {
		var $item = $(this).parent();
		var $answer = $item.find('.faq-answer');
		
		if ($item.hasClass('open')) {
			$item.removeClass('open');
		} else {
			// Close all other items
			$('.faq-item').removeClass('open');
			// Open this item
			$item.addClass('open');
		}
	});
	
	// Smooth scroll for anchor links
	$('a[href^="#"]').on('click', function(e) {
		var target = $(this.getAttribute('href'));
		if (target.length) {
			e.preventDefault();
			$('.docs-content').animate({
				scrollTop: target.offset().top - $('.docs-content').offset().top + $('.docs-content').scrollTop() - 20
			}, 500);
		}
	});
});
</script>