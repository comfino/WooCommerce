const settings = window.wc.wcSettings.getSetting('comfino_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Comfino Payments', 'comfino-payment-gateway');

const Label = () => {
    if (settings.icon) {
        return window.wp.element.createElement(
            'img',
            {
                src: settings.icon,
                alt: settings.title,
                style: { float: 'right', marginRight: '20px' }
            }
        );
    }

    return label;
};

const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || 'TEST');
};

const Comfino = {
    name: 'comfino',
    label: Object(window.wp.element.createElement)(Label, null),
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Comfino);
