const settings = window.wc.wcSettings.getSetting('comfino_data', {});
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__('Comfino Payments', 'comfino-payment-gateway');

/**
 * Content component.
 */
const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};

/**
 * Comfino payment method config object.
 */
const Comfino = {
    name: 'comfino',
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Comfino);
