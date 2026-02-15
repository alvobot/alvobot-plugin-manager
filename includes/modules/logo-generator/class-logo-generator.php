<?php
/**
 * Classe responsável pelo gerador de logo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_LogoGenerator {
	private $fonts;
	private $upload_dir;
	private $plugin_url;

	const LUCIDE_VERSION = '0.564.0';

	public function __construct() {
		$this->upload_dir = wp_upload_dir();
		$this->plugin_url = ALVOBOT_PRO_PLUGIN_URL;

		// Hooks
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_generate_logo', array( $this, 'ajax_generate_logo' ) );
		add_action( 'wp_ajax_save_logo', array( $this, 'ajax_save_logo' ) );
		add_action( 'wp_ajax_generate_favicon', array( $this, 'ajax_generate_favicon' ) );
		add_action( 'wp_ajax_save_favicon', array( $this, 'ajax_save_favicon' ) );
		add_action( 'wp_ajax_preview_logo', array( $this, 'ajax_generate_logo' ) ); // Preview em tempo real

		// Filtros para SVG
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'fix_svg_display' ), 10, 3 );

		// Inicializar API REST
		$this->init_rest_api();
	}

	private function init_rest_api() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-logo-generator-api.php';
		new AlvoBotPro_LogoGenerator_API( $this );
	}

	public function init() {
		if ( ! $this->is_module_active() ) {
			return;
		}
	}

	public function is_module_active() {
		$active_modules = get_option( 'alvobot_pro_active_modules', array() );
		return isset( $active_modules['logo_generator'] ) && $active_modules['logo_generator'];
	}

	/**
	 * Sanitize SVG input preserving valid SVG tags and attributes.
	 *
	 * @param string $svg Raw SVG string.
	 * @return string Sanitized SVG.
	 */
	private function sanitize_svg_input( $svg ) {
		$allowed = array(
			'svg'      => array(
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
			),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'fill' => true, 'stroke' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
			'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
		);
		return wp_kses( $svg, $allowed );
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'alvobot-pro-logo' ) === false ) {
			return;
		}

		// Enqueue WordPress color picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Enqueue main stylesheet
		wp_enqueue_style(
			'alvobot-pro-logo-generator',
			$this->plugin_url . 'includes/modules/logo-generator/assets/css/logo-generator.css',
			array(),
			ALVOBOT_PRO_VERSION
		);

		// Enqueue Lucide Icons from CDN
		wp_enqueue_script(
			'lucide-icons',
			'https://unpkg.com/lucide@' . self::LUCIDE_VERSION . '/dist/umd/lucide.min.js',
			array(),
			self::LUCIDE_VERSION,
			true
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'alvobot-pro-logo-generator',
			$this->plugin_url . 'includes/modules/logo-generator/assets/js/logo-generator.js',
			array( 'jquery', 'wp-color-picker', 'lucide-icons' ),
			ALVOBOT_PRO_VERSION,
			true
		);

		wp_localize_script(
			'alvobot-pro-logo-generator',
			'logoGeneratorParams',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'logo_generator_nonce' ),
			)
		);

		$this->enqueue_google_fonts();
	}

	public function enqueue_scripts( $hook ) {
		// Removido pois o CSS já é carregado em enqueue_admin_scripts
		return;
	}

	public function enqueue_google_fonts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection for asset loading, no data modification.
		if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'alvobot-pro-logo' ) {
			$fonts = array(
				'Montserrat:400,700',
				'Playfair+Display:400,700',
				'Raleway:400,700',
				'Abril+Fatface',
				'Roboto:400,700',
				'Lora:400,700',
				'Oswald:400,700',
				'Pacifico',
				'Quicksand:400,700',
				'Cinzel:400,700',
			);

			$query_args = array(
				'family'  => implode( '|', $fonts ),
				'display' => 'swap',
			);
			wp_enqueue_style( 'blg-google-fonts', add_query_arg( $query_args, 'https://fonts.googleapis.com/css' ), array(), null );

			// Adiciona CSS para preview da fonte
			wp_add_inline_style(
				'blg-google-fonts',
				'
                .font-preview-select {
                    max-width: 400px;
                    width: 100%;
                }
                .font-preview-select option {
                    padding: var(--alvobot-space-sm);
                    font-size: var(--alvobot-font-size-lg);
                }
            '
			);
		}
	}

	public function add_preview_styles() {
		?>
		<style>
			.logo-generator-container {
				max-width: 1200px;
				margin: var(--alvobot-space-xl) auto;
			}

			.preview-container {
				display: grid;
				grid-template-columns: 2fr 1fr;
				gap: var(--alvobot-space-xl);
				margin-bottom: var(--alvobot-space-3xl);
			}

			.logo-preview, .favicon-preview {
				background: var(--alvobot-white);
				padding: var(--alvobot-space-xl);
				border-radius: var(--alvobot-radius-sm);
				box-shadow: var(--alvobot-shadow-sm);
			}

			.logo-preview h3, .favicon-preview h3 {
				margin-top: 0;
				padding-bottom: var(--alvobot-space-md);
				border-bottom: 1px solid var(--alvobot-gray-300);
			}

			#logo-preview-content, #favicon-preview-content {
				min-height: 100px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: var(--alvobot-space-xl) 0;
			}

			#logo-preview-content svg {
				max-width: 100%;
				height: auto;
			}

			#favicon-preview-content svg {
				width: 32px;
				height: 32px;
			}

			.favicon-sizes {
				display: flex;
				gap: var(--alvobot-space-lg);
				justify-content: center;
				margin: var(--alvobot-space-lg) 0;
				font-size: var(--alvobot-font-size-xs);
				color: var(--alvobot-gray-600);
			}

			.favicon-preview .submit {
				text-align: center;
				margin-top: var(--alvobot-space-xl);
				padding: 0;
			}

			/* Icon Search */
			.icon-search {
				margin-bottom: var(--alvobot-space-lg);
			}

			.icon-search input {
				width: 100%;
				padding: var(--alvobot-space-sm);
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
			}

			/* Icon Categories */
			.icon-categories {
				display: flex;
				flex-wrap: wrap;
				gap: var(--alvobot-space-md);
				margin-bottom: var(--alvobot-space-xl);
				padding: var(--alvobot-space-md);
				background: var(--alvobot-gray-200);
				border-radius: var(--alvobot-radius-sm);
			}

			.icon-category {
				padding: 6px var(--alvobot-space-md);
				background: var(--alvobot-white);
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-full);
				cursor: pointer;
				font-size: var(--alvobot-font-size-sm);
				transition: all var(--alvobot-transition-normal);
			}

			.icon-category:hover {
				border-color: var(--alvobot-accent);
				color: var(--alvobot-accent);
			}

			.icon-category.active {
				background: var(--alvobot-accent);
				border-color: var(--alvobot-accent);
				color: var(--alvobot-white);
			}

			.icon-category .count {
				opacity: 0.7;
				font-size: var(--alvobot-font-size-xs);
				margin-left: var(--alvobot-space-xs);
			}

			/* Icon Grid */
			.icon-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
				gap: var(--alvobot-space-lg);
				margin-top: var(--alvobot-space-md);
				max-height: 400px;
				overflow-y: auto;
				padding: var(--alvobot-space-md);
				background: var(--alvobot-white);
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
			}

			.icon-option {
				position: relative;
				transition: all var(--alvobot-transition-normal);
			}

			.icon-option label {
				display: flex;
				flex-direction: column;
				align-items: center;
				cursor: pointer;
				padding: var(--alvobot-space-md);
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
				transition: all var(--alvobot-transition-normal);
			}

			.icon-option:hover label {
				border-color: var(--alvobot-accent);
				background: var(--alvobot-gray-100);
				transform: translateY(-2px);
			}

			.icon-option input[type="radio"] {
				display: none;
			}

			.icon-option input[type="radio"]:checked + label {
				background: var(--alvobot-info-bg);
				border-color: var(--alvobot-accent);
				box-shadow: var(--alvobot-shadow-md);
			}

			.icon-preview {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 48px;
				height: 48px;
				margin-bottom: var(--alvobot-space-sm);
			}

			.icon-preview img {
				width: 32px;
				height: 32px;
				object-fit: contain;
			}

			.icon-name {
				font-size: 11px;
				text-align: center;
				color: var(--alvobot-gray-600);
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
				width: 100%;
			}

			.no-icons-found {
				text-align: center;
				padding: var(--alvobot-space-xl);
				background: var(--alvobot-info-bg);
				border-radius: var(--alvobot-radius-sm);
				color: var(--alvobot-gray-600);
			}

			/* Hide icons that don't match search or category */
			.icon-option.hidden {
				display: none;
			}

			.notice {
				margin: var(--alvobot-space-lg) 0;
			}
		</style>
		<?php
	}

	public function get_available_fonts() {
		AlvoBotPro::debug_log( 'logo-generator', '[Logo Generator] Getting available fonts' );
		return array(
			'montserrat' => array(
				'name'        => 'Montserrat',
				'family'      => "'Montserrat', sans-serif",
				'description' => 'Moderna e limpa',
			),
			'playfair'   => array(
				'name'        => 'Playfair Display',
				'family'      => "'Playfair Display', serif",
				'description' => 'Elegante e clássica',
			),
			'raleway'    => array(
				'name'        => 'Raleway',
				'family'      => "'Raleway', sans-serif",
				'description' => 'Minimalista e contemporânea',
			),
			'abril'      => array(
				'name'        => 'Abril Fatface',
				'family'      => "'Abril Fatface', cursive",
				'description' => 'Ousada e decorativa',
			),
			'roboto'     => array(
				'name'        => 'Roboto',
				'family'      => "'Roboto', sans-serif",
				'description' => 'Versátil e profissional',
			),
			'lora'       => array(
				'name'        => 'Lora',
				'family'      => "'Lora', serif",
				'description' => 'Elegante e legível',
			),
			'oswald'     => array(
				'name'        => 'Oswald',
				'family'      => "'Oswald', sans-serif",
				'description' => 'Forte e condensada',
			),
			'pacifico'   => array(
				'name'        => 'Pacifico',
				'family'      => "'Pacifico', cursive",
				'description' => 'Manuscrita moderna',
			),
			'quicksand'  => array(
				'name'        => 'Quicksand',
				'family'      => "'Quicksand', sans-serif",
				'description' => 'Geométrica e amigável',
			),
			'cinzel'     => array(
				'name'        => 'Cinzel',
				'family'      => "'Cinzel', serif",
				'description' => 'Clássica e luxuosa',
			),
		);
	}

	/**
	 * Sanitiza conteúdo SVG para uso seguro.
	 * Permite apenas elementos e atributos SVG conhecidos.
	 */
	public function sanitize_svg_content( $svg_content ) {
		if ( empty( $svg_content ) ) {
			return '';
		}

		// Usa a biblioteca enshrined/svg-sanitize se disponível
		$sanitizer_class = '\\enshrined\\svgSanitize\\Sanitizer';
		if ( class_exists( $sanitizer_class ) ) {
			$sanitizer = new $sanitizer_class();
			$sanitizer->removeXMLTag( true );
			$clean = $sanitizer->sanitize( $svg_content );
			if ( $clean !== false ) {
				$svg_content = $clean;
			}
		} else {
			// Fallback: sanitização via regex
			$svg_content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg_content );
			$svg_content = preg_replace( '/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg_content );
			$svg_content = preg_replace( '/\bstyle\s*=\s*["\'][^"\']*["\']/i', '', $svg_content );
			$svg_content = preg_replace( '/\bxlink:href\s*=\s*["\'](?!#)[^"\']*["\']/i', '', $svg_content );
		}

		// Remove XML declarations e DOCTYPE (essencial para SVGs nested).
		$svg_content = preg_replace( '/<\?xml[^?]*\?>\s*/i', '', $svg_content );
		$svg_content = preg_replace( '/<!DOCTYPE[^>]*>\s*/i', '', $svg_content );

		return trim( $svg_content );
	}

	/**
	 * Ajusta as cores de um SVG
	 */
	private function adjust_svg_colors( $svg_content, $color ) {
		// Adiciona a cor como variável CSS
		$svg_content = preg_replace( '/<svg/', '<svg style="color: ' . esc_attr( $color ) . '"', $svg_content, 1 );

		// Substitui cores específicas por currentColor
		$svg_content = preg_replace( '/fill="(?!none)[^"]*"/', 'fill="currentColor"', $svg_content );
		$svg_content = preg_replace( '/stroke="(?!none)[^"]*"/', 'stroke="currentColor"', $svg_content );

		return $svg_content;
	}

	public function generate_logo( $blog_name, $font_color, $background_color, $icon_svg_content, $font_choice ) {
		if ( empty( $blog_name ) || empty( $icon_svg_content ) ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Parâmetros inválidos' );
			return false;
		}

		try {
			// Sanitiza e ajusta as cores do SVG
			$svg_content = $this->sanitize_svg_content( $icon_svg_content );
			if ( empty( $svg_content ) || strpos( $svg_content, '<' ) === false ) {
				AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: SVG vazio após sanitização' );
				return false;
			}
			$svg_content = $this->adjust_svg_colors( $svg_content, $font_color );

			// Extrai o viewBox
			preg_match( '/viewBox=["\']([\d\s\.-]+)["\']/', $svg_content, $matches );
			$viewBox      = isset( $matches[1] ) ? $matches[1] : '0 0 24 24';
			$viewBoxParts = explode( ' ', $viewBox );

			$vbWidth  = isset( $viewBoxParts[2] ) ? floatval( $viewBoxParts[2] ) : 24;
			$vbHeight = isset( $viewBoxParts[3] ) ? floatval( $viewBoxParts[3] ) : 24;

			// Dimensões do SVG principal
			$icon_size = 80;
			$padding   = 20;
			$font_size = 48;
			$height    = 120;

			// Definimos uma largura de referência para o viewBox, mas o SVG usará largura 100%
			// Calcula largura aproximada com base no texto (aprox. 0.6 * font_size por caractere + espaço para ícone)
			$text_width    = strlen( $blog_name ) * ( $font_size * 0.6 );
			$viewbox_width = $icon_size + ( 3 * $padding ) + $text_width; // ícone + espaçamento + texto

			// Calcula a escala mantendo a proporção
			$scale = min( $icon_size / $vbWidth, $icon_size / $vbHeight );

			// Calcula posições com centralização precisa
			$iconX = $padding;
			$iconY = ( $height - ( $vbHeight * $scale ) ) / 2;

			// Garante uma largura mínima razoável para o viewBox
			$min_width = 250;
			if ( $viewbox_width < $min_width ) {
				$viewbox_width = $min_width;
			}
			$textY = $height / 2; // Centralização vertical precisa

			// Obtém a família da fonte
			$fonts       = $this->get_available_fonts();
			$font_family = isset( $fonts[ $font_choice ] ) ? $fonts[ $font_choice ]['family'] : "'Montserrat', sans-serif";
			$font_name   = isset( $fonts[ $font_choice ] ) ? $fonts[ $font_choice ]['name'] : 'Montserrat';

			// Cria o novo SVG com alinhamento vertical melhorado
			return sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" style="width: 100%%;">
                    <rect width="100%%" height="100%%" fill="%s"/>
                    <defs>
                        <style type="text/css">
                            @import url("https://fonts.googleapis.com/css2?family=%s");
                        </style>
                    </defs>
                    <g transform="translate(%f, %f) scale(%f)">%s</g>
                    <text x="%d" y="%d" font-family="%s" font-size="%d" font-weight="bold"
                          fill="%s" dominant-baseline="middle" text-anchor="start">%s</text>
                </svg>',
				$viewbox_width,
				$height,
				esc_attr( $background_color ),
				rawurlencode( $font_name ),
				$iconX,
				$iconY,
				$scale,
				$svg_content,
				$icon_size + ( 2 * $padding ),
				$textY,
				esc_attr( $font_family ),
				$font_size,
				esc_attr( $font_color ),
				esc_html( $blog_name )
			);

		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao gerar logo - ' . $e->getMessage() );
			return false;
		}
	}

	public function generate_square_svg( $icon_svg_content, $font_color = '#000000', $background_color = '#FFFFFF' ) {
		if ( empty( $icon_svg_content ) ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Conteúdo SVG do favicon vazio' );
			return false;
		}

		try {
			// Sanitiza e ajusta as cores do SVG
			$svg_content = $this->sanitize_svg_content( $icon_svg_content );
			if ( empty( $svg_content ) || strpos( $svg_content, '<' ) === false ) {
				AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: SVG do favicon vazio após sanitização' );
				return false;
			}
			$svg_content = $this->adjust_svg_colors( $svg_content, $font_color );

			// Extrai o viewBox
			preg_match( '/viewBox=["\']([\d\s\.-]+)["\']/', $svg_content, $matches );
			$viewBox      = isset( $matches[1] ) ? $matches[1] : '0 0 24 24';
			$viewBoxParts = explode( ' ', $viewBox );

			$vbWidth  = isset( $viewBoxParts[2] ) ? floatval( $viewBoxParts[2] ) : 24;
			$vbHeight = isset( $viewBoxParts[3] ) ? floatval( $viewBoxParts[3] ) : 24;

			// Define o tamanho do favicon
			$size         = 512;
			$padding      = $size * 0.1;
			$content_size = $size - ( 2 * $padding );

			// Calcula a escala mantendo a proporção
			$scale = min( $content_size / $vbWidth, $content_size / $vbHeight );

			// Calcula as posições para centralizar o ícone
			$centerX = ( $size - ( $vbWidth * $scale ) ) / 2;
			$centerY = ( $size - ( $vbHeight * $scale ) ) / 2;

			// Cria o novo SVG
			return sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
                    <rect width="100%%" height="100%%" fill="%s"/>
                    <g transform="translate(%f, %f) scale(%f)">%s</g>
                </svg>',
				$size,
				$size,
				$size,
				$size,
				esc_attr( $background_color ),
				$centerX,
				$centerY,
				$scale,
				$svg_content
			);

		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao gerar favicon - ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Permite o upload de arquivos SVG.
	 */
	public function allow_svg_upload( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Sanitiza arquivos SVG no upload para remover scripts maliciosos (XSS).
	 */
	public function sanitize_svg_upload( $file ) {
		if ( $file['type'] !== 'image/svg+xml' ) {
			return $file;
		}

		$sanitizer_class = '\\enshrined\\svgSanitize\\Sanitizer';
		if ( ! class_exists( $sanitizer_class ) ) {
			$autoload_path = ALVOBOT_PRO_PLUGIN_DIR . 'vendor/autoload.php';
			if ( file_exists( $autoload_path ) ) {
				require_once $autoload_path;
			}
		}

		if ( class_exists( $sanitizer_class ) ) {
			$sanitizer   = new $sanitizer_class();
			$svg_content = file_get_contents( $file['tmp_name'] );
			$clean_svg   = $sanitizer->sanitize( $svg_content );

			if ( $clean_svg === false ) {
				$file['error'] = __( 'Este arquivo SVG não pôde ser sanitizado e foi rejeitado por segurança.', 'alvobot-pro' );
				return $file;
			}

			file_put_contents( $file['tmp_name'], $clean_svg );
		} else {
			// Fallback: rejeita SVGs com tags perigosas quando a biblioteca não está disponível
			$svg_content        = file_get_contents( $file['tmp_name'] );
			$dangerous_patterns = array(
				'/<script/i',
				'/on\w+\s*=/i',
				'/javascript\s*:/i',
				'/<foreignObject/i',
				'/<embed/i',
				'/<object/i',
				'/<iframe/i',
			);
			foreach ( $dangerous_patterns as $pattern ) {
				if ( preg_match( $pattern, $svg_content ) ) {
					$file['error'] = __( 'Este arquivo SVG contém conteúdo potencialmente malicioso e foi rejeitado.', 'alvobot-pro' );
					return $file;
				}
			}
		}

		return $file;
	}

	/**
	 * Ajusta a exibição de arquivos SVG na biblioteca de mídia.
	 */
	public function fix_svg_display( $response, $attachment, $meta ) {
		if ( $response['mime'] === 'image/svg+xml' ) {
			$response['sizes'] = array(
				'full' => array(
					'url'         => $response['url'],
					'width'       => $response['width'],
					'height'      => $response['height'],
					'orientation' => $response['orientation'],
				),
			);
		}
		return $response;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'alvobot-pro' ) );
		}
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div class="alvobot-admin-header">
					<div class="alvobot-header-icon">
						<i data-lucide="palette" class="alvobot-icon"></i>
					</div>
					<div class="alvobot-header-content">
						<h1>Gerador de Logo</h1>
						<p>Crie um logo profissional para seu site em segundos</p>
					</div>
				</div>

				<?php settings_errors( 'alvobot_pro_logo' ); ?>

				<div class="preview-container">
					<div class="logo-preview-section">
						<h3>Preview do Logo</h3>
						<div id="logo-preview" class="logo-preview">
							<div id="logo-preview-content"></div>
						</div>
					</div>
					<div class="favicon-preview-section">
						<h3>Preview do Favicon</h3>
						<div id="favicon-preview" class="favicon-preview">
							<div id="favicon-preview-content"></div>
						</div>
					</div>
				</div>

				<div class="alvobot-card">
					<div class="logo-generator-form">
						<form method="post" id="logo-generator-form">
							<?php wp_nonce_field( 'logo_generator_nonce', '_wpnonce' ); ?>
							<div class="form-fields">
								<div class="form-field">
									<label for="blog_name">Nome do Blog</label>
									<input type="text" id="blog_name" name="blog_name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="alvobot-input" required>
								</div>

								<div class="color-fields">
									<div class="form-field">
										<label for="font_color">Cor da Fonte</label>
										<input type="text" id="font_color" name="font_color" value="#000000" class="color-picker">
									</div>

									<div class="form-field">
										<label for="background_color">Cor do Fundo</label>
										<input type="text" id="background_color" name="background_color" value="#FFFFFF" class="color-picker">
									</div>
								</div>

								<div class="form-field icon-field">
									<label>Escolha o Ícone</label>
									<?php $this->render_icon_grid(); ?>
								</div>

								<div class="form-field">
									<label for="font_choice">Fonte</label>
									<select id="font_choice" name="font_choice" class="alvobot-select font-preview-select">
										<?php
										$fonts        = $this->get_available_fonts();
										$current_font = isset( $_POST['font_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['font_choice'] ) ) : 'montserrat'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Display logic for form default value.
										foreach ( $fonts as $key => $font ) {
											printf(
												'<option value="%s" style="font-family: %s" %s>%s - %s</option>',
												esc_attr( $key ),
												esc_attr( $font['family'] ),
												selected( $current_font, $key, false ),
												esc_html( $font['name'] ),
												esc_html( $font['description'] )
											);
										}
										?>
									</select>
								</div>

								<div class="form-field options-field">
									<label>Opções</label>
									<div class="checkbox-options">
										<label class="option-label">
											<input type="checkbox" id="set_as_logo" name="set_as_logo" value="1" checked>
											<span>Definir automaticamente como logo do site</span>
										</label>
										<label class="option-label">
											<input type="checkbox" id="set_as_favicon" name="set_as_favicon" value="1" checked>
											<span>Definir como favicon do site</span>
										</label>
										<div class="alvobot-btn-group alvobot-btn-group-right">
								<button type="submit" name="generate_logo" id="generate_logo" class="alvobot-btn alvobot-btn-primary alvobot-btn-lg">Gerar Logo</button>
							</div>
									</div>
								</div>
							</div>
						</form>
					</div>
				
			</div>
		</div>

		<?php
	}

	public function render_icon_grid() {
		// Busca (fora do grid container)
		echo '<input type="text" id="icon-search" class="icon-search-input" placeholder="Carregando ícones..." disabled>';

		// Grid de ícones (populado via JavaScript)
		echo '<div class="icon-grid" id="icon-grid"><p style="grid-column: 1/-1; text-align:center; color:var(--alvobot-gray-600);">Carregando ícones Lucide...</p></div>';

		// Hidden input para armazenar SVG do ícone selecionado
		echo '<input type="hidden" id="selected_icon_svg" name="icon_svg" value="">';

		// Mensagem quando nenhum ícone é encontrado
		echo '<div class="no-icons-found" style="display:none;">
            <p>Nenhum ícone encontrado. Tente outra busca.</p>
        </div>';
	}

	public function save_logo_as_attachment( $svg_content, $title = '' ) {
		// Validação do conteúdo SVG
		if ( empty( $svg_content ) || strpos( $svg_content, '<svg' ) === false ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Conteúdo SVG inválido' );
			return false;
		}

		// Sanitiza o título
		$title = sanitize_text_field( $title ?: 'Logo' );

		// Configura o diretório de upload
		$upload_dir = wp_upload_dir();

		// Verifica se o diretório de upload existe e é gravável
		if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Falha ao criar diretório: ' . $upload_dir['path'] );
			return false;
		}

		$filename = sanitize_file_name( $title . '-' . uniqid() . '.svg' );
		$filepath = $upload_dir['path'] . '/' . $filename;

		// Força a permissão correta do arquivo
		$file_permissions = 0644;

		// Salva o arquivo com verificação
		$saved = file_put_contents( $filepath, $svg_content );
		if ( $saved === false || filesize( $filepath ) === 0 ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao salvar arquivo SVG em: ' . $filepath );
			return false;
		}
		chmod( $filepath, $file_permissions );

		// Cria o attachment com metadados completos
		$attachment = array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => sanitize_file_name( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload_dir['url'] . '/' . $filename,
			'meta_input'     => array(
				'_wp_attachment_image_alt' => sanitize_text_field( $title ),
			),
		);

		// Insere e verifica o attachment
		$attach_id = wp_insert_attachment( $attachment, $filepath );
		if ( is_wp_error( $attach_id ) ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao criar attachment - ' . $attach_id->get_error_message() );
			@unlink( $filepath );
			return false;
		}

		// Gera metadados do anexo
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attach_id, $filepath );
		if ( ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $attach_id, $metadata );
		}

		return array(
			'id'   => $attach_id,
			'url'  => wp_get_attachment_url( $attach_id ),
			'path' => $filepath,
		);
	}

	public function ajax_save_logo() {
		check_ajax_referer( 'logo_generator_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada' );
			return;
		}

		// Gera o logo
		$blog_name        = isset( $_POST['blog_name'] ) ? sanitize_text_field( wp_unslash( $_POST['blog_name'] ) ) : '';
		$font_color       = isset( $_POST['font_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['font_color'] ) ) : '#000000';
		$background_color = isset( $_POST['background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['background_color'] ) ) : '#FFFFFF';
		$icon_svg         = isset( $_POST['icon_svg'] ) ? $this->sanitize_svg_input( wp_unslash( $_POST['icon_svg'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by custom sanitize_svg_input().
		$font_choice      = isset( $_POST['font_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['font_choice'] ) ) : 'montserrat';

		if ( empty( $icon_svg ) ) {
			wp_send_json_error( 'Nenhum ícone selecionado' );
			return;
		}

		$svg_content = $this->generate_logo( $blog_name, $font_color, $background_color, $icon_svg, $font_choice );

		if ( ! $svg_content ) {
			wp_send_json_error( 'Erro ao gerar o logo' );
			return;
		}

		// Validação adicional do SVG
		if ( strpos( $svg_content, '<svg' ) === false || strpos( $svg_content, '</svg>' ) === false ) {
			wp_send_json_error( 'SVG gerado é inválido' );
			return;
		}

		// Salva o logo
		$result = $this->save_logo_as_attachment( $svg_content, $blog_name . ' Logo' );

		if ( $result ) {
			$set_as_logo      = isset( $_POST['set_as_logo'] ) ? sanitize_text_field( wp_unslash( $_POST['set_as_logo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer above.
			$generate_favicon = isset( $_POST['generate_favicon'] ) ? sanitize_text_field( wp_unslash( $_POST['generate_favicon'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer above.

			// Define como logo do site se solicitado
			if ( '1' === $set_as_logo ) {
				set_theme_mod( 'custom_logo', $result['id'] );
			}

			// Gera e salva o favicon se solicitado
			if ( '1' === $generate_favicon ) {
				$favicon_result = $this->generate_and_save_favicon(
					$icon_svg,
					$font_color,
					$background_color
				);
			}

			// Adiciona mensagem de sucesso
			$message = 'Logo gerado e salvo com sucesso!';
			if ( '1' === $set_as_logo ) {
				$message .= ' O logo foi definido como logo do site.';
			}
			if ( '1' === $generate_favicon && $favicon_result ) {
				$message .= ' O favicon também foi gerado e aplicado.';
			}

			add_settings_error(
				'alvobot_pro_logo',
				'logo_success',
				$message,
				'success'
			);

			wp_send_json_success(
				array(
					'id'      => $result['id'],
					'url'     => $result['url'],
					'message' => $message,
				)
			);
		} else {
			wp_send_json_error( 'Erro ao salvar o logo' );
		}
	}

	public function generate_and_save_favicon( $icon_svg_content, $font_color = '#000000', $background_color = '#FFFFFF' ) {
		// Gera o SVG do favicon
		$svg = $this->generate_square_svg( $icon_svg_content, $font_color, $background_color );
		if ( ! $svg ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao gerar SVG do favicon' );
			return false;
		}

		// Salva o favicon como anexo
		$result = $this->save_logo_as_attachment( $svg, 'Favicon' );
		if ( ! $result ) {
			AlvoBotPro::debug_log( 'logo-generator', 'Logo Generator: Erro ao salvar favicon como anexo' );
			return false;
		}

		// Atualiza as opções do site
		update_option( 'site_icon', $result['id'] );

		return $result;
	}

	public function ajax_save_favicon() {
		check_ajax_referer( 'logo_generator_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada' );
			return;
		}

		$icon_svg         = isset( $_POST['icon_svg'] ) ? $this->sanitize_svg_input( wp_unslash( $_POST['icon_svg'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by custom sanitize_svg_input().
		$font_color       = isset( $_POST['font_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['font_color'] ) ) : '#000000';
		$background_color = isset( $_POST['background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['background_color'] ) ) : '#FFFFFF';

		if ( empty( $icon_svg ) ) {
			wp_send_json_error( 'Nenhum ícone selecionado' );
			return;
		}

		$result = $this->generate_and_save_favicon( $icon_svg, $font_color, $background_color );

		if ( $result ) {
			$message = 'Favicon gerado e aplicado com sucesso!';

			// Adiciona mensagem de sucesso
			add_settings_error(
				'alvobot_pro_logo',
				'favicon_success',
				$message,
				'success'
			);

			wp_send_json_success(
				array(
					'favicon' => array(
						'url' => $result['url'],
					),
					'message' => $message,
				)
			);
		} else {
			wp_send_json_error( 'Erro ao gerar o favicon' );
		}
	}

	public function ajax_generate_favicon() {
		// Verifica o nonce
		if ( ! check_ajax_referer( 'logo_generator_nonce', '_wpnonce', false ) ) {
			wp_send_json_error( 'Erro de segurança: nonce inválido' );
			return;
		}

		// Verifica permissões
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Você não tem permissão para executar esta ação' );
			return;
		}

		// Obtém e valida os parâmetros
		$icon_svg         = isset( $_POST['icon_svg'] ) ? $this->sanitize_svg_input( wp_unslash( $_POST['icon_svg'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by custom sanitize_svg_input().
		$font_color       = isset( $_POST['font_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['font_color'] ) ) : '#000000';
		$background_color = isset( $_POST['background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['background_color'] ) ) : '#FFFFFF';

		// Verifica se o ícone foi selecionado
		if ( empty( $icon_svg ) ) {
			wp_send_json_error( 'Por favor, selecione um ícone' );
			return;
		}

		// Gera o favicon
		$favicon_svg = $this->generate_square_svg( $icon_svg, $font_color, $background_color );
		if ( $favicon_svg === false ) {
			wp_send_json_error( 'Erro ao gerar o favicon' );
			return;
		}

		wp_send_json_success( $favicon_svg );
	}

	public function ajax_generate_logo() {
		// Verifica o nonce
		if ( ! check_ajax_referer( 'logo_generator_nonce', '_wpnonce', false ) ) {
			wp_send_json_error( 'Erro de segurança: nonce inválido' );
			return;
		}

		// Verifica permissões
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Você não tem permissão para executar esta ação' );
			return;
		}

		// Obtém e valida os parâmetros
		$blog_name        = isset( $_POST['blog_name'] ) ? sanitize_text_field( wp_unslash( $_POST['blog_name'] ) ) : '';
		$font_color       = isset( $_POST['font_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['font_color'] ) ) : '#000000';
		$background_color = isset( $_POST['background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['background_color'] ) ) : '#FFFFFF';
		$icon_svg         = isset( $_POST['icon_svg'] ) ? $this->sanitize_svg_input( wp_unslash( $_POST['icon_svg'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by custom sanitize_svg_input().
		$font_choice      = isset( $_POST['font_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['font_choice'] ) ) : 'montserrat';

		// Verifica se o ícone foi selecionado
		if ( empty( $icon_svg ) ) {
			wp_send_json_error( 'Por favor, selecione um ícone' );
			return;
		}

		// Gera o logo
		$logo_svg = $this->generate_logo( $blog_name, $font_color, $background_color, $icon_svg, $font_choice );
		if ( $logo_svg === false ) {
			wp_send_json_error( 'Erro ao gerar o logo' );
			return;
		}

		wp_send_json_success( $logo_svg );
	}

	public function render_settings_page_styles() {
		?>
		<style>
			.icon-selector {
				display: flex;
				flex-direction: column;
				gap: var(--alvobot-space-lg);
				background: var(--alvobot-white);
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
				padding: var(--alvobot-space-lg);
			}

			.icon-search {
				position: relative;
			}

			.icon-search .alvobot-icon {
				position: absolute;
				left: var(--alvobot-space-md);
				top: 50%;
				transform: translateY(-50%);
				color: var(--alvobot-gray-500);
			}

			.icon-search input {
				width: 100%;
				padding: var(--alvobot-space-sm) var(--alvobot-space-md) var(--alvobot-space-sm) 35px;
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
				font-size: var(--alvobot-font-size-base);
			}

			.icon-categories {
				display: flex;
				flex-wrap: wrap;
				gap: var(--alvobot-space-sm);
				padding-bottom: var(--alvobot-space-md);
				border-bottom: 1px solid var(--alvobot-gray-300);
			}

			.icon-category {
				padding: 6px var(--alvobot-space-md);
				border-radius: var(--alvobot-radius-full);
				background: var(--alvobot-gray-200);
				font-size: var(--alvobot-font-size-sm);
				cursor: pointer;
				transition: all var(--alvobot-transition-normal);
				color: var(--alvobot-gray-800);
			}

			.icon-category:hover {
				background: var(--alvobot-gray-300);
			}

			.icon-category.active {
				background: var(--alvobot-accent);
				color: var(--alvobot-white);
			}

			.icon-category .count {
				opacity: 0.8;
				font-size: var(--alvobot-font-size-xs);
				margin-left: var(--alvobot-space-xs);
			}

			.icon-grid-scroll {
				max-height: 300px;
				overflow-y: auto;
				padding: var(--alvobot-space-md) 5px;
				border: 1px solid var(--alvobot-gray-300);
				border-radius: var(--alvobot-radius-sm);
				background: var(--alvobot-gray-100);
			}

			.icon-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
				gap: var(--alvobot-space-md);
			}

			.icon-option {
				text-align: center;
			}

			.icon-option input[type="radio"] {
				display: none;
			}

			.icon-option label {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 5px;
				cursor: pointer;
				padding: var(--alvobot-space-sm);
				border-radius: var(--alvobot-radius-sm);
				transition: all var(--alvobot-transition-normal);
			}

			.icon-option label:hover {
				background: var(--alvobot-white);
				box-shadow: var(--alvobot-shadow-sm);
			}

			.icon-option input[type="radio"]:checked + label {
				background: var(--alvobot-white);
				box-shadow: var(--alvobot-shadow-md);
				border: 1px solid var(--alvobot-accent);
			}

			.icon-preview {
				width: 40px;
				height: 40px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto;
			}

			.icon-preview svg {
				width: 100%;
				height: 100%;
				fill: currentColor;
			}

			.icon-name {
				font-size: 11px;
				color: var(--alvobot-gray-800);
				max-width: 100%;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			.no-icons-found {
				display: none;
				text-align: center;
				padding: var(--alvobot-space-xl);
				color: var(--alvobot-gray-500);
			}

			.no-icons-found .alvobot-icon {
				font-size: 24px;
				width: 24px;
				height: 24px;
				margin-bottom: var(--alvobot-space-sm);
			}

			.no-icons-found p {
				margin: 0;
				font-size: var(--alvobot-font-size-sm);
			}
		</style>
		<?php
	}
}