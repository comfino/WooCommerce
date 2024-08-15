<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $paywall_api_url */
/** @var array $paywall_options */
?>
<iframe id="comfino-paywall-container" src="<?php echo $paywall_api_url ?>" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, '<?php echo $paywall_options['platformName']; ?>', '<?php echo $paywall_options['platformVersion']; ?>')"></iframe>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<script>
    window.Comfino = {
        paywallOptions: '<?php echo json_encode($paywall_options); ?>',
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
        }
    }

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

        if (document.readyState === 'complete') {
            Comfino.init();
        } else {
            document.addEventListener('readystatechange', () => {
                if (document.readyState === 'complete') {
                    Comfino.init();
                }
            });
        }
    }
</script>';
