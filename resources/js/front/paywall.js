const comfinoSettings = wc.wcSettings.getSetting('comfino_data', {});

window.Comfino = {
    label: wp.htmlEntities.decodeEntities(comfinoSettings.title) || wp.i18n.__('Comfino payments', 'comfino-payment-gateway'),
    isSelected: false,
    isPaywallActive: false,
    loanParams: { loanAmount: 0, loanType: '', loanTerm: 0 },
    listItemContainer: null,
    labelObserver: null,
    Label: () => {
        Comfino.isPaywallActive = true;

        if (comfinoSettings.icon) {
            return wp.element.RawHTML({
                children: Comfino.label + '<img id="comfino-gateway-logo" src="' + comfinoSettings.icon + '" alt="' + Comfino.label + '" style="margin-left: 10px; vertical-align: bottom">'
            });
        }

        return label;
    },
    Content: (properties) => {
        const { eventRegistration, emitResponse } = properties;
        const { onPaymentSetup } = eventRegistration;

        wp.element.useEffect(() => {
                const unsubscribe = onPaymentSetup(async () => {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                comfino_loan_amount: Comfino.loanParams.loanAmount.toString(),
                                comfino_loan_type: Comfino.loanParams.loanType,
                                comfino_loan_term: Comfino.loanParams.loanTerm.toString(),
                            }
                        }
                    };
                });

                return () => { unsubscribe(); };
            },
            [emitResponse.responseTypes.SUCCESS, onPaymentSetup]
        );

        return wp.element.RawHTML({ children: wp.htmlEntities.decodeEntities(comfinoSettings.iframe) });
    },
    EditContent: () => {
        return wp.element.RawHTML({ children: '<b>[Comfino Panel]</b>' });
    },
    init: () => {
        if (!Comfino.isPaywallActive) {
            return;
        }

        if (typeof ComfinoPaywallFrontend === 'undefined') {
            console.warn('ComfinoPaywallFrontend is undefined.');

            return;
        }

        if (!ComfinoPaywallFrontend.isInitialized()) {
            comfinoSettings.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState WooCommerce (Payment Blocks)', 'debug', loanParams);

                if (loanParams.loanTerm !== 0) {
                    Comfino.loanParams.loanAmount = loanParams.loanAmount;
                    Comfino.loanParams.loanType = loanParams.loanType;
                    Comfino.loanParams.loanTerm = loanParams.loanTerm;
                }
            }

            let iframe = document.getElementById('comfino-paywall-container');

            if (iframe === null) {
                let logoImgElement = document.getElementById('comfino-gateway-logo');

                if (logoImgElement === null) {
                    ComfinoPaywallFrontend.logEvent('Comfino logo not found in the payment block.', 'error');

                    return;
                }

                if (Comfino.listItemContainer === null) {
                    let initInputElement = ComfinoPaywallFrontend.findMatchingParentElement(
                        logoImgElement,
                        (currentElement) => (currentElement.querySelector('input[type="radio"][value="comfino"]') !== null)
                    );

                    if (initInputElement === null) {
                        ComfinoPaywallFrontend.logEvent('Comfino initialization input element not found in the payment block.', 'error');

                        return;
                    }

                    Comfino.listItemContainer = ComfinoPaywallFrontend.findMatchingParentElement(
                        initInputElement,
                        (currentElement) => (currentElement.tagName === 'DIV')
                    );
                }

                if (Comfino.labelObserver === null) {
                    Comfino.labelObserver = new MutationObserver((mutationsList, observer) => {
                        ComfinoPaywallFrontend.logEvent('Comfino item changed.', 'debug', mutationsList, observer);

                        if (Comfino.isSelected) {
                            let iframe = document.getElementById('comfino-paywall-container');

                            ComfinoPaywallFrontend.logEvent('Deferred Comfino initialization started.', 'debug', iframe);
                            ComfinoPaywallFrontend.init(null, iframe, comfinoSettings.paywallOptions);
                            ComfinoPaywallFrontend.executeClickLogic();
                        }
                    });

                    Comfino.labelObserver.observe(Comfino.listItemContainer, { characterData: true, subtree: true, childList: true });
                }

                return;
            }

            ComfinoPaywallFrontend.init(null, iframe, comfinoSettings.paywallOptions);
        }
    }
}

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-render-checkout-form', 'comfino', () => {
    if (wp.data.select('wc/store/payment').getActivePaymentMethod() === 'comfino') {
        Comfino.isSelected = true
    } else {
        Comfino.isSelected = false
    }
});

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'comfino', (paymentMethod) => {
    if (paymentMethod.value === 'comfino') {
        Comfino.isSelected = true

        if (ComfinoPaywallFrontend.isInitialized()) {
            ComfinoPaywallFrontend.executeClickLogic();
        }
    } else {
        Comfino.isSelected = false
    }
});

wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'comfino',
    label: Object(wp.element.createElement)(Comfino.Label, null),
    icon: 'money-alt',
    content: Object(wp.element.createElement)(Comfino.Content, null),
    edit: Object(wp.element.createElement)(Comfino.EditContent, null),
    canMakePayment: () => {
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

        return true;
    },
    ariaLabel: Comfino.label,
    supports: {
        features: comfinoSettings.supports
    }
});
