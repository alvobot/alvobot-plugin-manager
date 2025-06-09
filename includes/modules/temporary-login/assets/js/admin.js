/**
 * JavaScript para o módulo Temporary Login
 */

(function($) {
    'use strict';

    let tempLoginAdmin = {
        
        init: function() {
            this.bindEvents();
            this.loadStatus();
        },

        bindEvents: function() {
            $(document).on('click', '#create-temp-login', this.createTempLogin);
            $(document).on('click', '#revoke-temp-logins', this.revokeTempLogins);
            $(document).on('click', '#revoke-single-login', this.revokeTempLogins);
            $(document).on('click', '.copy-login-url', this.copyLoginUrl);
            $(document).on('click', '.extend-temp-login', this.extendTempLogin);
        },

        loadStatus: function() {
            const statusContainer = $('#temporary-login-status');
            
            if (!statusContainer.length) return;
            
            statusContainer.html('<div class="loading">Carregando status...</div>');
            
            $.ajax({
                url: '/wp-json/alvobot-pro/v1/temporary-login/status',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: function(response) {
                    tempLoginAdmin.renderStatus(response);
                },
                error: function(xhr) {
                    console.error('Erro ao carregar status:', xhr);
                    statusContainer.html('<div class="temp-login-inactive">Erro ao carregar status dos logins temporários.</div>');
                }
            });
        },

        renderStatus: function(data) {
            const statusContainer = $('#temporary-login-status');
            let html = '';
            
            if (data.status === 'active') {
                html = `
                    <div class="alvobot-temp-login-card alvobot-temp-login-active">
                        <div class="alvobot-temp-login-header">
                            <div class="alvobot-temp-login-status-badge alvobot-badge-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span class="alvobot-temp-login-status-text">Ativo</span>
                            </div>
                            <div class="alvobot-temp-login-actions">
                                <button class="alvobot-btn alvobot-btn-sm alvobot-btn-outline extend-temp-login" title="Estender acesso">
                                    <span class="dashicons dashicons-clock"></span>
                                </button>
                                <button class="alvobot-btn alvobot-btn-sm alvobot-btn-danger" id="revoke-single-login" title="Revogar acesso">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="alvobot-temp-login-details">
                            <div class="alvobot-temp-login-info">
                                <div class="alvobot-temp-login-title">Login Temporário Ativo</div>
                                <div class="alvobot-temp-login-expiry">
                                    <span class="dashicons dashicons-schedule"></span>
                                    Expira em: ${data.expiration_human}
                                </div>
                            </div>
                            
                            <div class="alvobot-temp-login-url">
                                <label class="alvobot-temp-login-label">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    URL de Acesso
                                </label>
                                <div class="alvobot-temp-login-url-wrapper">
                                    <input type="text" readonly value="${data.login_url}" class="alvobot-temp-login-url-input" />
                                    <button class="alvobot-btn alvobot-btn-primary alvobot-btn-sm copy-login-url" data-url="${data.login_url}">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        Copiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html = `
                    <div class="alvobot-temp-login-card alvobot-temp-login-empty">
                        <div class="alvobot-temp-login-empty-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="alvobot-temp-login-empty-content">
                            <h4 class="alvobot-temp-login-empty-title">Nenhum Login Temporário Ativo</h4>
                            <p class="alvobot-temp-login-empty-subtitle">
                                Crie um novo acesso temporário para permitir que outras pessoas acessem o painel administrativo por tempo limitado.
                            </p>
                        </div>
                    </div>
                `;
            }
            
            statusContainer.html(html);
        },

        createTempLogin: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('Criando...');
            
            $.ajax({
                url: '/wp-json/alvobot-pro/v1/temporary-login/create',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: JSON.stringify({}),
                contentType: 'application/json',
                success: function(response) {
                    if (response && response.message) {
                        tempLoginAdmin.showNotice(response.message, 'success');
                        tempLoginAdmin.loadStatus();
                    } else {
                        tempLoginAdmin.showNotice('Login temporário criado com sucesso!', 'success');
                        tempLoginAdmin.loadStatus();
                    }
                },
                error: function(xhr) {
                    console.error('Erro ao criar login temporário:', xhr);
                    tempLoginAdmin.showNotice('Erro ao criar login temporário.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        revokeTempLogins: function(e) {
            e.preventDefault();
            
            if (!confirm('Tem certeza que deseja revogar todos os logins temporários?')) {
                return;
            }
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('Revogando...');
            
            $.ajax({
                url: '/wp-json/alvobot-pro/v1/temporary-login/revoke',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: function(response) {
                    if (response && response.message) {
                        tempLoginAdmin.showNotice(response.message, 'success');
                        tempLoginAdmin.loadStatus();
                    } else {
                        tempLoginAdmin.showNotice('Logins temporários revogados com sucesso!', 'success');
                        tempLoginAdmin.loadStatus();
                    }
                },
                error: function(xhr) {
                    console.error('Erro ao revogar logins temporários:', xhr);
                    tempLoginAdmin.showNotice('Erro ao revogar logins temporários.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        copyLoginUrl: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const url = button.data('url') || button.closest('.temp-login-url-field').find('input').val();
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    tempLoginAdmin.showCopyFeedback(button);
                }).catch(function() {
                    tempLoginAdmin.fallbackCopyText(url, button);
                });
            } else {
                tempLoginAdmin.fallbackCopyText(url, button);
            }
        },

        fallbackCopyText: function(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showCopyFeedback(button);
            } catch (err) {
                console.error('Erro ao copiar:', err);
                this.showNotice('Erro ao copiar URL. Tente selecionar e copiar manualmente.', 'error');
            }
            
            document.body.removeChild(textArea);
        },

        showCopyFeedback: function(button) {
            const originalText = button.text();
            button.text('Copiado!').addClass('alvobot-btn-success');
            
            setTimeout(function() {
                button.text(originalText).removeClass('alvobot-btn-success');
            }, 2000);
        },

        extendTempLogin: function(e) {
            e.preventDefault();
            
            if (!confirm('Tem certeza que deseja estender este login temporário?')) {
                return;
            }
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('Estendendo...');
            
            $.ajax({
                url: '/wp-json/alvobot-pro/v1/temporary-login/extend',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: function(response) {
                    if (response && response.message) {
                        tempLoginAdmin.showNotice(response.message, 'success');
                        tempLoginAdmin.loadStatus();
                    } else {
                        tempLoginAdmin.showNotice('Login temporário estendido com sucesso!', 'success');
                        tempLoginAdmin.loadStatus();
                    }
                },
                error: function(xhr) {
                    console.error('Erro ao estender login temporário:', xhr);
                    tempLoginAdmin.showNotice('Erro ao estender login temporário.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            const notice = $(`
                <div class="alvobot-notice alvobot-notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dispensar este aviso.</span>
                    </button>
                </div>
            `);
            
            // Remove notices existentes
            $('.alvobot-notice').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Adiciona o novo notice
            $('.alvobot-admin-header').after(notice);
            
            // Auto-remove após 5 segundos
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Bind do botão de fechar
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Inicializa quando o DOM estiver pronto
    $(document).ready(function() {
        // Verifica se estamos na página correta
        if ($('.alvobot-temporary-login-wrap').length || $('#temporary-login-status').length) {
            tempLoginAdmin.init();
        }
    });

    // Expõe o objeto globalmente para debug
    window.tempLoginAdmin = tempLoginAdmin;

})(jQuery);