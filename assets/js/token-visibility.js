jQuery(document).ready(function($) {
    $('.alvobot-token-toggle').on('click', function() {
        var $tokenField = $(this).prev('.alvobot-token-value');
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
