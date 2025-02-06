window.ComfinoPaywallInit = {
    init: () => {
        const iframeContainer = document.getElementById('comfino-iframe-container');

        if (iframeContainer.querySelector('#comfino-paywall-container') !== null) {
            ComfinoPaywallFrontend.logEvent('Comfino paywall iframe already initialized.', 'info', iframeContainer);

            return;
        }

        ComfinoPaywallData.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent('updateOrderPaymentState WooCommerce', 'debug', loanParams);

            if (loanParams.loanTerm !== 0) {
                document.getElementById('comfino-loan-amount').value = loanParams.loanAmount;
                document.getElementById('comfino-loan-type').value = loanParams.loanType;
                document.getElementById('comfino-loan-term').value = loanParams.loanTerm;
            }
        }

        const iframe = ComfinoPaywallFrontend.createPaywallIframe(ComfinoPaywallData.paywallUrl, ComfinoPaywallData.paywallOptions);
        const frontendInitElement = document.getElementById('payment_method_comfino');

        if ('priceModifier' in frontendInitElement.dataset) {
            const priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

            if (!Number.isNaN(priceModifier)) {
                iframe.src += ('&priceModifier=' + priceModifier);
            }
        }

        iframeContainer.appendChild(iframe);

        ComfinoPaywallFrontend.init(frontendInitElement, iframe, ComfinoPaywallData.paywallOptions);
    }
}

if (document.readyState === 'complete') {
    ComfinoPaywallInit.init();
} else {
    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            ComfinoPaywallInit.init();
        }
    });
}
