jQuery(document).ready(function($) {
    $('.token-toggle').on('click', function() {
        var $tokenField = $(this).prev('.token-value');
        var $button = $(this);
        
        if ($button.hasClass('showing')) {
            $tokenField.text('••••••••••••••••••••••••••••••••');
            $button.removeClass('showing');
        } else {
            $tokenField.text($tokenField.data('token'));
            $button.addClass('showing');
        }
    });
});
