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
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};

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
