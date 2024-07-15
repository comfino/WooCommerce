<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<iframe id="comfino-paywall-container" src="<?php /** @var string $paywall_api_url */ echo $paywall_api_url ?>" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, '<?php /** @var array $paywall_options */ echo $paywall_options['platformName']; ?>', '<?php echo $paywall_options['platformVersion']; ?>')"></iframe>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<script>
    if (ComfinoPaywallFrontend.isInitialized()) {
        Comfino.init();
    } else {
        window.Comfino = {
            paywallOptions: '<?php echo json_encode($paywall_options); ?>',
            init: () => {
                ComfinoPaywallFrontend.init(
                    document.getElementById('payment_method_comfino'),
                    document.getElementById('comfino-paywall-container'),
                    Comfino.paywallOptions
                );
            }
        }

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
