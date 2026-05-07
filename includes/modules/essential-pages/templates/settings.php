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
		$essential_pages = array(
			'terms'   => array(
				'title' => __( 'Termos de Uso', 'alvobot-pro' ),
				'slug'  => 'termos-de-uso',
			),
			'privacy' => array(
				'title' => __( 'Política de Privacidade', 'alvobot-pro' ),
				'slug'  => 'politica-de-privacidade',
			),
			'contact' => array(
				'title' => __( 'Contato', 'alvobot-pro' ),
				'slug'  => 'contato',
			),
		);

		foreach ( $essential_pages as $key => $essential_page ) {
			$existing_page = get_page_by_path( $essential_page['slug'] );
			if ( $existing_page && 'publish' === $existing_page->post_status ) {
				$essential_page_status = __( 'Publicado', 'alvobot-pro' );
				$essential_page_class  = 'status-published';
			} elseif ( $existing_page ) {
				$essential_page_status = __( 'Não publicada', 'alvobot-pro' );
				$essential_page_class  = 'status-draft';
			} else {
				$essential_page_status = __( 'Não criada', 'alvobot-pro' );
				$essential_page_class  = 'status-pending';
			}
			?>
			<div class="page-card">
				<h2><?php echo esc_html( $essential_page['title'] ); ?></h2>
				<div class="page-info">
					<p>
						<span class="label"><?php esc_html_e( 'Slug:', 'alvobot-pro' ); ?></span>
						<span><?php echo esc_html( $essential_page['slug'] ); ?></span>
					</p>
					<p>
						<span class="label"><?php esc_html_e( 'Status:', 'alvobot-pro' ); ?></span>
						<span class="status-badge <?php echo esc_attr( $essential_page_class ); ?>"><?php echo esc_html( $essential_page_status ); ?></span>
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
