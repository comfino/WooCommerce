jQuery(function ($) {
    const paymentTextEnabledCheckbox = $('#woocommerce_comfino_payment_text_enabled');
    const paymentTextInput = $('#woocommerce_comfino_payment_text');

    if (paymentTextEnabledCheckbox.length && paymentTextInput.length) {
        const togglePaymentTextInput = function () {
            paymentTextInput.prop('disabled', !paymentTextEnabledCheckbox.is(':checked'));
        };

        togglePaymentTextInput();

        paymentTextEnabledCheckbox.on('change', togglePaymentTextInput);
    }

    const maxSelectGroups = {};

    $('input[type="checkbox"][data-comfino-max-select]').each(function () {
        const maxSelect = $(this).data('comfino-max-select');

        (maxSelectGroups[maxSelect] = maxSelectGroups[maxSelect] || []).push(this);
    });

    $.each(maxSelectGroups, function (maxSelectValue, checkboxes) {
        const maxSelect = parseInt(maxSelectValue, 10);
        const group = $(checkboxes);

        const enforceLimit = function () {
            const limitReached = group.filter(':checked').length >= maxSelect;

            group.not(':checked').prop('disabled', limitReached);
        };

        group.on('change', enforceLimit);

        enforceLimit();
    });
});
