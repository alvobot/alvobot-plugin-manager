<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Updater {
	private $file;
	private $plugin;
	private $basename;
	private $active;
	// Sentinel: null = não buscado ainda, false = falha na API, object = dados válidos.
	private $github_response;
	private $github_repo = 'alvobot/alvobot-plugin-manager';
	private $github_api  = 'https://api.github.com/repos/';

	// Domínios aceitos para o pacote de download (segurança).
	private $allowed_package_hosts = array(
		'codeload.github.com',
		'api.github.com',
	);

	// Chave do transient de cache da resposta da API do GitHub.
	private $api_cache_key = 'alvobotpro_github_latest_release';

	public function __construct( $file ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $file );

		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'clear_stale_update_transient' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_action_links' ) );
		add_action( 'wp_ajax_alvobotpro_manual_check_update', array( $this, 'handle_manual_check' ) );
		add_action( 'admin_footer', array( $this, 'add_manual_check_script' ) );
	}

	public function init() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->active = is_plugin_active( $this->basename );
	}

	/**
	 * Remove a entrada stale do transient já salvo no banco sem precisar da API do GitHub.
	 * Resolve o caso em que o plugin foi atualizado mas o transient antigo ainda existe,
	 * e o filtro pre_set_site_transient não rodou ainda (dentro do ciclo de 12h).
	 * Limitado a páginas admin onde o aviso de update é exibido para evitar queries
	 * desnecessárias em todas as páginas admin.
	 */
	public function clear_stale_update_transient() {
		global $pagenow;

		$relevant_pages = array( 'plugins.php', 'update.php', 'update-core.php', 'admin-ajax.php' );
		if ( ! in_array( $pagenow, $relevant_pages, true ) ) {
			return;
		}

		$transient = get_site_transient( 'update_plugins' );

		if ( empty( $transient ) || ! isset( $transient->response[ $this->basename ] ) ) {
			return;
		}

		$entry = $transient->response[ $this->basename ];

		// Protege contra objeto corrompido sem o campo new_version.
		if ( ! isset( $entry->new_version ) ) {
			unset( $transient->response[ $this->basename ] );
			set_site_transient( 'update_plugins', $transient );
			AlvoBotPro::debug_log( 'updater', 'Entrada corrompida (sem new_version) removida do transient.' );
			return;
		}

		// Se a versão no transient é <= versão instalada, a entrada é stale.
		if ( version_compare( $entry->new_version, ALVOBOT_PRO_VERSION, 'le' ) ) {
			unset( $transient->response[ $this->basename ] );

			// WordPress espera no_update como array associativo (não stdClass).
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $this->basename ] = (object) array(
				'slug'        => dirname( $this->basename ),
				'plugin'      => $this->basename,
				'new_version' => ALVOBOT_PRO_VERSION,
				'url'         => 'https://github.com/' . $this->github_repo,
				'package'     => '',
				'icons'       => array(),
			);

			set_site_transient( 'update_plugins', $transient );
			AlvoBotPro::debug_log( 'updater', 'Entrada stale removida do transient para versão: ' . ALVOBOT_PRO_VERSION );
		}
	}

	public function add_action_links( $links ) {
		$check_link = sprintf(
			'<a href="#" class="alvobotpro-check-update" data-nonce="%s">%s</a>',
			wp_create_nonce( 'alvobotpro_check_update' ),
			__( 'Verificar Atualizações', 'alvobot-pro' )
		);
		array_unshift( $links, $check_link );
		return $links;
	}

	public function handle_manual_check() {
		check_ajax_referer( 'alvobotpro_check_update' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		delete_site_transient( 'update_plugins' );
		delete_site_transient( $this->api_cache_key );
		wp_clean_plugins_cache( true );
		wp_update_plugins();

		// Verifica o resultado real: se o plugin ainda aparece no response após a
		// verificação, há de fato uma atualização disponível.
		$transient   = get_site_transient( 'update_plugins' );
		$has_update  = isset( $transient->response[ $this->basename ] );
		$api_success = ( $this->github_response !== false );

		if ( ! $api_success ) {
			wp_send_json_error(
				array(
					'message' => __( 'Não foi possível conectar ao GitHub para verificar atualizações. Tente novamente mais tarde.', 'alvobot-pro' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Verificação de atualizações concluída!', 'alvobot-pro' ),
				'has_update' => $has_update,
			)
		);
	}

	public function add_manual_check_script() {
		global $pagenow;

		// Injeta o script apenas na página de plugins para evitar overhead em todo o admin.
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}
		?>
		<style>
		.alvobotpro-check-update.is-loading {
			opacity: 0.5;
			pointer-events: none;
			cursor: default;
		}
		</style>
		<script>
		jQuery(document).ready(function($) {
			$('.alvobotpro-check-update').on('click', function(e) {
				e.preventDefault();

				var button = $(this);

				// Protege contra duplo clique.
				if ( button.data('loading') ) {
					return;
				}

				var originalText = button.text();
				button.data('loading', true).text('<?php echo esc_js( __( 'Verificando...', 'alvobot-pro' ) ); ?>').addClass('is-loading');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					timeout: 30000,
					data: {
						action: 'alvobotpro_manual_check_update',
						_ajax_nonce: button.data('nonce')
					},
					success: function(response) {
						if ( response.success ) {
							location.reload();
						} else {
							var msg = ( response.data && response.data.message )
								? response.data.message
								: '<?php echo esc_js( __( 'Erro ao verificar atualizações.', 'alvobot-pro' ) ); ?>';
							alert( msg );
							button.data('loading', false).text(originalText).removeClass('is-loading');
						}
					},
					error: function(xhr, status) {
						var msg = ( status === 'timeout' )
							? '<?php echo esc_js( __( 'Tempo limite atingido. Tente novamente.', 'alvobot-pro' ) ); ?>'
							: '<?php echo esc_js( __( 'Erro ao verificar atualizações.', 'alvobot-pro' ) ); ?>';
						alert( msg );
						button.data('loading', false).text(originalText).removeClass('is-loading');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Busca os dados do último release do GitHub.
	 *
	 * Usa um transient próprio como cache (1 hora) para evitar requests HTTP
	 * excessivas ao GitHub (limite de 60 req/h sem token autenticado).
	 *
	 * Após uma falha, define $this->github_response = false (sentinel) para evitar
	 * novas tentativas HTTP na mesma request.
	 *
	 * Ignora pre-releases para não oferecer versões beta em produção.
	 */
	private function get_repository_info() {
		// null = não buscado ainda; false = falhou; object = válido.
		if ( $this->github_response !== null ) {
			return;
		}

		// Tenta cache em transient antes de ir à API.
		$cached = get_site_transient( $this->api_cache_key );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return;
		}

		$request_uri = $this->github_api . $this->github_repo . '/releases/latest';
		$response    = wp_remote_get(
			$request_uri,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API WP_Error: ' . $response->get_error_message() );
			$this->github_response = false;
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API HTTP ' . $response_code );
			$this->github_response = false;
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $body ) || ! is_object( $body ) ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API: resposta JSON inválida ou vazia.' );
			$this->github_response = false;
			return;
		}

		// Valida campos obrigatórios.
		if ( empty( $body->tag_name ) || empty( $body->zipball_url ) ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API: release sem tag_name ou zipball_url.' );
			$this->github_response = false;
			return;
		}

		// Ignora pre-releases para não expor versões beta em produção.
		if ( ! empty( $body->prerelease ) ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API: último release é pre-release, ignorando.' );
			$this->github_response = false;
			return;
		}

		// Valida o domínio do pacote de download (segurança).
		$package_host = wp_parse_url( $body->zipball_url, PHP_URL_HOST );
		if ( ! in_array( $package_host, $this->allowed_package_hosts, true ) ) {
			AlvoBotPro::debug_log( 'updater', 'GitHub API: zipball_url com host não autorizado: ' . $package_host );
			$this->github_response = false;
			return;
		}

		$this->github_response = $body;

		// Cache de 1 hora para reduzir chamadas à API do GitHub.
		set_site_transient( $this->api_cache_key, $this->github_response, HOUR_IN_SECONDS );
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_repository_info();

		// false = API falhou; null nunca ocorre aqui pois get_repository_info() sempre seta um valor.
		if ( ! $this->github_response ) {
			return $transient;
		}

		$latest_version  = ltrim( trim( $this->github_response->tag_name ), 'vV' );
		$current_version = ALVOBOT_PRO_VERSION;

		AlvoBotPro::debug_log( 'core', 'Current version: ' . $current_version );
		AlvoBotPro::debug_log( 'core', 'Latest version: ' . $latest_version );

		$doUpdate = version_compare( $latest_version, $current_version, 'gt' );

		if ( $doUpdate ) {
			$plugin = array(
				'slug'         => dirname( $this->basename ),
				'plugin'       => $this->basename,
				'new_version'  => $latest_version,
				'url'          => 'https://github.com/' . $this->github_repo,
				'package'      => $this->github_response->zipball_url,
				'icons'        => array(),
				'tested'       => '6.4',
				'requires_php' => '7.4',
			);

			$transient->response[ $this->basename ] = (object) $plugin;
		} else {
			// Remove entrada stale para não exibir aviso falso de atualização disponível.
			unset( $transient->response[ $this->basename ] );

			// Registra como atualizado para o WordPress não exibir o aviso.
			// WordPress espera no_update como array associativo (não stdClass).
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $this->basename ] = (object) array(
				'slug'        => dirname( $this->basename ),
				'plugin'      => $this->basename,
				'new_version' => $current_version,
				'url'         => 'https://github.com/' . $this->github_repo,
				'package'     => '',
				'icons'       => array(),
			);
		}

		return $transient;
	}

	public function plugin_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->basename ) ) {
			return $result;
		}

		$this->get_repository_info();
		if ( ! $this->github_response ) {
			return $result;
		}

		$plugin_data = get_plugin_data( $this->file );

		// Formata published_at do ISO 8601 para o formato esperado pelo WordPress.
		$last_updated = '';
		if ( ! empty( $this->github_response->published_at ) ) {
			$timestamp    = strtotime( $this->github_response->published_at );
			$last_updated = $timestamp ? date_i18n( 'Y-m-d g:i a', $timestamp ) : $this->github_response->published_at;
		}

		$plugin_info = array(
			'name'              => $plugin_data['Name'],
			'slug'              => dirname( $this->basename ),
			'version'           => ltrim( trim( $this->github_response->tag_name ), 'vV' ),
			'author'            => $plugin_data['Author'],
			'author_profile'    => $plugin_data['AuthorURI'],
			'last_updated'      => $last_updated,
			'homepage'          => $plugin_data['PluginURI'],
			'short_description' => $plugin_data['Description'],
			'sections'          => array(
				'description' => $plugin_data['Description'],
				// body pode ser null em releases sem changelog.
				'changelog'   => nl2br( $this->github_response->body ?? '' ),
			),
			'download_link'     => $this->github_response->zipball_url,
			'requires'          => '5.8',
			'tested'            => '6.4',
			'requires_php'      => '7.4',
		);

		return (object) $plugin_info;
	}

	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		AlvoBotPro::debug_log( 'updater', 'after_install iniciado - hook_extra: ' . print_r( $hook_extra, true ) );
		AlvoBotPro::debug_log( 'updater', 'after_install result: ' . print_r( $result, true ) );

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			AlvoBotPro::debug_log( 'updater', 'Não é nosso plugin, saindo - basename: ' . $this->basename );
			return $result;
		}

		AlvoBotPro::debug_log( 'updater', 'É nosso plugin, processando update...' );

		// Lê o estado ativo diretamente aqui, não confia em $this->active capturado
		// no admin_init, pois o contexto pode ser WP-CLI, cron ou multisite background.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$was_active = is_plugin_active( $this->basename );

		$plugin_folder_name = dirname( $this->basename );
		$plugin_folder      = WP_PLUGIN_DIR . '/' . $plugin_folder_name;

		AlvoBotPro::debug_log( 'updater', 'Plugin folder: ' . $plugin_folder );
		AlvoBotPro::debug_log( 'updater', 'Result destination: ' . $result['destination'] );

		// Normaliza trailing slashes antes de comparar para evitar falso negativo.
		if ( untrailingslashit( $result['destination'] ) === untrailingslashit( $plugin_folder ) ) {
			AlvoBotPro::debug_log( 'updater', 'Destino já é correto, não precisa mover.' );
			delete_site_transient( 'update_plugins' );
			delete_site_transient( $this->api_cache_key );
			wp_clean_plugins_cache( true );
			return $result;
		}

		// --- Remoção da pasta atual ---

		$temp_folder = null;

		if ( $wp_filesystem->exists( $plugin_folder ) ) {
			AlvoBotPro::debug_log( 'updater', 'Pasta atual existe: ' . $plugin_folder );

			// Detecta symlink: usa unlink() nativo para remover apenas o link,
			// nunca o conteúdo real apontado pelo symlink.
			if ( is_link( $plugin_folder ) ) {
				AlvoBotPro::debug_log( 'updater', 'Pasta é um symlink, removendo apenas o link.' );
				$unlink_result = unlink( $plugin_folder );
				AlvoBotPro::debug_log( 'updater', 'Resultado do unlink: ' . ( $unlink_result ? 'SUCESSO' : 'FALHOU' ) );

				if ( ! $unlink_result ) {
					return new WP_Error( 'symlink_remove_failed', 'Não foi possível remover o symlink do plugin. Verifique permissões.' );
				}
			} else {
				// Tenta deletar a pasta real.
				$delete_result = $wp_filesystem->delete( $plugin_folder, true );
				AlvoBotPro::debug_log( 'updater', 'Resultado da deleção: ' . ( $delete_result ? 'SUCESSO' : 'FALHOU' ) );

				if ( ! $delete_result ) {
					// Fallback: renomeia temporariamente para desocupar o caminho.
					$temp_folder   = $plugin_folder . '_old_' . uniqid();
					$rename_result = $wp_filesystem->move( $plugin_folder, $temp_folder );
					AlvoBotPro::debug_log( 'updater', 'Rename para temp (' . $temp_folder . '): ' . ( $rename_result ? 'SUCESSO' : 'FALHOU' ) );

					if ( ! $rename_result ) {
						AlvoBotPro::debug_log( 'updater', 'ERRO: Não foi possível nem deletar nem renomear a pasta atual.' );
						return new WP_Error( 'delete_failed', 'Não foi possível remover o plugin antigo. Verifique permissões de arquivo.' );
					}
				}
			}
		} else {
			AlvoBotPro::debug_log( 'updater', 'Pasta atual não existe.' );
		}

		// --- Move o novo plugin para o caminho correto ---

		AlvoBotPro::debug_log( 'updater', 'Movendo de ' . $result['destination'] . ' para ' . $plugin_folder );
		$move_result = $wp_filesystem->move( $result['destination'], $plugin_folder );
		AlvoBotPro::debug_log( 'updater', 'Resultado da movimentação: ' . ( $move_result ? 'SUCESSO' : 'FALHOU' ) );

		if ( ! $move_result ) {
			// Tenta reverter: restaura o plugin antigo do temp_folder para não deixar o
			// plugin em estado indefinido.
			if ( null !== $temp_folder && $wp_filesystem->exists( $temp_folder ) ) {
				AlvoBotPro::debug_log( 'updater', 'Tentando reverter: movendo ' . $temp_folder . ' de volta para ' . $plugin_folder );
				$wp_filesystem->move( $temp_folder, $plugin_folder );
			}

			AlvoBotPro::debug_log( 'updater', 'ERRO: Falha ao mover para pasta final.' );
			return new WP_Error( 'move_failed', 'Não foi possível mover o plugin para a pasta correta.' );
		}

		$result['destination'] = $plugin_folder;

		// Remove pasta temporária se existir.
		if ( null !== $temp_folder && $wp_filesystem->exists( $temp_folder ) ) {
			AlvoBotPro::debug_log( 'updater', 'Removendo pasta temporária: ' . $temp_folder );
			$wp_filesystem->delete( $temp_folder, true );
		}

		// Limpa transients ANTES de reativar para que o plugin não leia dados stale
		// durante o hook de ativação.
		delete_site_transient( 'update_plugins' );
		delete_site_transient( $this->api_cache_key );
		wp_clean_plugins_cache( true );

		// Reativa o plugin se estava ativo antes da atualização.
		if ( $was_active ) {
			AlvoBotPro::debug_log( 'updater', 'Reativando plugin: ' . $this->basename );
			activate_plugin( $this->basename );
		}

		AlvoBotPro::debug_log( 'updater', 'Update concluído com sucesso.' );

		return $result;
	}
}
