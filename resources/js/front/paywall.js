const settings = wc.wcSettings.getSetting('comfino_data', {});
const label = wp.htmlEntities.decodeEntities(settings.title) || wp.i18n.__('Comfino payments', 'comfino-payment-gateway');

window.Comfino = {
    isSelected: false,
    init: () => {
        if (!ComfinoPaywallFrontend.isInitialized()) {
            let iframe = document.getElementById('comfino-paywall-container');

            settings.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState WooCommerce (Payment Blocks)', 'debug', loanParams);

                if (loanParams.loanTerm !== 0) {
                    document.getElementById('comfino-loan-amount').value = loanParams.loanAmount;
                    document.getElementById('comfino-loan-type').value = loanParams.loanType;
                    document.getElementById('comfino-loan-term').value = loanParams.loanTerm;
                }
            }

            ComfinoPaywallFrontend.init(null, iframe, settings.paywallOptions);
        }
    }
}

const Label = () => {
    if (settings.icon) {
        return wp.element.RawHTML({
            children: label + '<img src="' + settings.icon + '" alt="' + label + '" style="margin-left: 10px; vertical-align: bottom">'
        });
    }

    return label;
};

const Content = () => {
    return wp.element.RawHTML({ children: wp.htmlEntities.decodeEntities(settings.iframe) });
};

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-render-checkout-form', 'comfino', () => {
    if (wp.data.select('wc/store/payment').getActivePaymentMethod() === 'comfino') {
        Comfino.isSelected = true
    } else {
        Comfino.isSelected = false
    }
});

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'comfino', (paymentMethod) => {
    console.log(paymentMethod);
    if (paymentMethod.value === 'comfino') {
        Comfino.isSelected = true

        ComfinoPaywallFrontend.executeClickLogic();
    } else {
        Comfino.isSelected = false
    }
});

wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'comfino',
    label: Object(wp.element.createElement)(Label, null),
    icon: 'money-alt',
    content: Object(wp.element.createElement)(Content, null),
    edit: Object(wp.element.createElement)('EDIT', null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
});

if (document.readyState === 'complete') {
    Comfino.init();

    if (Comfino.isSelected) {
        ComfinoPaywallFrontend.executeClickLogic();
    }
} else {
    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            Comfino.init();

            if (Comfino.isSelected) {
                ComfinoPaywallFrontend.executeClickLogic();
            }
        }
    });
}
