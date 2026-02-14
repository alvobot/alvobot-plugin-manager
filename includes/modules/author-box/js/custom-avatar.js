jQuery(document).ready(function ($) {
	var mediaUploader;

	$('#ab_upload_avatar_button').on('click', function (e) {
		e.preventDefault();

		// If the media uploader already exists, open it
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}

		// Create the media uploader
		mediaUploader = wp.media({
			title: 'Choose Avatar',
			button: {
				text: 'Set as avatar',
			},
			multiple: false,
		});

		// When an image is selected
		mediaUploader.on('select', function () {
			var attachment = mediaUploader.state().get('selection').first().toJSON();

			// Update the preview image
			$('.ab-avatar-preview img')
				.attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url)
				.show();

			// Update the hidden input
			$('#ab_custom_avatar_id').val(attachment.id);

			// Show the remove button
			$('#ab_remove_avatar_button').show();
		});

		// Open the uploader
		mediaUploader.open();
	});

	// Handle remove button click
	$('#ab_remove_avatar_button').on('click', function (e) {
		e.preventDefault();

		// Hide the preview image
		$('.ab-avatar-preview img').hide();

		// Clear the hidden input
		$('#ab_custom_avatar_id').val('');

		// Hide the remove button
		$(this).hide();
	});
});
