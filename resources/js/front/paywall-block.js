const comfinoSettings = wc.wcSettings.getSetting('comfino_data', {});

window.Comfino = {
    label: wp.htmlEntities.decodeEntities(comfinoSettings.title) || wp.i18n.__('Comfino payments', 'comfino-payment-gateway'),
    isSelected: false,
    loanParams: { loanAmount: 0, loanType: '', loanTerm: 0 },
    shippingMethods: null,
    paywallTemplate: null,
    initialized: false,
    iframeLoaded: false,
    Label: () => {
        ComfinoPaywallFrontend.logEvent('Comfino.Label', 'debug', Comfino.label, comfinoSettings.icon);

        if (comfinoSettings.icon) {
            return wp.element.RawHTML({
                children: Comfino.label + '<img id="comfino-gateway-logo" src="' + comfinoSettings.icon + '" alt="' +
                    Comfino.label + '" style="margin-left: 10px; vertical-align: baseline">'
            });
        }

        return Comfino.label;
    },
    Content: (properties) => {
        const { eventRegistration, emitResponse } = properties;
        const { onPaymentSetup } = eventRegistration;

        if (Comfino.paywallTemplate === null) {
            ComfinoPaywallFrontend.logEvent('Comfino.Content', 'debug', properties);
        }

        // Handle payment setup.
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

        // Initialize paywall in useEffect to ensure DOM is ready.
        wp.element.useEffect(() => {
            if (!Comfino.initialized && typeof ComfinoPaywallFrontend !== 'undefined') {
                ComfinoPaywallFrontend.logEvent('Comfino.Content - initializing paywall frontend.', 'debug');

                // Setup callbacks.
                comfinoSettings.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                    ComfinoPaywallFrontend.logEvent('updateOrderPaymentState WooCommerce (Payment Blocks)', 'debug', loanParams);

                    if (loanParams.loanTerm !== 0) {
                        Comfino.loanParams.loanAmount = loanParams.loanAmount;
                        Comfino.loanParams.loanType = loanParams.loanType;
                        Comfino.loanParams.loanTerm = loanParams.loanTerm;
                    }
                };

                comfinoSettings.paywallOptions.onOfferLoadSuccess = (loanParams) => {
                    ComfinoPaywallFrontend.logEvent('onOfferLoadSuccess WooCommerce (Payment Blocks)', 'debug', loanParams);
                };

                // Monitor iframe load state since lazy loading delays the onload event.
                const monitorIframeLoad = () => {
                    const iframe = document.getElementById('comfino-paywall-container');

                    if (!iframe) {
                        return;
                    }

                    // Check if iframe is visible and not yet loaded.
                    if (iframe.style.display === 'block' && !Comfino.iframeLoaded) {
                        // Check if iframe content is actually loaded.
                        if (iframe.contentWindow && iframe.contentWindow.location) {
                            try {
                                // Try to check if iframe has loaded content.
                                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

                                if (iframeDoc && iframeDoc.readyState === 'complete') {
                                    ComfinoPaywallFrontend.logEvent('Iframe loaded while visible, triggering onload.', 'debug');
                                    ComfinoPaywallFrontend.onload(
                                        iframe,
                                        comfinoSettings.paywallOptions.platformName,
                                        comfinoSettings.paywallOptions.platformVersion
                                    );

                                    Comfino.iframeLoaded = true;

                                    return;
                                }
                            } catch (e) {
                                // Cross-origin iframe, can't access contentDocument. Iframe is loading from external source, attach load listener.
                                ComfinoPaywallFrontend.logEvent('Setting up load listener for cross-origin iframe.', 'debug');

                                iframe.addEventListener('load', function onIframeLoad() {
                                    ComfinoPaywallFrontend.logEvent('Iframe load event fired.', 'debug');

                                    if (!Comfino.iframeLoaded) {
                                        ComfinoPaywallFrontend.onload(
                                            iframe,
                                            comfinoSettings.paywallOptions.platformName,
                                            comfinoSettings.paywallOptions.platformVersion
                                        );
                                    }

                                    iframe.removeEventListener('load', onIframeLoad);
                                }, { once: true });

                                return;
                            }
                        }
                    }

                    // Keep checking until loaded.
                    if (Comfino.isSelected && !Comfino.iframeLoaded) {
                        setTimeout(monitorIframeLoad, 100);
                    }
                };

                // Start monitoring when Comfino is selected.
                Comfino.startLoadMonitor = monitorIframeLoad;

                // Wait for iframe to be in DOM.
                const checkAndInitialize = () => {
                    const iframe = document.getElementById('comfino-paywall-container');

                    if (iframe !== null) {
                        ComfinoPaywallFrontend.logEvent('Paywall iframe found, initializing...', 'debug', iframe);
                        ComfinoPaywallFrontend.init(null, iframe, comfinoSettings.paywallOptions);

                        Comfino.initialized = true;

                        // If already selected, make iframe visible and execute click logic.
                        if (Comfino.isSelected) {
                            ComfinoPaywallFrontend.logEvent('Comfino already selected, making iframe visible.', 'debug');
                            iframe.style.display = 'block';
                            ComfinoPaywallFrontend.executeClickLogic();
                        }
                    } else {
                        // Retry after a short delay.
                        setTimeout(checkAndInitialize, 50);
                    }
                };

                checkAndInitialize();
                monitorIframeLoad();
            }
        }, []);

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
     * Updates paywall when cart total changes (shipping method, etc.).
     *
     * orderData:
     * {
     *     cart: Cart,
     *     cartTotals: CartTotals,
     *     cartNeedsShipping: boolean,
     *     shippingAddress: CartShippingAddress,
     *     billingAddress: CartBillingAddress,
     *     selectedShippingMethods: Record,
     *     paymentRequirements: string[]
     * }
     *
     * @param {Object} orderData
     */
    updatePaywallIfNeeded: (orderData) => {
        if (!ComfinoPaywallFrontend.isInitialized() || !orderData.cartTotals) {
            return;
        }

        if (Comfino.loanParams.loanAmount > 0) {
            const cartTotal = parseInt(orderData.cartTotals.total_price);

            if (Comfino.loanParams.loanAmount !== cartTotal) {
                ComfinoPaywallFrontend.logEvent('Total value changed, reloading paywall.', 'debug', Comfino.loanParams.loanAmount, cartTotal);
                ComfinoPaywallFrontend.reloadPaywall();
            }
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

    if (paymentMethod.paymentMethodSlug === 'comfino') {
        Comfino.isSelected = true

        /* Wait for iframe to appear in DOM (React needs time to re-render Content component).
           This is necessary because the hook fires before React re-renders the payment method content. */
        const makeIframeVisible = () => {
            const iframe = document.getElementById('comfino-paywall-container');

            if (iframe) {
                ComfinoPaywallFrontend.logEvent('Making iframe visible (payment method selected).', 'debug');

                iframe.style.display = 'block';

                if (ComfinoPaywallFrontend.isInitialized()) {
                    /* Re-initialize to update the iframe reference in case React recreated it.
                       This prevents "Cannot read properties of null (reading 'postMessage')" errors
                       when switching between payment methods. */
                    Comfino.iframeLoaded = false;

                    ComfinoPaywallFrontend.init(null, iframe, comfinoSettings.paywallOptions);
                    ComfinoPaywallFrontend.executeClickLogic();

                    // Start monitoring for lazy-loaded iframe.
                    if (Comfino.startLoadMonitor) {
                        Comfino.startLoadMonitor();
                    }
                }
            } else {
                // Retry after a short delay to allow React to re-render.
                setTimeout(makeIframeVisible, 50);
            }
        };

        makeIframeVisible();
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

        // Check if shipping method changed and update paywall if needed.
        if (Comfino.shippingMethods !== null &&
            Comfino.shippingMethods.toString() !== Object.values(orderData.selectedShippingMethods).toString()
        ) {
            ComfinoPaywallFrontend.logEvent('Shipping method changed, updating paywall', 'debug');
            Comfino.updatePaywallIfNeeded(orderData);
        }

        Comfino.shippingMethods = Object.values(orderData.selectedShippingMethods);

        return true;
    },
    ariaLabel: Comfino.label,
    supports: {
        features: comfinoSettings.supports
    }
});
