<?php
/**
 * Pixel Tracking - Admin Page Template with Tabs
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation display logic, no data modification.
$alvobot_pt_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'pixels';
$alvobot_pt_page_slug  = 'alvobot-pro-pixel-tracking';
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">

		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="activity" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php esc_html_e( 'Pixel Tracking', 'alvobot-pro' ); ?></h1>
				<p><?php esc_html_e( 'Rastreamento de eventos com Meta Pixel e Facebook Conversion API.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<?php $this->render_admin_notices(); ?>

		<nav class="nav-tab-wrapper">
			<a href="?page=<?php echo esc_attr( $alvobot_pt_page_slug ); ?>&tab=pixels"
				class="nav-tab <?php echo 'pixels' === $alvobot_pt_active_tab ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="radio" class="alvobot-icon"></i>
				<?php esc_html_e( 'Pixels', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $alvobot_pt_page_slug ); ?>&tab=conversoes"
				class="nav-tab <?php echo 'conversoes' === $alvobot_pt_active_tab ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="target" class="alvobot-icon"></i>
				<?php esc_html_e( 'Conversoes', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $alvobot_pt_page_slug ); ?>&tab=configuracoes"
				class="nav-tab <?php echo 'configuracoes' === $alvobot_pt_active_tab ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="settings" class="alvobot-icon"></i>
				<?php esc_html_e( 'Configuracoes', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $alvobot_pt_page_slug ); ?>&tab=status"
				class="nav-tab <?php echo 'status' === $alvobot_pt_active_tab ? 'nav-tab-active' : ''; ?>">
				<i data-lucide="bar-chart-3" class="alvobot-icon"></i>
				<?php esc_html_e( 'Status', 'alvobot-pro' ); ?>
			</a>
		</nav>

		<div class="alvobot-tab-content">
			<?php
			switch ( $alvobot_pt_active_tab ) {
				case 'pixels':
					include plugin_dir_path( __FILE__ ) . 'tab-pixels.php';
					break;
				case 'conversoes':
					include plugin_dir_path( __FILE__ ) . 'tab-conversoes.php';
					break;
				case 'configuracoes':
					include plugin_dir_path( __FILE__ ) . 'tab-configuracoes.php';
					break;
				case 'status':
					include plugin_dir_path( __FILE__ ) . 'tab-status.php';
					break;
				default:
					include plugin_dir_path( __FILE__ ) . 'tab-pixels.php';
			}
			?>
		</div>
	</div>
</div>
