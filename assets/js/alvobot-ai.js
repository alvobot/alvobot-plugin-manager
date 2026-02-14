/**
 * AlvoBot AI - JavaScript compartilhado para funcionalidades de AI
 * Gerencia creditos, custos e geracoes AI para todos os modulos.
 */
(function($) {
    'use strict';

    window.AlvobotAI = {

        /**
         * Atualiza creditos via AJAX e re-renderiza badges
         */
        refreshCredits: function(callback) {
            $.post(alvobotAI.ajaxurl, {
                action: 'alvobot_ai_get_credits',
                nonce: alvobotAI.nonce
            }, function(response) {
                if (response.success) {
                    alvobotAI.credits = response.data;
                    AlvobotAI._updateAllBadges();
                }
                if (typeof callback === 'function') {
                    callback(response.success ? response.data : null);
                }
            });
        },

        /**
         * Renderiza badge de creditos num container
         */
        renderCreditsBadge: function($container) {
            var credits = alvobotAI.credits || {};
            var total = parseInt(credits.total_available) || 0;
            var limit = parseInt(credits.monthly_limit) || 0;
            var hasPlan = !!credits.has_active_plan;
            var hasError = !!credits.error;

            var colorClass = 'alvobot-credits-ok';
            var text = '';

            if (hasError) {
                colorClass = 'alvobot-credits-error';
                text = 'Erro ao carregar créditos';
            } else if (!hasPlan) {
                colorClass = 'alvobot-credits-noplan';
                text = 'Sem plano ativo';
            } else {
                if (limit > 0 && total <= limit * 0.1) {
                    colorClass = 'alvobot-credits-critical';
                } else if (limit > 0 && total <= limit * 0.25) {
                    colorClass = 'alvobot-credits-low';
                }
                text = '<strong>' + total + '</strong> créditos disponíveis';
            }

            var html = '<div class="alvobot-credits-badge ' + colorClass + '">' +
                '<i data-lucide="ticket" class="alvobot-icon"></i> ' +
                text +
                ' <button type="button" class="alvobot-credits-refresh" title="Atualizar créditos">' +
                '<i data-lucide="refresh-cw" class="alvobot-icon"></i>' +
                '</button></div>';

            $container.html(html);
        },

        /**
         * Mostra modal de confirmacao de custo
         */
        showCostConfirmation: function(actionName, cost, onConfirm) {
            var credits = alvobotAI.credits || {};
            var total = parseInt(credits.total_available) || 0;

            if (total < cost) {
                alert('Créditos insuficientes. Você tem ' + total + ' créditos, mas precisa de ' + cost + '.');
                return;
            }

            var msg = 'Esta ação consome ' + cost + ' crédito' + (cost > 1 ? 's' : '') + '.\n' +
                'Você tem ' + total + ' créditos disponíveis.\n\n' +
                'Deseja continuar?';

            if (confirm(msg)) {
                onConfirm();
            }
        },

        /**
         * Chama geracao AI generica
         */
        generate: function(generationType, params, callbacks) {
            callbacks = callbacks || {};

            if (typeof callbacks.onStart === 'function') {
                callbacks.onStart();
            }

            $.post(alvobotAI.ajaxurl, {
                action: 'alvobot_ai_generate',
                nonce: alvobotAI.nonce,
                generation_type: generationType,
                params: params
            }, function(response) {
                if (response.success) {
                    // Update local credits
                    if (response.data && response.data.credits) {
                        alvobotAI.credits.total_available = response.data.credits.remaining;
                        AlvobotAI._updateAllBadges();
                    }
                    if (typeof callbacks.onSuccess === 'function') {
                        callbacks.onSuccess(response.data);
                    }
                } else {
                    if (typeof callbacks.onError === 'function') {
                        callbacks.onError(response.data);
                    }
                }
            }).fail(function() {
                if (typeof callbacks.onError === 'function') {
                    callbacks.onError({ message: 'Erro de conexão com o servidor.' });
                }
            }).always(function() {
                if (typeof callbacks.onComplete === 'function') {
                    callbacks.onComplete();
                }
            });
        },

        /**
         * Aplica autor gerado ao WordPress
         */
        applyAuthor: function(data, callbacks) {
            callbacks = callbacks || {};

            $.post(alvobotAI.ajaxurl, {
                action: 'alvobot_ai_apply_author',
                nonce: alvobotAI.nonce,
                user_id: data.user_id,
                display_name: data.display_name,
                description: data.description,
                avatar_url: data.avatar_url || '',
                lang_code: data.lang_code || ''
            }, function(response) {
                if (response.success) {
                    if (typeof callbacks.onSuccess === 'function') {
                        callbacks.onSuccess(response.data);
                    }
                } else {
                    if (typeof callbacks.onError === 'function') {
                        callbacks.onError(response.data);
                    }
                }
            }).fail(function() {
                if (typeof callbacks.onError === 'function') {
                    callbacks.onError('Erro de conexão com o servidor.');
                }
            });
        },

        /**
         * Atualiza todos os badges de creditos na pagina
         */
        _updateAllBadges: function() {
            $('.alvobot-credits-badge-container').each(function() {
                AlvobotAI.renderCreditsBadge($(this));
            });
        }
    };

    // Event delegation: refresh button
    $(document).on('click', '.alvobot-credits-refresh', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.find('.alvobot-icon, svg').addClass('spin');
        AlvobotAI.refreshCredits(function() {
            $btn.find('.alvobot-icon, svg').removeClass('spin');
        });
    });

})(jQuery);
