<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

	<h2><?php echo esc_html__( 'Documentação da API Multi Languages', 'alvobot-pro' ); ?></h2>
	
		<div class="notice notice-info">
			<p>
				<?php echo esc_html__( 'Esta página contém a documentação completa da API REST do módulo Multi Languages.', 'alvobot-pro' ); ?>
				<?php echo esc_html__( 'Todos os endpoints estão disponíveis no namespace', 'alvobot-pro' ); ?> <code>/wp-json/alvobot-pro/v1/</code>.
			</p>
			<p>
				<?php echo esc_html__( 'Todos os endpoints exigem autenticação e permissões de edição (edit_posts) via sessão WordPress ou Basic Auth válido.', 'alvobot-pro' ); ?>
			</p>
			<p>
				<?php echo esc_html__( 'Os endpoints com prefixo /admin/ são legados e retornam cabeçalhos de depreciação. Prefira os endpoints principais ou AJAX administrativo.', 'alvobot-pro' ); ?>
			</p>
		</div>
	
	<div class="card">
		<h2><?php echo esc_html__( 'Endpoints Disponíveis', 'alvobot-pro' ); ?></h2>
		
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th width="25%"><?php echo esc_html__( 'Endpoint', 'alvobot-pro' ); ?></th>
					<th width="10%"><?php echo esc_html__( 'Método', 'alvobot-pro' ); ?></th>
					<th width="45%"><?php echo esc_html__( 'Descrição', 'alvobot-pro' ); ?></th>
					<th width="20%"><?php echo esc_html__( 'Permissão', 'alvobot-pro' ); ?></th>
				</tr>
			</thead>
				<tbody>
					<!-- Endpoints Administrativos -->
						<tr>
							<td><code>/admin/translate</code></td>
							<td><code>POST</code></td>
							<td><?php echo esc_html__( '[LEGADO] Endpoint administrativo sem implementação completa. Use /translate. Sunset: 2026-12-31.', 'alvobot-pro' ); ?></td>
							<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>/admin/queue/status</code></td>
							<td><code>GET</code></td>
							<td><?php echo esc_html__( '[LEGADO] Status da fila (compatibilidade). Prefira /queue/status.', 'alvobot-pro' ); ?></td>
							<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
						</tr>
						<tr>
							<td><code>/admin/queue/add</code></td>
							<td><code>POST</code></td>
							<td><?php echo esc_html__( '[LEGADO] Adiciona item na fila (compatibilidade). Prefira /queue/add.', 'alvobot-pro' ); ?></td>
							<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
						</tr>

					<tr>
						<td><code>/queue/status</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Endpoint canônico para consultar status da fila de tradução.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/queue/add</code></td>
						<td><code>POST</code></td>
						<td><?php echo esc_html__( 'Endpoint canônico para adicionar itens à fila de tradução.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>

					<!-- Endpoints para Posts -->
				<tr>
					<td><code>/translate</code></td>
					<td><code>POST</code></td>
					<td><?php echo esc_html__( 'Cria uma nova tradução para um post existente.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate</code></td>
					<td><code>PUT</code></td>
					<td><?php echo esc_html__( 'Atualiza uma tradução existente de um post.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate</code></td>
					<td><code>DELETE</code></td>
					<td><?php echo esc_html__( 'Exclui uma tradução existente de um post.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/change-post-language</code></td>
					<td><code>PUT</code></td>
					<td><?php echo esc_html__( 'Altera o idioma de um post existente sem criar uma nova tradução.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
					<tr>
						<td><code>/translations</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém todas as traduções de posts com paginação.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/translations/check</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Verifica se existe uma tradução para um post em um determinado idioma.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/translations/missing</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Lista posts sem traduções com paginação.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
				
				<!-- Endpoints para Categorias -->
				<tr>
					<td><code>/translate/category</code></td>
					<td><code>POST</code></td>
					<td><?php echo esc_html__( 'Cria uma nova tradução para uma categoria existente.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate/category</code></td>
					<td><code>PUT</code></td>
					<td><?php echo esc_html__( 'Atualiza uma tradução existente de uma categoria.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate/category</code></td>
					<td><code>DELETE</code></td>
					<td><?php echo esc_html__( 'Exclui uma tradução existente de uma categoria.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
					<tr>
						<td><code>/translations/categories</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém todas as traduções de categorias com paginação.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
				
				<!-- Endpoints para Slugs -->
					<tr>
						<td><code>/slugs</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém todos os slugs de um tipo de post com paginação.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
				<tr>
					<td><code>/translate/slug</code></td>
					<td><code>POST</code></td>
					<td><?php echo esc_html__( 'Cria uma tradução de slug para um post.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate/slug</code></td>
					<td><code>PUT</code></td>
					<td><?php echo esc_html__( 'Atualiza uma tradução existente de slug.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>/translate/slug</code></td>
					<td><code>DELETE</code></td>
					<td><?php echo esc_html__( 'Exclui uma tradução existente de slug (redefine para o slug padrão).', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
				
				<!-- Endpoints de Utilidade -->
					<tr>
						<td><code>/languages</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Lista todos os idiomas configurados no Polylang.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/language-url</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém a URL de um post em um idioma específico.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
				
				<!-- Endpoints para Taxonomias -->
					<tr>
						<td><code>/taxonomies</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Lista todas as taxonomias disponíveis para tradução.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/taxonomy/terms</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém todos os termos de uma taxonomia com suas traduções.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>/taxonomy/untranslated</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém termos sem traduções completas.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
				
				<!-- Endpoints de Sincronização e Estatísticas -->
				<tr>
					<td><code>/sync-translations</code></td>
					<td><code>POST</code></td>
					<td><?php echo esc_html__( 'Sincroniza as traduções de um post com o Polylang.', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Editor', 'alvobot-pro' ); ?></td>
				</tr>
					<tr>
						<td><code>/translation-stats</code></td>
						<td><code>GET</code></td>
						<td><?php echo esc_html__( 'Obtém estatísticas de tradução para o site.', 'alvobot-pro' ); ?></td>
						<td><?php echo esc_html__( 'Editor (autenticado)', 'alvobot-pro' ); ?></td>
					</tr>
			</tbody>
		</table>
	</div>
	
	<div class="card" style="margin-top: 20px;">
		<h2><?php echo esc_html__( 'Exemplos de Uso', 'alvobot-pro' ); ?></h2>
		
		<h3><?php echo esc_html__( 'Criar uma tradução de post', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>POST /wp-json/alvobot-pro/v1/translate</code></p>
			<p><strong>Corpo da requisição:</strong></p>
			<pre><code>{
	"post_id": 123,
	"language_code": "en",
	"title": "Translated Title",
	"content": "Translated Content",
	"excerpt": "Translated Excerpt",
	"slug": "translated-slug",
	"categories": [4, 5, 6],
	"featured_media": 789,
	"meta_input": {
		"custom_field": "Custom Value"
	}
}</code></pre>
		</div>
		
		<h3><?php echo esc_html__( 'Atualizar uma tradução existente', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>PUT /wp-json/alvobot-pro/v1/translate</code></p>
			<p><strong>Corpo da requisição:</strong></p>
			<pre><code>{
	"post_id": 123,
	"language_code": "en",
	"title": "Updated Title",
	"content": "Updated Content",
	"excerpt": "Updated Excerpt",
	"slug": "updated-slug"
}</code></pre>
		</div>
		
		<h3><?php echo esc_html__( 'Alterar o idioma de um post', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>PUT /wp-json/alvobot-pro/v1/change-post-language</code></p>
			<p><strong>Corpo da requisição:</strong></p>
			<pre><code>{
	"post_id": 123,
	"language_code": "es",
	"update_translations": true
}</code></pre>
		</div>
		
		<h3><?php echo esc_html__( 'Criar uma tradução de categoria', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>POST /wp-json/alvobot-pro/v1/translate/category</code></p>
			<p><strong>Corpo da requisição:</strong></p>
			<pre><code>{
	"category_id": 45,
	"language_code": "en",
	"name": "Translated Category",
	"description": "Translated Description",
	"slug": "translated-category"
}</code></pre>
		</div>
		
		<h3><?php echo esc_html__( 'Verificar existência de tradução', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>GET /wp-json/alvobot-pro/v1/translations/check?post_id=123&language_code=en</code></p>
		</div>
		
		<h3><?php echo esc_html__( 'Obter termos de uma taxonomia', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>GET /wp-json/alvobot-pro/v1/taxonomy/terms?taxonomy=category&per_page=10&page=1&hide_empty=false</code></p>
		</div>
		
		<h3><?php echo esc_html__( 'Sincronizar traduções', 'alvobot-pro' ); ?></h3>
		<div class="api-example">
			<p><strong>Endpoint:</strong> <code>POST /wp-json/alvobot-pro/v1/sync-translations</code></p>
			<p><strong>Corpo da requisição:</strong></p>
			<pre><code>{
	"translations": {
		"en": 123,
		"pt": 456,
		"es": 789
	}
}</code></pre>
		</div>
	</div>
	
	<div class="card" style="margin-top: 20px;">
		<h2><?php echo esc_html__( 'Códigos de Resposta', 'alvobot-pro' ); ?></h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th width="15%"><?php echo esc_html__( 'Código', 'alvobot-pro' ); ?></th>
					<th width="25%"><?php echo esc_html__( 'Status', 'alvobot-pro' ); ?></th>
					<th width="60%"><?php echo esc_html__( 'Descrição', 'alvobot-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>200</code></td>
					<td><?php echo esc_html__( 'OK', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'A requisição foi bem-sucedida.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>201</code></td>
					<td><?php echo esc_html__( 'Created', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Um novo recurso foi criado com sucesso.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>400</code></td>
					<td><?php echo esc_html__( 'Bad Request', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'A requisição contém parâmetros inválidos ou está mal formada.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>401</code></td>
					<td><?php echo esc_html__( 'Unauthorized', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Autenticação é necessária para acessar este recurso.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>403</code></td>
					<td><?php echo esc_html__( 'Forbidden', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'O usuário não tem permissão para acessar este recurso.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>404</code></td>
					<td><?php echo esc_html__( 'Not Found', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'O recurso solicitado não foi encontrado.', 'alvobot-pro' ); ?></td>
				</tr>
				<tr>
					<td><code>500</code></td>
					<td><?php echo esc_html__( 'Internal Server Error', 'alvobot-pro' ); ?></td>
					<td><?php echo esc_html__( 'Ocorreu um erro interno no servidor.', 'alvobot-pro' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	
	<style>
		.api-example {
			background-color: #f9f9f9;
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 20px;
		}
		
		.api-example pre {
			background-color: #f0f0f0;
			padding: 10px;
			overflow-x: auto;
			border-radius: 3px;
			margin: 10px 0;
		}
		
		.card {
			padding: 20px;
			background: #fff;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
			border: 1px solid #e5e5e5;
			margin-bottom: 20px;
		}
	</style>
