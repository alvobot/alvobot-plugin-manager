<?php
/**
 * Logo Generator API Class
 */
class AlvoBotPro_LogoGenerator_API {
	private $logo_generator;
	private $namespace = 'alvobot-pro/v1';

	public function __construct( $logo_generator ) {
		$this->logo_generator = $logo_generator;
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/logos',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_logo' ),
				'permission_callback' => array( $this, 'verify_token' ),
			)
		);
	}

	/**
	 * Verify the request token
	 */
	public function verify_token( $request ) {
		$params = $request->get_json_params();
		$token  = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';

		if ( empty( $token ) || $token !== get_option( 'grp_site_token' ) ) {
			return new WP_Error( 'unauthorized', 'Token inválido', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handle logo generation request.
	 * Accepts icon_svg (SVG content) directly from the client.
	 */
	public function handle_generate_logo( $request ) {
		$params = $request->get_json_params();

		// Validate required parameters
		if ( empty( $params['blog_name'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'O parâmetro blog_name é obrigatório',
				array( 'status' => 400 )
			);
		}

		if ( empty( $params['icon_svg'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'O parâmetro icon_svg é obrigatório',
				array( 'status' => 400 )
			);
		}

		// Get and sanitize parameters
		$blog_name        = sanitize_text_field( $params['blog_name'] );
		$icon_svg         = wp_unslash( $params['icon_svg'] );
		$font_color       = isset( $params['font_color'] ) ? sanitize_hex_color( $params['font_color'] ) : '#000000';
		$background_color = isset( $params['background_color'] ) ? sanitize_hex_color( $params['background_color'] ) : '#FFFFFF';
		$font_choice      = isset( $params['font_choice'] ) ? sanitize_text_field( $params['font_choice'] ) : 'montserrat';
		$save_to_media    = isset( $params['save_to_media'] ) ? (bool) $params['save_to_media'] : false;
		$apply_to_site    = isset( $params['apply_to_site'] ) ? (bool) $params['apply_to_site'] : false;
		$generate_favicon = isset( $params['generate_favicon'] ) ? (bool) $params['generate_favicon'] : false;

		try {
			// Generate logo using SVG content directly
			$logo_svg_result = $this->logo_generator->generate_logo(
				$blog_name,
				$font_color,
				$background_color,
				$icon_svg,
				$font_choice
			);

			if ( ! $logo_svg_result ) {
				throw new Exception( 'Falha ao gerar o logo - resultado vazio' );
			}

			$response_data = array(
				'success'  => true,
				'logo_svg' => $logo_svg_result,
			);

			// Se solicitado, salvar no media library
			if ( $save_to_media || $apply_to_site ) {
				$result = $this->logo_generator->save_logo_as_attachment( $logo_svg_result, $blog_name );

				if ( ! $result ) {
					throw new Exception( 'Falha ao salvar o logo no media library' );
				}

				$response_data['media'] = $result;

				if ( $apply_to_site ) {
					set_theme_mod( 'custom_logo', $result['id'] );
					$response_data['message'] = 'Logo gerado, salvo e aplicado com sucesso!';
				} else {
					$response_data['message'] = 'Logo gerado e salvo com sucesso!';
				}
			} else {
				$response_data['message'] = 'Logo gerado com sucesso!';
			}

			// Se solicitado, gerar e aplicar o favicon
			if ( $generate_favicon ) {
				try {
					$favicon_result            = $this->logo_generator->generate_and_save_favicon(
						$icon_svg,
						$font_color,
						$background_color
					);
					$response_data['favicon']  = $favicon_result;
					$response_data['message'] .= ' Favicon gerado e aplicado com sucesso!';
				} catch ( Exception $e ) {
					$response_data['favicon_error'] = 'Erro ao gerar favicon: ' . $e->getMessage();
				}
			}

			return new WP_REST_Response( $response_data, 200 );

		} catch ( Exception $e ) {
			return new WP_Error(
				'generation_failed',
				'Falha ao gerar o logo: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
