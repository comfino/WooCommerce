/* Comfino web frontend SDK — WooCommerce Blocks checkout init */
(function () {
    'use strict';

    const registerPaymentMethod = window.wc && window.wc.wcBlocksRegistry
        ? window.wc.wcBlocksRegistry.registerPaymentMethod
        : null;

    if (!registerPaymentMethod) {
        return;
    }

    const createElement = window.wp.element.createElement;

    // Comfino payment method configuration
    const config = window.wc.wcSettings.getSetting('comfino_data', {});

    const ComfinoPaymentContent = {
        name: 'comfino',
        label: config.label || 'Comfino',
        content: createElement(
            window.wp.element.Fragment,
            null,
            createElement('input', {id: 'comfino-loan-type', name: 'comfino_loan_type', type: 'hidden', value: ''}),
            createElement('input', {id: 'comfino-loan-term', name: 'comfino_loan_term', type: 'hidden', value: ''}),
            createElement('div', {id: 'comfino-paywall-container'})
        ),
        edit: createElement('div', null, 'Comfino'),
        canMakePayment: function () {
            return true;
        },
        ariaLabel: config.ariaLabel || 'Comfino payment method',
        supports: {features: config.supports || []},
        paymentMethodId: 'comfino',
        savedTokenComponent: null,
    };

    registerPaymentMethod(ComfinoPaymentContent);

    // Watch for payment method selection.
    if (window.wc && window.wc.wcBlocksData && window.wc.wcBlocksData.PAYMENT_STORE_KEY) {
        const select = window.wp.data.select(window.wc.wcBlocksData.PAYMENT_STORE_KEY);

        window.wp.data.subscribe(function () {
            if (select.getActivePaymentMethod() !== 'comfino') {
                return;
            }

            // SDK already injected — re-trigger init for React re-renders that recreate the paywall container.
            if (document.querySelector('script[data-comfino-sdk]')) {
                if (window.ComfinoPaywallInit) {
                    window.ComfinoPaywallInit.init();
                }

                return;
            }

            if (!config.sdkScriptUrl || !config.authToken) {
                return;
            }

            // productTypes: null = no filter active, [] = all filtered, [...] = filtered subset to pass to bootstrapPaywall().
            if (Array.isArray(config.productTypes) && config.productTypes.length === 0) {
                // All product types filtered out for this cart — don't load the paywall SDK.
                return;
            }

            /* All paywall bootstrap options assigned directly from comfino_data — wcSettings (wp_json_encode)
               preserves scalar types and the insertion order of associative arrays (creditors map ordering MUST
               survive end-to-end because the paywall renderer uses it literally). */
            const comfinoPaywallData = {
                authToken: config.authToken,
                loanAmount: config.loanAmount,
                platform: 'woocommerce',
                environment: config.environment,
                productTypes: config.productTypes,
                cart: config.cart,
                paywallSettings: config.paywallSettings,
                directRedirect: config.directRedirect,
                creditors: config.creditors,
                allowedProductsConfig: config.allowedProductsConfig
            };

            /* Load Comfino web frontend SDK as a plain script via DOM injection.

               Why not a dynamic import or async load? The SDK is a UMD bundle. When RequireJS's global define() is
               present (e.g. in some WP themes), UMD takes the AMD branch — it calls define() and returns its export
               to RequireJS, but skips the global assignment (window.Comfino.*).

               Solution: hide window.define before the script executes so the SDK's UMD wrapper sees no AMD environment,
               takes the global-assignment branch, and sets window.Comfino. Restore define() in onload/onerror. By the
               time the user reaches the payment step, all modules are already defined, so the brief window where define
               is hidden is safe.

               Pass data directly to bootstrapPaywall() in onload — no intermediate global state used. */

            // Preserve original window.define for later restoration.
            const _amdDefine = window.define;

            /* Temporarily hide window.define so the SDK's UMD bundle takes the global-assignment branch and
               exposes window.Comfino. */
            window.define = undefined;

            // Construct SDK script element and append to DOM.
            const script = document.createElement('script');
            script.src = config.sdkScriptUrl;
            script.setAttribute('data-comfino-sdk', '1');

            if (config.scriptNonce) {
                script.setAttribute('nonce', config.scriptNonce);
            }

            script.onload = function () {
                window.define = _amdDefine;
                window.Comfino.bootstrapPaywall(comfinoPaywallData);
            };
            script.onerror = function () {
                window.define = _amdDefine;
            };

            document.head.appendChild(script);
        });
    }

    // Watch cart total changes in the Blocks store and reload the paywall with the updated amount.
    if (window.wc && window.wc.wcBlocksData && window.wc.wcBlocksData.CART_STORE_KEY) {
        const cartSelect = window.wp.data.select(window.wc.wcBlocksData.CART_STORE_KEY);
        let lastCartTotal = null;

        window.wp.data.subscribe(function () {
            const totals = cartSelect.getCartTotals();

            if (!totals || totals.total_price === lastCartTotal) {
                return;
            }

            lastCartTotal = totals.total_price;

            if (window.ComfinoPaywallInit) {
                const newTotal = parseInt(totals.total_price, 10);

                if (!isNaN(newTotal) && newTotal > 0) {
                    window.ComfinoPaywallInit.reload(newTotal);
                }
            }
        });
    }
}());
