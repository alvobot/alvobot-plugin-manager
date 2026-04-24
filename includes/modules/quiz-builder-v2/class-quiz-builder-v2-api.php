<?php
/**
 * Quiz Builder V2 — REST API
 *
 * Endpoints for managing quizzes and leads via the AlvoBot platform API.
 * Auth: alvobot_site_token (same as other modules).
 *
 * Routes:
 *   GET    /alvobot-pro/v1/quiz-builder/quizzes            — list all quizzes
 *   POST   /alvobot-pro/v1/quiz-builder/quizzes            — create quiz
 *   GET    /alvobot-pro/v1/quiz-builder/quizzes/{id}       — get single quiz
 *   PUT    /alvobot-pro/v1/quiz-builder/quizzes/{id}       — update quiz
 *   DELETE /alvobot-pro/v1/quiz-builder/quizzes/{id}       — delete quiz
 *   GET    /alvobot-pro/v1/quiz-builder/quizzes/{id}/leads — list leads for quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_QuizBuilderV2_API {

	private static $instance = null;
	private $namespace       = 'alvobot-pro/v1/quiz-builder';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/* =========================================================================
	 * ROUTES
	 * ====================================================================== */

	public function register_routes() {

		// GET /quizzes — list all
		// POST /quizzes — create
		register_rest_route(
			$this->namespace,
			'/quizzes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_quizzes' ),
					'permission_callback' => array( $this, 'verify_token' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_quiz' ),
					'permission_callback' => array( $this, 'verify_token' ),
					'args'                => $this->get_quiz_args( true ),
				),
			)
		);

		// GET /quizzes/{id} — get single
		// PUT /quizzes/{id} — update
		// DELETE /quizzes/{id} — delete
		register_rest_route(
			$this->namespace,
			'/quizzes/(?P<id>[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_quiz' ),
					'permission_callback' => array( $this, 'verify_token' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_quiz' ),
					'permission_callback' => array( $this, 'verify_token' ),
					'args'                => $this->get_quiz_args( false ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_quiz' ),
					'permission_callback' => array( $this, 'verify_token' ),
				),
			)
		);

		// GET /quizzes/{id}/leads — list leads
		register_rest_route(
			$this->namespace,
			'/quizzes/(?P<id>[a-zA-Z0-9_]+)/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_leads' ),
				'permission_callback' => array( $this, 'verify_token' ),
			)
		);
	}

	/* =========================================================================
	 * AUTH
	 * ====================================================================== */

	public function verify_token( $request ) {
		$token = $request->get_header( 'X-Alvobot-Token' );

		// Fallback: token in JSON body (same pattern as author-box)
		if ( ! $token ) {
			$json  = $request->get_json_params();
			$token = isset( $json['token'] ) ? $json['token'] : '';
		}

		$stored_token = get_option( 'alvobot_site_token', '' );

		if ( empty( $token ) || empty( $stored_token ) || ! hash_equals( (string) $stored_token, (string) $token ) ) {
			return new WP_Error(
				'unauthorized',
				'Token inválido',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/* =========================================================================
	 * CALLBACKS
	 * ====================================================================== */

	/**
	 * GET /quizzes
	 */
	public function list_quizzes( $request ) {
		$quizzes = get_option( 'alvobot_qbv2_data', array() );

		// Attach lead count to each quiz
		$result = array();
		foreach ( $quizzes as $quiz ) {
			$leads               = get_option( 'alvobot_qbv2_leads_' . $quiz['id'], array() );
			$quiz['lead_count']  = count( $leads );
			$quiz['full_page_url'] = home_url( '/?alvobot_quiz=' . $quiz['id'] );
			$quiz['shortcode']     = '[alvobot_quiz id="' . $quiz['id'] . '"]';
			$result[]            = $quiz;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * GET /quizzes/{id}
	 */
	public function get_quiz( $request ) {
		$id      = $request->get_param( 'id' );
		$quizzes = get_option( 'alvobot_qbv2_data', array() );

		if ( ! isset( $quizzes[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Quiz não encontrado', array( 'status' => 404 ) );
		}

		$quiz                  = $quizzes[ $id ];
		$leads                 = get_option( 'alvobot_qbv2_leads_' . $id, array() );
		$quiz['lead_count']    = count( $leads );
		$quiz['full_page_url'] = home_url( '/?alvobot_quiz=' . $id );
		$quiz['shortcode']     = '[alvobot_quiz id="' . $id . '"]';

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $quiz,
			),
			200
		);
	}

	/**
	 * POST /quizzes
	 */
	public function create_quiz( $request ) {
		$json = $request->get_json_params();

		if ( empty( $json['title'] ) ) {
			return new WP_Error( 'missing_title', 'O campo title é obrigatório', array( 'status' => 400 ) );
		}

		$quiz = $this->sanitize_quiz( $json );
		$quiz['id'] = 'qb_' . substr( md5( uniqid( '', true ) ), 0, 8 );

		$quizzes = get_option( 'alvobot_qbv2_data', array() );
		$quizzes[ $quiz['id'] ] = $quiz;
		update_option( 'alvobot_qbv2_data', $quizzes, false );

		$quiz['full_page_url'] = home_url( '/?alvobot_quiz=' . $quiz['id'] );
		$quiz['shortcode']     = '[alvobot_quiz id="' . $quiz['id'] . '"]';

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $quiz,
			),
			201
		);
	}

	/**
	 * PUT /quizzes/{id}
	 */
	public function update_quiz( $request ) {
		$id      = $request->get_param( 'id' );
		$quizzes = get_option( 'alvobot_qbv2_data', array() );

		if ( ! isset( $quizzes[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Quiz não encontrado', array( 'status' => 404 ) );
		}

		$json    = $request->get_json_params();
		$current = $quizzes[ $id ];

		// Merge: only overwrite fields that were sent
		$merged = array_merge( $current, $json );
		$merged['id'] = $id; // prevent ID change

		$quiz = $this->sanitize_quiz( $merged );

		$quizzes[ $id ] = $quiz;
		update_option( 'alvobot_qbv2_data', $quizzes, false );

		$quiz['full_page_url'] = home_url( '/?alvobot_quiz=' . $id );
		$quiz['shortcode']     = '[alvobot_quiz id="' . $id . '"]';

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $quiz,
			),
			200
		);
	}

	/**
	 * DELETE /quizzes/{id}
	 */
	public function delete_quiz( $request ) {
		$id      = $request->get_param( 'id' );
		$quizzes = get_option( 'alvobot_qbv2_data', array() );

		if ( ! isset( $quizzes[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Quiz não encontrado', array( 'status' => 404 ) );
		}

		unset( $quizzes[ $id ] );
		update_option( 'alvobot_qbv2_data', $quizzes, false );
		delete_option( 'alvobot_qbv2_leads_' . $id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Quiz excluído com sucesso',
			),
			200
		);
	}

	/**
	 * GET /quizzes/{id}/leads
	 */
	public function get_leads( $request ) {
		$id      = $request->get_param( 'id' );
		$quizzes = get_option( 'alvobot_qbv2_data', array() );

		if ( ! isset( $quizzes[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Quiz não encontrado', array( 'status' => 404 ) );
		}

		$leads = get_option( 'alvobot_qbv2_leads_' . $id, array() );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $leads,
				'total'   => count( $leads ),
			),
			200
		);
	}

	/* =========================================================================
	 * ARGS DEFINITION
	 * ====================================================================== */

	private function get_quiz_args( $title_required ) {
		return array(
			'token'         => array(
				'required' => false,
				'type'     => 'string',
			),
			'title'         => array(
				'required'          => $title_required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'headline'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'subheadline'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'logo_url'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'color_header'  => array(
				'required' => false,
				'type'     => 'string',
			),
			'color_primary' => array(
				'required' => false,
				'type'     => 'string',
			),
			'color_accent'  => array(
				'required' => false,
				'type'     => 'string',
			),
			'redirect_url'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'enable_lead'   => array(
				'required' => false,
				'type'     => 'boolean',
			),
			'lead_title'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'lead_subtitle' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'lead_btn_text' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'footer_text'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'footer_links'  => array(
				'required' => false,
				'type'     => 'array',
			),
			'questions'     => array(
				'required' => false,
				'type'     => 'array',
			),
		);
	}

	/* =========================================================================
	 * SANITIZATION (same logic as main class)
	 * ====================================================================== */

	private function sanitize_quiz( $raw ) {
		$quiz = array(
			'id'            => sanitize_text_field( $raw['id'] ?? '' ),
			'title'         => sanitize_text_field( $raw['title'] ?? '' ),
			'headline'      => sanitize_text_field( $raw['headline'] ?? '' ),
			'subheadline'   => sanitize_text_field( $raw['subheadline'] ?? '' ),
			'logo_url'      => esc_url_raw( $raw['logo_url'] ?? '' ),
			'color_header'  => sanitize_hex_color( $raw['color_header'] ?? '#1a3a2a' ) ?: '#1a3a2a',
			'color_primary' => sanitize_hex_color( $raw['color_primary'] ?? '#22c55e' ) ?: '#22c55e',
			'color_accent'  => sanitize_hex_color( $raw['color_accent'] ?? '#f97316' ) ?: '#f97316',
			'redirect_url'  => esc_url_raw( $raw['redirect_url'] ?? '' ),
			'enable_lead'   => ! empty( $raw['enable_lead'] ),
			'lead_title'    => sanitize_text_field( $raw['lead_title'] ?? '' ),
			'lead_subtitle' => sanitize_text_field( $raw['lead_subtitle'] ?? '' ),
			'lead_btn_text' => sanitize_text_field( $raw['lead_btn_text'] ?? 'ENVIAR RESPOSTAS' ),
			'footer_text'   => sanitize_text_field( $raw['footer_text'] ?? '' ),
			'footer_links'  => array(),
			'questions'     => array(),
		);

		if ( ! empty( $raw['footer_links'] ) && is_array( $raw['footer_links'] ) ) {
			foreach ( $raw['footer_links'] as $link ) {
				if ( empty( $link['text'] ) ) {
					continue;
				}
				$quiz['footer_links'][] = array(
					'text' => sanitize_text_field( $link['text'] ),
					'url'  => esc_url_raw( $link['url'] ?? '' ),
				);
			}
		}

		if ( ! empty( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
			foreach ( $raw['questions'] as $q ) {
				if ( empty( $q['text'] ) ) {
					continue;
				}
				$question = array(
					'id'      => sanitize_text_field( $q['id'] ?? ( 'q_' . substr( md5( uniqid( '', true ) ), 0, 6 ) ) ),
					'text'    => sanitize_text_field( $q['text'] ),
					'answers' => array(),
				);
				if ( ! empty( $q['answers'] ) && is_array( $q['answers'] ) ) {
					foreach ( $q['answers'] as $a ) {
						if ( empty( $a['text'] ) ) {
							continue;
						}
						$question['answers'][] = array(
							'id'   => sanitize_text_field( $a['id'] ?? ( 'a_' . substr( md5( uniqid( '', true ) ), 0, 6 ) ) ),
							'text' => sanitize_text_field( $a['text'] ),
							'url'  => esc_url_raw( $a['url'] ?? '' ),
						);
					}
				}
				$quiz['questions'][] = $question;
			}
		}

		return $quiz;
	}
}

// Initialize
add_action( 'init', array( 'AlvoBotPro_QuizBuilderV2_API', 'get_instance' ) );
