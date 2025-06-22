jQuery(document).ready(function($) {
    'use strict';

    const TranslationQueue = {
        init: function() {
            this.bindEvents();
            this.loadData();
            console.log('Translation Queue JS Initialized');
        },

        bindEvents: function() {
            $('#alvobot-translation-queue-table').on('click', '.remove-item', this.handleRemoveItem);
        },

        loadData: function() {
            $.ajax({
                url: alvobotMultiLanguages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_get_queue_status',
                    nonce: alvobotMultiLanguages.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        // Atualiza a tabela com os dados
                        if (data.queue && data.queue.items) {
                            const $table = $('#alvobot-translation-queue-table tbody');
                            $table.empty();
                            
                            data.queue.items.forEach(function(item) {
                                const row = `
                                    <tr>
                                        <td>${item.post_title}</td>
                                        <td>${item.source_lang}</td>
                                        <td>${item.target_langs}</td>
                                        <td>${item.status}</td>
                                        <td>${item.progress}%</td>
                                        <td>
                                            <button class="remove-item" data-id="${item.id}">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                                $table.append(row);
                            });
                        }
                        
                        // Atualiza estatísticas
                        if (data.status) {
                            $('#queue-total').text(data.status.total || 0);
                            $('#queue-pending').text(data.status.pending || 0);
                            $('#queue-processing').text(data.status.processing || 0);
                            $('#queue-completed').text(data.status.completed || 0);
                            $('#queue-error').text(data.status.error || 0);
                        }
                    } else {
                        console.error('Erro ao carregar dados da fila:', response.data);
                        alert('Erro ao carregar dados: ' + (response.data.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição AJAX:', error);
                }
            });
        },

        handleRemoveItem: function(e) {
            e.preventDefault();

            const button = $(this);
            const itemId = button.data('id');
            const row = button.closest('tr');

            if (!itemId) {
                console.error('Queue item ID not found.');
                return;
            }

            if (!confirm('Tem certeza que deseja remover este item da fila? Esta ação não pode ser desfeita.')) {
                return;
            }
            
            row.css('opacity', '0.5');
            button.prop('disabled', true);

            $.ajax({
                url: alvobotMultiLanguages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_remove_queue_item',
                    nonce: alvobotMultiLanguages.nonce,
                    id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Falha ao remover o item: ' + (response.data || 'Erro desconhecido.'));
                        row.css('opacity', '1');
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Ocorreu um erro de comunicação. Tente novamente.');
                    row.css('opacity', '1');
                    button.prop('disabled', false);
                }
            });
        }
    };

    TranslationQueue.init();
});
