window.ComfinoPaywallInit = {
    init: (paywallUrl, paywallOptions) => {
        window.Comfino = {
            paywallUrl: paywallUrl,
            paywallOptions: paywallOptions,
            init: () => {
                const iframe = ComfinoPaywallFrontend.createPaywallIframe(Comfino.paywallUrl, Comfino.paywallOptions);
                const frontendInitElement = document.getElementById('payment_method_comfino');

                if ('priceModifier' in frontendInitElement.dataset) {
                    const priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

                    if (!Number.isNaN(priceModifier)) {
                        iframe.src += ('&priceModifier=' + priceModifier);
                    }
                }

                document.getElementById('comfino-iframe-container').appendChild(iframe);

                ComfinoPaywallFrontend.init(frontendInitElement, iframe, Comfino.paywallOptions);
            },
            setup: () => {
                if (ComfinoPaywallFrontend.isInitialized()) {
                    Comfino.init();
                } else {
                    Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                        ComfinoPaywallFrontend.logEvent('updateOrderPaymentState WooCommerce', 'debug', loanParams);

                        if (loanParams.loanTerm !== 0) {
                            document.getElementById('comfino-loan-amount').value = loanParams.loanAmount;
                            document.getElementById('comfino-loan-type').value = loanParams.loanType;
                            document.getElementById('comfino-loan-term').value = loanParams.loanTerm;
                        }
                    }

                    Comfino.init();
                }
            }
        }

        if (document.readyState === 'complete') {
            Comfino.setup();
        } else {
            document.addEventListener('readystatechange', () => {
                if (document.readyState === 'complete') {
                    Comfino.setup();
                }
            });
        }
    }
}
