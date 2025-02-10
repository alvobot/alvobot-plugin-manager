jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.color-picker').wpColorPicker();
    
    // Update font size display
    $('#font_size').on('input', function() {
        $('#font_size_display').text($(this).val() + 'px');
    });

    // Preview logo style change
    $('#logo_style').on('change', function() {
        var style = $(this).val();
        var preview = $('.logo-style-preview');
        
        if (!preview.length) {
            preview = $('<div class="logo-style-preview"></div>').insertAfter($(this));
        }
        
        var descriptions = {
            'minimal': 'Clean and simple design with modern sans-serif font',
            'modern': 'Bold and contemporary look with strong typography',
            'classic': 'Elegant serif font with traditional styling',
            'bold': 'Strong impact with heavy weight font'
        };
        
        preview.text(descriptions[style] || '');
    });

    // Trigger initial style preview
    $('#logo_style').trigger('change');
});
