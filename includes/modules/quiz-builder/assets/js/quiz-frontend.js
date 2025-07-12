/**
 * WP Quiz Plugin - Frontend JavaScript
 * 
 * Handles auto-advancing, URL navigation modes, and other interactive features
 * Updated: 2025-01-19 - Fixed suffix mode to use clean parameters
 */

// Variáveis globais
let urlMode = 'suffix'; // valor padrão, será atualizado com as configurações do shortcode

document.addEventListener('DOMContentLoaded', function() {
    // 0. Carregar configurações do shortcode
    loadQuizSettings();
    
    // 1. Check if we need to scroll to quiz (when page loads with quiz parameters)
    scrollToQuizIfNeeded();
    
    // 2. Setup auto-advance functionality
    setupAutoAdvance();
    
    // 3. Setup quiz navigation buttons to use custom URL handling
    setupQuizNavigation();
});

/**
 * Scroll to quiz container if URL contains quiz parameters
 */
function scrollToQuizIfNeeded() {
    // Check if URL contains quiz parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('quiz_id') && (urlParams.has('quiz_step') || urlParams.has('action'))) {
        const quizId = urlParams.get('quiz_id');
        const quizContainer = document.getElementById('wp-quiz-' + quizId);
        
        // If quiz container exists, scroll to it smoothly
        if (quizContainer) {
            setTimeout(function() {
                quizContainer.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100); // Small delay to ensure page is fully loaded
        }
    }
}

/**
 * Setup auto-advance for quizzes
 */
/**
 * Load quiz settings from the localized script data
 */
function loadQuizSettings() {
    console.log('[ALVOBOT DEBUG] Loading quiz settings...');
    console.log('[ALVOBOT DEBUG] alvobot_quiz_settings available:', typeof alvobot_quiz_settings !== 'undefined');
    
    if (typeof alvobot_quiz_settings !== 'undefined') {
        console.log('[ALVOBOT DEBUG] Full settings object:', alvobot_quiz_settings);
        // Carrega o modo de URL das configurações
        if (alvobot_quiz_settings.url_mode) {
            urlMode = alvobot_quiz_settings.url_mode;
            console.log('[ALVOBOT DEBUG] URL mode set to:', urlMode);
        }
    } else {
        console.log('[ALVOBOT DEBUG] alvobot_quiz_settings is undefined, using default:', urlMode);
    }
}

/**
 * Setup quiz navigation to use custom URL handling
 */
