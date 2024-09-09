const settings = window.wc.wcSettings.getSetting('comfino_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Comfino payments', 'comfino-payment-gateway');

const Label = () => {
    if (settings.icon) {
        return window.wp.element.RawHTML({
            children: label + '<img src="' + settings.icon + '" alt="' + label + '" style="margin-left: 10px; vertical-align: bottom">'
        });
    }

    return label;
};

const Content = () => {
    return window.wp.element.RawHTML({ children: window.wp.htmlEntities.decodeEntities(settings.iframe) });
};

window.wp.hooks.addAction('experimental__woocommerce_blocks-checkout-render-checkout-form', 'comfino', () => {
    console.log('Rendered checkout');
    const paymentMethod = wp.data.select('wc/store/payment').getActivePaymentMethod();
    console.log(paymentMethod);
});

window.wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'comfino', (paymentMethod) => {
    console.log(paymentMethod);
    //paymentMethod.value
});

window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'comfino',
    label: Object(window.wp.element.createElement)(Label, null),
    icon: 'money-alt',
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)('EDIT', null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
});
