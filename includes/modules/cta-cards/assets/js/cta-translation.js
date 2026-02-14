/**
 * CTA Cards Translation JavaScript
 * Handles translation functionality for CTA Cards shortcodes
 */

jQuery(document).ready(function ($) {
	// Add translation button to editor toolbar
	addCTATranslationButton();

	// Modal event handlers
	setupModalEvents();

	/**
	 * Add CTA Translation button to editor
	 */
	function addCTATranslationButton() {
		// Check if we're on post edit page
		if (typeof wp === 'undefined' || typeof wp.data === 'undefined') {
			return;
		}

		// Add button to classic editor if TinyMCE is available
		if (typeof tinymce !== 'undefined') {
			// Wait for TinyMCE to be ready
			setTimeout(function () {
				addClassicEditorButton();
			}, 1000);
		}

		// Add button for Gutenberg/Block editor
		if (wp.data && wp.data.select('core/editor')) {
			addBlockEditorButton();
		}

		// Add floating button as fallback
		addFloatingButton();
	}

	/**
	 * Add button to classic editor
	 */
	function addClassicEditorButton() {
		if (typeof tinymce === 'undefined') return;

		// Add button to editor toolbar
		if ($('#wp-content-editor-tools').length) {
			const $toolbar = $('#wp-content-editor-tools .wp-media-buttons');
			if ($toolbar.length && !$toolbar.find('.alvobot-cta-translate-btn').length) {
				$toolbar.append(
					'<button type="button" class="button alvobot-cta-translate-btn">' +
						'<i data-lucide="languages" class="alvobot-icon" style="margin-right: 5px;"></i>' +
						alvobotCTATranslation.strings.translate_cta +
						'</button>',
				);
			}
		}
	}

	/**
	 * Add button for block editor (Gutenberg)
	 */
	function addBlockEditorButton() {
		// Create a floating button for Gutenberg
		if ($('.edit-post-header-toolbar').length && !$('.alvobot-cta-translate-floating').length) {
			$('body').append(
				'<div class="alvobot-cta-translate-floating">' +
					'<button type="button" class="alvobot-cta-translate-btn components-button">' +
					'<i data-lucide="languages" class="alvobot-icon"></i>' +
					'<span>' +
					alvobotCTATranslation.strings.translate_cta +
					'</span>' +
					'</button>' +
					'</div>',
			);

			// Add styles for floating button
			$('<style>')
				.prop('type', 'text/css')
				.html(`
                    .alvobot-cta-translate-floating {
                        position: fixed;
                        top: 100px;
                        right: 20px;
                        z-index: 100000;
                        background: white;
                        border-radius: 6px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        padding: 5px;
                    }
                    .alvobot-cta-translate-floating .alvobot-cta-translate-btn {
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        padding: 8px 12px;
                        border: 1px solid #ddd;
                        background: #f0f0f1;
                        color: #3c434a;
                        border-radius: 3px;
                        cursor: pointer;
                        font-size: 13px;
                        text-decoration: none;
                    }
                    .alvobot-cta-translate-floating .alvobot-cta-translate-btn:hover {
                        background: #fff;
                        border-color: #0073aa;
                        color: #0073aa;
                    }
                    .alvobot-cta-translate-floating .alvobot-icon {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                    }
                `)
				.appendTo('head');
		}
	}

	/**
	 * Add floating button as fallback
	 */
	function addFloatingButton() {
		// Only add if no other button was added
		if (!$('.alvobot-cta-translate-btn').length) {
			$('body').append(
				'<div class="alvobot-cta-translate-floating fallback">' +
					'<button type="button" class="alvobot-cta-translate-btn button">' +
					'<i data-lucide="languages" class="alvobot-icon"></i>' +
					alvobotCTATranslation.strings.translate_cta +
					'</button>' +
					'</div>',
			);
		}
	}

	/**
	 * Setup modal events
	 */
	function setupModalEvents() {
		// Open modal when translate button is clicked
		$(document).on('click', '.alvobot-cta-translate-btn', function (e) {
			e.preventDefault();
			openTranslationModal();
		});

		// Close modal
		$(document).on('click', '.alvobot-modal-close, .alvobot-modal-cancel', function () {
			closeTranslationModal();
		});

		// Close modal when clicking outside
		$(document).on('click', '#alvobot-cta-translation-modal', function (e) {
			if (e.target === this) {
				closeTranslationModal();
			}
		});

		// Handle translation submission
		$(document).on('click', '.alvobot-translate-cta-submit', function () {
			submitTranslation();
		});

		// Escape key closes modal
		$(document).on('keydown', function (e) {
			if (e.keyCode === 27 && $('#alvobot-cta-translation-modal').is(':visible')) {
				closeTranslationModal();
			}
		});
	}

	/**
	 * Open translation modal
	 */
	function openTranslationModal() {
		// Check if post has CTA shortcodes
		const content = getPostContent();
		const ctaPattern = /\[cta_card[^\]]*\]/g;
		const matches = content.match(ctaPattern);

		if (!matches || matches.length === 0) {
			alert(alvobotCTATranslation.strings.no_cta_found);
			return;
		}

		// Show modal
		$('#alvobot-cta-translation-modal').show();

		// Reset form
		$('#alvobot-cta-translation-modal input[type="checkbox"]').prop('checked', false);

		// Update info text
		const $modal = $('#alvobot-cta-translation-modal');
		$modal
			.find('.alvobot-modal-body p')
			.text(
				`Este post contém ${matches.length} shortcode(s) [cta_card]. Selecione os idiomas para os quais deseja traduzir os textos dos CTA Cards:`,
			);
	}

	/**
	 * Close translation modal
	 */
	function closeTranslationModal() {
		$('#alvobot-cta-translation-modal').hide();
	}

	/**
	 * Submit translation request
	 */
	function submitTranslation() {
		const $modal = $('#alvobot-cta-translation-modal');
		const $submitBtn = $('.alvobot-translate-cta-submit');

		// Get selected languages
		const selectedLanguages = [];
		$modal.find('input[name="target_languages[]"]:checked').each(function () {
			selectedLanguages.push($(this).val());
		});

		if (selectedLanguages.length === 0) {
			alert('Selecione pelo menos um idioma de destino.');
			return;
		}

		// Get replace option
		const replaceOriginal = $modal.find('input[name="replace_original"]').is(':checked');

		// Get post ID
		const postId = getPostId();
		if (!postId) {
			alert('Erro: ID do post não encontrado.');
			return;
		}

		// Show loading state
		$submitBtn.prop('disabled', true).text(alvobotCTATranslation.strings.translating);

		// Make AJAX request
		$.ajax({
			url: alvobotCTATranslation.ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_translate_cta_shortcode',
				nonce: alvobotCTATranslation.nonce,
				post_id: postId,
				target_languages: selectedLanguages,
				replace_original: replaceOriginal ? 1 : 0,
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);

					// If content was replaced, reload the page to show updated content
					if (replaceOriginal) {
						window.location.reload();
					} else {
						// Show translated shortcodes in console for now
						console.log('Translated shortcodes:', response.data.translated_shortcodes);
						closeTranslationModal();
					}
				} else {
					alert(alvobotCTATranslation.strings.error + ' ' + (response.data || ''));
				}
			},
			error: function () {
				alert(alvobotCTATranslation.strings.error);
			},
			complete: function () {
				$submitBtn.prop('disabled', false).text('Traduzir');
			},
		});
	}

	/**
	 * Get post content from editor
	 */
	function getPostContent() {
		// Try Gutenberg first
		if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
			const content = wp.data.select('core/editor').getEditedPostContent();
			if (content) return content;
		}

		// Try TinyMCE
		if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
			return tinymce.get('content').getContent();
		}

		// Fallback to textarea
		const $textarea = $('#content');
		if ($textarea.length) {
			return $textarea.val();
		}

		return '';
	}

	/**
	 * Get current post ID
	 */
	function getPostId() {
		// Try Gutenberg
		if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
			const postId = wp.data.select('core/editor').getCurrentPostId();
			if (postId) return postId;
		}

		// Try from URL
		const urlParams = new URLSearchParams(window.location.search);
		const postParam = urlParams.get('post');
		if (postParam) return postParam;

		// Try from global variables
		if (typeof typenow !== 'undefined' && typeof pagenow !== 'undefined') {
			const postIdFromBody = $('body')
				.attr('class')
				.match(/postid-(\d+)/);
			if (postIdFromBody) return postIdFromBody[1];
		}

		// Try from form
		const $postIdInput = $('#post_ID');
		if ($postIdInput.length) {
			return $postIdInput.val();
		}

		return null;
	}
});
