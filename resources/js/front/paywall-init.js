window.ComfinoPaywall = {
    init: (paywallOptions) => {
        /* DOM manipulation */

        const stylesMap = new Map(), scriptsMap = new Map();

        /**
         * @param {string} cssLink
         * @param {onLoadCallback} onLoadCallback
         * @callback onLoadCallback
         * @returns {void}
         */
        function attachStyleLink(cssLink, onLoadCallback)
        {
            if (stylesMap.has(cssLink)) {
                onLoadCallback();

                return;
            }

            let link = document.createElement('link');

            link.onload = onLoadCallback;
            link.rel = 'stylesheet';
            link.href = cssLink;

            link.setAttribute('data-cmp-ab', '2');

            document.getElementsByTagName('head')[0].appendChild(link);

            stylesMap.set(cssLink, link);
        }

        /**
         * @param {string} jsLink
         * @param {boolean} async
         * @param {onLoadCallback} onLoadCallback
         * @callback onLoadCallback
         * @returns {void}
         */
        function attachScriptLink(jsLink, async, onLoadCallback)
        {
            if (scriptsMap.has(jsLink)) {
                onLoadCallback();

                return;
            }

            let script = document.createElement('script');

            script.onload = onLoadCallback;
            script.src = jsLink;
            script.async = async;

            script.setAttribute('data-cmp-ab', '2');

            document.getElementsByTagName('head')[0].appendChild(script);

            scriptsMap.set(jsLink, script);
        }

        window.Comfino = {
            paywallOptions: paywallOptions,
            init: () => {
                let iframe = document.getElementById('comfino-paywall-container');
                let frontendInitElement = document.getElementById('payment_method_comfino');

                if ('priceModifier' in frontendInitElement.dataset) {
                    let priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

                    if (!Number.isNaN(priceModifier)) {
                        iframe.src += ('&priceModifier=' + priceModifier);
                    }
                }

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
