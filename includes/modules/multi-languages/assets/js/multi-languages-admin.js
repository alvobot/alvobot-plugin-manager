jQuery(document).ready(function($) {
    'use strict';

    const $metaBox = $('#alvobot-change-language-meta-box-content');
    if (!$metaBox.length) {
        return;
    }

    const $button = $metaBox.find('#alvobot-change-language-button');
    const $spinner = $metaBox.find('#alvobot-change-language-spinner');
    const $feedbackDiv = $metaBox.find('#alvobot-change-language-feedback');
    const $newLanguageSelect = $metaBox.find('#alvobot_new_language_code');
    const $updateTranslationsCheckbox = $metaBox.find('#alvobot_update_translations');
    const $currentLanguageDisplay = $metaBox.find('#alvobot-current-language-display');
    const $nonceField = $metaBox.find('#alvobot_change_post_language_nonce_field');

    $button.on('click', function() {
        const newLangCode = $newLanguageSelect.val();
        const updateTranslations = $updateTranslationsCheckbox.is(':checked');
        const postId = alvobotMultiLang.post_id;
        const currentLanguageName = alvobotMultiLang.current_language_name;

        if (!newLangCode) {
            $feedbackDiv.html('<p class="notice notice-warning">' + (alvobotMultiLang.text.select_language || 'Por favor, selecione um novo idioma.') + '</p>');
            return;
        }

        const selectedLanguageName = $newLanguageSelect.find('option:selected').text();

        let confirmMessage = alvobotMultiLang.text.confirm_change_associate || 'Você está prestes a alterar o idioma principal deste post para [New Language]. Ele permanecerá conectado às suas traduções existentes. Continuar?';
        if (!updateTranslations) {
            confirmMessage = alvobotMultiLang.text.confirm_change_dissociate || 'Você está prestes a alterar o idioma deste post para [New Language] e dissociá-lo de suas traduções atuais. O post se tornará independente no novo idioma. Continuar?';
        }
        confirmMessage = confirmMessage.replace('[New Language]', selectedLanguageName.split('(')[0].trim());


        if (!confirm(confirmMessage)) {
            return;
        }

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $feedbackDiv.empty();

        $.ajax({
            url: alvobotMultiLang.api_url_base, // This should be the full REST API URL
            method: 'PUT',
            beforeSend: function (xhr) {
                // Nonce for REST API
                xhr.setRequestHeader('X-WP-Nonce', alvobotMultiLang.rest_nonce); 
            },
            data: JSON.stringify({
                post_id: postId,
                language_code: newLangCode,
                update_translations: updateTranslations,
                // The site token is expected by the permission_callback of change_post_language
                // The permission_callback in AlvoBotPro_MultiLanguages::permissions_check() expects current_user_can('edit_posts')
                // And the API endpoint registration for change_post_language uses this.
                // It does not use the 'grp_site_token' for this specific endpoint's permission check.
                // If it were required, it would need to be added here.
                // For now, assuming 'edit_posts' capability check is sufficient as per class-multi-languages.php
            }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $feedbackDiv.html('<p class="notice notice-success">' + (alvobotMultiLang.text.language_changed_success || 'Idioma alterado com sucesso!') + '</p>');
                    // Update current language display
                    // Fetching full language details (like flag URL) is complex here without another AJAX call or preloading all lang data
                    // For simplicity, just update text. A page reload might be better for full Polylang UI sync.
                    $currentLanguageDisplay.text(selectedLanguageName + ' (' + newLangCode + ')'); 
                    alvobotMultiLang.current_language_slug = newLangCode; // Update for subsequent checks if any
                    alvobotMultiLang.current_language_name = selectedLanguageName;
                    $newLanguageSelect.val(''); // Reset dropdown

                    // Consider a page reload for full sync with Polylang's UI elements
                     setTimeout(function() {
                         window.location.reload();
                     }, 1500);

                } else {
                    let errorMessage = alvobotMultiLang.text.error_changing_language || 'Erro ao alterar idioma:';
                    if (response.data && response.data.message) {
                        errorMessage += ' ' + response.data.message;
                    } else if (response.message) {
                         errorMessage += ' ' + response.message;
                    }
                    $feedbackDiv.html('<p class="notice notice-error">' + errorMessage + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMessage = alvobotMultiLang.text.error_changing_language || 'Erro ao alterar idioma:';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMessage += ' ' + jqXHR.responseJSON.message;
                } else {
                    errorMessage += ' ' + errorThrown + ' (' + jqXHR.status + ')';
                }
                $feedbackDiv.html('<p class="notice notice-error">' + errorMessage + '</p>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
