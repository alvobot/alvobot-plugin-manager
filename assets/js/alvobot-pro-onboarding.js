jQuery(document).ready(function($) {
    'use strict';

    let currentStep = 1;
    const totalSteps = 5; // Manually set to 5 as per the new design

    const $steps = $('.onboarding-step-content');
    const $stepIndicators = $('.step-indicator'); // Will use data-step-target
    const $prevButton = $('#alvobot-onboarding-prev');
    const $nextButton = $('#alvobot-onboarding-next');
    const $finishButton = $('#alvobot-onboarding-finish');
    const $skipButton = $('#alvobot-onboarding-skip');

    let onboardingModuleStates = {};

    // Initialize translations if not provided by wp_localize_script (fallback)
    const translations = alvobotOnboarding.translations || {
        skipSetup: 'Pular Configuração',
        skipAndFinish: 'Concluir', // Changed from 'Pular e Concluir' for the last step skip
        nextButton: 'Próximo Passo',
        prevButton: 'Anterior',
        finishButton: 'Concluir Configuração',
        step1Title: '1. Boas-vindas',
        step2Title: '2. Configuração Essencial',
        step3Title: '3. Módulos',
        step4Title: '4. Links Úteis',
        step5Title: '5. Concluído'
    };

    function updateWizardControls() {
        // Show/hide steps
        $steps.removeClass('active').hide();
        $steps.filter('[data-step-id="' + currentStep + '"]').addClass('active').fadeIn(300);

        // Update step indicators
        $stepIndicators.removeClass('active');
        $stepIndicators.filter('[data-step-target="' + currentStep + '"]').addClass('active'); // Use data-step-target
        
        // Update button text from translations
        $prevButton.text(translations.prevButton);
        $nextButton.text(translations.nextButton);
        $finishButton.text(translations.finishButton);

        // Update button visibility
        if (currentStep === 1) {
            $prevButton.hide();
        } else {
            $prevButton.show();
        }

        if (currentStep === totalSteps) {
            $nextButton.hide();
            $finishButton.show();
            $skipButton.text(translations.skipAndFinish); // Or just "Concluir" if skip is not desired on last step
        } else {
            $nextButton.show();
            $finishButton.hide();
            $skipButton.text(translations.skipSetup);
        }
    }

    function collectModuleStates() {
        onboardingModuleStates = {}; // Reset
        $('.alvobot-onboarding-module-toggle').each(function() {
            const $toggle = $(this);
            const moduleSlug = $toggle.data('module-slug');
            if (moduleSlug) {
                onboardingModuleStates[moduleSlug] = $toggle.is(':checked');
            }
        });
    }

    $nextButton.on('click', function() {
        if (currentStep === 3) { // After module configuration step
            collectModuleStates();
        }
        if (currentStep < totalSteps) {
            currentStep++;
            updateWizardControls();
        }
    });

    $prevButton.on('click', function() {
        if (currentStep > 1) {
            currentStep--;
            updateWizardControls();
        }
    });
    
    function completeOnboarding(skipped = false) {
        // Disable buttons to prevent multiple submissions
        $finishButton.prop('disabled', true).text('Processando...');
        $skipButton.prop('disabled', true);
        $prevButton.prop('disabled', true);
        $nextButton.prop('disabled', true);

        $.ajax({
            url: alvobotOnboarding.ajaxurl,
            type: 'POST',
            data: {
                action: 'alvobot_pro_complete_onboarding',
                nonce: alvobotOnboarding.nonce,
                skipped: skipped
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url || adminurl + 'admin.php?page=alvobot-pro';
                } else {
                    alert('Erro ao finalizar o onboarding: ' + (response.data.message || 'Erro desconhecido. Redirecionando para o painel.'));
                    window.location.href = adminurl + 'admin.php?page=alvobot-pro'; // Fallback redirect
                }
            },
            error: function() {
                alert('Erro de AJAX ao finalizar o onboarding. Redirecionando para o painel.');
                window.location.href = adminurl + 'admin.php?page=alvobot-pro'; // Fallback redirect
            }
        });
    }

    $skipButton.on('click', function() {
        completeOnboarding(true);
    });

    $finishButton.on('click', function() {
        if (currentStep === 3) { // If finish is clicked on module step, collect states first
            collectModuleStates();
        }
        // Or ensure module states are collected if user navigates back and forth and finishes on step 5
        if (Object.keys(onboardingModuleStates).length === 0 && $('.alvobot-onboarding-module-toggle').length > 0) {
             collectModuleStates(); // Collect if not already done
        }


        // Disable button to prevent multiple submissions
        $finishButton.prop('disabled', true).text('Salvando...');

        $.ajax({
            url: alvobotOnboarding.ajaxurl,
            type: 'POST',
            data: {
                action: 'alvobot_pro_save_onboarding_settings',
                nonce: alvobotOnboarding.nonce, // Assuming same nonce can be used or a specific one for settings
                module_states: onboardingModuleStates
            },
            success: function(saveResponse) {
                if (saveResponse.success) {
                    completeOnboarding(false); // Proceed to mark onboarding as complete
                } else {
                    alert('Erro ao salvar configurações dos módulos: ' + (saveResponse.data.message || 'Erro desconhecido.'));
                    $finishButton.prop('disabled', false).text(translations.finishButton); // Re-enable button
                }
            },
            error: function() {
                alert('Erro de AJAX ao salvar configurações dos módulos.');
                $finishButton.prop('disabled', false).text(translations.finishButton); // Re-enable button
            }
        });
    });
    
    // Update step indicator text from translations
    $stepIndicators.each(function() {
        const stepNum = $(this).data('step-target');
        const translationKey = 'step' + stepNum + 'Title';
        if (translations[translationKey]) {
            $(this).text(translations[translationKey]);
        }
    });

    // Initial setup
    updateWizardControls();
});
