// WP Quiz Builder - Updated 2025-01-20 - Removed deprecated components
// Utility functions (global scope)

// Convert hex color to RGB
function hexToRgb(hex) {
	hex = hex.replace('#', '');
	if (hex.length === 3) {
		hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
	}
	return {
		r: parseInt(hex.substr(0, 2), 16),
		g: parseInt(hex.substr(2, 2), 16),
		b: parseInt(hex.substr(4, 2), 16),
	};
}

// Calculate relative luminance
function getRelativeLuminance(rgb) {
	const rsRGB = rgb.r / 255;
	const gsRGB = rgb.g / 255;
	const bsRGB = rgb.b / 255;

	const r = rsRGB <= 0.03928 ? rsRGB / 12.92 : ((rsRGB + 0.055) / 1.055) ** 2.4;
	const g = gsRGB <= 0.03928 ? gsRGB / 12.92 : ((gsRGB + 0.055) / 1.055) ** 2.4;
	const b = bsRGB <= 0.03928 ? bsRGB / 12.92 : ((bsRGB + 0.055) / 1.055) ** 2.4;

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

// Calculate contrast ratio
function getContrastRatio(color1, color2) {
	const rgb1 = hexToRgb(color1);
	const rgb2 = hexToRgb(color2);

	const l1 = getRelativeLuminance(rgb1);
	const l2 = getRelativeLuminance(rgb2);

	const lighter = Math.max(l1, l2);
	const darker = Math.min(l1, l2);

	return (lighter + 0.05) / (darker + 0.05);
}

// Check and fix contrast issues
function checkContrast(bgColor, textColor, element) {
	// Handle transparent background
	if (!bgColor || bgColor.toLowerCase() === 'transparent' || bgColor === '') {
		bgColor = '#ffffff';
	}

	// NEVER allow transparent text
	if (!textColor || textColor.toLowerCase() === 'transparent' || textColor === '') {
		textColor = '#000000';
		if (element) {
			element.style.color = textColor + ' !important';
		}
		console.warn('Text color was transparent, forcing to black');
		return;
	}

	// If background is transparent, ensure text is dark enough for light backgrounds
	if (!bgColor || bgColor.toLowerCase() === 'transparent' || bgColor === '') {
		const rgb = hexToRgb(textColor);
		const luminance = getRelativeLuminance(rgb);

		// If text is too light (luminance > 0.5), make it darker
		if (luminance > 0.5) {
			textColor = '#000000';
			if (element) {
				element.style.color = textColor + ' !important';
			}
			console.warn('Text too light for transparent background, forcing to black');
		}
	}

	// Calculate contrast ratio
	const ratio = getContrastRatio(bgColor, textColor);

	// Log if contrast is too low (WCAG AA requires 4.5:1)
	if (ratio < 4.5) {
		console.warn(
			`Low contrast detected: ${textColor} on ${bgColor} (${ratio.toFixed(2)}:1). Recommended: 4.5:1 or higher`,
		);
	}

	return ratio;
}

function applyStylesToElement(element, styles) {
	if (!element || !styles) return;

	if (styles.backgroundColor) element.style.backgroundColor = styles.backgroundColor + ' !important';
	if (styles.color) {
		// Prevent transparent text
		if (styles.color.toLowerCase() === 'transparent') {
			styles.color = '#000000';
		}
		element.style.color = styles.color + ' !important';
	}
	if (styles.fontWeight) element.style.fontWeight = styles.fontWeight + ' !important';
	if (styles.fontSize) element.style.fontSize = styles.fontSize + ' !important';

	// Check contrast if both colors are set
	if (styles.backgroundColor && styles.color) {
		checkContrast(styles.backgroundColor, styles.color, element);
	}
}

function getAnswerText(answer, defaultText = '') {
	return typeof answer === 'string' ? answer : answer?.text || defaultText;
}

document.addEventListener('DOMContentLoaded', function () {
	const questionsContainer = document.getElementById('questions-container');
	const shortcodeField = document.getElementById('generated-shortcode');

	// Check if required elements exist
	if (!questionsContainer || !shortcodeField) {
		console.warn('Quiz Builder: Required elements not found');
		return;
	}

	// Utility function to reset checkboxes for a question
	function resetRadioButtons(questionElement, newQuestionId) {
		const checkboxes = questionElement.querySelectorAll('.correct-answer');
		checkboxes.forEach((checkbox, index) => {
			checkbox.name = `correct-answer-${newQuestionId}-${index}`;
			checkbox.checked = false;
		});
	}

	// Utility function to create answer item HTML
	function createAnswerItemHTML(answerText, index, questionId = null) {
		const correctIndicator = questionId
			? `
            <div class="answer-controls">
                <label class="checkbox-modern" style="display: none;">
                    <input type="checkbox" class="correct-answer" name="correct-answer-${questionId}-${index}" value="${index}">
                    <span class="checkmark"></span>
                </label>
            </div>
        `
			: '';

		return `
            <div class="answer-item">
                ${correctIndicator}
                <input type="text" class="answer-text" placeholder="Opção ${index + 1}" value="${answerText}">
                <button type="button" class="btn btn--ghost btn--sm" onclick="openStylePopup(this, 'answer')" title="Alterar visual">
                    <i data-lucide="palette" class="alvobot-icon"></i>
                </button>
                <button type="button" class="btn btn--ghost btn--sm" onclick="duplicateAnswer(this)" title="Duplicar">
                    <i data-lucide="file" class="alvobot-icon"></i>
                </button>
                <button type="button" class="btn btn--ghost btn--sm btn--danger" onclick="removeAnswer(this)">❌</button>
            </div>
        `;
	}

	function createQuestionElement(questionData = null) {
		const questionDiv = document.createElement('div');
		questionDiv.className = 'question-item';
		questionDiv.dataset.questionId = Date.now();

		const hasCorrect = questionData && typeof questionData.correct !== 'undefined';
		const correctChecked = hasCorrect ? 'checked' : '';

		questionDiv.innerHTML = `
            <div class="question-header">
                <div class="question-number">
                    <span class="number-badge">1</span>
                </div>
                <div class="question-main">
                    <input type="text" class="question-text" placeholder="Digite sua pergunta..." value="${questionData?.question || 'Nova questão'}">
                </div>
                <div class="question-actions">
                    <button type="button" class="btn btn--ghost btn--sm" onclick="openStylePopup(this, 'question')" title="Alterar visual">
                        <i data-lucide="palette" class="alvobot-icon"></i>
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" onclick="duplicateQuestion(this)" title="Duplicar">
                        <i data-lucide="file" class="alvobot-icon"></i>
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm btn--danger" onclick="removeQuestion(this)">❌</button>
                </div>
            </div>
            <div class="question-content">
                <div class="answers-section">
                    <div class="answers-header">
                        <label>Opções de resposta:</label>
                        <button type="button" class="btn btn--primary btn--sm btn-add-answer" onclick="addAnswer(this)">➕ Adicionar</button>
                    </div>
                    <div class="answers-list">
                        ${generateAnswersHTML(questionData?.answers || ['Opção 1', 'Opção 2'], questionDiv.dataset.questionId)}
                    </div>
                </div>
                <div class="quiz-mode-toggle">
                    <label>
                        <input type="checkbox" class="has-correct-answer" onchange="toggleCorrectMode(this)" ${correctChecked}>
                        Esta questão tem resposta correta (modo quiz)
                    </label>
                </div>
                <div class="settings-advanced" style="display: ${hasCorrect ? 'block' : 'none'};">
                    <div class="setting-row">
                        <label>Explicação da resposta:</label>
                        <textarea class="explanation" placeholder="Explicação opcional que aparece após responder...">${questionData?.explanation || ''}</textarea>
                    </div>
                </div>
            </div>
        `;

		return questionDiv;
	}

	function createLeadCaptureElement(data = null) {
		const template = document.getElementById('lead-capture-template');
		const clone = template.content.cloneNode(true);
		const div = clone.querySelector('.lead-capture-item');
		div.dataset.questionId = Date.now();

		if (data) {
			div.querySelector('.question-text').value = data.title || 'Preencha seus dados para continuar';
			div.querySelector('.field-name').checked = data.fields?.name !== false;
			div.querySelector('.field-email').checked = data.fields?.email !== false;
			div.querySelector('.field-phone').checked = data.fields?.phone === true;
			div.querySelector('.webhook-url').value = data.webhookUrl || '';
			div.querySelector('.submit-text').value = data.submitText || 'Continuar';
			// New fields
			if (div.querySelector('.webhook-platform')) {
				div.querySelector('.webhook-platform').value = data.webhookPlatform || 'generic';
			}
			if (div.querySelector('.redirect-after-submit')) {
				div.querySelector('.redirect-after-submit').value = data.redirectAfterSubmit || '';
			}
			// i18n label/placeholder fields
			if (data.i18n) {
				if (div.querySelector('.label-name')) {
					div.querySelector('.label-name').value = data.i18n.labelName || 'Nome completo';
				}
				if (div.querySelector('.placeholder-name')) {
					div.querySelector('.placeholder-name').value = data.i18n.placeholderName || 'Digite seu nome';
				}
				if (div.querySelector('.label-email')) {
					div.querySelector('.label-email').value = data.i18n.labelEmail || 'E-mail';
				}
				if (div.querySelector('.placeholder-email')) {
					div.querySelector('.placeholder-email').value = data.i18n.placeholderEmail || 'seu@email.com';
				}
				if (div.querySelector('.label-phone')) {
					div.querySelector('.label-phone').value = data.i18n.labelPhone || 'Telefone / WhatsApp';
				}
				if (div.querySelector('.placeholder-phone')) {
					div.querySelector('.placeholder-phone').value = data.i18n.placeholderPhone || 'Seu número';
				}
			}
		}

		// Add event listeners
		div.querySelector('.btn--danger').addEventListener('click', function () {
			div.remove();
			updateQuestionNumbers();
			updateQuestionCounts();
			generateShortcode();
		});

		div.querySelectorAll('input, select').forEach((input) => {
			input.addEventListener('change', generateShortcode);
			input.addEventListener('input', generateShortcode);
		});

		return div;
	}

	function addLeadCapture(data = null) {
		const emptyState = questionsContainer.querySelector('.drop-zone-empty');
		if (emptyState) emptyState.remove();

		const element = createLeadCaptureElement(data);
		questionsContainer.appendChild(element);

		updateQuestionNumbers();
		updateQuestionCounts();
		generateShortcode();
	}

	function generateAnswersHTML(answers, questionId) {
		return answers
			.map((answer, index) => {
				const answerText = getAnswerText(answer, `Opção ${index + 1}`);
				return createAnswerItemHTML(answerText, index, questionId);
			})
			.join('');
	}

	function addQuestion(questionData = null) {
		const emptyState = questionsContainer.querySelector('.drop-zone-empty');
		if (emptyState) emptyState.remove();

		const questionDiv = createQuestionElement(questionData);
		questionsContainer.appendChild(questionDiv);

		// Apply question styles if they exist
		if (questionData && questionData.styles) {
			questionDiv.dataset.styles = JSON.stringify(questionData.styles);
			const questionTextElement = questionDiv.querySelector('.question-text');
			applyStylesToElement(questionTextElement, questionData.styles);
		}

		// Apply answer styles if they exist
		if (questionData && questionData.answers) {
			const answerItems = questionDiv.querySelectorAll('.answer-item');
			questionData.answers.forEach((answer, index) => {
				if (typeof answer === 'object' && answer.styles && answerItems[index]) {
					answerItems[index].dataset.styles = JSON.stringify(answer.styles);
					const answerTextElement = answerItems[index].querySelector('.answer-text');
					applyStylesToElement(answerTextElement, answer.styles);
				}
			});
		}

		if (questionData && questionData.correct !== undefined) {
			const checkbox = questionDiv.querySelector('.has-correct-answer');
			toggleCorrectMode(checkbox);

			const answers = questionDiv.querySelectorAll('.answer-item');

			// Lidar com resposta única (number) ou múltiplas (array)
			const correctAnswers = Array.isArray(questionData.correct) ? questionData.correct : [questionData.correct];

			correctAnswers.forEach((correctIndex) => {
				const checkbox = answers[correctIndex]?.querySelector('.correct-answer');
				if (checkbox) checkbox.checked = true;
			});
		}

		updateQuestionNumbers();
		updateQuestionCounts();
		generateShortcode();
	}

	// Make addQuestion and addLeadCapture globally accessible
	window.addQuestion = addQuestion;
	window.addLeadCapture = addLeadCapture;

	function updateQuestionNumbers() {
		questionsContainer.querySelectorAll('.question-item').forEach((question, index) => {
			const badge = question.querySelector('.number-badge');
			if (badge) badge.textContent = index + 1;
		});
	}

	function updateQuestionCounts() {
		const questionsCount = document.querySelectorAll('.question-item').length;
		const statsQuestions = document.getElementById('stats-questions');
		const statsTime = document.getElementById('stats-time');

		if (statsQuestions) statsQuestions.textContent = questionsCount;
		if (statsTime) {
			const estimatedTime = Math.max(1, Math.ceil(questionsCount * 0.5));
			statsTime.textContent = `~${estimatedTime}min`;
		}
	}

	function buildQuestionData() {
		const questions = [];

		if (!questionsContainer) return questions;

		Array.from(questionsContainer.children).forEach((child) => {
			if (child.classList.contains('question-item') && !child.classList.contains('lead-capture-item')) {
				// It's a question
				const questionText = child.querySelector('.question-text').value.trim();
				if (!questionText) return;

				const answers = [];
				child.querySelectorAll('.answer-item').forEach((answerDiv) => {
					const answerInput = answerDiv.querySelector('.answer-text');
					const answerText = answerInput.value.trim();
					if (answerText) {
						const answerData = answerDiv.dataset.styles
							? { text: answerText, styles: JSON.parse(answerDiv.dataset.styles) }
							: answerText;
						answers.push(answerData);
					}
				});

				if (answers.length >= 1) {
					const questionData = { type: 'question', question: questionText, answers };

					if (child.dataset.styles) {
						questionData.styles = JSON.parse(child.dataset.styles);
					}

					const hasCorrect = child.querySelector('.has-correct-answer').checked;
					if (hasCorrect) {
						const correctCheckboxes = child.querySelectorAll('.correct-answer:checked');
						if (correctCheckboxes.length > 0) {
							const correctAnswers = Array.from(correctCheckboxes).map((cb) => parseInt(cb.value));
							questionData.correct = correctAnswers.length === 1 ? correctAnswers[0] : correctAnswers;
						}

						const explanation = child.querySelector('.explanation').value.trim();
						if (explanation) questionData.explanation = explanation;
					}

					questions.push(questionData);
				}
			} else if (child.classList.contains('lead-capture-item')) {
				// It's a lead capture
				const leadData = {
					type: 'lead_capture',
					title: child.querySelector('.question-text').value.trim(),
					fields: {
						name: child.querySelector('.field-name').checked,
						email: child.querySelector('.field-email').checked,
						phone: child.querySelector('.field-phone').checked,
					},
					webhookUrl: child.querySelector('.webhook-url').value.trim(),
					submitText: child.querySelector('.submit-text').value.trim(),
				};
				// Add new fields
				const platformSelect = child.querySelector('.webhook-platform');
				if (platformSelect) {
					leadData.webhookPlatform = platformSelect.value;
				}
				const redirectInput = child.querySelector('.redirect-after-submit');
				if (redirectInput && redirectInput.value.trim()) {
					leadData.redirectAfterSubmit = redirectInput.value.trim();
				}
				// Collect i18n label/placeholder values
				const i18n = {};
				const labelName = child.querySelector('.label-name');
				const placeholderName = child.querySelector('.placeholder-name');
				const labelEmail = child.querySelector('.label-email');
				const placeholderEmail = child.querySelector('.placeholder-email');
				const labelPhone = child.querySelector('.label-phone');
				const placeholderPhone = child.querySelector('.placeholder-phone');

				if (labelName && labelName.value.trim()) i18n.labelName = labelName.value.trim();
				if (placeholderName && placeholderName.value.trim())
					i18n.placeholderName = placeholderName.value.trim();
				if (labelEmail && labelEmail.value.trim()) i18n.labelEmail = labelEmail.value.trim();
				if (placeholderEmail && placeholderEmail.value.trim())
					i18n.placeholderEmail = placeholderEmail.value.trim();
				if (labelPhone && labelPhone.value.trim()) i18n.labelPhone = labelPhone.value.trim();
				if (placeholderPhone && placeholderPhone.value.trim())
					i18n.placeholderPhone = placeholderPhone.value.trim();

				if (Object.keys(i18n).length > 0) {
					leadData.i18n = i18n;
				}
				questions.push(leadData);
			}
		});

		return questions;
	}

	window.generateShortcode = function generateShortcode() {
		const questions = buildQuestionData();

		const settings = {
			redirectUrl: document.getElementById('redirect_url')?.value || '',
			style: document.getElementById('quiz_style')?.value || 'default',
			showProgress: document.getElementById('show_progress')?.checked || false,
			showScore: document.getElementById('show_score')?.checked || false,
			randomize: document.getElementById('randomize')?.checked || false,
		};

		let shortcode = '[quiz';

		if (settings.redirectUrl) shortcode += ` redirect_url="${settings.redirectUrl}"`;
		if (settings.style !== 'default') shortcode += ` style="${settings.style}"`;
		if (settings.showProgress) shortcode += ` show_progress="true"`;
		if (!settings.showScore) shortcode += ` show_score="false"`;
		if (settings.randomize) shortcode += ` randomize="true"`;

		shortcode += ']\n';

		if (questions.length > 0) {
			shortcode += JSON.stringify(questions, null, 2);
		}

		shortcode += '\n[/quiz]';

		if (shortcodeField) shortcodeField.value = shortcode;
		updatePreview();
	};

	function restoreEmptyState() {
		questionsContainer.innerHTML = `
            <div class="drop-zone-empty">
                <div class="empty-state">
                    <i data-lucide="grip-vertical" class="alvobot-icon empty-icon"></i>
                    <h4>Arraste componentes aqui</h4>
                    <p>Comece arrastando "Nova Questão" da barra lateral</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn--primary" id="btn--add-question">
                            <i data-lucide="plus" class="alvobot-icon"></i>
                            Adicionar Questão
                        </button>
                    </div>
                </div>
            </div>
        `;

		const addButton = questionsContainer.querySelector('#btn--add-question');
		if (addButton) {
			addButton.addEventListener('click', () => addQuestion());
		}
	}

	// Global functions
	window.removeQuestion = function (btn) {
		btn.closest('.question-item').remove();

		if (questionsContainer.querySelectorAll('.question-item').length === 0) {
			restoreEmptyState();
		}

		updateQuestionNumbers();
		updateQuestionCounts();
		generateShortcode();
	};

	window.duplicateQuestion = function (btn) {
		const questionItem = btn.closest('.question-item');
		const clone = questionItem.cloneNode(true);
		const newQuestionId = Date.now();

		clone.dataset.questionId = newQuestionId;
		clone.querySelector('.question-text').value += ' (cópia)';

		resetRadioButtons(clone, newQuestionId);

		const originalCheckedRadio = questionItem.querySelector('.correct-answer:checked');
		if (originalCheckedRadio) {
			const cloneRadio = clone.querySelectorAll('.correct-answer')[parseInt(originalCheckedRadio.value)];
			if (cloneRadio) cloneRadio.checked = true;
		}

		questionItem.insertAdjacentElement('afterend', clone);
		updateQuestionNumbers();
		updateQuestionCounts();
		generateShortcode();
	};

	window.addAnswer = function (btn) {
		const answersContainer = btn.closest('.answers-section').querySelector('.answers-list');
		const answerCount = answersContainer.children.length + 1;

		const answerDiv = document.createElement('div');
		answerDiv.innerHTML = createAnswerItemHTML(`Opção ${answerCount}`, answerCount - 1);
		answersContainer.appendChild(answerDiv.firstElementChild);
		generateShortcode();
	};

	window.removeAnswer = function (btn) {
		const answersContainer = btn.closest('.answers-list');
		if (answersContainer.children.length > 1) {
			btn.closest('.answer-item').remove();
			generateShortcode();
		}
	};

	window.duplicateAnswer = function (btn) {
		const answerItem = btn.closest('.answer-item');
		const clone = answerItem.cloneNode(true);

		clone.querySelector('.answer-text').value += ' (cópia)';

		const radioInput = clone.querySelector('.correct-answer');
		if (radioInput) radioInput.checked = false;

		answerItem.insertAdjacentElement('afterend', clone);
		generateShortcode();
	};

	window.toggleCorrectMode = function (checkbox) {
		const questionItem = checkbox.closest('.question-item');
		const answers = questionItem.querySelectorAll('.answer-item');

		if (checkbox.checked) {
			answers.forEach((answer, index) => {
				const questionId = questionItem.dataset.questionId || Date.now();
				let correctLabel = answer.querySelector('.checkbox-modern');

				if (!correctLabel) {
					const controls = answer.querySelector('.answer-controls');
					if (controls) {
						correctLabel = document.createElement('label');
						correctLabel.className = 'checkbox-modern';
						correctLabel.innerHTML = `
                            <input type="checkbox" class="correct-answer" name="correct-answer-${questionId}-${index}" value="${index}">
                            <span class="checkmark"></span>
                        `;

						const checkboxInput = correctLabel.querySelector('.correct-answer');
						checkboxInput.addEventListener('change', generateShortcode);

						controls.insertBefore(correctLabel, controls.firstChild);
					}
				}

				if (correctLabel) {
					correctLabel.style.display = 'flex';
				}
			});

			let settingsAdvanced = questionItem.querySelector('.settings-advanced');
			if (!settingsAdvanced) {
				settingsAdvanced = document.createElement('div');
				settingsAdvanced.className = 'settings-advanced';
				settingsAdvanced.innerHTML = `
                    <div class="setting-row">
                        <label>Explicação da resposta:</label>
                        <textarea class="explanation" placeholder="Explicação opcional que aparece após responder..."></textarea>
                    </div>
                `;

				const quizModeToggle = questionItem.querySelector('.quiz-mode-toggle');
				quizModeToggle.insertAdjacentElement('afterend', settingsAdvanced);
			}

			settingsAdvanced.style.display = 'block';
		} else {
			questionItem.querySelectorAll('.checkbox-modern').forEach((checkboxLabel) => {
				checkboxLabel.style.display = 'none';
			});

			const settingsAdvanced = questionItem.querySelector('.settings-advanced');
			if (settingsAdvanced) settingsAdvanced.style.display = 'none';
		}

		generateShortcode();
	};

	window.openStylePopup = function (btn, type) {
		const existingPopup = document.querySelector('.style-popup.active');
		if (existingPopup) existingPopup.remove();

		const target = type === 'question' ? btn.closest('.question-item') : btn.closest('.answer-item');
		const currentStyles = target.dataset.styles ? JSON.parse(target.dataset.styles) : {};

		const popup = document.createElement('div');
		popup.className = 'style-popup active';
		popup.innerHTML = `
            <div class="style-popup-header">
                <h4 class="style-popup-title">${type === 'question' ? 'Estilo da Pergunta' : 'Estilo da Resposta'}</h4>
                <button type="button" class="style-popup-close" onclick="closeStylePopup(this)">&times;</button>
            </div>
            <div class="style-popup-grid">
                <div class="style-control">
                    <label>Cor do fundo</label>
                    <input type="color" class="style-bg-color" value="${currentStyles.backgroundColor || '#ffffff'}">
                </div>
                <div class="style-control">
                    <label>Cor do texto</label>
                    <input type="color" class="style-text-color" value="${currentStyles.color || '#000000'}">
                </div>
                <div class="style-control">
                    <label>Peso do texto</label>
                    <select class="style-font-weight">
                        <option value="300" ${currentStyles.fontWeight == 300 ? 'selected' : ''}>Light</option>
                        <option value="400" ${currentStyles.fontWeight == 400 ? 'selected' : ''}>Regular</option>
                        <option value="500" ${currentStyles.fontWeight == 500 ? 'selected' : ''}>Medium</option>
                        <option value="600" ${currentStyles.fontWeight == 600 ? 'selected' : ''}>Semibold</option>
                        <option value="700" ${currentStyles.fontWeight == 700 ? 'selected' : ''}>Bold</option>
                        <option value="800" ${currentStyles.fontWeight == 800 ? 'selected' : ''}>Extra Bold</option>
                    </select>
                </div>
                <div class="style-control">
                    <label>Tamanho</label>
                    <input type="number" class="style-font-size" 
                           value="${parseInt(currentStyles.fontSize) || (type === 'question' ? 20 : 16)}" 
                           min="${type === 'question' ? 12 : 10}" 
                           max="${type === 'question' ? 36 : 32}">
                </div>
            </div>
            <div class="style-popup-footer">
                <button type="button" class="btn btn--clear" onclick="clearStyles(this, '${type}')">Limpar</button>
            </div>
        `;

		btn.parentElement.appendChild(popup);

		popup.addEventListener('click', (e) => e.stopPropagation());

		popup.querySelectorAll('input, select').forEach((input) => {
			input.addEventListener('change', () => applyStyles(target, type));
		});

		document.addEventListener('click', function closePopupOnClickOutside(e) {
			if (!popup.contains(e.target) && !btn.contains(e.target)) {
				popup.remove();
				document.removeEventListener('click', closePopupOnClickOutside);
			}
		});
	};

	window.closeStylePopup = function (btn) {
		btn.closest('.style-popup').remove();
	};

	window.clearStyles = function (btn, type) {
		const popup = btn.closest('.style-popup');
		const target = type === 'question' ? popup.closest('.question-item') : popup.closest('.answer-item');

		delete target.dataset.styles;

		const element = target.querySelector(type === 'question' ? '.question-text' : '.answer-text');
		element.style.removeProperty('background-color');
		element.style.removeProperty('color');
		element.style.removeProperty('font-weight');
		element.style.removeProperty('font-size');

		popup.remove();
		generateShortcode();
	};

	function applyStyles(target, type) {
		const popup = target.querySelector('.style-popup.active');
		if (!popup) return;

		const styles = {
			backgroundColor: popup.querySelector('.style-bg-color').value,
			color: popup.querySelector('.style-text-color').value,
			fontWeight: popup.querySelector('.style-font-weight').value,
			fontSize: popup.querySelector('.style-font-size').value + 'px',
		};

		target.dataset.styles = JSON.stringify(styles);

		const element = target.querySelector(type === 'question' ? '.question-text' : '.answer-text');
		applyStylesToElement(element, styles);

		generateShortcode();
	}

	function updatePreview() {
		const previewContent = document.getElementById('preview-content');
		if (!previewContent) return;

		const questions = buildQuestionData();

		if (questions.length === 0) {
			previewContent.innerHTML = `
                <div class="preview-placeholder">
                    <i data-lucide="eye" class="alvobot-icon"></i>
                    <p>Crie uma questão para ver o preview aqui</p>
                </div>
            `;
			return;
		}

		const style = document.getElementById('quiz_style')?.value || 'default';
		const showProgress = document.getElementById('show_progress')?.checked !== false;

		let html = `<div class="wp-quiz-container wp-quiz-style-${style}">`;

		questions.forEach((item, index) => {
			if (showProgress) {
				const progressPercent = ((index + 1) / questions.length) * 100;
				html += `
                    <div class="quiz-progress-bar">
                        <div class="progress-fill" style="width: ${progressPercent}%"></div>
                        <span class="progress-text">${index + 1}/${questions.length}</span>
                    </div>
                `;
			}

			if (item.type === 'lead_capture') {
				const platformLabel =
					{
						generic: 'Webhook Genérico',
						gohighlevel: 'GoHighLevel',
						sendpulse: 'SendPulse',
					}[item.webhookPlatform] || 'Webhook';

				// Get i18n values or use defaults
				const i18n = item.i18n || {};
				const placeholderName = i18n.placeholderName || 'Nome completo';
				const placeholderEmail = i18n.placeholderEmail || 'Seu melhor email';
				const placeholderPhone = i18n.placeholderPhone || '(XX) XXXXX-XXXX';

				html += `
                    <div class="quiz-question-container">
                        <h3 class="quiz-question">${escapeHtml(item.title)}</h3>
                        <div class="quiz-lead-form">
                            ${
								item.fields.name
									? `
                            <div class="form-group">
                                <input type="text" placeholder="${escapeHtml(placeholderName)}" class="input-modern" disabled>
                            </div>`
									: ''
							}

                            ${
								item.fields.email
									? `
                            <div class="form-group">
                                <input type="email" placeholder="${escapeHtml(placeholderEmail)}" class="input-modern" disabled>
                            </div>`
									: ''
							}

                            ${
								item.fields.phone
									? `
                            <div class="form-group">
                                <input type="tel" placeholder="${escapeHtml(placeholderPhone)}" class="input-modern" disabled>
                            </div>`
									: ''
							}

                            <button class="quiz-btn" disabled>${escapeHtml(item.submitText || 'Continuar')}</button>

                            ${item.webhookUrl ? `<small style="color: #666; margin-top: 8px; display: block;">📡 ${platformLabel}</small>` : ''}
                            ${item.redirectAfterSubmit ? `<small style="color: #666;">🔗 Redireciona para: ${escapeHtml(item.redirectAfterSubmit)}</small>` : ''}
                        </div>
                    </div>
                `;
			} else {
				// It's a question (default or explicit type)
				const question = item;
				const questionTextStyles = question.styles
					? `style="color: ${question.styles.color} !important; font-size: ${question.styles.fontSize} !important; font-weight: ${question.styles.fontWeight} !important;"`
					: '';

				const questionContainerStyles = question.styles?.backgroundColor
					? `style="background-color: ${question.styles.backgroundColor} !important;"`
					: '';

				html += `
                    <div class="quiz-question-container" ${questionContainerStyles}>
                        <h3 class="quiz-question" ${questionTextStyles}>${escapeHtml(question.question)}</h3>
                        <div class="quiz-answers">
                `;

				question.answers.forEach((answer, ansIndex) => {
					const answerText = answer.text || answer; // Handle object or string
					const answerStyles = answer.styles
						? `style="background-color: ${answer.styles.backgroundColor} !important; color: ${answer.styles.color} !important; font-size: ${answer.styles.fontSize} !important; font-weight: ${answer.styles.fontWeight} !important;"`
						: '';

					const answerTextStyles = answer.styles
						? `style="color: ${answer.styles.color} !important; font-size: ${answer.styles.fontSize} !important; font-weight: ${answer.styles.fontWeight} !important;"`
						: '';

					html += `
                        <label class="quiz-answer" ${answerStyles}>
                            <input type="radio" name="preview_answer_${index}" value="${ansIndex}" disabled>
                            <span class="answer-text" ${answerTextStyles}>${escapeHtml(answerText)}</span>
                        </label>
                    `;
				});

				html += `</div></div>`;
			}
		});

		html += '</div>';

		const cssStyles = `
            <style>
                .wp-quiz-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; max-width: 100%; margin: 0; padding: 10px; }
                .quiz-progress-bar { background-color: #f0f0f0; height: 20px; border-radius: 10px; position: relative; margin-bottom: 20px; overflow: hidden; }
                .progress-fill { background-color: #4CAF50; height: 100%; border-radius: 10px; transition: width 0.3s ease; }
                .progress-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: 600; color: #333; }
                .quiz-question-container { background-color: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 2px solid #4CAF50; }
                .quiz-question { font-size: 14px; font-weight: 600; margin-bottom: 15px; color: #333; }
                .quiz-answers { display: flex; flex-direction: column; gap: 8px; }
                .quiz-answer { display: block; background-color: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px 12px; cursor: pointer; transition: all 0.2s ease; }
                .quiz-answer:hover { border-color: #4CAF50; background-color: #f5f5f5; }
                .quiz-answer input[type="radio"] { margin-right: 8px; }
                .answer-text { font-size: 12px; color: #333; }
                .quiz-navigation { display: flex; justify-content: flex-end; margin: 15px 0; }
                .quiz-btn { padding: 8px 16px; font-size: 12px; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; transition: all 0.2s ease; background-color: #4CAF50; color: white; width: 100%; margin-top: 10px; }
                .quiz-btn:hover { background-color: #45a049; }
                .quiz-btn:disabled { opacity: 0.6; cursor: not-allowed; }
                
                /* Form Styles */
                .quiz-lead-form { display: flex; flex-direction: column; gap: 10px; }
                .form-group { display: flex; flex-direction: column; }
                .input-modern { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
                
                .wp-quiz-style-modern .quiz-question-container { border-color: #3b82f6; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); }
                .wp-quiz-style-modern .input-modern { background-color: rgba(255,255,255,0.8); border-color: #bfdbfe; }
                
                .wp-quiz-style-minimal .quiz-question-container { border: 1px solid #e5e7eb; background: #ffffff; }
                .wp-quiz-style-minimal .input-modern { border-radius: 0; border-color: #9ca3af; background: transparent; }
                .wp-quiz-style-minimal .quiz-btn { border-radius: 0; background: #333; }
            </style>
            ${html}
        `;

		previewContent.innerHTML = cssStyles;
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Event listeners
	const componentItems = document.querySelectorAll('.component-item');
	componentItems.forEach((item) => {
		item.addEventListener('dragstart', (e) => {
			e.dataTransfer.setData('text/plain', item.dataset.type || 'question');
		});
	});

	if (questionsContainer) {
		questionsContainer.addEventListener('dragover', (e) => e.preventDefault());
		questionsContainer.addEventListener('drop', (e) => {
			e.preventDefault();
			const type = e.dataTransfer.getData('text/plain');
			if (type === 'question') {
				addQuestion();
			} else if (type === 'lead-capture') {
				addLeadCapture();
			}
		});
	}

	const addButton = document.querySelector('#btn--add-question');
	if (addButton) addButton.addEventListener('click', () => addQuestion());

	const settingsInputs = document.querySelectorAll(
		'#redirect_url, #quiz_style, #show_progress, #show_score, #randomize',
	);
	settingsInputs.forEach((input) => {
		input.addEventListener('change', generateShortcode);
		input.addEventListener('input', generateShortcode);
	});

	const copyButton = document.getElementById('btn--copy');
	if (copyButton) {
		copyButton.addEventListener('click', function () {
			if (shortcodeField) {
				shortcodeField.select();
				document.execCommand('copy');

				const originalText = copyButton.innerHTML;
				copyButton.innerHTML = '✅ Copiado!';
				copyButton.style.backgroundColor = '#28a745';

				setTimeout(() => {
					copyButton.innerHTML = originalText;
					copyButton.style.backgroundColor = '';
				}, 2000);
			}
		});
	}

	// Template and Import buttons
	const templateButton = document.getElementById('btn--template');
	const importButton = document.getElementById('btn--import');

	if (templateButton) {
		templateButton.addEventListener('click', openTemplatesModal);
	}

	if (importButton) {
		importButton.addEventListener('click', openImportModal);
	}

	setTimeout(() => generateShortcode(), 500);
});

// Templates Modal Functions
function openTemplatesModal() {
	const modal = createModal('Templates de Quiz', getTemplatesContent(), [
		{ text: 'Cancelar', class: 'btn btn--cancel', onclick: closeModal },
		{ text: 'Aplicar Template', class: 'btn btn--apply', onclick: applySelectedTemplate },
	]);
	document.body.appendChild(modal);
}

function getTemplatesContent() {
	return `
        <div class="templates-grid">
            <div class="template-card" data-template="cartao-negativado">
                <div class="template-icon">💳</div>
                <h3>Cartão Negativado</h3>
                <p>Cartão de crédito para pessoas com nome sujo. Pré-qualificação para aprovação mesmo sendo negativado.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="emprestimo-urgente">
                <div class="template-icon">💰</div>
                <h3>Empréstimo Urgente</h3>
                <p>Empréstimo rápido para emergências. Qualificação para crédito com aprovação em minutos.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="recuperar-facebook">
                <div class="template-icon">📘</div>
                <h3>Recuperar Facebook</h3>
                <p>Recuperar conta do Facebook hackeada ou bloqueada. Diagnóstico da situação e soluções.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="recuperar-instagram">
                <div class="template-icon">📷</div>
                <h3>Recuperar Instagram</h3>
                <p>Recuperar conta do Instagram desabilitada ou hackeada. Passo a passo personalizado.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="recuperar-email">
                <div class="template-icon">📧</div>
                <h3>Recuperar Email</h3>
                <p>Recuperar acesso ao email (Gmail, Outlook, Yahoo). Diagnóstico e método de recuperação.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="fies-financiamento">
                <div class="template-icon">🎓</div>
                <h3>FIES Financiamento</h3>
                <p>Financiamento estudantil pelo FIES. Verificação de elegibilidade e documentação necessária.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="auxilio-brasil">
                <div class="template-icon">🇧🇷</div>
                <h3>Auxílio Brasil</h3>
                <p>Pré-qualificação para Auxílio Brasil. Verificação de critérios e documentação necessária.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="seguro-desemprego">
                <div class="template-icon">🛡️</div>
                <h3>Seguro Desemprego</h3>
                <p>Verificação de direito ao seguro desemprego. Requisitos e documentação para solicitação.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="mei-gratuito">
                <div class="template-icon">🏢</div>
                <h3>MEI Gratuito</h3>
                <p>Abertura gratuita de MEI. Verificação de elegibilidade e orientação para formalização.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="vagas-emprego">
                <div class="template-icon">💼</div>
                <h3>Vagas Emprego</h3>
                <p>Qualificação para vagas de emprego. Análise de perfil e direcionamento para oportunidades.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="minha-casa-minha-vida">
                <div class="template-icon">🏠</div>
                <h3>Minha Casa Minha Vida</h3>
                <p>Verificação de elegibilidade para o programa habitacional do governo. Análise de renda e perfil.</p>
                <div class="template-stats">
                    <span>📊 6 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="cnh-gratuita">
                <div class="template-icon">🚗</div>
                <h3>CNH Gratuita</h3>
                <p>Pré-qualificação para CNH social gratuita. Verifica critérios e documentação necessária.</p>
                <div class="template-stats">
                    <span>📊 6 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="prouni-bolsa">
                <div class="template-icon">🎓</div>
                <h3>ProUni Bolsa 100%</h3>
                <p>Qualificação para bolsa integral do ProUni. Verifica nota do ENEM e critérios socioeconômicos.</p>
                <div class="template-stats">
                    <span>📊 6 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="auxilio-emergencial">
                <div class="template-icon">🚨</div>
                <h3>Auxílio Emergencial</h3>
                <p>Qualificação para auxílio emergencial. Avalia situação socioeconômica e elegibilidade.</p>
                <div class="template-stats">
                    <span>📊 6 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="energia-social">
                <div class="template-icon">💡</div>
                <h3>Tarifa Social de Energia</h3>
                <p>Qualificação para desconto na conta de luz. Verifica renda e cadastro no CadÚnico.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="divida-luz-agua">
                <div class="template-icon">💸</div>
                <h3>Negociar Dívidas Luz/Água</h3>
                <p>Renegociação de débitos de energia e água. Condições especiais e parcelamento.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="bolsa-familia">
                <div class="template-icon">👨‍👩‍👧‍👦</div>
                <h3>Novo Bolsa Família</h3>
                <p>Elegibilidade para o programa Bolsa Família. Verifica critérios e valor do benefício.</p>
                <div class="template-stats">
                    <span>📊 6 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
            
            <div class="template-card" data-template="limpar-nome-spc">
                <div class="template-icon">✅</div>
                <h3>Limpar Nome SPC/Serasa</h3>
                <p>Soluções para negativação. Feirão Limpa Nome e acordos com desconto.</p>
                <div class="template-stats">
                    <span>📊 5 questões</span>
                    <span>⏱️ ~3min</span>
                </div>
            </div>
        </div>
    `;
}

// Import Modal Functions
function openImportModal() {
	const modal = createModal('Importar Questões', getImportContent(), [
		{ text: 'Cancelar', class: 'btn btn--cancel', onclick: closeModal },
		{ text: 'Importar', class: 'btn btn--import', onclick: processImport },
	]);
	document.body.appendChild(modal);
}

function getImportContent() {
	return `
        <div class="import-single-option">
            <h3>📝 Colar JSON ou Shortcode Completo</h3>
            <p>Cole aqui o JSON das questões ou um shortcode completo do quiz. O sistema detectará automaticamente o formato.</p>
            <textarea id="json-text-input" placeholder="Cole aqui o JSON das questões ou shortcode completo...

Exemplos:
- JSON: [{'question': 'Sua pergunta?', 'answers': ['Op1', 'Op2']}]
- Shortcode: [quiz redirect_url='/obrigado'][...][/quiz]"></textarea>
        </div>
        
        <div id="import-preview" class="import-preview" style="display: none;">
            <h4>Preview das Questões:</h4>
            <div id="questions-preview" class="questions-preview"></div>
        </div>
    `;
}

// Template Data
const templates = {
	'cnh-gratuita': {
		settings: {
			redirect_url: '/inscricao-cnh-gratuita',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você já possui Carteira Nacional de Habilitação (CNH)?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Não, nunca tirei',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Sim, mas está vencida há mais de 5 anos',
					'Sim, mas foi cassada/suspensa',
					{
						text: 'Já possuo CNH válida',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual é a sua renda familiar mensal?',
				answers: [
					{
						text: 'Até 2 salários mínimos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'De 2 a 3 salários mínimos',
						styles: { backgroundColor: '#8BC34A', color: '#ffffff' },
					},
					{
						text: 'De 3 a 5 salários mínimos',
						styles: { backgroundColor: '#FFEB3B', color: '#F57C00' },
					},
					{
						text: 'Mais de 5 salários mínimos',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Você está inscrito no CadÚnico (Cadastro Único)?',
				answers: [
					{
						text: 'Sim, estou inscrito',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não sei o que é',
					'Não, mas gostaria de me inscrever',
					'Não preciso, tenho renda alta',
				],
			},
			{
				question: 'Qual o principal motivo para tirar a CNH?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Conseguir um emprego melhor',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Independência e mobilidade',
					'Trabalhar como motorista de app',
					'Emergências familiares',
					'Realizar um sonho pessoal',
				],
			},
			{
				question: 'O que você mais deseja receber AGORA para conseguir sua CNH gratuita?',
				styles: {
					backgroundColor: '#FF6D00',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📋 Passo a passo completo da inscrição',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📍 Locais disponíveis na minha região',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📚 Material de estudo GRATUITO',
						styles: {
							backgroundColor: '#9C27B0',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ TODOS os benefícios + bônus exclusivos',
						styles: {
							backgroundColor: '#FF5722',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'cartao-negativado': {
		settings: {
			redirect_url: '/solicitar-cartao',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Seu nome está negativado nos órgãos de proteção?',
				styles: {
					backgroundColor: '#D32F2F',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, estou negativado',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Não sei se estou negativado',
					'Já estive, mas quitei as dívidas',
					{
						text: 'Não, meu nome está limpo',
						styles: { backgroundColor: '#4CAF50', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual é a sua renda mensal comprovada?',
				answers: [
					{
						text: 'Até R$ 1.500',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 1.500 a R$ 3.000',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 3.000 a R$ 5.000',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de R$ 5.000',
						styles: {
							backgroundColor: '#9C27B0',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
				],
			},
			{
				question: 'Você já teve cartão de crédito antes?',
				answers: [
					{
						text: 'Sim, e sempre paguei em dia',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Sim, mas tive problemas de pagamento',
					'Sim, mas foi cancelado',
					'Não, seria meu primeiro cartão',
				],
			},
			{
				question: 'Qual limite você precisa no cartão?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					'Até R$ 500',
					{
						text: 'R$ 500 a R$ 1.000',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 1.000 a R$ 3.000',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Mais de R$ 3.000',
				],
			},
			{
				question: 'O que você MAIS PRECISA para conseguir seu cartão HOJE?',
				styles: {
					backgroundColor: '#E91E63',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '💳 Cartões que aprovam NEGATIVADOS',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Documentos necessários organizados',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 Dicas para aumentar chance de aprovação',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🚀 PACOTE COMPLETO: cartões + docs + estratégias',
						styles: {
							backgroundColor: '#7B1FA2',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'emprestimo-urgente': {
		settings: {
			redirect_url: '/solicitar-emprestimo',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você precisa de dinheiro com urgência?',
				styles: {
					backgroundColor: '#D32F2F',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, preciso HOJE MESMO',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Preciso em até 3 dias',
						styles: {
							backgroundColor: '#FF5722',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Preciso em até 1 semana',
					'Não tenho pressa',
				],
			},
			{
				question: 'Qual valor você precisa?',
				answers: [
					{
						text: 'Até R$ 1.000',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 1.000 a R$ 5.000',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 5.000 a R$ 15.000',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Mais de R$ 15.000',
				],
			},
			{
				question: 'Você tem conta em banco?',
				answers: [
					{
						text: 'Sim, conta corrente',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Sim, conta poupança',
					'Sim, conta digital',
					'Não tenho conta bancária',
				],
			},
			{
				question: 'Sua maior preocupação é:',
				styles: {
					backgroundColor: '#FF5722',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					'Ser aprovado rapidamente',
					'Conseguir juros baixos',
					'Não ter que sair de casa',
					'Parcelas que cabem no bolso',
				],
			},
			{
				question: 'O que seria FUNDAMENTAL para resolver sua situação financeira?',
				styles: {
					backgroundColor: '#B71C1C',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🚀 Empréstimo aprovado em MINUTOS',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Melhores taxas do mercado',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📱 Tudo pelo celular, sem burocracia',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ ACESSO VIP: aprovação + juros baixos + sem sair de casa',
						styles: {
							backgroundColor: '#4A148C',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'recuperar-facebook': {
		settings: {
			redirect_url: '/recuperar-facebook',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Há quanto tempo você perdeu acesso ao seu Facebook?',
				styles: {
					backgroundColor: '#1877F2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Menos de 24 horas',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '1 a 7 dias',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 1 semana',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não lembro quando perdi',
				],
			},
			{
				question: 'Você ainda tem acesso ao email cadastrado?',
				answers: [
					{
						text: 'Sim, tenho acesso total',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Tenho acesso, mas não lembro a senha',
					'Não tenho mais acesso ao email',
					'Não lembro qual email usei',
				],
			},
			{
				question: 'Você lembra da senha do Facebook?',
				answers: [
					'Sim, mas não está funcionando',
					'Lembro de senhas antigas',
					'Não lembro de nenhuma senha',
					'Uso sempre a mesma senha',
				],
			},
			{
				question: 'O que aconteceu com sua conta?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Foi hackeada/invadida',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Esqueci a senha',
					'Foi bloqueada pelo Facebook',
					'Mudei de celular e perdi acesso',
				],
			},
			{
				question: 'O que você MAIS PRECISA para recuperar seu Facebook AGORA?',
				styles: {
					backgroundColor: '#1565C0',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🔓 Método que FUNCIONA para recuperar',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📞 Contato direto com suporte Facebook',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🛡️ Como proteger para não perder de novo',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🚀 SOLUÇÃO COMPLETA: recuperação + proteção + suporte',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'recuperar-instagram': {
		settings: {
			redirect_url: '/recuperar-instagram',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Seu Instagram foi hackeado ou você perdeu a senha?',
				styles: {
					backgroundColor: '#E4405F',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Foi hackeado/invadido',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Esqueci a senha',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Mudei de celular e perdi acesso',
					'Foi bloqueado pelo Instagram',
				],
			},
			{
				question: 'Você ainda tem acesso ao email ou telefone cadastrado?',
				answers: [
					{
						text: 'Sim, tenho acesso ao email',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Sim, tenho acesso ao telefone',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não tenho acesso a nenhum',
					'Não lembro quais usei',
				],
			},
			{
				question: 'Você consegue ver seu perfil quando pesquisa?',
				answers: [
					'Sim, mas não consigo entrar',
					'Não, desapareceu completamente',
					'Aparece, mas com conteúdo estranho',
					'Não sei como pesquisar',
				],
			},
			{
				question: 'O que mais te preocupa em relação à sua conta?',
				styles: {
					backgroundColor: '#FFEBEE',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Perder todas as fotos e memórias',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Perder meus seguidores',
					'Alguém postando em meu nome',
					'Não conseguir trabalhar/vender',
				],
			},
			{
				question: 'O que você MAIS PRECISA para recuperar seu Instagram HOJE?',
				styles: {
					backgroundColor: '#C2185B',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📱 Método GARANTIDO que funciona',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📞 Contato direto com suporte Instagram',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🔐 Proteger contra novos ataques',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🚀 RECUPERAÇÃO VIP: método + suporte + proteção',
						styles: {
							backgroundColor: '#7B1FA2',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'recuperar-email': {
		settings: {
			redirect_url: '/recuperar-email',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Qual serviço de email você precisa recuperar?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Gmail/Google',
						styles: {
							backgroundColor: '#4285F4',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Outlook/Hotmail',
						styles: {
							backgroundColor: '#0078D4',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Yahoo Mail',
						styles: {
							backgroundColor: '#6001D2',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Outro provedor',
				],
			},
			{
				question: 'Há quanto tempo você perdeu acesso ao email?',
				answers: [
					{
						text: 'Menos de 1 semana',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: '1 semana a 1 mês',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 1 mês',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não lembro quando perdi',
				],
			},
			{
				question: 'Você lembra da senha?',
				answers: [
					'Sim, mas não está funcionando',
					'Lembro de senhas antigas',
					'Não lembro de nenhuma senha',
					'Nunca soube a senha (outro cadastrou)',
				],
			},
			{
				question: 'Você tem acesso ao telefone de recuperação?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Sim, mesmo número',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Mudei de número',
					'Não tinha telefone cadastrado',
					'Não lembro qual número usei',
				],
			},
			{
				question: 'O que seria MAIS URGENTE para você recuperar o email?',
				styles: {
					backgroundColor: '#1565C0',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📧 Método que FUNCIONA para qualquer email',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📞 Contato direto com suporte técnico',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🔒 Como proteger o email depois',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ SOLUÇÃO TOTAL: recuperação + proteção + suporte',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'fies-financiamento': {
		settings: {
			redirect_url: '/solicitar-fies',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você já fez o ENEM e obteve pelo menos 450 pontos?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, tenho mais de 450 pontos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Fiz, mas não atingi 450 pontos',
					'Não fiz o ENEM ainda',
					'Não sei minha nota',
				],
			},
			{
				question: 'Qual a renda bruta mensal da sua família?',
				answers: [
					{
						text: 'Até 3 salários mínimos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'De 3 a 5 salários mínimos',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 5 salários mínimos',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Não sei informar',
				],
			},
			{
				question: 'Você já está matriculado em alguma faculdade?',
				answers: [
					{
						text: 'Sim, em faculdade privada',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não, mas já fui aprovado',
					'Não, ainda estou procurando',
					'Estou em faculdade pública',
				],
			},
			{
				question: 'Como você pretende pagar a faculdade?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: '100% financiado pelo FIES',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					'Parte FIES, parte família',
					'Só com recursos próprios',
					'Ainda não sei',
				],
			},
			{
				question: 'O que seria FUNDAMENTAL para você conseguir seu FIES?',
				styles: {
					backgroundColor: '#1565C0',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '💰 Calculadora de financiamento personalizada',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🏫 Faculdades que aceitam minha nota ENEM',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Documentação completa necessária',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎓 PACOTE COMPLETO: cálculos + faculdades + docs',
						styles: {
							backgroundColor: '#E65100',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'auxilio-brasil': {
		settings: {
			redirect_url: '/solicitar-auxilio-brasil',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você está inscrito no CadÚnico?',
				styles: {
					backgroundColor: '#2E7D32',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, já estou inscrito',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Não sei o que é CadÚnico',
					'Não, mas quero me inscrever',
					'Já fui, mas pode estar desatualizado',
				],
			},
			{
				question: 'Qual a renda per capita da sua família?',
				answers: [
					{
						text: 'Até R$ 105 por pessoa',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'R$ 105 a R$ 210 por pessoa',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de R$ 210 por pessoa',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Não sei calcular',
				],
			},
			{
				question: 'Você tem filhos menores de 18 anos?',
				answers: [
					{
						text: 'Sim, tenho filhos menores',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Sim, e estão na escola',
					'Não tenho filhos',
					'Tenho, mas já são maiores',
				],
			},
			{
				question: 'Sua situação de trabalho atual:',
				styles: {
					backgroundColor: '#E8F5E8',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Desempregado',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Trabalho informal',
					'MEI com baixa renda',
					'Emprego formal com baixa renda',
				],
			},
			{
				question: 'O que você MAIS PRECISA para receber o Auxílio Brasil?',
				styles: {
					backgroundColor: '#1B5E20',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📋 Passo a passo para se inscrever HOJE',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Calcular quanto vou receber',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📄 Lista completa de documentos',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 GUIA COMPLETO: inscrição + cálculo + documentos',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'seguro-desemprego': {
		settings: {
			redirect_url: '/solicitar-seguro-desemprego',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você foi demitido sem justa causa?',
				styles: {
					backgroundColor: '#FF5722',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, fui demitido sem justa causa',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Fui demitido com justa causa',
					'Pedi demissão',
					'Ainda estou empregado',
				],
			},
			{
				question: 'Há quanto tempo você trabalhava com carteira assinada?',
				answers: [
					{
						text: 'Mais de 12 meses',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'Entre 6 e 12 meses',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Menos de 6 meses',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Não sei ao certo',
				],
			},
			{
				question: 'Você já recebeu seguro-desemprego antes?',
				answers: ['Nunca recebi', 'Recebi uma vez', 'Recebi duas vezes', 'Já recebi mais de duas vezes'],
			},
			{
				question: 'Há quanto tempo você foi demitido?',
				styles: {
					backgroundColor: '#FFEBEE',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Menos de 30 dias',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: '30 a 60 dias',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 60 dias',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não lembro a data',
				],
			},
			{
				question: 'O que você MAIS PRECISA para garantir seu seguro-desemprego?',
				styles: {
					backgroundColor: '#D32F2F',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🚀 Solicitar HOJE MESMO sem erro',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Calcular quantas parcelas vou receber',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Lista de documentos organizados',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ GARANTIA TOTAL: solicitação + cálculo + documentos',
						styles: {
							backgroundColor: '#7B1FA2',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'mei-gratuito': {
		settings: {
			redirect_url: '/abrir-mei',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você já tem CNPJ ou é MEI?',
				styles: {
					backgroundColor: '#388E3C',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					'Não, seria meu primeiro CNPJ',
					'Já tive, mas cancelei',
					{
						text: 'Tenho, mas quero regularizar',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Já sou MEI regular',
						styles: { backgroundColor: '#4CAF50', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual atividade você quer exercer como MEI?',
				answers: [
					{
						text: 'Prestação de serviços',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Comércio/Vendas',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Indústria/Fabricação',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Ainda não sei definir',
				],
			},
			{
				question: 'Você tem os documentos básicos em mãos?',
				answers: [
					{
						text: 'Sim, CPF, RG e comprovante de endereço',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Tenho alguns, mas não todos',
					'Não sei quais documentos preciso',
					'Não tenho nenhum documento',
				],
			},
			{
				question: 'Qual é o seu objetivo principal?',
				styles: {
					backgroundColor: '#E8F5E8',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					'Emitir nota fiscal',
					'Ter benefícios do INSS',
					'Abrir conta bancária PJ',
					'Trabalhar com empresas',
				],
			},
			{
				question: 'O que você MAIS PRECISA para abrir seu MEI HOJE?',
				styles: {
					backgroundColor: '#2E7D32',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🚀 Abrir MEI em MINUTOS sem complicação',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Passo a passo simples e gratuito',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 Escolher a atividade certa',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ PACOTE MEI COMPLETO: abertura + atividade + gestão',
						styles: {
							backgroundColor: '#7B1FA2',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'vagas-emprego': {
		settings: {
			redirect_url: '/vagas-disponiveis',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Qual é a sua situação atual de trabalho?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Desempregado e procurando urgente',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Empregado, mas querendo mudar',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Trabalhando, mas preciso de renda extra',
					'Primeiro emprego',
				],
			},
			{
				question: 'Qual é o seu nível de escolaridade?',
				answers: [
					'Ensino fundamental',
					{
						text: 'Ensino médio completo',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Ensino superior (cursando/completo)',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Curso técnico',
				],
			},
			{
				question: 'Que tipo de trabalho você procura?',
				answers: [
					{
						text: 'Presencial/CLT',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Home office/remoto',
					'Freelancer/temporário',
					'Qualquer coisa para ganhar dinheiro',
				],
			},
			{
				question: 'Qual faixa salarial você precisa?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Até 1 salário mínimo',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: '1 a 2 salários mínimos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: '2 a 4 salários mínimos',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Mais de 4 salários mínimos',
				],
			},
			{
				question: 'O que você MAIS PRECISA para conseguir um emprego AGORA?',
				styles: {
					backgroundColor: '#1565C0',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '💼 Vagas abertas NA MINHA REGIÃO',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📝 Currículo profissional que impressiona',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 Dicas para passar na entrevista',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🚀 EMPREGO GARANTIDO: vagas + currículo + entrevista',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'minha-casa-minha-vida': {
		settings: {
			redirect_url: '/pre-cadastro-mcmv',
			style: 'minimal',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Qual é a sua renda familiar bruta mensal?',
				styles: {
					backgroundColor: '#2E7D32',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Até R$ 2.640 (Faixa 1)',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'R$ 2.640 a R$ 4.400 (Faixa 2)',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 4.400 a R$ 8.000 (Faixa 3)',
						styles: { backgroundColor: '#FFEB3B', color: '#F57C00' },
					},
					{
						text: 'Mais de R$ 8.000',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Você já possui imóvel próprio?',
				answers: [
					{
						text: 'Não, nunca tive casa própria',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não, mas já tive no passado',
					{
						text: 'Sim, mas pretendo trocar',
						styles: { backgroundColor: '#FF9800', color: '#ffffff' },
					},
					{
						text: 'Sim, já tenho casa própria',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Há quanto tempo você trabalha com carteira assinada?',
				answers: [
					{
						text: 'Mais de 2 anos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Entre 1 e 2 anos',
						styles: { backgroundColor: '#8BC34A', color: '#ffffff' },
					},
					'Menos de 1 ano',
					'Sou autônomo/MEI',
					{
						text: 'Estou desempregado',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual tipo de imóvel você deseja?',
				styles: {
					backgroundColor: '#E8F5E8',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Apartamento em condomínio',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Casa térrea',
					'Casa sobrado',
					'Tanto faz, quero o mais barato',
					'Depende da localização',
				],
			},
			{
				question: 'O que seria MAIS VALIOSO para você realizar o sonho da casa própria?',
				styles: {
					backgroundColor: '#1B5E20',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🏠 Lista de imóveis disponíveis NA MINHA FAIXA',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Simulação do meu financiamento personalizada',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Checklist completo de documentos',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 ACESSO TOTAL: imóveis + simulação + documentos',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'prouni-bolsa': {
		settings: {
			redirect_url: '/inscricao-prouni',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você fez o ENEM e obteve pelo menos 450 pontos na média?',
				styles: {
					backgroundColor: '#1976D2',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, fiz e passei dos 450 pontos',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Fiz, mas não sei minha nota',
					'Fiz, mas não atingi 450 pontos',
					{
						text: 'Não fiz o ENEM',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual é a renda per capita da sua família?',
				answers: [
					{
						text: 'Até 1,5 salário mínimo por pessoa',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '16px',
						},
					},
					{
						text: 'De 1,5 a 3 salários mínimos por pessoa',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 3 salários mínimos por pessoa',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Não sei calcular',
				],
			},
			{
				question: 'Como você cursou o ensino médio?',
				answers: [
					{
						text: 'Integralmente em escola pública',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Em escola privada com bolsa integral',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Parte em pública, parte em privada',
					{
						text: 'Integralmente em escola privada',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Qual curso de graduação você deseja fazer?',
				answers: [
					{
						text: 'Medicina/Odontologia',
						styles: {
							backgroundColor: '#FF5722',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Engenharia/Tecnologia',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Direito/Administração',
						styles: {
							backgroundColor: '#795548',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Pedagogia/Licenciaturas',
					'Ainda não decidi',
				],
			},
			{
				question: 'O que te ajudaria MAIS para conquistar sua vaga no ProUni?',
				styles: {
					backgroundColor: '#0D47A1',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🎯 Cursos com MAIOR chance de aprovação',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📊 Minha nota de corte personalizada',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Documentos necessários organizados',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🚀 ESTRATÉGIA COMPLETA: vagas + notas + documentos',
						styles: {
							backgroundColor: '#7B1FA2',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'auxilio-emergencial': {
		settings: {
			redirect_url: '/solicitar-auxilio',
			style: 'minimal',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Qual é a sua situação de trabalho atual?',
				styles: {
					backgroundColor: '#FF5722',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Desempregado sem benefício',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Trabalho informal/autônomo',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'MEI com faturamento baixo',
						styles: {
							backgroundColor: '#FFEB3B',
							color: '#F57C00',
							fontWeight: '600',
						},
					},
					{
						text: 'Tenho emprego formal',
						styles: { backgroundColor: '#4CAF50', color: '#ffffff' },
					},
				],
			},
			{
				question: 'Você é responsável pelo sustento da família?',
				answers: [
					{
						text: 'Sim, sou o único responsável',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'Sim, mas divido com alguém',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não, mas contribuo',
					'Não sou responsável',
				],
			},
			{
				question: 'Quantas pessoas moram na sua casa?',
				answers: [
					'Moro sozinho',
					{
						text: '2 a 3 pessoas',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: '4 a 6 pessoas',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Mais de 6 pessoas',
						styles: {
							backgroundColor: '#B71C1C',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
				],
			},
			{
				question: 'Sua renda mensal atual é:',
				styles: {
					backgroundColor: '#FFEBEE',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Zero ou quase zero',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'Menos de 1 salário mínimo',
						styles: {
							backgroundColor: '#FF5722',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'1 salário mínimo',
					{
						text: 'Mais de 1 salário mínimo',
						styles: { backgroundColor: '#4CAF50', color: '#ffffff' },
					},
				],
			},
			{
				question: 'O que você PRECISA URGENTE para resolver sua situação?',
				styles: {
					backgroundColor: '#B71C1C',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🚨 Como solicitar o auxílio HOJE MESMO',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Outros benefícios que eu posso receber',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Documentos que preciso ter em mãos',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ ACESSO TOTAL: solicitação + benefícios + documentos',
						styles: {
							backgroundColor: '#4A148C',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'bpc-loas': {
		settings: {
			redirect_url: '/solicitar-bpc-loas',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Qual é a sua idade?',
				styles: {
					backgroundColor: '#6A1B9A',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '65 anos ou mais',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Entre 60 e 64 anos',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Menos de 60 anos',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Tenho deficiência',
				],
			},
			{
				question: 'Você tem alguma deficiência ou limitação?',
				answers: [
					{
						text: 'Sim, tenho deficiência física',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Sim, tenho deficiência mental/intelectual',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Sim, tenho deficiência visual/auditiva',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não tenho deficiência',
				],
			},
			{
				question: 'Qual é a renda per capita da sua família?',
				answers: [
					{
						text: 'Até 1/4 de salário mínimo por pessoa',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'Mais de 1/4 de salário mínimo',
						styles: { backgroundColor: '#F44336', color: '#ffffff' },
					},
					'Não sei calcular',
					'Moro sozinho',
				],
			},
			{
				question: 'Você está inscrito no CadÚnico?',
				styles: {
					backgroundColor: '#E1BEE7',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Sim, já estou inscrito',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não sei o que é CadÚnico',
					'Não, mas quero me inscrever',
					'Já fui, mas pode estar desatualizado',
				],
			},
			{
				question: 'O que você MAIS PRECISA para conseguir o BPC/LOAS?',
				styles: {
					backgroundColor: '#4A148C',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📋 Passo a passo completo para solicitar',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🏥 Onde fazer perícia médica',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📄 Lista de documentos necessários',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 GUIA COMPLETO: solicitação + perícia + documentos',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'carteira-trabalho-digital': {
		settings: {
			redirect_url: '/carteira-trabalho-digital',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você já tem a Carteira de Trabalho Digital?',
				styles: {
					backgroundColor: '#0D47A1',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Não, preciso fazer pela primeira vez',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					'Tenho, mas não consigo acessar',
					'Tenho, mas preciso atualizar dados',
					'Tenho e funciona normalmente',
				],
			},
			{
				question: 'Qual é o seu maior problema com documentos trabalhistas?',
				answers: [
					{
						text: 'Perdi a carteira física',
						styles: {
							backgroundColor: '#F44336',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'Nunca tive carteira de trabalho',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Dados estão desatualizados',
					'Empresa não anotou na carteira',
				],
			},
			{
				question: 'Você tem emprego atualmente?',
				answers: [
					{
						text: 'Sim, trabalho com carteira assinada',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Sim, mas trabalho informal',
					'Não, estou desempregado',
					'Sou aposentado',
				],
			},
			{
				question: 'Você tem acesso a internet e smartphone?',
				styles: {
					backgroundColor: '#E3F2FD',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Sim, tenho smartphone e internet',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Tenho smartphone, mas internet limitada',
					'Só tenho acesso em lan house',
					'Preciso de ajuda para usar',
				],
			},
			{
				question: 'O que seria MAIS ÚTIL para você ter sua carteira digital?',
				styles: {
					backgroundColor: '#1565C0',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '📱 Tutorial completo passo a passo',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🔧 Resolver problemas de acesso',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📄 Como corrigir dados incorretos',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '⚡ SOLUÇÃO COMPLETA: criar + acessar + corrigir',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},

	'credito-mei': {
		settings: {
			redirect_url: '/credito-mei',
			style: 'modern',
			show_progress: true,
			randomize: false,
		},
		questions: [
			{
				question: 'Você já é MEI (Microempreendedor Individual)?',
				styles: {
					backgroundColor: '#E65100',
					color: '#ffffff',
					fontSize: '20px',
					fontWeight: '700',
				},
				answers: [
					{
						text: 'Sim, já sou MEI há mais de 1 ano',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: 'Sim, sou MEI há menos de 1 ano',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Não, mas quero me tornar MEI',
					'Não sei o que é MEI',
				],
			},
			{
				question: 'Qual é o seu faturamento mensal como MEI?',
				answers: [
					{
						text: 'Mais de R$ 3.000/mês',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
						},
					},
					{
						text: 'Entre R$ 1.500 e R$ 3.000/mês',
						styles: {
							backgroundColor: '#8BC34A',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Menos de R$ 1.500/mês',
					'Ainda não faturei nada',
				],
			},
			{
				question: 'Para que você precisa do crédito?',
				answers: [
					{
						text: 'Comprar equipamentos/ferramentas',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'Capital de giro para estoque',
					'Reformar/alugar ponto comercial',
					'Investir em marketing/divulgação',
					'Pagar dívidas do negócio',
				],
			},
			{
				question: 'Quanto de crédito você precisa?',
				styles: {
					backgroundColor: '#FFF3E0',
					fontSize: '18px',
					fontWeight: '600',
				},
				answers: [
					{
						text: 'Até R$ 5.000',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					{
						text: 'R$ 5.000 a R$ 15.000',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '600',
						},
					},
					'R$ 15.000 a R$ 50.000',
					'Mais de R$ 50.000',
				],
			},
			{
				question: 'O que seria MAIS IMPORTANTE para conseguir seu crédito MEI?',
				styles: {
					backgroundColor: '#BF360C',
					color: '#ffffff',
					fontSize: '22px',
					fontWeight: '700',
				},
				answers: [
					{
						text: '🏦 Melhores bancos que emprestam para MEI',
						styles: {
							backgroundColor: '#4CAF50',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '💰 Simulação de valores e juros',
						styles: {
							backgroundColor: '#2196F3',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '📋 Documentos necessários',
						styles: {
							backgroundColor: '#FF9800',
							color: '#ffffff',
							fontWeight: '700',
							fontSize: '18px',
						},
					},
					{
						text: '🎯 ACESSO TOTAL: bancos + simulação + documentos',
						styles: {
							backgroundColor: '#E91E63',
							color: '#ffffff',
							fontWeight: '800',
							fontSize: '20px',
						},
					},
				],
			},
		],
	},
};

// Modal helper functions
function createModal(title, content, buttons) {
	const modal = document.createElement('div');
	modal.className = 'alvobot-modal';

	modal.innerHTML = `
        <div class="alvobot-modal-content">
            <div class="alvobot-modal-header">
                <h2>${title}</h2>
                <button type="button" class="alvobot-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="alvobot-modal-body">
                ${content}
            </div>
            <div class="alvobot-modal-footer">
                ${buttons
					.map(
						(btn) =>
							`<button type="button" class="${btn.class}" onclick="${btn.onclick.name}()">${btn.text}</button>`,
					)
					.join('')}
            </div>
        </div>
    `;

	// Add event listeners for template selection
	modal.addEventListener('click', function (e) {
		if (e.target.closest('.template-card')) {
			const cards = modal.querySelectorAll('.template-card');
			cards.forEach((card) => card.classList.remove('selected'));
			e.target.closest('.template-card').classList.add('selected');
		}

		if (e.target === modal) {
			closeModal();
		}
	});

	// Add text input listener
	const textInput = modal.querySelector('#json-text-input');
	if (textInput) {
		textInput.addEventListener('input', handleTextImport);
	}

	return modal;
}

function closeModal() {
	const modal = document.querySelector('.alvobot-modal');
	if (modal) {
		modal.remove();
	}
}

function applySelectedTemplate() {
	const selectedCard = document.querySelector('.template-card.selected');
	if (!selectedCard) {
		alert('Por favor, selecione um template.');
		return;
	}

	const templateType = selectedCard.getAttribute('data-template');
	const template = templates[templateType];

	// Clear current questions
	const questionsContainer = document.getElementById('questions-container');
	questionsContainer.innerHTML = '';

	// Apply settings
	Object.keys(template.settings).forEach((key) => {
		const element = document.getElementById(key);
		if (element) {
			if (element.type === 'checkbox') {
				element.checked = template.settings[key];
			} else {
				element.value = template.settings[key];
			}
		}
	});

	// Add questions
	template.questions.forEach((questionData) => {
		addQuestion(questionData);
	});

	closeModal();
	window.generateShortcode();
}

function handleTextImport(event) {
	const content = event.target.value.trim();
	if (!content) {
		hideImportPreview();
		return;
	}

	try {
		// Try to parse as JSON first
		let data;
		let shortcodeParams = {};

		if (content.startsWith('[quiz')) {
			// Extract JSON from shortcode - improved regex to handle multiline JSON
			const jsonMatch = content.match(/\[quiz([^\]]*)\]\s*([\s\S]*?)\s*\[\/quiz\]/);
			if (jsonMatch) {
				// Extract parameters from shortcode
				const paramsString = jsonMatch[1];
				const jsonString = jsonMatch[2].trim();

				// Parse shortcode parameters
				const paramRegex = /(\w+)="([^"]*)"/g;
				let match;
				while ((match = paramRegex.exec(paramsString)) !== null) {
					shortcodeParams[match[1]] = match[2];
				}

				// Store parameters for later use
				window.importedParams = shortcodeParams;

				data = JSON.parse(jsonString);
			} else {
				console.error('Shortcode inválido - regex não encontrou match');
				throw new Error('Shortcode inválido');
			}
		} else {
			// Direct JSON
			data = JSON.parse(content);
		}

		displayImportPreview(data);
	} catch (error) {
		console.error('Erro na importação:', error.message);
		hideImportPreview();
	}
}

function displayImportPreview(questions) {
	const preview = document.getElementById('import-preview');
	const container = document.getElementById('questions-preview');

	if (!Array.isArray(questions) || questions.length === 0) {
		hideImportPreview();
		return;
	}

	container.innerHTML = questions
		.map((q, index) => {
			// Check if it's a lead_capture type
			if (q.type === 'lead_capture') {
				const fields = [];
				if (q.fields?.name) fields.push('Nome');
				if (q.fields?.email) fields.push('Email');
				if (q.fields?.phone) fields.push('Telefone');
				return `
                <div class="question-preview lead-capture-preview">
                    <strong>📋 Lead Capture ${index + 1}:</strong> ${q.title || 'Captura de Leads'}
                    <div class="answers-preview">
                        <span class="answer-preview">Campos: ${fields.join(', ') || 'Nenhum'}</span>
                        ${q.webhookUrl ? `<span class="answer-preview">Webhook: ✓</span>` : ''}
                        ${q.redirectAfterSubmit ? `<span class="answer-preview">Redirect: ${q.redirectAfterSubmit}</span>` : ''}
                    </div>
                </div>
            `;
			}

			// Regular question
			return `
            <div class="question-preview">
                <strong>Questão ${index + 1}:</strong> ${q.question || 'Sem título'}
                <div class="answers-preview">
                    ${(q.answers || [])
						.map((answer, answerIndex) => {
							const answerText = getAnswerText(answer);
							const isCorrect = q.correct === answerIndex;
							return `<span class="answer-preview ${isCorrect ? 'correct' : ''}">${answerText}</span>`;
						})
						.join('')}
                </div>
            </div>
        `;
		})
		.join('');

	preview.style.display = 'block';
	window.importData = questions;
}

function hideImportPreview() {
	const preview = document.getElementById('import-preview');
	if (preview) {
		preview.style.display = 'none';
	}
	window.importData = null;
}

function processImport() {
	if (!window.importData) {
		alert('Nenhum dado válido para importar.');
		return;
	}

	// Apply imported shortcode parameters if available
	if (window.importedParams) {
		// Apply redirect_url
		if (window.importedParams.redirect_url) {
			const redirectInput = document.getElementById('redirect_url');
			if (redirectInput) redirectInput.value = window.importedParams.redirect_url;
		}

		// Apply style
		if (window.importedParams.style) {
			const styleSelect = document.getElementById('quiz_style');
			if (styleSelect) styleSelect.value = window.importedParams.style;
		}

		// Apply checkboxes
		const checkboxParams = ['show_progress', 'show_score', 'randomize'];
		checkboxParams.forEach((param) => {
			if (window.importedParams[param] !== undefined) {
				const checkbox = document.getElementById(param);
				if (checkbox) checkbox.checked = window.importedParams[param] === 'true';
			}
		});
	}

	// Clear current questions
	const questionsContainer = document.getElementById('questions-container');
	questionsContainer.innerHTML = '';

	// Add imported questions/lead captures
	window.importData.forEach((itemData) => {
		if (itemData.type === 'lead_capture') {
			addLeadCapture(itemData);
		} else {
			addQuestion(itemData);
		}
	});

	closeModal();
	generateShortcode();
}

// Make functions global
window.closeModal = closeModal;
window.applySelectedTemplate = applySelectedTemplate;
window.processImport = processImport;
