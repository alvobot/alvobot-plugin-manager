/**
 * Essential Pages Admin JavaScript
 * Versão 2.0 - Completamente refatorada
 */
jQuery(document).ready(function ($) {
	console.log('Essential Pages Admin JS loaded (v2.0)');

	// Remover TODOS os handlers de evento existentes dos botões
	$(document).off('click', '.alvobot-btn');
	$(document).off('submit', 'form');
	$('.alvobot-btn').off('click');
	$('form').off('submit');

	// Remover atributos onclick inline que possam estar causando comportamentos duplicados
	$('.alvobot-btn').prop('onclick', null);
	$('button[type="submit"]').prop('onclick', null);

	function showNotice(message) {
		const $notice = $(`<div class="notice notice-info is-dismissible"><p>${message}</p></div>`);
		$('.alvobot-notice-container').empty().append($notice);
	}

	// Não vamos prevenir o comportamento padrão dos formulários
	// deixamos o WordPress gerenciar a validação e a submissão

	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('action_complete')) {
		const action = urlParams.get('action_complete');
		const status = urlParams.get('status') || 'success';

		let message = '';
		switch (action) {
			case 'created':
				message = 'Página criada com sucesso!';
				break;
			case 'deleted':
				message = 'Página excluída com sucesso!';
				break;
			case 'all_created':
				message = 'Todas as páginas foram criadas com sucesso!';
				break;
			case 'all_deleted':
				message = 'Todas as páginas foram excluídas com sucesso!';
				break;
			default:
				message = 'Operação concluída com sucesso!';
		}

		showNotice(message);

		// Clean URL to prevent duplicate notifications on refresh
		window.history.replaceState({}, document.title, window.location.pathname + '?page=alvobot-pro-essential-pages');
	}
});
