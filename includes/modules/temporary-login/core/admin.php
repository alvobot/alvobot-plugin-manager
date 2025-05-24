<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AlvoBotPro_TemporaryLogin_Admin {
	const PAGE_SLUG = 'alvobot-pro-temporary-login'; // Updated page slug

	public static function init() {
		// Menu is added by the main AlvoBotPro class.
		// Add action to enqueue scripts for this module's admin page.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

		// Add plugin action links if ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE is defined
		if ( defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE' ) ) {
			add_filter( 'plugin_action_links_' . plugin_basename( ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE ), [ __CLASS__, 'plugin_action_links' ] );
		}

		// Register settings for the API key
		register_setting(
			'alvobot_pro_temporary_login_settings', // Option group
			'alvobot_pro_temporary_login_api_key',  // Option name
			[ __CLASS__, 'sanitize_api_key' ]       // Sanitization callback
		);
		
		// Register admin-post handlers for form submissions
		add_action('admin_post_create_temporary_user', [__CLASS__, 'handle_create_temporary_user']);
		add_action('admin_post_delete_temporary_user', [__CLASS__, 'handle_delete_temporary_user']);
		add_action('admin_post_extend_temporary_user', [__CLASS__, 'handle_extend_temporary_user']);
		
		// Note: Admin_Pointer initialization was in the original Plugin class,
		// it should be called from AlvoBotPro_TemporaryLogin class if still needed.
		// Admin_Pointer::init(); // Or Admin_Pointer::add_hooks();
	}

	/**
	 * Sanitizes the API key.
	 * For now, using a simple text field sanitization.
	 *
	 * @param string $input The API key.
	 * @return string Sanitized API key.
	 */
	public static function sanitize_api_key( $input ) {
		return sanitize_text_field( $input );
	}

	// add_admin_menu() is removed as the menu is registered by the main plugin.

	/**
	 * Renders the admin page content. This is the callback for the submenu page.
	 */
	public static function render_admin_page() {
		// Display a basic admin interface instead of the React app
		?>
		<div class="alvobot-pro-wrap">
			<div class="alvobot-pro-header">
				<h1><?php echo esc_html__('Temporary Login', 'alvobot-pro'); ?></h1>
				<p><?php echo esc_html__('Crie e gerencie usuários temporários com acesso limitado ao WordPress.', 'alvobot-pro'); ?></p>
			</div>
			
			<div class="alvobot-pro-notices">
				<?php
				// Exibir mensagens de sucesso
				if (isset($_GET['message'])) {
					$message = '';
					switch ($_GET['message']) {
						case 'user_created':
							$message = __('Usuário temporário criado com sucesso.', 'alvobot-pro');
							break;
						case 'user_deleted':
							$message = __('Usuário temporário excluído com sucesso.', 'alvobot-pro');
							break;
						case 'expiration_extended':
							$message = __('Expiração estendida com sucesso.', 'alvobot-pro');
							break;
					}
					if (!empty($message)) {
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
					}
				}
				
				// Exibir mensagens de erro
				if (isset($_GET['error'])) {
					$error = '';
					switch ($_GET['error']) {
						case 'user_creation_failed':
							$error = __('Falha ao criar usuário temporário.', 'alvobot-pro');
							break;
						case 'invalid_user':
							$error = __('ID de usuário inválido.', 'alvobot-pro');
							break;
						case 'not_temporary_user':
							$error = __('Este não é um usuário temporário.', 'alvobot-pro');
							break;
						case 'extension_failed':
							$error = __('Falha ao estender a expiração.', 'alvobot-pro');
							break;
					}
					if (!empty($error)) {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
					}
				}
				?>
			</div>
			

			
			<?php
			// Buscar usuários temporários
			$users = self::get_temporary_users();
			?>
			
			<div class="alvobot-pro-modules">
				<!-- Cartão de criação de usuário temporário -->
				<div class="alvobot-pro-module-card">
					<div class="alvobot-pro-module-header">
						<h3 class="alvobot-pro-module-title"><?php echo esc_html__('Criar Novo Usuário Temporário', 'alvobot-pro'); ?></h3>
					</div>
					<div class="alvobot-pro-module-content">
						<p><?php echo esc_html__('Crie um novo usuário com acesso temporário ao seu site WordPress.', 'alvobot-pro'); ?></p>

						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<input type="hidden" name="action" value="create_temporary_user">
							<?php wp_nonce_field('create_temporary_user_nonce', 'create_temporary_user_nonce'); ?>
							
							<table class="form-table">
								<tr>
									<th scope="row"><label for="role"><?php echo esc_html__('Função', 'alvobot-pro'); ?></label></th>
									<td>
										<select name="role" id="role" class="regular-text">
											<?php
											// Obter todos os papéis
											$roles = get_editable_roles();
											foreach ($roles as $role_id => $role_info) {
												// Não incluir administrador
												if ($role_id !== 'administrator') {
													echo '<option value="' . esc_attr($role_id) . '">' . esc_html($role_info['name']) . '</option>';
												}
											}
											?>
										</select>
										<p class="description"><?php echo esc_html__('Selecione a função para o usuário temporário', 'alvobot-pro'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="duration"><?php echo esc_html__('Duração', 'alvobot-pro'); ?></label></th>
									<td>
										<select name="duration" id="duration" class="regular-text">
											<option value="1">1 <?php echo esc_html__('dia', 'alvobot-pro'); ?></option>
											<option value="3">3 <?php echo esc_html__('dias', 'alvobot-pro'); ?></option>
											<option value="7" selected>7 <?php echo esc_html__('dias', 'alvobot-pro'); ?></option>
											<option value="14">14 <?php echo esc_html__('dias', 'alvobot-pro'); ?></option>
											<option value="30">30 <?php echo esc_html__('dias', 'alvobot-pro'); ?></option>
										</select>
										<p class="description"><?php echo esc_html__('Tempo de acesso do usuário', 'alvobot-pro'); ?></p>
									</td>
								</tr>
							</table>
							
							<p class="submit">
								<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Criar Usuário Temporário', 'alvobot-pro'); ?>">
							</p>
						</form>
					</div>
				</div>
				
				<!-- Cartão de gerenciamento de usuários -->
				<div class="alvobot-pro-module-card">
					<div class="alvobot-pro-module-header">
						<h3 class="alvobot-pro-module-title"><?php echo esc_html__('Gerenciamento de Usuários', 'alvobot-pro'); ?></h3>
					</div>
					<div class="alvobot-pro-module-content">
						<p><?php echo esc_html__('Visualize e gerencie todos os usuários temporários do seu site.', 'alvobot-pro'); ?></p>
						
						<?php if (!empty($users)) : ?>
							<div class="status-card">
								<div class="status-header">
									<h2><?php echo esc_html__('Usuários Temporários Ativos', 'alvobot-pro'); ?></h2>
								</div>
								<div class="status-content">
									<table class="widefat">
										<thead>
											<tr>
												<th><?php echo esc_html__('Usuário', 'alvobot-pro'); ?></th>
												<th><?php echo esc_html__('Função', 'alvobot-pro'); ?></th>
												<th><?php echo esc_html__('Data de Criação', 'alvobot-pro'); ?></th>
												<th><?php echo esc_html__('Expira em', 'alvobot-pro'); ?></th>
												<th><?php echo esc_html__('Ações', 'alvobot-pro'); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($users as $user) : ?>
												<tr>
													<td>
														<strong><?php echo esc_html($user['username']); ?></strong>
														<br>
														<small><?php echo esc_html($user['email']); ?></small>
													</td>
													<td><?php echo esc_html($user['role']); ?></td>
													<td><?php echo esc_html($user['created_at']); ?></td>
													<td>
														<?php echo esc_html($user['expires_at']); ?>
													</td>
													<td>
														<div class="row-actions">
															<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
																<input type="hidden" name="action" value="extend_temporary_user">
																<input type="hidden" name="user_id" value="<?php echo esc_attr($user['id']); ?>">
																<?php wp_nonce_field('extend_temporary_user_nonce', 'extend_temporary_user_nonce'); ?>
																<button type="submit" class="button button-small button-primary">
																	<?php echo esc_html__('Estender', 'alvobot-pro'); ?>
																</button>
															</form>
															
															<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline; margin-left: 5px;">
																<input type="hidden" name="action" value="delete_temporary_user">
																<input type="hidden" name="user_id" value="<?php echo esc_attr($user['id']); ?>">
																<?php wp_nonce_field('delete_temporary_user_nonce', 'delete_temporary_user_nonce'); ?>
																<button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Tem certeza de que deseja excluir este usuário?', 'alvobot-pro')); ?>')">
																	<?php echo esc_html__('Excluir', 'alvobot-pro'); ?>
																</button>
															</form>
														</div>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						<?php else : ?>
							<div class="status-card">
								<div class="status-header">
									<h2><?php echo esc_html__('Usuários Temporários', 'alvobot-pro'); ?></h2>
								</div>
								<div class="status-content">
									<p><?php echo esc_html__('Nenhum usuário temporário encontrado.', 'alvobot-pro'); ?></p>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
		
		<script>
		// Script para copiar URL para a área de transferência
		document.addEventListener('DOMContentLoaded', function() {
			const copyButtons = document.querySelectorAll('.copy-url');
			copyButtons.forEach(button => {
				button.addEventListener('click', function() {
					const url = this.getAttribute('data-url');
					navigator.clipboard.writeText(url).then(function() {
						alert('<?php echo esc_js(__('URL copiada para a área de transferência!', 'alvobot-pro')); ?>');
					}).catch(function() {
						alert('<?php echo esc_js(__('Falha ao copiar. Por favor, copie manualmente.', 'alvobot-pro')); ?>');
					});
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Enqueues scripts and styles for the module's admin page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		// Check if we are on this module's admin page.
		// The hook_suffix for a page added by add_submenu_page is 'alvobot-pro_page_alvobot-pro-temporary-login'
		// (parent_slug_page_menu_slug) or if it's a top-level page 'toplevel_page_menu_slug'.
		// Since it's a submenu of 'alvobot-pro', the format is 'alvobot-pro_page_alvobot-pro-temporary-login'.
		if ( 'alvobot-pro_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		// Ensure constants are defined by the main module class AlvoBotPro_TemporaryLogin
		if ( !defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_PATH' ) || !defined( 'ALVOBOT_PRO_TEMPORARY_LOGIN_URL' ) ) {
			return; // Or handle error: display admin notice, log, etc.
		}

		// Webpack output path is 'assets/dist' relative to the module root.
		$asset_file_path = ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'assets/dist/admin.asset.php';
		if ( ! file_exists( $asset_file_path ) ) {
			// Fallback for older path structure if needed, or log error.
			// For now, let's try the direct 'dist' path if 'assets/dist' fails, as a temporary measure.
			$asset_file_path_fallback = ALVOBOT_PRO_TEMPORARY_LOGIN_PATH . 'dist/admin.asset.php';
			if ( ! file_exists( $asset_file_path_fallback ) ) {
				return; // Or handle error more gracefully
			}
			$asset_file_path = $asset_file_path_fallback; // Use fallback
		}
		$asset_file = include( $asset_file_path );

		// Determine the correct URL part ('assets/dist/' or 'dist/')
		$assets_url_subpath = (strpos($asset_file_path, 'assets/dist') !== false) ? 'assets/dist/' : 'dist/';

		wp_enqueue_script(
			'alvobot-pro-temporary-login-admin', // Updated script handle
			ALVOBOT_PRO_TEMPORARY_LOGIN_URL . $assets_url_subpath . 'admin.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'alvobot-pro-temporary-login-admin', // Updated style handle
			ALVOBOT_PRO_TEMPORARY_LOGIN_URL . $assets_url_subpath . 'admin.css',
			[], // Add dependencies like 'wp-components' if Material UI is not bundled
			$asset_file['version']
		);

		// Localize script with necessary data
		wp_localize_script(
			'alvobot-pro-temporary-login-admin',
			'alvobotProTemporaryLogin', // Updated localization object name
			[
				// The REST API URL for this module's endpoints should be namespaced under 'alvobot-pro'
				'apiUrl' => esc_url_raw( rest_url( 'alvobot-pro/v1/temporary-login' ) ), // Example: adjust as per actual REST routes
				'rest_nonce' => wp_create_nonce( 'wp_rest' ), // Standard REST API nonce, renamed for clarity
				'ajax_nonce' => wp_create_nonce( Ajax::AJAX_NONCE_ACTION ), // Nonce for general admin-ajax.php actions
				'api_key_nonce' => wp_create_nonce( 'alvobot_pro_temporary_login_api_key_nonce' ), // Nonce for API key management
				'current_api_key' => get_option( 'alvobot_pro_temporary_login_api_key', '' ),
				'page_slug' => self::PAGE_SLUG,
				'text_domain' => 'alvobot-pro', // Consistent text domain
				// Add any other data needed by the React app
			]
		);
	}

	/**
	 * Adds plugin action links.
	 * This will only work if ALVOBOT_PRO_TEMPORARY_LOGIN_MODULE_FILE was correctly defined
	 * and this file is considered the "main" file for a plugin by WordPress.
	 * For a module, this might not be standard.
	 */
	public static function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			esc_html__( 'Settings', 'alvobot-pro' ) // Text domain updated
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
	
	/**
	 * Obter todos os usuários temporários
	 * 
	 * @return array Lista de usuários temporários
	 */
	private static function get_temporary_users() {
		$users = array();
		
		$args = array(
			'meta_key' => '_temporary_login_is_temporary',
			'meta_value' => true,
		);
		
		$user_query = new WP_User_Query($args);
		
		if (!empty($user_query->get_results())) {
			foreach ($user_query->get_results() as $user) {
				$expiration_timestamp = AlvoBotPro_TemporaryLogin_Options::get_expiration($user->ID);
				$login_url = AlvoBotPro_TemporaryLogin_Options::get_login_url($user->ID);
				
				$users[] = array(
					'id' => $user->ID,
					'username' => $user->user_login,
					'role' => $user->roles[0],
					'login_url' => $login_url,
					'created_at' => get_user_meta($user->ID, '_temporary_login_created_at', true) ?: current_time('mysql'),
					'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
					'created_by_user_id' => get_user_meta($user->ID, '_temporary_login_created_by_user_id', true) ?: null,
				);
			}
		}
		
		return $users;
	}
	
	/**
	 * Manipula a criação de um usuário temporário via formulário
	 */
	public static function handle_create_temporary_user() {
		// Verifica nonce e permissões
		if (!isset($_POST['create_temporary_user_nonce']) || 
			!wp_verify_nonce($_POST['create_temporary_user_nonce'], 'create_temporary_user_nonce') || 
			!current_user_can('manage_options')) {
			wp_die(__('Acesso negado.', 'alvobot-pro'));
		}
		
		// Validação básica
		$role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : 'administrator';
		$duration_value = isset($_POST['duration_value']) ? absint($_POST['duration_value']) : 14;
		$duration_unit = isset($_POST['duration_unit']) ? sanitize_text_field($_POST['duration_unit']) : 'days';
		
		// Cria o usuário temporário
		$args = array(
			'role' => $role,
			'duration_value' => $duration_value,
			'duration_unit' => $duration_unit,
			'created_by_user_id' => get_current_user_id(),
		);
		
		$user_id = AlvoBotPro_TemporaryLogin_Options::generate_temporary_user($args);
		
		// Adiciona meta com a data de criação
		if (!is_wp_error($user_id)) {
			update_user_meta($user_id, '_temporary_login_created_at', current_time('mysql'));
			
			// Redireciona com mensagem de sucesso
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'message' => 'user_created',
			), admin_url('admin.php')));
			exit;
		} else {
			// Redireciona com mensagem de erro
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'user_creation_failed',
			), admin_url('admin.php')));
			exit;
		}
	}
	
	/**
	 * Manipula a exclusão de um usuário temporário
	 */
	public static function handle_delete_temporary_user() {
		// Verifica nonce e permissões
		if (!isset($_POST['delete_temporary_user_nonce']) || 
			!wp_verify_nonce($_POST['delete_temporary_user_nonce'], 'delete_temporary_user_nonce') || 
			!current_user_can('manage_options')) {
			wp_die(__('Acesso negado.', 'alvobot-pro'));
		}
		
		$user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
		
		if (empty($user_id)) {
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'invalid_user',
			), admin_url('admin.php')));
			exit;
		}
		
		// Verifica se é um usuário temporário
		$is_temporary = get_user_meta($user_id, '_temporary_login_is_temporary', true);
		if (!$is_temporary) {
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'not_temporary_user',
			), admin_url('admin.php')));
			exit;
		}
		
		// Verifica se há um usuário para reassociar o conteúdo
		$reassign_to = get_user_meta($user_id, '_temporary_login_created_by_user_id', true);
		
		// Exclui o usuário
		if (!empty($reassign_to) && get_user_by('id', $reassign_to)) {
			wp_delete_user($user_id, $reassign_to);
		} else {
			wp_delete_user($user_id);
		}
		
		// Redireciona com mensagem de sucesso
		wp_redirect(add_query_arg(array(
			'page' => self::PAGE_SLUG,
			'message' => 'user_deleted',
		), admin_url('admin.php')));
		exit;
	}
	
	/**
	 * Manipula a extensão da expiração de um usuário temporário
	 */
	public static function handle_extend_temporary_user() {
		// Verifica nonce e permissões
		if (!isset($_POST['extend_temporary_user_nonce']) || 
			!wp_verify_nonce($_POST['extend_temporary_user_nonce'], 'extend_temporary_user_nonce') || 
			!current_user_can('manage_options')) {
			wp_die(__('Acesso negado.', 'alvobot-pro'));
		}
		
		$user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
		
		if (empty($user_id)) {
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'invalid_user',
			), admin_url('admin.php')));
			exit;
		}
		
		// Verifica se é um usuário temporário
		$is_temporary = get_user_meta($user_id, '_temporary_login_is_temporary', true);
		if (!$is_temporary) {
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'not_temporary_user',
			), admin_url('admin.php')));
			exit;
		}
		
		// Estende a expiração
		$success = AlvoBotPro_TemporaryLogin_Options::extend_expiration($user_id);
		
		if ($success) {
			// Redireciona com mensagem de sucesso
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'message' => 'expiration_extended',
			), admin_url('admin.php')));
			exit;
		} else {
			// Redireciona com mensagem de erro
			wp_redirect(add_query_arg(array(
				'page' => self::PAGE_SLUG,
				'error' => 'extension_failed',
			), admin_url('admin.php')));
			exit;
		}
	}
}

// Admin::init(); // Initialization is now handled by AlvoBotPro_TemporaryLogin class loading this file.
// The AlvoBotPro_TemporaryLogin class should call Admin::init() in its load_dependencies or init_hooks.
