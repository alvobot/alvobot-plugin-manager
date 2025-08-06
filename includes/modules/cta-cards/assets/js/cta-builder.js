/**
 * AlvoBot CTA Cards Builder
 * Modern builder interface JavaScript
 */

(function($) {
    'use strict';

    var CTABuilder = {
        
        selectedTemplate: null,
        formData: {},
        updateTimer: null,
        
        init: function() {
            this.bindEvents();
            this.initTemplateSelection();
            this.initPreviewToggle();
            this.updateStats();
        },

        bindEvents: function() {
            var self = this;

            // Template selection
            $(document).on('click', '.template-item', function() {
                self.selectTemplate($(this).data('template'));
            });

            // Form changes trigger preview update
            $(document).on('input change keyup paste', '#cta-config-form input, #cta-config-form select, #cta-config-form textarea', function() {
                // Add small delay for better performance on rapid typing
                clearTimeout(self.updateTimer);
                self.updateTimer = setTimeout(function() {
                    self.collectFormData();
                    self.updatePreview();
                    self.generateShortcode();
                    self.updateStats();
                }, 300);
            });

            // Reset button - only clear fields, don't deselect template
            $(document).on('click', '#btn--reset', function() {
                self.clearFields();
            });

            // Copy shortcode
            $(document).on('click', '#btn--copy', function() {
                self.copyShortcode();
            });

            // Preview toggle
            $(document).on('click', '#preview-mobile, #preview-desktop', function() {
                self.togglePreview($(this).attr('id'));
            });

            // Examples button
            $(document).on('click', '#btn--examples', function() {
                window.location.href = '?page=alvobot-cta-cards&tab=examples';
            });

            // Color picker changes (both direct and WordPress color picker)
            $(document).on('change', 'input[type="color"]', function() {
                clearTimeout(self.updateTimer);
                self.updateTimer = setTimeout(function() {
                    self.collectFormData();
                    self.updatePreview();
                    self.generateShortcode();
                    self.updateStats();
                }, 100);
            });
        },

        initTemplateSelection: function() {
            // Add click handlers and visual feedback
            $('.template-item').each(function() {
                $(this).on('mouseenter', function() {
                    if (!$(this).hasClass('active')) {
                        $(this).css('transform', 'scale(1.02)');
                    }
                }).on('mouseleave', function() {
                    if (!$(this).hasClass('active')) {
                        $(this).css('transform', 'scale(1)');
                    }
                });
            });
        },

        initPreviewToggle: function() {
            // Initialize with desktop view
            $('#preview-desktop').addClass('is-active');
            $('#preview-mobile').removeClass('is-active');
            $('#cta-preview-content').addClass('desktop-frame').removeClass('mobile-frame');
        },

        selectTemplate: function(templateName) {
            var self = this;
            
            // Update UI
            $('.template-item').removeClass('active');
            $('.template-item[data-template="' + templateName + '"]').addClass('active');

            this.selectedTemplate = templateName;
            
            // Hide empty state, show form
            $('#cta-empty-state').hide();
            $('#cta-config-form').show();
            
            // Generate form fields
            this.buildForm(templateName);
            
            // Fill with example data
            this.fillExampleData(templateName);
            
            // Initialize color pickers after form is built
            setTimeout(function() {
                self.initColorPickers();
                // Update preview after color pickers are ready
                setTimeout(function() {
                    self.collectFormData();
                    self.updatePreview();
                    self.generateShortcode();
                    self.updateStats();
                }, 100);
            }, 100);
        },

        buildForm: function(templateName) {
            var html = '';
            
            // Basic fields (all templates)
            html += '<div class="form-section">';
            html += '<h4>Conteúdo Principal</h4>';
            html += '<div class="form-row">';
            html += '<div class="form-group">';
            html += '<label for="cta-title">Título:</label>';
            html += '<input type="text" id="cta-title" name="title" placeholder="Digite o título do seu CTA">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '<label for="cta-subtitle">Subtítulo:</label>';
            html += '<input type="text" id="cta-subtitle" name="subtitle" placeholder="Subtítulo (opcional)">';
            html += '</div>';
            html += '</div>';
            
            // Description (most templates)
            if (['vertical', 'horizontal', 'banner', 'pulse', 'multi-button', 'led-border'].includes(templateName)) {
                html += '<div class="form-row">';
                html += '<div class="form-group full-width">';
                html += '<label for="cta-description">Descrição:</label>';
                html += '<textarea id="cta-description" name="description" rows="3" placeholder="Descrição do seu CTA (opcional)"></textarea>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>'; // End content section

            // Template specific fields
            html += this.getTemplateSpecificFields(templateName);

            // Action section
            html += '<div class="form-section">';
            html += '<h4>Ação Principal</h4>';
            html += '<div class="form-row">';
            html += '<div class="form-group">';
            html += '<label for="cta-button">Texto do Botão:</label>';
            html += '<input type="text" id="cta-button" name="button" placeholder="Saiba Mais" value="Saiba Mais">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '<label for="cta-url">URL de Destino:</label>';
            html += '<input type="url" id="cta-url" name="url" placeholder="https://exemplo.com/pagina">';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="form-row">';
            html += '<div class="form-group">';
            html += '<label for="cta-target">Abrir Link:</label>';
            html += '<select id="cta-target" name="target">';
            html += '<option value="_self">Na mesma janela</option>';
            html += '<option value="_blank">Em nova janela</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="form-group">';
            html += '<label for="cta-align">Alinhamento:</label>';
            html += '<select id="cta-align" name="align">';
            html += '<option value="center">Centro</option>';
            html += '<option value="left">Esquerda</option>';
            html += '<option value="right">Direita</option>';
            html += '</select>';
            html += '</div>';
            html += '</div>';
            html += '</div>'; // End action section

            // Colors section
            html += '<div class="form-section">';
            html += '<h4>Personalização de Cores</h4>';
            html += '<div class="form-row">';
            html += '<div class="form-group">';
            html += '<label for="cta-color-primary">Cor Principal:</label>';
            html += '<input type="color" id="cta-color-primary" name="color_primary" value="#2271b1">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '<label for="cta-color-button">Cor do Botão:</label>';
            html += '<input type="color" id="cta-color-button" name="color_button" value="#2271b1">';
            html += '</div>';
            html += '</div>';
            html += '<div class="form-row">';
            html += '<div class="form-group">';
            html += '<label for="cta-color-text">Cor do Texto:</label>';
            html += '<input type="color" id="cta-color-text" name="color_text" value="#333333">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '<label for="cta-color-bg">Cor de Fundo:</label>';
            html += '<input type="color" id="cta-color-bg" name="color_bg" value="#ffffff">';
            html += '</div>';
            html += '</div>';
            html += '</div>'; // End colors section

            $('#form-content').html(html);
        },

        getTemplateSpecificFields: function(templateName) {
            var html = '';
            
            switch(templateName) {
                case 'vertical':
                case 'horizontal':
                case 'multi-button':
                    html += '<div class="form-section">';
                    html += '<h4>Imagem</h4>';
                    html += '<div class="form-row">';
                    html += '<div class="form-group full-width">';
                    html += '<label for="cta-image">URL da Imagem:</label>';
                    html += '<input type="url" id="cta-image" name="image" placeholder="https://exemplo.com/imagem.jpg">';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    break;
                    
                case 'banner':
                    html += '<div class="form-section">';
                    html += '<h4>Imagem de Fundo</h4>';
                    html += '<div class="form-row">';
                    html += '<div class="form-group full-width">';
                    html += '<label for="cta-background">URL da Imagem de Fundo:</label>';
                    html += '<input type="url" id="cta-background" name="background" placeholder="https://exemplo.com/background.jpg">';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    break;
                    
                case 'minimal':
                    html += '<div class="form-section">';
                    html += '<h4>Badge</h4>';
                    html += '<div class="form-row">';
                    html += '<div class="form-group">';
                    html += '<label for="cta-tag">Tag/Badge:</label>';
                    html += '<input type="text" id="cta-tag" name="tag" placeholder="PDF, GRÁTIS, NOVO...">';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    break;
                    
                case 'simple':
                case 'pulse':
                case 'led-border':
                    html += '<div class="form-section">';
                    html += '<h4>Ícone/Emoji</h4>';
                    html += '<div class="form-row">';
                    html += '<div class="form-group">';
                    html += '<label for="cta-icon">Ícone/Emoji:</label>';
                    html += '<input type="text" id="cta-icon" name="icon" placeholder="🌟 ou dashicons-star-filled">';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="form-note">';
                    html += '<p><small>Use emojis (🌟, ⚡, ▶️) ou ícones Dashicons</small></p>';
                    html += '</div>';
                    html += '</div>';
                    break;
            }
            
            // Template specific additional fields
            if (templateName === 'pulse') {
                html += '<div class="form-section">';
                html += '<h4>Configurações do Pulse</h4>';
                html += '<div class="form-row">';
                html += '<div class="form-group">';
                html += '<label for="cta-pulse-text">Texto do Pulse:</label>';
                html += '<input type="text" id="cta-pulse-text" name="pulse_text" placeholder="AO VIVO" value="AO VIVO">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '<label for="cta-pulse-color">Cor do Pulse:</label>';
                html += '<input type="color" id="cta-pulse-color" name="pulse_color" value="#ff6b6b">';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            if (templateName === 'multi-button') {
                html += '<div class="form-section">';
                html += '<h4>Botões Adicionais</h4>';
                html += '<div class="form-row">';
                html += '<div class="form-group">';
                html += '<label for="cta-button2">Botão 2 (opcional):</label>';
                html += '<input type="text" id="cta-button2" name="button2" placeholder="Segundo botão">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '<label for="cta-url2">URL 2:</label>';
                html += '<input type="url" id="cta-url2" name="url2" placeholder="https://exemplo.com/pagina2">';
                html += '</div>';
                html += '</div>';
                html += '<div class="form-row">';
                html += '<div class="form-group">';
                html += '<label for="cta-button3">Botão 3 (opcional):</label>';
                html += '<input type="text" id="cta-button3" name="button3" placeholder="Terceiro botão">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '<label for="cta-url3">URL 3:</label>';
                html += '<input type="url" id="cta-url3" name="url3" placeholder="https://exemplo.com/pagina3">';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            if (templateName === 'led-border') {
                html += '<div class="form-section">';
                html += '<h4>Imagem (Opcional)</h4>';
                html += '<div class="form-row">';
                html += '<div class="form-group full-width">';
                html += '<label for="cta-image">URL da Imagem:</label>';
                html += '<input type="url" id="cta-image" name="image" placeholder="https://exemplo.com/imagem.jpg">';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="form-section">';
                html += '<h4>Configurações LED</h4>';
                html += '<div class="form-row">';
                html += '<div class="form-group">';
                html += '<label for="cta-led-colors">Cores LED (separadas por vírgula):</label>';
                html += '<input type="text" id="cta-led-colors" name="led_colors" placeholder="#ff0080,#00ff80,#8000ff" value="#ff0080,#00ff80,#8000ff,#ff8000">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '<label for="cta-led-speed">Velocidade:</label>';
                html += '<select id="cta-led-speed" name="led_speed">';
                html += '<option value="1s">Rápida (1s)</option>';
                html += '<option value="2s" selected>Normal (2s)</option>';
                html += '<option value="3s">Lenta (3s)</option>';
                html += '<option value="4s">Muito Lenta (4s)</option>';
                html += '</select>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            return html;
        },

        initColorPickers: function() {
            var self = this;
            // Initialize WordPress color picker if available
            if ($.fn.wpColorPicker) {
                $('input[type="color"]').wpColorPicker({
                    change: function(event, ui) {
                        // Trigger preview update when color changes
                        clearTimeout(self.updateTimer);
                        self.updateTimer = setTimeout(function() {
                            self.collectFormData();
                            self.updatePreview();
                            self.generateShortcode();
                            self.updateStats();
                        }, 100);
                    }
                });
            }
        },

        collectFormData: function() {
            var data = {
                template: this.selectedTemplate
            };
            
            // Collect all form fields
            $('#cta-config-form input, #cta-config-form select, #cta-config-form textarea').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val().trim();
                
                if (name && value) {
                    data[name] = value;
                }
            });
            
            this.formData = data;
            return data;
        },

        updatePreview: function() {
            if (!this.selectedTemplate) {
                return;
            }
            
            var data = this.formData;
            var $previewContent = $('#cta-preview-content');
            
            // Build shortcode for preview
            var shortcode = '[cta_card';
            $.each(data, function(key, value) {
                if (value) {
                    shortcode += ' ' + key + '="' + value.replace(/"/g, '&quot;') + '"';
                }
            });
            shortcode += ']';
            
            // For now, use static preview always for reliability
            // TODO: Enable AJAX preview when debugging is complete
            this.renderStaticPreview(shortcode, $previewContent);
            
            // Try AJAX in background for comparison
            // this.renderPreview(shortcode);
        },

        renderPreview: function(shortcode) {
            var $previewContent = $('#cta-preview-content');
            var self = this;
            
            // Show loading state
            $previewContent.html('<div class="preview-loading"><i class="dashicons dashicons-update-alt"></i><p>Atualizando preview...</p></div>');
            
            console.log('Rendering preview for shortcode:', shortcode);
            console.log('AJAX URL:', alvobotCTACards.ajaxurl || ajaxurl);
            console.log('Nonce:', alvobotCTACards.nonce);
            
            // Make AJAX request to render shortcode
            $.ajax({
                url: alvobotCTACards.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'alvobot_render_cta_preview',
                    shortcode: shortcode,
                    nonce: alvobotCTACards.nonce || ''
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        $previewContent.html(response.data);
                    } else {
                        console.error('Preview error:', response.data);
                        $previewContent.html('<div class="preview-error"><i class="dashicons dashicons-warning"></i><p>Erro: ' + (response.data || 'Erro desconhecido') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    // Try fallback - render a static preview
                    self.renderStaticPreview(shortcode, $previewContent);
                }
            });
        },

        renderStaticPreview: function(shortcode, $container) {
            // Fallback: render a basic preview without server-side rendering
            var data = this.formData;
            var template = data.template || 'vertical';
            
            var html = '<div class="alvobot-cta-card alvobot-cta-' + template + '" style="margin: 0; background-color: ' + (data.color_bg || '#ffffff') + ';">';
            
            switch(template) {
                case 'vertical':
                    html += '<div class="cta-content" style="text-align: center; padding: 20px;">';
                    if (data.image) html += '<div style="margin-bottom: 20px;"><img src="' + data.image + '" style="max-width: 100%; height: auto; border-radius: 6px; max-height: 200px; object-fit: cover;" /></div>';
                    if (data.title) html += '<h3 style="margin: 0 0 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.subtitle) html += '<p style="margin: 0 0 10px 0; color: #666;">' + data.subtitle + '</p>';
                    if (data.description) html += '<div style="margin: 0 0 20px 0; color: #555;">' + data.description + '</div>';
                    if (data.button) html += '<a href="#" style="display: inline-block; padding: 12px 24px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 6px;">' + data.button + '</a>';
                    html += '</div>';
                    break;
                    
                case 'horizontal':
                    html += '<div style="display: flex; align-items: center; padding: 20px; gap: 20px; flex-wrap: wrap;">';
                    if (data.image) html += '<div style="flex: 0 0 150px; min-width: 150px;"><img src="' + data.image + '" style="width: 100%; height: 120px; object-fit: cover; border-radius: 6px;" /></div>';
                    html += '<div style="flex: 1; min-width: 200px;">';
                    if (data.title) html += '<h3 style="margin: 0 0 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.description) html += '<div style="margin: 0 0 15px 0; color: #555;">' + data.description + '</div>';
                    if (data.button) html += '<a href="#" style="display: inline-block; padding: 10px 20px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 6px;">' + data.button + '</a>';
                    html += '</div></div>';
                    // Add responsive styles for smaller screens
                    html += '<style>@media (max-width: 480px) { .alvobot-cta-horizontal > div { flex-direction: column !important; text-align: center !important; } .alvobot-cta-horizontal img { max-width: 100% !important; } }</style>';
                    break;
                    
                case 'minimal':
                    html += '<div style="padding: 20px; border: 2px solid #e0e0e0;">';
                    if (data.tag) html += '<span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: ' + (data.color_primary || '#2271b1') + '; color: white; margin-bottom: 15px;">' + data.tag + '</span>';
                    if (data.title) html += '<h4 style="margin: 0 0 8px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h4>';
                    if (data.subtitle) html += '<p style="margin: 0 0 15px 0; color: #666;">' + data.subtitle + '</p>';
                    if (data.button) html += '<a href="#" style="color: #2271b1; text-decoration: none; font-weight: 600;">' + data.button + ' →</a>';
                    html += '</div>';
                    break;
                    
                case 'banner':
                    var bannerBg = data.background ? 'url(' + data.background + ')' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    html += '<div style="position: relative; background: ' + bannerBg + '; background-size: cover; background-position: center; min-height: 200px; display: flex; align-items: center; justify-content: center; color: white; text-align: center;">';
                    html += '<div style="position: relative; z-index: 2; padding: 30px;">';
                    if (data.title) html += '<h2 style="margin: 0 0 15px 0; color: white; font-size: 28px;">' + data.title + '</h2>';
                    if (data.description) html += '<div style="margin: 0 0 25px 0; color: rgba(255,255,255,0.9);">' + data.description + '</div>';
                    if (data.button) html += '<a href="#" style="display: inline-block; padding: 15px 30px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; text-transform: uppercase;">' + data.button + '</a>';
                    html += '</div></div>';
                    break;
                    
                case 'simple':
                    html += '<div style="border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden;">';
                    html += '<div style="display: flex; align-items: center; padding: 15px 20px;">';
                    if (data.icon) html += '<span style="font-size: 20px; margin-right: 12px;">' + data.icon + '</span>';
                    if (data.title) html += '<span style="flex: 1; font-weight: 600; color: ' + (data.color_text || '#333') + '">' + data.title + '</span>';
                    html += '<span style="margin-left: 10px; color: #2271b1;">→</span>';
                    html += '</div></div>';
                    break;
                    
                case 'pulse':
                    html += '<div style="position: relative; padding: 30px 20px; text-align: center;">';
                    if (data.pulse_text) {
                        html += '<div style="position: absolute; top: 15px; right: 15px; display: flex; align-items: center; gap: 8px;">';
                        html += '<span style="width: 12px; height: 12px; background: ' + (data.pulse_color || '#ff6b6b') + '; border-radius: 50%; animation: pulse 2s infinite;"></span>';
                        html += '<span style="font-size: 12px; font-weight: 700; color: ' + (data.pulse_color || '#ff6b6b') + '">' + data.pulse_text + '</span>';
                        html += '</div>';
                    }
                    if (data.icon) html += '<div style="margin-bottom: 20px; font-size: 48px;">' + data.icon + '</div>';
                    if (data.title) html += '<h3 style="margin: 0 0 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.subtitle) html += '<p style="margin: 0 0 15px 0; color: #666;">' + data.subtitle + '</p>';
                    if (data.description) html += '<div style="margin: 0 0 25px 0; color: #555;">' + data.description + '</div>';
                    if (data.button) html += '<a href="#" style="display: inline-block; padding: 12px 24px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 6px;">' + data.button + '</a>';
                    html += '</div>';
                    break;
                    
                case 'multi-button':
                    html += '<div style="padding: 30px 20px; text-align: center;">';
                    if (data.image) html += '<div style="margin-bottom: 20px;"><img src="' + data.image + '" style="max-width: 100%; height: auto; border-radius: 6px; max-height: 200px; object-fit: cover;" /></div>';
                    if (data.title) html += '<h3 style="margin: 0 0 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.subtitle) html += '<p style="margin: 0 0 15px 0; color: #666;">' + data.subtitle + '</p>';
                    if (data.description) html += '<div style="margin: 0 0 25px 0; color: #555;">' + data.description + '</div>';
                    html += '<div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;">';
                    if (data.button) html += '<a href="#" style="padding: 14px 28px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 6px; font-weight: 700;">' + data.button + '</a>';
                    if (data.button2) html += '<a href="#" style="padding: 12px 24px; background: ' + (data.color_button2 || '#28a745') + '; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">' + data.button2 + '</a>';
                    if (data.button3) html += '<a href="#" style="padding: 10px 20px; background: transparent; border: 2px solid ' + (data.color_button3 || '#6c757d') + '; color: ' + (data.color_button3 || '#6c757d') + '; text-decoration: none; border-radius: 6px; font-weight: 600;">' + data.button3 + '</a>';
                    html += '</div></div>';
                    break;
                    
                case 'led-border':
                    html += '<div style="padding: 30px 20px; text-align: center;">';
                    if (data.image) html += '<div style="margin-bottom: 20px;"><img src="' + data.image + '" style="max-width: 100%; height: auto; border-radius: 6px; max-height: 200px; object-fit: cover;" /></div>';
                    if (data.icon) {
                        var iconDisplay = data.icon;
                        // Check if it's a dashicon
                        if (data.icon.indexOf('dashicons-') === 0) {
                            iconDisplay = '<span class="dashicons ' + data.icon + '" style="font-size: 48px; width: 48px; height: 48px;"></span>';
                        }
                        html += '<div style="margin-bottom: 20px; font-size: 48px; filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.8));">' + iconDisplay + '</div>';
                    }
                    if (data.title) html += '<h3 style="margin: 0 0 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.subtitle) html += '<p style="margin: 0 0 15px 0; color: #666;">' + data.subtitle + '</p>';
                    if (data.description) html += '<div style="margin: 0 0 25px 0; color: #555;">' + data.description + '</div>';
                    if (data.button) {
                        var ledSpeed = data.led_speed || '2s';
                        html += '<div style="margin-top: 25px;">';
                        html += '<div class="led-border-preview" style="position: relative; display: inline-block; padding: 4px; border-radius: 10px; background: linear-gradient(45deg, #ff0080, #00ff80, #8000ff, #ff8000); background-size: 300% 300%; animation: led-rotate ' + ledSpeed + ' linear infinite;">';
                        html += '<a href="#" style="position: relative; display: block; padding: 14px 28px; background: #000; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 700;">' + data.button + '</a>';
                        html += '</div></div>';
                    }
                    html += '</div>';
                    break;
                    
                default:
                    html += '<div class="cta-content" style="padding: 20px; text-align: center;">';
                    html += '<p style="color: #666; margin: 0;"><em>Preview estático para template: ' + template + '</em></p>';
                    if (data.title) html += '<h3 style="margin: 10px 0; color: ' + (data.color_text || '#333') + '">' + data.title + '</h3>';
                    if (data.button) html += '<a href="#" style="display: inline-block; padding: 12px 24px; background: ' + (data.color_button || '#2271b1') + '; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">' + data.button + '</a>';
                    html += '</div>';
            }
            
            html += '</div>';
            
            // Add basic CSS animations for better preview
            var animationCSS = '<style>';
            animationCSS += '@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.7; } }';
            animationCSS += '@keyframes led-rotate { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }';
            animationCSS += '.preview-pulse-dot { animation: pulse 2s infinite; }';
            animationCSS += '.led-border-preview { background-size: 300% 300% !important; }';
            animationCSS += '</style>';
            
            $container.html(animationCSS + html);
        },

        generateShortcode: function() {
            if (!this.selectedTemplate) {
                $('#generated-shortcode').val('');
                return;
            }
            
            var data = this.formData;
            var shortcode = '[cta_card';
            
            $.each(data, function(key, value) {
                if (value) {
                    shortcode += ' ' + key + '="' + value.replace(/"/g, '&quot;') + '"';
                }
            });
            
            shortcode += ']';
            $('#generated-shortcode').val(shortcode);
        },

        copyShortcode: function() {
            var $textarea = $('#generated-shortcode');
            var $button = $('#btn--copy');
            
            $textarea.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    $button.find('.copy-text').text('Copiado!');
                    $button.addClass('btn--success');
                    setTimeout(function() {
                        $button.find('.copy-text').text('Copiar');
                        $button.removeClass('btn--success');
                    }, 2000);
                }
            } catch (err) {
                console.log('Copy failed:', err);
            }
        },

        fillExampleData: function(templateName) {
            var examples = {
                'vertical': {
                    'title': 'Confira nosso novo produto',
                    'subtitle': 'Lançamento especial com desconto',
                    'description': 'Uma solução completa para suas necessidades. Não perca esta oportunidade única!',
                    'image': 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?q=80&w=400&auto=format&fit=crop',
                    'button': 'Ver Mais',
                    'url': 'https://exemplo.com/produto'
                },
                'horizontal': {
                    'title': 'Artigo Relacionado',
                    'description': 'Leia também este conteúdo que pode ser do seu interesse.',
                    'image': 'https://images.unsplash.com/photo-1754220820888-e6f7e610a7a2?q=80&w=400&auto=format&fit=crop',
                    'button': 'Ler Artigo',
                    'url': 'https://exemplo.com/artigo'
                },
                'minimal': {
                    'title': 'Download Gratuito',
                    'subtitle': 'Guia completo em PDF',
                    'tag': 'PDF',
                    'button': 'Baixar Agora',
                    'url': 'https://exemplo.com/download.pdf',
                    'target': '_blank'
                },
                'banner': {
                    'title': 'Oferta Especial!',
                    'description': 'Aproveite nossa promoção exclusiva com até 50% de desconto.',
                    'button': 'Aproveitar Oferta',
                    'url': 'https://exemplo.com/promocao',
                    'color_button': '#ff6b6b'
                },
                'simple': {
                    'title': 'Recursos Premium',
                    'icon': '🌟',
                    'url': 'https://exemplo.com/recursos'
                },
                'pulse': {
                    'title': 'Transmissão Ao Vivo',
                    'subtitle': 'Webinar Gratuito',
                    'description': 'Aprenda as melhores estratégias de marketing digital direto com os especialistas!',
                    'button': 'Participar Agora',
                    'url': 'https://exemplo.com/webinar',
                    'icon': '▶️',
                    'pulse_text': 'AO VIVO',
                    'pulse_color': '#ff6b6b'
                },
                'multi-button': {
                    'title': 'Escolha Seu Plano',
                    'subtitle': 'Opções flexíveis para todos os perfis',
                    'description': 'Encontre o plano perfeito para suas necessidades e comece hoje mesmo!',
                    'image': 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=400&auto=format&fit=crop',
                    'button': 'Plano Básico',
                    'url': 'https://exemplo.com/basico',
                    'button2': 'Plano Pro',
                    'url2': 'https://exemplo.com/pro',
                    'button3': 'Saiba Mais',
                    'url3': 'https://exemplo.com/planos',
                    'color_button': '#2271b1',
                    'color_button2': '#28a745',
                    'color_button3': '#6c757d'
                },
                'led-border': {
                    'title': 'Oferta Limitada',
                    'subtitle': 'Tecnologia de Ponta',
                    'description': 'Produto revolucionário com desconto especial por tempo limitado!',
                    'image': 'https://images.unsplash.com/photo-1518709268805-4e9042af2176?q=80&w=400&auto=format&fit=crop',
                    'button': 'Comprar Agora',
                    'url': 'https://exemplo.com/produto',
                    'icon': '⚡',
                    'led_colors': '#ff0080,#00ff80,#8000ff,#ff8000',
                    'led_speed': '2s'
                }
            };

            var data = examples[templateName] || {};
            
            // Fill form fields with example data
            $.each(data, function(key, value) {
                var $field = $('#cta-' + key.replace('_', '-'));
                if ($field.length) {
                    $field.val(value);
                }
            });
        },

        clearFields: function() {
            var self = this;
            
            // Clear all form fields but keep template selected
            $('#cta-config-form input[type="text"], #cta-config-form input[type="url"], #cta-config-form textarea').val('');
            
            // Reset selects to defaults
            $('#cta-target').val('_self');
            $('#cta-align').val('center');
            $('#cta-led-speed').val('2s');
            
            // Reset colors to defaults
            $('#cta-color-primary').val('#2271b1');
            $('#cta-color-button').val('#2271b1');
            $('#cta-color-text').val('#333333');
            $('#cta-color-bg').val('#ffffff');
            $('#cta-pulse-color').val('#ff6b6b');
            
            // Update color pickers if they exist
            if ($.fn.wpColorPicker) {
                $('#cta-color-primary').wpColorPicker('color', '#2271b1');
                $('#cta-color-button').wpColorPicker('color', '#2271b1');
                $('#cta-color-text').wpColorPicker('color', '#333333');
                $('#cta-color-bg').wpColorPicker('color', '#ffffff');
                $('#cta-pulse-color').wpColorPicker('color', '#ff6b6b');
            }
            
            // Update preview with slight delay
            setTimeout(function() {
                self.collectFormData();
                self.updatePreview();
                self.generateShortcode();
                self.updateStats();
            }, 200);
        },

        resetForm: function() {
            // Clear selection
            $('.template-item').removeClass('active');
            this.selectedTemplate = null;
            this.formData = {};
            
            // Show empty state
            $('#cta-config-form').hide();
            $('#cta-empty-state').show();
            
            // Clear preview and shortcode
            $('#cta-preview-content').html('<div class="preview-placeholder"><i class="dashicons dashicons-visibility"></i><p>Escolha um template para ver o preview</p></div>');
            $('#generated-shortcode').val('');
            
            // Update stats
            this.updateStats();
        },

        togglePreview: function(mode) {
            var $frame = $('#cta-preview-content');
            
            if (mode === 'preview-mobile') {
                $('#preview-mobile').addClass('is-active');
                $('#preview-desktop').removeClass('is-active');
                $frame.addClass('mobile-frame').removeClass('desktop-frame');
            } else {
                $('#preview-desktop').addClass('is-active');
                $('#preview-mobile').removeClass('is-active');
                $frame.addClass('desktop-frame').removeClass('mobile-frame');
            }
        },

        updateStats: function() {
            var templateName = this.selectedTemplate || 'Nenhum';
            var url = this.formData.url || 'Não definida';
            
            // Capitalize template name
            if (templateName !== 'Nenhum') {
                templateName = templateName.charAt(0).toUpperCase() + templateName.slice(1);
            }
            
            $('#stats-template').text(templateName);
            $('#stats-url').text(url);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on the builder page
        if ($('.cta-builder-layout').length) {
            CTABuilder.init();
        }
    });

    // Add loading animation CSS
    $('<style>')
        .prop('type', 'text/css')
        .html(
            '.preview-loading { text-align: center; padding: 40px; color: #718096; }' +
            '.preview-loading .dashicons { font-size: 32px; animation: spin 1s linear infinite; margin-bottom: 15px; }' +
            '.preview-error { text-align: center; padding: 40px; color: #e53e3e; }' +
            '.preview-error .dashicons { font-size: 32px; margin-bottom: 15px; }' +
            '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }' +
            '.btn--success { background: #27ae60 !important; }' +
            '.mobile-frame { max-width: 320px !important; margin: 0 auto; }' +
            '.desktop-frame { max-width: 100% !important; }'
        )
        .appendTo('head');

})(jQuery);