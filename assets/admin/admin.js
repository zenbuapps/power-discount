(function ($) {
    'use strict';

    $(document).on('click', '.pd-toggle-status', function (e) {
        e.preventDefault();
        var $link = $(this);
        if ($link.hasClass('pd-disabled')) {
            return;
        }
        var id = $link.data('id');
        var nonce = $link.data('nonce');
        if (!id) {
            return;
        }
        $link.addClass('pd-disabled').css('opacity', 0.5);
        $.post(PowerDiscountAdmin.ajaxUrl, {
            action: 'pd_toggle_rule_status',
            id: id,
            nonce: nonce
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            $link.removeClass('pd-disabled').css('opacity', 1);
            var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Toggle failed';
            window.alert(msg);
        });
    });
})(jQuery);
