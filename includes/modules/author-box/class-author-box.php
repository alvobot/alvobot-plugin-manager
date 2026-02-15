<?php
/**
 * Classe do módulo Author Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/shared/countries-languages.php';

class AlvoBotPro_AuthorBox {
	private $version;
	private $plugin_name = 'alvobot-pro';
	private $option_name = 'alvobot_pro_author_box';

	public function __construct() {
		AlvoBotPro::debug_log( 'author_box', 'Inicializando módulo Author Box' );
		$this->version = ALVOBOT_PRO_VERSION;
		$this->load_translations_class();
		$this->init();
		AlvoBotPro::debug_log( 'author_box', 'Módulo Author Box inicializado com sucesso' );
	}

	/**
	 * Carrega a classe de traduções
	 */
	private function load_translations_class() {
		$translations_file = __DIR__ . '/includes/class-author-box-translations.php';
		if ( file_exists( $translations_file ) ) {
			require_once $translations_file;
			AlvoBotPro::debug_log( 'author_box', 'Classe de traduções carregada com sucesso' );
		} else {
			AlvoBotPro::debug_log( 'author_box', 'Arquivo de traduções não encontrado: ' . $translations_file );
		}
	}

	public function init() {
		// Carrega o arquivo da API
		require_once plugin_dir_path( __FILE__ ) . 'php/api.php';

		// Garante que as opções padrão existam
		$options = get_option( $this->option_name );
		if ( $options === false ) {
			update_option( $this->option_name, $this->get_default_options() );
		}

		// Registra configurações
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Adiciona campos ao perfil do usuário
		add_action( 'show_user_profile', array( $this, 'add_social_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'add_social_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_social_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_social_fields' ) );

		// Adiciona o Author Box ao conteúdo (prioridade mais alta para garantir que execute por último)
		remove_filter( 'the_content', array( $this, 'append_author_box' ) ); // Remove primeiro para evitar duplicatas
		add_filter( 'the_content', array( $this, 'append_author_box' ), 999 );

		// Registra scripts e estilos
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX para biografias multilíngues
		add_action( 'wp_ajax_ab_save_bios', array( $this, 'ajax_save_bios' ) );
		add_action( 'wp_ajax_ab_load_user_bios', array( $this, 'ajax_load_user_bios' ) );
	}

	private function get_default_options() {
		// Usa sistema de traduções se disponível
		$default_title = class_exists( 'Alvobot_AuthorBox_Translations' )
			? Alvobot_AuthorBox_Translations::get_translation( 'about_author' )
			: __( 'Sobre o Autor', 'alvobot-pro' );

		return array(
			'display_on_posts'           => 1,
			'display_on_pages'           => 0,
			'show_description'           => 1,
			'hide_theme_author_box'      => 1,
			'title_use_auto_translation' => 1,
			'avatar_size'                => 96,
			'title_text'                 => $default_title,
		);
	}

	public function register_settings() {
		register_setting(
			$this->option_name,
			$this->option_name,
			array( $this, 'sanitize_options' )
		);

		add_settings_section(
			'author_box_section',
			'',
			null,
			$this->option_name
		);

		$this->add_settings_fields();
	}

	private function add_settings_fields() {
		// Obtém traduções
		$t = class_exists( 'Alvobot_AuthorBox_Translations' ) ? 'Alvobot_AuthorBox_Translations' : null;

		// Título do Author Box
		add_settings_field(
			'title_text',
			$t ? $t::get_translation( 'title' ) : __( 'Título', 'alvobot-pro' ),
			array( $this, 'render_text_field' ),
			$this->option_name,
			'author_box_section',
			array(
				'label_for'   => 'title_text',
				'name'        => 'title_text',
				'description' => $t ? $t::get_translation( 'title_description' ) : __( 'Título que será exibido acima do Author Box.', 'alvobot-pro' ),
			)
		);

		// Tradução automática do título — só exibe quando Polylang ou WPML está ativo
		if ( function_exists( 'PLL' ) || function_exists( 'icl_get_languages' ) ) {
			add_settings_field(
				'title_use_auto_translation',
				$t ? $t::get_translation( 'auto_translate_title' ) : __( 'Traduzir título automaticamente', 'alvobot-pro' ),
				array( $this, 'render_checkbox_field' ),
				$this->option_name,
				'author_box_section',
				array(
					'label_for'   => 'title_use_auto_translation',
					'name'        => 'title_use_auto_translation',
					'description' => $t ? $t::get_translation( 'auto_translate_title_desc' ) : __( 'Quando ativo, o título se adapta automaticamente ao idioma da página.', 'alvobot-pro' ),
				)
			);
		}

		// Exibir em Posts
		add_settings_field(
			'display_on_posts',
			$t ? $t::get_translation( 'display' ) : __( 'Exibição', 'alvobot-pro' ),
			array( $this, 'render_display_options' ),
			$this->option_name,
			'author_box_section',
			array(
				'label_for' => 'display_on_posts',
			)
		);

		// Exibir Descrição
		add_settings_field(
			'show_description',
			$t ? $t::get_translation( 'biography' ) : __( 'Biografia', 'alvobot-pro' ),
			array( $this, 'render_checkbox_field' ),
			$this->option_name,
			'author_box_section',
			array(
				'label_for'   => 'show_description',
				'name'        => 'show_description',
				'description' => $t ? $t::get_translation( 'show_description' ) : __( 'Exibir a biografia do autor.', 'alvobot-pro' ),
			)
		);
	}

	public function render_settings_section() {
		echo wp_kses_post( '<p>' . esc_html__( 'Configure as opções do Author Box abaixo.', 'alvobot-pro' ) . '</p>' );
	}

	public function render_text_field( $args ) {
		$options = get_option( $this->option_name );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
		?>
		<input type="text" 
				id="<?php echo esc_attr( $args['label_for'] ); ?>" 
				name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $args['name'] ); ?>]" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_number_field( $args ) {
		$options = get_option( $this->option_name );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
		?>
		<input type="number" 
				id="<?php echo esc_attr( $args['label_for'] ); ?>" 
				name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $args['name'] ); ?>]" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="small-text" 
				min="<?php echo esc_attr( $args['min'] ); ?>" 
				max="<?php echo esc_attr( $args['max'] ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_checkbox_field( $args ) {
		$options = get_option( $this->option_name );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
		?>
		<label>
			<input type="checkbox" 
					id="<?php echo esc_attr( $args['label_for'] ); ?>" 
					name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $args['name'] ); ?>]" 
					<?php checked( ! empty( $value ) ); ?>>
			<?php echo esc_html( $args['description'] ); ?>
		</label>
		<?php
	}

	public function render_display_options( $args ) {
		$options = get_option( $this->option_name );
		$t       = class_exists( 'Alvobot_AuthorBox_Translations' ) ? 'Alvobot_AuthorBox_Translations' : null;
		?>
		<div class="display-options">
			<label>
				<input type="checkbox"
						id="display_on_posts"
						name="<?php echo esc_attr( $this->option_name ); ?>[display_on_posts]"
						<?php checked( ! empty( $options['display_on_posts'] ) ); ?>>
				<?php echo esc_html( $t ? $t::get_translation( 'display_on_posts' ) : __( 'Exibir em Posts', 'alvobot-pro' ) ); ?>
			</label>
			<br>
			<label>
				<input type="checkbox"
						id="display_on_pages"
						name="<?php echo esc_attr( $this->option_name ); ?>[display_on_pages]"
						<?php checked( ! empty( $options['display_on_pages'] ) ); ?>>
				<?php echo esc_html( $t ? $t::get_translation( 'display_on_pages' ) : __( 'Exibir em Páginas', 'alvobot-pro' ) ); ?>
			</label>
			<br>
			<label>
				<input type="checkbox"
						id="hide_theme_author_box"
						name="<?php echo esc_attr( $this->option_name ); ?>[hide_theme_author_box]"
						<?php checked( ! empty( $options['hide_theme_author_box'] ) ); ?>>
				<?php esc_html_e( 'Ocultar Author Box do tema', 'alvobot-pro' ); ?>
			</label>
			<p class="description">
				<?php echo esc_html( $t ? $t::get_translation( 'select_display_location' ) : __( 'Selecione onde o Author Box será exibido.', 'alvobot-pro' ) ); ?>
			</p>
		</div>
		<?php
	}

	public function sanitize_options( $input ) {
		$defaults  = $this->get_default_options();
		$sanitized = array();

		// Display on posts
		$sanitized['display_on_posts'] = isset( $input['display_on_posts'] ) ? 1 : 0;

		// Display on pages
		$sanitized['display_on_pages'] = isset( $input['display_on_pages'] ) ? 1 : 0;

		// Show description
		$sanitized['show_description'] = isset( $input['show_description'] ) ? 1 : 0;

		// Hide theme author box
		$sanitized['hide_theme_author_box'] = isset( $input['hide_theme_author_box'] ) ? 1 : 0;

		// Title auto translation
		$sanitized['title_use_auto_translation'] = isset( $input['title_use_auto_translation'] ) ? 1 : 0;

		// Avatar size
		$sanitized['avatar_size'] = isset( $input['avatar_size'] ) ?
			absint( $input['avatar_size'] ) : $defaults['avatar_size'];

		// Title text
		$sanitized['title_text'] = isset( $input['title_text'] ) ?
			sanitize_text_field( $input['title_text'] ) : $defaults['title_text'];

		return $sanitized;
	}

	public function add_social_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$t = class_exists( 'Alvobot_AuthorBox_Translations' ) ? 'Alvobot_AuthorBox_Translations' : null;
		?>
		<div class="alvobot-pro-wrap">
			<div class="alvobot-pro-header">
				<h1><?php echo esc_html( $t ? $t::get_translation( 'author_box_settings' ) : __( 'Configurações do Author Box', 'alvobot-pro' ) ); ?></h1>
				<p><?php echo esc_html( $t ? $t::get_translation( 'personalize_info' ) : __( 'Personalize como suas informações de autor aparecem no Author Box.', 'alvobot-pro' ) ); ?></p>
			</div>

			<!-- Preview Card -->
			<div class="alvobot-pro-preview-card">
				<h3><?php echo esc_html( $t ? $t::get_translation( 'live_preview' ) : __( 'Preview em Tempo Real', 'alvobot-pro' ) ); ?></h3>
				<div class="ab-avatar-preview" style="margin-bottom: 10px;">
					<?php
					$custom_avatar_id = get_user_meta( $user->ID, 'ab_custom_avatar_id', true );
					$avatar_url       = $custom_avatar_id ? wp_get_attachment_image_url( $custom_avatar_id, 'thumbnail' ) : get_avatar_url( $user->ID );
					?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" />
				</div>
			</div>

			<!-- Settings Card -->
			<div class="alvobot-pro-card">
				<div class="alvobot-pro-card-header">
					<h2><?php echo esc_html( $t ? $t::get_translation( 'avatar_settings' ) : __( 'Configurações do Avatar', 'alvobot-pro' ) ); ?></h2>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th>
							<label for="ab_custom_avatar"><?php echo esc_html( $t ? $t::get_translation( 'custom_avatar' ) : __( 'Avatar Personalizado', 'alvobot-pro' ) ); ?></label>
						</th>
						<td>
							<input type="hidden" name="ab_custom_avatar_id" id="ab_custom_avatar_id"
									value="<?php echo esc_attr( $custom_avatar_id ); ?>" />
							<input type="button" class="button" id="ab_upload_avatar_button"
									value="<?php echo esc_attr( $t ? $t::get_translation( 'select_image' ) : __( 'Selecionar Imagem', 'alvobot-pro' ) ); ?>" />
							<?php if ( $custom_avatar_id ) : ?>
								<input type="button" class="button" id="ab_remove_avatar_button"
										value="<?php echo esc_attr( $t ? $t::get_translation( 'remove_image' ) : __( 'Remover Imagem', 'alvobot-pro' ) ); ?>" />
							<?php endif; ?>
							<p class="description">
								<?php echo esc_html( $t ? $t::get_translation( 'avatar_description' ) : __( 'Esta imagem será usada no Author Box em vez do seu Gravatar.', 'alvobot-pro' ) ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	public function save_social_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// Salva o avatar personalizado
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress core in user profile update handler.
		if ( isset( $_POST['ab_custom_avatar_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress core in user profile update handler.
			update_user_meta( $user_id, 'ab_custom_avatar_id', sanitize_text_field( wp_unslash( $_POST['ab_custom_avatar_id'] ) ) );
		}
	}

	public function append_author_box( $content ) {
		AlvoBotPro::debug_log( 'author_box', 'Verificando se deve exibir author box para post ID: ' . get_the_ID() );

		// Obtém as opções
		$options = get_option( $this->option_name );

		// Verifica se deve exibir o author box
		if ( ! is_singular() ) {
			AlvoBotPro::debug_log( 'author_box', 'Não é página singular - author box não será exibido' );
			return $content;
		}

		// Verifica o tipo de post atual
		$is_single = is_single();
		$is_page   = is_page();

		// Verifica se deve exibir baseado nas configurações
		$show_on_posts = ! empty( $options['display_on_posts'] );
		$show_on_pages = ! empty( $options['display_on_pages'] );

		AlvoBotPro::debug_log( 'author_box', "Configurações: Posts={$show_on_posts}, Pages={$show_on_pages}, Current: Single={$is_single}, Page={$is_page}" );

		// Retorna apenas o conteúdo se não deve exibir
		if ( ( $is_single && ! $show_on_posts ) || ( $is_page && ! $show_on_pages ) ) {
			AlvoBotPro::debug_log( 'author_box', 'Author box desabilitado para este tipo de conteúdo' );
			return $content;
		}

		// Obtém o ID do autor
		$author_id = get_the_author_meta( 'ID' );

		if ( ! $author_id ) {
			AlvoBotPro::debug_log( 'author_box', 'Autor não encontrado - author box não será exibido' );
			return $content;
		}

		AlvoBotPro::debug_log( 'author_box', "Exibindo author box para autor ID: {$author_id}" );

		// Gera o HTML do author box
		$author_box = $this->get_author_box_html();

		// Retorna o conteúdo com o author box
		return $content . $author_box;
	}

	private function get_author_box_html() {
		$options = get_option( $this->option_name );

		// Se estiver na página de admin, usa o usuário atual
		if ( is_admin() ) {
			$author             = wp_get_current_user();
			$author_id          = $author->ID;
			$author_name        = $author->display_name;
			$author_description = $author->description;
			$author_url         = get_author_posts_url( $author_id );
		} else {
			$author_id          = get_the_author_meta( 'ID' );
			$author_name        = get_the_author();
			$author_description = get_the_author_meta( 'description' );
			$author_url         = get_author_posts_url( $author_id );

			// Bio multilíngue: tenta carregar bio do idioma atual
			if ( class_exists( 'Alvobot_AuthorBox_Translations' ) ) {
				$current_lang = Alvobot_AuthorBox_Translations::get_current_language();
				$lang_bio     = get_user_meta( $author_id, 'ab_bio_' . $current_lang, true );
				if ( ! empty( $lang_bio ) ) {
					$author_description = $lang_bio;
				}
			}
		}

		// Verifica se existe um avatar personalizado
		$custom_avatar_id = get_user_meta( $author_id, 'ab_custom_avatar_id', true );
		$avatar_html      = '';

		if ( $custom_avatar_id ) {
			$avatar_url = wp_get_attachment_image_url( $custom_avatar_id, 'thumbnail' );
			if ( $avatar_url ) {
				$avatar_html = sprintf(
					'<img src="%s" alt="%s" class="avatar" width="%d" height="%d" />',
					esc_url( $avatar_url ),
					esc_attr( $author_name ),
					$options['avatar_size'],
					$options['avatar_size']
				);
			}
		}

		// Se não houver avatar personalizado, usa o gravatar padrão
		if ( empty( $avatar_html ) ) {
			$avatar_html = get_avatar( $author_id, $options['avatar_size'] );
		}

		ob_start();
		?>
		<?php
		// Determina o texto do título
		$title_text = '';
		if ( ! empty( $options['title_use_auto_translation'] ) && class_exists( 'Alvobot_AuthorBox_Translations' ) ) {
			$title_text = Alvobot_AuthorBox_Translations::get_translation( 'about_author' );
		} elseif ( ! empty( $options['title_text'] ) ) {
			$title_text = $options['title_text'];
		}
		?>
		<div class="alvobot-author-box">
			<?php if ( ! empty( $title_text ) ) : ?>
				<h3 class="author-box-title"><?php echo esc_html( $title_text ); ?></h3>
			<?php endif; ?>
			
			<div class="author-content">
				<div class="author-avatar">
					<?php echo wp_kses_post( $avatar_html ); ?>
				</div>
				
				<div class="author-info">
					<h4 class="author-name">
						<a href="<?php echo esc_url( $author_url ); ?>">
							<?php echo esc_html( $author_name ); ?>
						</a>
					</h4>

					<?php if ( ! empty( $options['show_description'] ) && ! empty( $author_description ) ) : ?>
						<div class="author-description">
							<?php echo wp_kses_post( $author_description ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$t = class_exists( 'Alvobot_AuthorBox_Translations' ) ? 'Alvobot_AuthorBox_Translations' : null;
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div class="alvobot-admin-header">
					<div class="alvobot-header-icon">
						<i data-lucide="user-circle" class="alvobot-icon"></i>
					</div>
					<div class="alvobot-header-content">
						<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
						<p><?php echo esc_html( $t ? $t::get_translation( 'display_settings' ) : __( 'Configure as opções de exibição do Author Box em seus posts e páginas.', 'alvobot-pro' ) ); ?></p>
					</div>
				</div>

				<div class="alvobot-notice-container">
					<?php settings_errors(); ?>
				</div>

				<!-- Settings Card -->
				<div class="alvobot-card">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title"><?php echo esc_html( $t ? $t::get_translation( 'settings' ) : __( 'Configurações', 'alvobot-pro' ) ); ?></h2>
						</div>
					</div>
					<div class="alvobot-card-body">
						<form action="options.php" method="post">
							<?php
							settings_fields( $this->option_name );
							do_settings_sections( $this->option_name );
							submit_button(
								$t ? $t::get_translation( 'save_changes' ) : __( 'Salvar Alterações', 'alvobot-pro' ),
								'alvobot-btn alvobot-btn-primary'
							);
							?>
						</form>
					</div>
				</div>

				<!-- AI Author Generation Card -->
				<?php if ( get_option( 'alvobot_site_token' ) ) : ?>
					<?php
					$ai_api            = class_exists( 'AlvoBotPro_AI_API' ) ? AlvoBotPro_AI_API::get_instance() : null;
					$ai_cost_generate  = $ai_api ? $ai_api->get_action_cost( 'generate_author' ) : 3;
					$ai_cost_translate = $ai_api ? ( $ai_api->get_action_cost( 'translate_bio' ) ?: 1 ) : 1;
					$ai_multilingual   = function_exists( 'PLL' ) || function_exists( 'icl_get_languages' );
					$ai_languages      = $ai_multilingual && class_exists( 'Alvobot_AuthorBox_Translations' )
						? Alvobot_AuthorBox_Translations::get_site_languages()
						: array();
					$ai_num_langs      = count( $ai_languages );
					$ai_cost           = $ai_multilingual && $ai_num_langs > 1
						? $ai_cost_generate + ( $ai_num_langs - 1 ) * $ai_cost_translate
						: $ai_cost_generate;
					$wp_users          = get_users(
						array(
							'role__in' => array( 'administrator', 'editor', 'author' ),
							'orderby'  => 'display_name',
						)
					);
					$locale            = get_locale();
					$default_lang      = substr( $locale, 0, 2 );
					$locale_country    = array(
						'pt_BR' => 'BR',
						'en_US' => 'US',
						'en_GB' => 'GB',
						'es_ES' => 'ES',
						'fr_FR' => 'FR',
						'de_DE' => 'DE',
						'it_IT' => 'IT',
					);
					$default_country   = isset( $locale_country[ $locale ] ) ? $locale_country[ $locale ] : 'BR';
					$countries         = alvobot_get_countries();
					$all_languages     = alvobot_get_languages();
					?>
				<div class="alvobot-card alvobot-ai-section">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title"><?php esc_html_e( 'Gerar Autor com IA', 'alvobot-pro' ); ?></h2>
							<p class="alvobot-card-subtitle"><?php esc_html_e( 'Crie um perfil de autor fictício completo usando inteligência artificial.', 'alvobot-pro' ); ?></p>
						</div>
						<span class="alvobot-ai-cost-badge">
							<i data-lucide="coins" class="alvobot-icon" style="width: 14px; height: 14px;"></i>
							<?php echo esc_html( $ai_cost ); ?> <?php esc_html_e( 'créditos', 'alvobot-pro' ); ?>
						</span>
					</div>
					<div class="alvobot-card-body">
						<!-- AI Generation Form -->
						<div id="alvobot-ai-author-form">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="ai-author-niche"><?php esc_html_e( 'Nicho do site', 'alvobot-pro' ); ?> <span style="color: var(--alvobot-error);">*</span></label>
									</th>
									<td>
										<input type="text" id="ai-author-niche" class="regular-text" placeholder="<?php esc_attr_e( 'Ex: tecnologia, saúde, finanças, culinária...', 'alvobot-pro' ); ?>" />
										<p class="description"><?php esc_html_e( 'O nicho principal do seu site. Isso ajuda a IA a criar um autor relevante.', 'alvobot-pro' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="ai-author-country"><?php esc_html_e( 'País', 'alvobot-pro' ); ?></label>
									</th>
									<td>
										<select id="ai-author-country" class="regular-text">
											<?php foreach ( $countries as $code => $name ) : ?>
												<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $code, $default_country ); ?>>
													<?php echo esc_html( $name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'País de origem do autor fictício.', 'alvobot-pro' ); ?></p>
									</td>
								</tr>
								<?php if ( $ai_multilingual ) : ?>
								<tr>
									<th scope="row">
										<label><?php esc_html_e( 'Idiomas', 'alvobot-pro' ); ?></label>
									</th>
									<td>
										<div style="display: flex; gap: 6px; flex-wrap: wrap;">
											<?php foreach ( $ai_languages as $code => $name ) : ?>
												<span class="alvobot-badge" style="font-size: var(--alvobot-font-size-xs); padding: var(--alvobot-space-xs) var(--alvobot-space-md);"><?php echo esc_html( strtoupper( $code ) . ' — ' . $name ); ?></span>
											<?php endforeach; ?>
										</div>
										<p class="description"><?php printf( esc_html__( 'O autor será gerado no primeiro idioma (%1$d créditos) e a bio traduzida automaticamente para os demais (%2$d crédito por tradução).', 'alvobot-pro' ), intval( $ai_cost_generate ), intval( $ai_cost_translate ) ); ?></p>
										<!-- Hidden select for JS compatibility -->
										<select id="ai-author-language" style="display: none;">
											<?php foreach ( $all_languages as $code => $label ) : ?>
												<option value="<?php echo esc_attr( $label ); ?>"
														data-code="<?php echo esc_attr( $code ); ?>"
														<?php selected( $code, $default_lang ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php else : ?>
								<tr>
									<th scope="row">
										<label for="ai-author-language"><?php echo $t ? esc_html( $t::get_translation( 'bio_language' ) ) : esc_html__( 'Idioma da biografia', 'alvobot-pro' ); ?></label>
									</th>
									<td>
										<select id="ai-author-language" class="regular-text">
											<?php foreach ( $all_languages as $code => $label ) : ?>
												<option value="<?php echo esc_attr( $label ); ?>"
														data-code="<?php echo esc_attr( $code ); ?>"
														<?php selected( $code, $default_lang ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php echo $t ? esc_html( $t::get_translation( 'bio_language_desc' ) ) : esc_html__( 'A biografia gerada será salva para este idioma específico.', 'alvobot-pro' ); ?></p>
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<th scope="row">
										<label for="ai-author-user"><?php esc_html_e( 'Aplicar ao usuário', 'alvobot-pro' ); ?></label>
									</th>
									<td>
										<select id="ai-author-user" class="regular-text">
											<?php foreach ( $wp_users as $wp_user ) : ?>
												<option value="<?php echo esc_attr( $wp_user->ID ); ?>">
													<?php echo esc_html( $wp_user->display_name . ' (' . $wp_user->user_login . ')' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Selecione o usuário WordPress que receberá o perfil gerado.', 'alvobot-pro' ); ?></p>
									</td>
								</tr>
							</table>
							<div style="padding: 0 0 var(--alvobot-space-md);">
								<button type="button" id="alvobot-ai-generate-author" class="alvobot-btn alvobot-btn-primary" style="display: inline-flex; align-items: center; gap: var(--alvobot-space-sm);">
									<i data-lucide="sparkles" class="alvobot-icon" style="width: 18px; height: 18px;"></i>
									<?php esc_html_e( 'Gerar Autor', 'alvobot-pro' ); ?>
								</button>
							</div>
						</div>

						<!-- AI Loading -->
						<div id="alvobot-ai-author-loading" style="display: none; text-align: center; padding: var(--alvobot-space-3xl) var(--alvobot-space-xl);">
							<div class="alvobot-ai-loading-spinner"></div>
							<p style="margin-top: var(--alvobot-space-lg); font-size: var(--alvobot-font-size-base); color: var(--alvobot-gray-600);"><?php esc_html_e( 'Gerando perfil de autor com IA... Isso pode levar alguns segundos.', 'alvobot-pro' ); ?></p>
						</div>

						<!-- AI Preview (generated result) -->
						<div id="alvobot-ai-author-preview" class="alvobot-ai-preview" style="display: none;">
							<h3 style="margin-top: 0;"><?php esc_html_e( 'Autor Gerado — Revise e edite antes de aplicar', 'alvobot-pro' ); ?></h3>
							<div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
								<div style="flex-shrink: 0; text-align: center;">
									<img id="alvobot-ai-author-avatar" src="" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--alvobot-gray-300);" />
								</div>
								<div style="flex: 1; min-width: 280px;">
									<table class="form-table" role="presentation" style="margin-top: 0;">
										<tr>
											<th scope="row"><label for="ai-preview-name"><?php esc_html_e( 'Nome', 'alvobot-pro' ); ?></label></th>
											<td><input type="text" id="ai-preview-name" class="regular-text" /></td>
										</tr>
										<tr id="ai-preview-bio-row">
											<th scope="row"><label for="ai-preview-bio"><?php esc_html_e( 'Biografia', 'alvobot-pro' ); ?></label></th>
											<td>
												<textarea id="ai-preview-bio" rows="5" class="large-text"></textarea>
												<div id="ai-preview-bio-multilingual" style="display: none;"></div>
											</td>
										</tr>
									</table>
								</div>
							</div>
							<input type="hidden" id="ai-preview-avatar-url" value="" />
							<div style="padding: var(--alvobot-space-lg) 0 0; display: flex; gap: var(--alvobot-space-md); flex-wrap: wrap;">
								<button type="button" id="alvobot-ai-apply-author" class="alvobot-btn alvobot-btn-primary" style="display: inline-flex; align-items: center; gap: var(--alvobot-space-sm);">
									<i data-lucide="check-circle" class="alvobot-icon" style="width: 18px; height: 18px;"></i>
									<?php esc_html_e( 'Aplicar ao Autor', 'alvobot-pro' ); ?>
								</button>
								<button type="button" id="alvobot-ai-regenerate-author" class="alvobot-btn alvobot-btn-secondary" style="display: inline-flex; align-items: center; gap: var(--alvobot-space-sm);">
									<i data-lucide="refresh-cw" class="alvobot-icon" style="width: 18px; height: 18px;"></i>
									<?php esc_html_e( 'Gerar Outro', 'alvobot-pro' ); ?>
								</button>
							</div>
						</div>

						<!-- AI Error -->
						<div id="alvobot-ai-author-error" style="display: none; padding: var(--alvobot-space-lg); background: var(--alvobot-error-bg); border: 1px solid var(--alvobot-error); border-radius: var(--alvobot-radius-md); color: var(--alvobot-error-dark);">
							<strong><?php esc_html_e( 'Erro:', 'alvobot-pro' ); ?></strong> <span id="alvobot-ai-author-error-msg"></span>
						</div>

						<!-- AI Success -->
						<div id="alvobot-ai-author-success" style="display: none; padding: var(--alvobot-space-lg); background: var(--alvobot-success-bg); border: 1px solid var(--alvobot-success); border-radius: var(--alvobot-radius-md); color: var(--alvobot-success-dark);">
							<strong><?php esc_html_e( 'Sucesso!', 'alvobot-pro' ); ?></strong> <span id="alvobot-ai-author-success-msg"></span>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="alvobot-card">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title"><?php esc_html_e( 'Gerar Autor com IA', 'alvobot-pro' ); ?></h2>
						</div>
					</div>
					<div class="alvobot-card-body">
						<p style="color: var(--alvobot-gray-600);">
							<?php esc_html_e( 'Conecte seu site ao AlvoBot para utilizar a geração de autor com IA.', 'alvobot-pro' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=alvobot-pro' ) ); ?>"><?php esc_html_e( 'Ir para o Dashboard', 'alvobot-pro' ); ?></a>
						</p>
					</div>
				</div>
				<?php endif; ?>

				<!-- Multilingual Bios Card (only when Polylang or WPML is active) -->
				<?php
				$is_multilingual = function_exists( 'PLL' ) || function_exists( 'icl_get_languages' );
				if ( $is_multilingual ) :
					$bio_users      = get_users(
						array(
							'role__in' => array( 'administrator', 'editor', 'author' ),
							'orderby'  => 'display_name',
						)
					);
					$site_languages = class_exists( 'Alvobot_AuthorBox_Translations' )
					? Alvobot_AuthorBox_Translations::get_site_languages()
					: array(
						'pt' => 'Português',
						'en' => 'English',
					);
					$first_user_id  = ! empty( $bio_users ) ? $bio_users[0]->ID : 0;
					?>
				<div class="alvobot-card">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title">
								<i data-lucide="languages" class="alvobot-icon" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;"></i>
								<?php echo $t ? esc_html( $t::get_translation( 'multilingual_bios' ) ) : esc_html__( 'Biografias Multilíngues', 'alvobot-pro' ); ?>
							</h2>
							<p class="alvobot-card-subtitle">
								<?php echo $t ? esc_html( $t::get_translation( 'multilingual_bios_desc' ) ) : esc_html__( 'Gerencie biografias do autor por idioma.', 'alvobot-pro' ); ?>
							</p>
						</div>
					</div>
					<div class="alvobot-card-body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="bio-lang-user"><?php echo $t ? esc_html( $t::get_translation( 'select_user' ) ) : esc_html__( 'Selecione o Usuário', 'alvobot-pro' ); ?></label>
								</th>
								<td>
									<select id="bio-lang-user" class="regular-text">
										<?php foreach ( $bio_users as $bio_user ) : ?>
											<option value="<?php echo esc_attr( $bio_user->ID ); ?>">
												<?php echo esc_html( $bio_user->display_name . ' (' . $bio_user->user_login . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</table>

						<div class="ab-bio-lang-tabs">
							<div class="ab-bio-lang-tab-headers" role="tablist">
								<?php $first = true; foreach ( $site_languages as $code => $name ) : ?>
									<button type="button" class="ab-bio-lang-tab-btn <?php echo esc_attr( $first ? 'active' : '' ); ?>"
											data-lang="<?php echo esc_attr( $code ); ?>"
											role="tab" aria-selected="<?php echo esc_attr( $first ? 'true' : 'false' ); ?>">
										<?php echo esc_html( strtoupper( $code ) . ' — ' . $name ); ?>
									</button>
									<?php
									$first = false;
endforeach;
								?>
							</div>
							<?php $first = true; foreach ( $site_languages as $code => $name ) : ?>
								<div class="ab-bio-lang-tab-content <?php echo esc_attr( $first ? 'active' : '' ); ?>"
									data-lang="<?php echo esc_attr( $code ); ?>" role="tabpanel">
									<textarea id="ab-bio-<?php echo esc_attr( $code ); ?>"
												class="large-text ab-bio-textarea" rows="4"
												data-lang="<?php echo esc_attr( $code ); ?>"
												placeholder="<?php echo esc_attr( sprintf( __( 'Biografia em %s...', 'alvobot-pro' ), $name ) ); ?>"
									><?php echo esc_textarea( get_user_meta( $first_user_id, 'ab_bio_' . $code, true ) ); ?></textarea>
								</div>
								<?php
								$first = false;
endforeach;
							?>
						</div>

						<div style="padding: var(--alvobot-space-lg) 0 0; display: flex; gap: var(--alvobot-space-md); align-items: center;">
							<button type="button" id="ab-save-bios" class="alvobot-btn alvobot-btn-primary" style="display: inline-flex; align-items: center; gap: var(--alvobot-space-sm);">
								<i data-lucide="save" class="alvobot-icon" style="width:18px;height:18px;"></i>
								<?php echo $t ? esc_html( $t::get_translation( 'save_bios' ) ) : esc_html__( 'Salvar Biografias', 'alvobot-pro' ); ?>
							</button>
							<span id="ab-bio-save-status" style="display: none; font-size: var(--alvobot-font-size-base); color: var(--alvobot-success-dark);"></span>
						</div>
					</div>
				</div>
				<?php endif; // $is_multilingual ?>

				<!-- Preview Card -->
				<div class="alvobot-card">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title"><?php echo esc_html( $t ? $t::get_translation( 'preview_author_box' ) : __( 'Preview do Author Box', 'alvobot-pro' ) ); ?></h2>
						</div>
					</div>
					<div class="alvobot-card-body">
						<?php echo wp_kses_post( $this->get_author_box_html() ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function enqueue_public_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$options       = get_option( $this->option_name );
		$is_single     = is_single();
		$is_page       = is_page();
		$show_on_posts = ! empty( $options['display_on_posts'] );
		$show_on_pages = ! empty( $options['display_on_pages'] );

		// Só carrega se deve exibir o Author Box
		if ( ( $is_single && ! $show_on_posts ) || ( $is_page && ! $show_on_pages ) ) {
			return;
		}

		// Carrega estilos principais do AlvoBot
		$main_styles_url = plugins_url( 'assets/css/styles.css', dirname( dirname( __DIR__ ) ) );
		wp_enqueue_style(
			'alvobot-pro-main-styles',
			$main_styles_url,
			array(),
			$this->version
		);

		// Carrega estilos específicos do Author Box
		wp_enqueue_style(
			'alvobot-author-box-public',
			plugin_dir_url( __FILE__ ) . 'css/public.css',
			array( 'alvobot-pro-main-styles' ),
			$this->version
		);

		// Oculta o author box do tema se a opção estiver ativa
		if ( ! empty( $options['hide_theme_author_box'] ) ) {
			$hide_css = '
				.author-box:not(.alvobot-author-box),
				.author-bio:not(.alvobot-author-box),
				.post-author-box:not(.alvobot-author-box),
				.entry-author:not(.alvobot-author-box),
				.about-author:not(.alvobot-author-box),
				.single-post-author:not(.alvobot-author-box),
				.author-info:not(.alvobot-author-box .author-info),
				.post-author-info:not(.alvobot-author-box),
				.author-card:not(.alvobot-author-box),
				.author-description:not(.alvobot-author-box .author-description),
				.author-box-wrapper:not(.alvobot-author-box),
				.wp-block-post-author-biography,
				.wp-block-post-author,
				.saboxplugin-wrap,
				.pp-author-boxes-wrapper,
				[class*="author-box"]:not(.alvobot-author-box):not(.alvobot-author-box *),
				[class*="author-bio"]:not(.alvobot-author-box):not(.alvobot-author-box *),
				[class*="about-author"]:not(.alvobot-author-box):not(.alvobot-author-box *)
				{ display: none !important; }
			';
			wp_add_inline_style( 'alvobot-author-box-public', $hide_css );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		$is_profile_page = ( 'profile.php' === $hook || 'user-edit.php' === $hook );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection for asset loading, no data modification.
		$is_module_page  = ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'alvobot-pro-author-box' );

		if ( ! $is_profile_page && ! $is_module_page ) {
			return;
		}

		// Enqueue the main AlvoBot styles for admin
		wp_enqueue_style(
			'alvobot-admin',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/css/styles.css',
			array(),
			$this->version
		);

		// Carrega os scripts necessários para o media uploader em páginas de perfil
		if ( $is_profile_page ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'ab-custom-avatar',
				plugin_dir_url( __FILE__ ) . 'js/custom-avatar.js',
				array( 'jquery' ),
				$this->version,
				true
			);
		}

		// Enfileira o Color Picker e seus scripts específicos somente na página de configurações do módulo
		if ( $is_module_page ) {
			// Enqueue module-specific CSS with dependency on main styles
			wp_enqueue_style(
				'alvobot-author-box-admin',
				plugin_dir_url( __FILE__ ) . 'css/admin.css',
				array( 'alvobot-admin' ),  // Ensure module CSS depends on main styles
				$this->version
			);

			// Enqueue public CSS for preview in admin
			wp_enqueue_style(
				'alvobot-author-box-public-preview',
				plugin_dir_url( __FILE__ ) . 'css/public.css',
				array( 'alvobot-author-box-admin' ),
				$this->version
			);

			// Scripts de administração
			wp_enqueue_script(
				'alvobot-author-box-admin-js',
				plugin_dir_url( __FILE__ ) . 'js/admin.js',
				array( 'jquery', 'alvobot-ai' ),
				$this->version,
				true
			);

			$ab_is_multilingual = function_exists( 'PLL' ) || function_exists( 'icl_get_languages' );
			$ab_site_languages  = $ab_is_multilingual && class_exists( 'Alvobot_AuthorBox_Translations' )
				? Alvobot_AuthorBox_Translations::get_site_languages()
				: array();

			wp_localize_script(
				'alvobot-author-box-admin-js',
				'alvobotAuthorBox',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'ab_multilingual_nonce' ),
					'isMultilingual' => $ab_is_multilingual,
					'siteLanguages'  => $ab_site_languages,
				)
			);
		}
	}

	public function ajax_save_bios() {
		check_ajax_referer( 'ab_multilingual_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$bios    = isset( $_POST['bios'] ) ? wp_unslash( $_POST['bios'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-key below with sanitize_key and wp_kses_post.

		if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Usuário inválido' ) );
		}

		foreach ( $bios as $lang_code => $bio_text ) {
			$lang_code = sanitize_key( $lang_code );
			$bio_text  = wp_kses_post( $bio_text );
			if ( preg_match( '/^[a-z]{2}$/', $lang_code ) ) {
				update_user_meta( $user_id, 'ab_bio_' . $lang_code, $bio_text );
			}
		}

		// Também atualiza o description padrão do WP com o idioma principal do site
		$site_lang = substr( get_locale(), 0, 2 );
		if ( isset( $bios[ $site_lang ] ) && ! empty( $bios[ $site_lang ] ) ) {
			update_user_meta( $user_id, 'description', wp_kses_post( $bios[ $site_lang ] ) );
		}

		$t = class_exists( 'Alvobot_AuthorBox_Translations' ) ? 'Alvobot_AuthorBox_Translations' : null;
		wp_send_json_success(
			array(
				'message' => $t ? $t::get_translation( 'bios_saved' ) : 'Biografias salvas com sucesso.',
			)
		);
	}

	public function ajax_load_user_bios() {
		check_ajax_referer( 'ab_multilingual_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Usuário inválido' ) );
		}

		$site_languages = class_exists( 'Alvobot_AuthorBox_Translations' )
			? Alvobot_AuthorBox_Translations::get_site_languages()
			: array( 'pt' => 'Português' );

		$bios = array();
		foreach ( array_keys( $site_languages ) as $lang_code ) {
			$bios[ $lang_code ] = get_user_meta( $user_id, 'ab_bio_' . $lang_code, true );
		}

		wp_send_json_success( array( 'bios' => $bios ) );
	}
}