function setupQuizNavigation() {
    console.log('[ALVOBOT DEBUG] Setting up quiz navigation...');
    console.log('[ALVOBOT DEBUG] Current URL mode:', urlMode);
    
    // Encontrar todos os formulários de quiz
    const quizForms = document.querySelectorAll('form.quiz-form');
    const quizLinks = document.querySelectorAll('a.quiz-answer-link');
    
    console.log('[ALVOBOT DEBUG] Found', quizForms.length, 'quiz forms');
    console.log('[ALVOBOT DEBUG] Found', quizLinks.length, 'quiz hyperlinks');
    
    // Log hyperlinks if any
    quizLinks.forEach(function(link, index) {
        console.log('[ALVOBOT DEBUG] Hyperlink', index, ':', link.href);
    });
    
    quizForms.forEach(function(form, index) {
        console.log('[ALVOBOT DEBUG] Processing form', index);
        
        // Modificar o comportamento do formulário para usar history.pushState
        form.addEventListener('submit', function(event) {
            console.log('[ALVOBOT DEBUG] Form submit event triggered. URL mode:', urlMode);
            // Se o modo de URL for 'suffix', não interceptamos - deixamos os hyperlinks funcionarem
            if (urlMode === 'params') {
                console.log('[ALVOBOT DEBUG] Intercepting form submit for params mode');
                event.preventDefault();
                
                // Obter os dados do formulário
                const formData = new FormData(form);
                const action = formData.get('action') || '';
                let quizId = formData.get('quiz_id') || '';
                const step = formData.get('quiz_step') || '0'; // quiz_step é 0-based
                
                // Determinar a ação (apenas avanço suportado)
                // step já é 0-based (0, 1, 2...)
                let nextStep = parseInt(step);
                if (action === 'next') {
                    nextStep++; // avança para próxima pergunta
                } else if (action === 'restart') {
                    nextStep = 0; // volta para primeira pergunta
                }
                
                // Obter a URL base sem parâmetros nem fragmentos
                const baseUrl = window.location.href.split('?')[0].split('#')[0];
                
                // Detectar se estamos em uma página de pré-artigo
                const isPreArticle = window.location.pathname.startsWith('/pre/');
                
                // Verificar se o quiz_id obtido está vazio e tentar obtê-lo do elemento input se necessário
                if (!quizId) {
                    const quizIdInput = form.querySelector('input[name="quiz_id"]');
                    if (quizIdInput) {
                        quizId = quizIdInput.value;
                    }
                }
                
                // Implementar o modo de sufixo que adiciona o número diretamente à URL
                // Formato: /url-da-pagina-NUMERO
                
                
                // Obter a URL base canônica e parâmetros originais do atributo fornecido pelo PHP
                const quizContainer = form.closest('.wp-quiz-container');
                let urlBase = '';
                let originalParams = '';
                
                if (quizContainer && quizContainer.getAttribute('data-base-url')) {
                    // Usar a URL limpa fornecida pelo PHP
                    urlBase = quizContainer.getAttribute('data-base-url');
                    
                    // Obter parâmetros originais se existirem
                    originalParams = quizContainer.getAttribute('data-original-params') || '';
                } else {
                    // Fallback: extrair URL base removendo apenas sufixos aquiz-e
                    urlBase = baseUrl.replace(/-aquiz-e\d+$/, '');
                }
                
                // Se estamos em pré-artigo, garantir que a URL base mantenha o prefixo /pre/
                if (isPreArticle) {
                    // Extrair o slug do post da URL atual (remover sufixo -aquiz-e se existir)
                    const pathParts = window.location.pathname.split('/');
                    let postSlug = pathParts[pathParts.length - 1] || pathParts[pathParts.length - 2];
                    
                    // Se o slug contém -aquiz-e, remover o sufixo
                    if (postSlug.includes('-aquiz-e')) {
                        postSlug = postSlug.replace(/-aquiz-e\d+$/, '');
                    }
                    
                    if (postSlug) {
                        urlBase = window.location.origin + '/pre/' + postSlug;
                    }
                }
                
                // Garantir que não há barra no final
                urlBase = urlBase.replace(/\/$/, '');
                
                // Construir a nova URL com sufixo para AdSense e parâmetro para controle
                let newUrl = urlBase;
                
                // Adicionar o sufixo na URL para todas as perguntas exceto a primeira
                // nextStep é 0-based, então:
                // Pergunta 1 (nextStep=0): sem sufixo
                // Pergunta 2 (nextStep=1): -aquiz-e1
                // Pergunta 3 (nextStep=2): -aquiz-e2
                if (nextStep > 0) {
                    newUrl += '-aquiz-e' + nextStep;
                }
                
                // Adicionar barra final
                newUrl += '/';
                
                // Preparar parâmetros da URL
                let urlParams = new URLSearchParams();
                
                // Adicionar parâmetros originais primeiro (utm_source, utm_campaign, etc.)
                if (originalParams) {
                    const originalParamsObj = new URLSearchParams(originalParams);
                    for (const [key, value] of originalParamsObj) {
                        urlParams.set(key, value);
                    }
                }
                
                // Adicionar parâmetros do quiz
                // IMPORTANTE: quiz_display_step é 0-based para coincidir com a indexação interna do PHP
                urlParams.set('quiz_display_step', nextStep);
                
                // Se temos o ID do quiz, adicionar como parâmetro
                if (quizId) {
                    urlParams.set('quiz_id', quizId);
                }
                
                // Adicionar a ação como parâmetro, se não for avanço padrão
                if (action === 'restart') {
                    urlParams.set('action', 'restart');
                }
                
                // Adicionar resposta atual se houver
                if (formData.get('current_answer')) {
                    urlParams.set('current_answer', formData.get('current_answer'));
                }
                
                // Adicionar respostas atuais se houver
                if (formData.get('answers')) {
                    urlParams.set('answers', formData.get('answers'));
                }
                
                // Construir URL final
                newUrl += '?' + urlParams.toString();
                
                // Redirecionar para a nova URL, forçando recarga completa da página
                window.location.href = newUrl;
                
                // Como estamos recarregando a página, o código abaixo não será executado
                // mas deixamos comentado para referência futura
                /*
                // Esconder todas as perguntas
                const quizContainer = form.closest('.wp-quiz-container');
                if (quizContainer) {
                    const allQuestions = quizContainer.querySelectorAll('.quiz-question');
                    allQuestions.forEach(q => q.style.display = 'none');
                    
                    // Mostrar apenas a pergunta atual
                    const currentQuestion = quizContainer.querySelector('.quiz-question[data-step="' + nextStep + '"]');
                    if (currentQuestion) {
                        currentQuestion.style.display = 'block';
                        
                        // Atualizar o campo hidden do formulário para refletir o passo atual
                        const stepInput = form.querySelector('input[name="quiz_step"]');
                        if (stepInput) {
                            stepInput.value = nextStep;
                        }
                        
                        // Rolar para o quiz
                        quizContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                */
                
                return false;
            } else {
                console.log('[ALVOBOT DEBUG] Suffix mode - letting form submit naturally (this should NOT happen if no forms exist)');
            }
            // Se for modo params, deixamos o formulário enviar normalmente
        });
    });
}

