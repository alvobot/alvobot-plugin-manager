<?php
// No namespace, to make class globally accessible

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AlvoBotPro_TemporaryLogin_Admin_Pointer {
	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	public static function enqueue_scripts() {
		$current_screen = get_current_screen();

		if ( ! $current_screen || 'plugins' !== $current_screen->id ) {
			return;
		}

		$is_dismissed = get_user_meta( get_current_user_id(), 'alvobot_pro_temporary_login_install_pointer_dismissed', true );

		if ( '1' === $is_dismissed ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		add_action( 'admin_print_footer_scripts', [ __CLASS__, 'print_footer_scripts' ] );
	}

	public static function print_footer_scripts() {
		$pointer_content = '<h3>' . esc_html__( 'Temporary Login Installed', 'alvobot-pro' ) . '</h3>';
		$pointer_content .= '<p>' . esc_html__( 'You can now create temporary users for secure access.', 'alvobot-pro' ) . '</p>';
		?>
		<script>
			jQuery( document ).ready( function( $ ) {
				const pointerOptions = {
					content: '<?php echo $pointer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
					close: function() {
						$.post( ajaxurl, {
							action: 'alvobot_pro_temporary_login_dismiss_pointer',
							_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'alvobot_pro_temporary_login_dismiss_pointer' ) ); ?>'
						} );
					},
					position: {
						edge: 'top',
						align: 'left'
					}
				};
				$( '#menu-plugins' ).pointer( pointerOptions ).pointer( 'open' );
			} );
		</script>
		<?php
	}

	public static function ajax_dismiss_pointer() {
		check_ajax_referer( 'alvobot_pro_temporary_login_dismiss_pointer' );

		update_user_meta( get_current_user_id(), 'alvobot_pro_temporary_login_install_pointer_dismissed', '1' );

		wp_die();
	}
}

AlvoBotPro_TemporaryLogin_Admin_Pointer::init();

add_action( 'wp_ajax_alvobot_pro_temporary_login_dismiss_pointer', [ 'AlvoBotPro_TemporaryLogin_Admin_Pointer', 'ajax_dismiss_pointer' ] );
