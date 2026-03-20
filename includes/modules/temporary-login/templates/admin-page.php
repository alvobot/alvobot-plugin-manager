<?php
/**
 * Template da seção adicional do Temporary Login
 * Inclui informações de API e documentação
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
// Configurar as variáveis necessárias para o JavaScript
if (typeof wpApiSettings === 'undefined') {
	window.wpApiSettings = {
		root: '<?php echo esc_url_raw( rest_url() ); ?>',
		nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
	};
}

// Função para alternar documentação da API
function toggleApiDocs() {
	var docs = document.getElementById('api-documentation');
	var toggleText = document.getElementById('api-toggle-text');
	if (!docs || !toggleText) { return; }

	if (docs.style.display === 'none') {
		docs.style.display = 'block';
		toggleText.textContent = '<?php echo esc_js( __( 'Ocultar Documentação', 'alvobot-pro' ) ); ?>';
	} else {
		docs.style.display = 'none';
		toggleText.textContent = '<?php echo esc_js( __( 'Ver Documentação da API', 'alvobot-pro' ) ); ?>';
	}
}

// Event listeners para botões de copiar
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.copy-endpoint').forEach(button => {
		button.addEventListener('click', function() {
			const textToCopy = this.getAttribute('data-copy');

			if (navigator.clipboard) {
				navigator.clipboard.writeText(textToCopy).then(function() {
					button.textContent = '<?php echo esc_js( __( 'Copiado!', 'alvobot-pro' ) ); ?>';
					setTimeout(() => {
						button.textContent = '<?php echo esc_js( __( 'Copiar URL', 'alvobot-pro' ) ); ?>';
					}, 2000);
				}).catch(function(err) {
					console.error('Failed to copy: ', err);
					button.textContent = '<?php echo esc_js( __( 'Copiar URL', 'alvobot-pro' ) ); ?>';
				});
			}
		});
	});
});
</script>
