<?php
/**
 * CTA Cards Admin Page Template
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation display logic, no data modification.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'builder';
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
		
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="mouse-pointer-click" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<p><?php esc_html_e( 'Crie cards de CTA (Call-to-Action) personalizados para seus artigos usando shortcodes.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<nav class="nav-tab-wrapper">
			<a href="?page=alvobot-cta-cards&tab=builder"
				class="nav-tab <?php echo $active_tab == 'builder' ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="wrench" class="alvobot-icon"></i>
				<?php esc_html_e( 'Gerador', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=alvobot-cta-cards&tab=examples"
				class="nav-tab <?php echo $active_tab == 'examples' ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="layout" class="alvobot-icon"></i>
				<?php esc_html_e( 'Exemplos', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=alvobot-cta-cards&tab=docs"
				class="nav-tab <?php echo $active_tab == 'docs' ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="book-open" class="alvobot-icon"></i>
				<?php esc_html_e( 'Documentação', 'alvobot-pro' ); ?>
			</a>
		</nav>

		<div class="cta-tab-content alvobot-tab-content">
			<?php
			switch ( $active_tab ) {
				case 'builder':
					include plugin_dir_path( __FILE__ ) . 'builder-view.php';
					break;
				case 'examples':
					include plugin_dir_path( __FILE__ ) . 'examples-view.php';
					break;
				case 'docs':
					include plugin_dir_path( __FILE__ ) . 'docs-view.php';
					break;
				default:
					include plugin_dir_path( __FILE__ ) . 'builder-view.php';
			}
			?>
		</div>
	</div>
</div>