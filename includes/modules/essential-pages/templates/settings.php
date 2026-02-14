<?php
/**
 * Template para a página de configurações do módulo Essential Pages
 */
?>
<div class="alvobot-admin-wrap alvobot-essential-pages">
	<div class="alvobot-admin-container">
	<div class="alvobot-admin-header">
		<div class="alvobot-header-icon">
			<i data-lucide="file-check" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-header-content">
			<h1><?php esc_html_e( 'AlvoBot Essential Pages', 'alvobot-pro' ); ?></h1>
			<p><?php esc_html_e( 'Gerencie as páginas essenciais do site. Veja abaixo o status atual e utilize as ações disponíveis para cada página.', 'alvobot-pro' ); ?></p>
		</div>
	</div>

	<div class="page-cards">
		<?php
		$pages = array(
			'terms'   => array(
				'title' => 'Termos de Uso',
				'slug'  => 'termos-de-uso',
			),
			'privacy' => array(
				'title' => 'Política de Privacidade',
				'slug'  => 'politica-de-privacidade',
			),
			'contact' => array(
				'title' => 'Contato',
				'slug'  => 'contato',
			),
		);

		foreach ( $pages as $key => $page ) {
			$existing_page = get_page_by_path( $page['slug'] );
			$status        = $existing_page ? 'Publicado' : 'Não criada';
			$status_class  = $existing_page ? 'status-published' : 'status-pending';
			?>
			<div class="page-card">
				<h2><?php esc_html_e( $page['title'], 'alvobot-pro' ); ?></h2>
				<div class="page-info">
					<p>
						<span class="label"><?php esc_html_e( 'Slug:', 'alvobot-pro' ); ?></span>
						<span><?php echo esc_html( $page['slug'] ); ?></span>
					</p>
					<p>
						<span class="label"><?php esc_html_e( 'Status:', 'alvobot-pro' ); ?></span>
						<span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php esc_html_e( $status, 'alvobot-pro' ); ?></span>
					</p>
				</div>
				<div class="page-actions">
					<?php if ( $existing_page ) : ?>
						<a href="<?php echo esc_url( get_permalink( $existing_page->ID ) ); ?>" class="button" target="_blank"><?php esc_html_e( 'Ver', 'alvobot-pro' ); ?></a>
						<button class="button" data-action="edit" data-page="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Editar', 'alvobot-pro' ); ?></button>
						<button class="button button-link-delete" data-action="delete" data-page="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Excluir', 'alvobot-pro' ); ?></button>
					<?php else : ?>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'create_essential_page', 'essential_page_nonce' ); ?>
							<input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
							<button type="submit" class="button" name="create_essential_page"><?php esc_html_e( 'Criar', 'alvobot-pro' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
		?>
	</div>

	<div class="global-actions">
		<div class="left-actions">
			<button class="button button-primary" data-action="create-all"><?php esc_html_e( 'Criar/Recriar Todas', 'alvobot-pro' ); ?></button>
		</div>
		<div class="right-actions">
			<button class="button button-link-delete" data-action="delete-all"><?php esc_html_e( 'Excluir Todas', 'alvobot-pro' ); ?></button>
		</div>
	</div>
	</div>
</div>