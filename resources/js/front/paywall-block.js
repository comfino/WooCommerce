const comfinoSettings = wc.wcSettings.getSetting('comfino_data', {});

window.Comfino = {
    label: wp.htmlEntities.decodeEntities(comfinoSettings.title) || wp.i18n.__('Comfino payments', 'comfino-payment-gateway'),
    isSelected: false,
    isPaywallActive: false,
    loanParams: { loanAmount: 0, loanType: '', loanTerm: 0 },
    listItemContainer: null,
    labelObserver: null,
    paywallTemplate: null,
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

        if (Comfino.paywallTemplate === null) {
            const paywallTemplate = new DOMParser().parseFromString(comfinoSettings.iframeTemplate, 'text/html');
            paywallTemplate.body.firstChild.innerHTML = ComfinoPaywallFrontend.renderPaywallIframe(comfinoSettings.paywallUrl, comfinoSettings.paywallOptions);
            Comfino.paywallTemplate = paywallTemplate.body.innerHTML;

            ComfinoPaywallFrontend.logEvent('Comfino paywall template initialized.', 'debug', paywallTemplate.body.firstChild);
        }

        return wp.element.RawHTML({ children: wp.htmlEntities.decodeEntities(Comfino.paywallTemplate) });
    },
    EditContent: () => {
        return wp.element.RawHTML({ children: '<b>[Comfino Panel]</b>' });
    },
    /**
     * Initializes paywall frontend.
     *
     * orderData:
     * {
     *    cart: Cart,
     *    cartTotals: CartTotals,
     *    cartNeedsShipping: boolean,
     *    shippingAddress: CartShippingAddress,
     *    billingAddress: CartBillingAddress,
     *    selectedShippingMethods: Record,
     *    paymentRequirements: string[]
     * }
     *
     * @param {Object} orderData
     */
    init: (orderData) => {
        ComfinoPaywallFrontend.logEvent('Comfino.init', 'debug', orderData);

        if (!Comfino.isPaywallActive) {
            return;
        }

        if (typeof ComfinoPaywallFrontend === 'undefined') {
            console.warn('ComfinoPaywallFrontend is undefined.');

            return;
        }

        ComfinoPaywallFrontend.logEvent('Comfino.init - initialization started.', 'debug');

        if (!ComfinoPaywallFrontend.isInitialized()) {
            ComfinoPaywallFrontend.logEvent('Paywall frontend initialization.', 'debug', comfinoSettings);

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
        } else {
            // Comfino paywall frontend already initialized - update payment amount if shipping method changed and total order value changed.
            ComfinoPaywallFrontend.logEvent('Paywall frontend already initialized.', 'debug', Comfino.loanParams);

            if (Comfino.loanParams.loanAmount > 0) {
                const cartTotal = parseInt(orderData.cartTotals.total_price);

                if (Comfino.loanParams.loanAmount !== cartTotal) {
                    ComfinoPaywallFrontend.logEvent('Total value changed.', 'debug', Comfino.loanParams.loanAmount, cartTotal);
                    ComfinoPaywallFrontend.reloadPaywall();
                }
            }
        }

        if (!Comfino.isSelected && Array.isArray(orderData.paymentMethods) && orderData.paymentMethods.length === 1) {
            // Workaround for Firefox bug (woocommerce_blocks-checkout-render-checkout-form hook fired with incomplete data).
            ComfinoPaywallFrontend.logEvent('Firefox bug workaround executed.', 'debug', orderData.paymentMethods);

            Comfino.isSelected = (orderData.paymentMethods[0] === 'comfino');
        }
    }
}

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-render-checkout-form', 'comfino', () => {
    const payment = wp.data.select('wc/store/payment');

    ComfinoPaywallFrontend.logEvent(
        'woocommerce_blocks-checkout-render-checkout-form',
        'debug',
        payment.getActivePaymentMethod(),
        payment.getAvailablePaymentMethods(),
        payment.getPaymentMethodData(),
        payment.getState()
    );

    Comfino.isSelected = (payment.getActivePaymentMethod() === 'comfino');
});

wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'comfino', (paymentMethod) => {
    ComfinoPaywallFrontend.logEvent('woocommerce_blocks-checkout-set-active-payment-method', 'debug', paymentMethod);

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
    canMakePayment: (orderData) => {
        ComfinoPaywallFrontend.logEvent('canMakePayment', 'debug', orderData);

        if (document.readyState === 'complete') {
            ComfinoPaywallFrontend.logEvent('document.readyState: complete', 'debug');

            Comfino.init(orderData);

            if (Comfino.isSelected) {
                ComfinoPaywallFrontend.executeClickLogic();
            }
        } else {
            document.addEventListener('readystatechange', () => {
                ComfinoPaywallFrontend.logEvent(`document.readyState: ${document.readyState}`, 'debug');

                if (document.readyState === 'complete') {
                    Comfino.init(orderData);

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