/**
 * Setup auto-advance for quizzes
 */
function setupAutoAdvance() {
    console.log('[ALVOBOT DEBUG] Setting up auto-advance...');
    // Find all quizzes with auto-advance enabled
    const autoAdvanceQuizzes = document.querySelectorAll('.wp-quiz-container[data-auto-advance="true"]');
    
    console.log('[ALVOBOT DEBUG] Found', autoAdvanceQuizzes.length, 'auto-advance quizzes');
    
    autoAdvanceQuizzes.forEach(function(quizContainer, quizIndex) {
        console.log('[ALVOBOT DEBUG] Processing auto-advance quiz', quizIndex, 'with URL mode:', urlMode);
        if (urlMode === 'suffix') {
            console.log('[ALVOBOT DEBUG] Skipping auto-advance setup for suffix mode - hyperlinks handle navigation');
            // No modo suffix, os links já navegam automaticamente
            return;
        }
        
        // Find all radio inputs within this quiz (for params mode)
        const radioInputs = quizContainer.querySelectorAll('input[type="radio"]');
        
        // Add change event listener to each radio input
        radioInputs.forEach(function(radio, radioIndex) {
            radio.addEventListener('change', function() {
                // Short delay before submitting to allow the user to see their selection
                setTimeout(function() {
                    // Find the form within this quiz container
                    const form = quizContainer.querySelector('form.quiz-form');
                    
                    if (!form) {
                        return;
                    }
                    
                    // Remove any existing action hidden inputs to avoid duplicates
                    const existingActions = form.querySelectorAll('input[name="action"]');
                    existingActions.forEach(function(input) {
                        if (input.type === 'hidden') {
                            input.parentNode.removeChild(input);
                        }
                    });
                    
                    // Add the action parameter to navigate to the next question
                    const actionInput = document.createElement('input');
                    actionInput.setAttribute('type', 'hidden');
                    actionInput.setAttribute('name', 'action');
                    actionInput.setAttribute('value', 'next');
                    form.appendChild(actionInput);
                    
                    // Ensure the selected radio value is set as current_answer hidden field
                    // This is needed because form.submit() might not properly include radio values
                    const selectedRadio = form.querySelector('input[name="current_answer"]:checked');
                    if (selectedRadio) {
                        // Remove any existing current_answer hidden input
                        const existingCurrentAnswer = form.querySelectorAll('input[name="current_answer"][type="hidden"]');
                        existingCurrentAnswer.forEach(function(input) {
                            input.parentNode.removeChild(input);
                        });
                        
                        // Add hidden input with the selected value
                        const currentAnswerInput = document.createElement('input');
                        currentAnswerInput.setAttribute('type', 'hidden');
                        currentAnswerInput.setAttribute('name', 'current_answer');
                        currentAnswerInput.setAttribute('value', selectedRadio.value);
                        form.appendChild(currentAnswerInput);
                    }
                    
                    // Submit the form (only in params mode - suffix mode doesn't use forms)
                    if (urlMode !== 'suffix') {
                        form.submit();
                    }
                }, 500); // 500ms delay
            });
        });
    });
}
