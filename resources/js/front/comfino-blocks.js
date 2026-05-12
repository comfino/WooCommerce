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

            // allowedProductTypes: null = no filter active, [] = all filtered (don't load SDK),
            // [...] = filtered subset to pass to bootstrapPaywall().
            const allowedProductTypes = config.productTypes;
            const allowedProductsConfig = config.allowedProductsConfig;

            if (Array.isArray(allowedProductTypes) && allowedProductTypes.length === 0) {
                // All product types filtered out for this cart — don't load the paywall.
                return;
            }

            // Comfino paywall bootstrap options
            const comfinoPaywallData = {
                authToken: config.authToken,
                loanAmount: config.loanAmount,
                environment: config.environment,
                platform: 'woocommerce'
            };

            if (Array.isArray(allowedProductTypes) && allowedProductTypes.length > 0) {
                // Set allowed financial product types for paywall if provided.
                comfinoPaywallData.productTypes = allowedProductTypes;
            }

            if (Array.isArray(allowedProductsConfig) && allowedProductsConfig.length > 0) {
                comfinoPaywallData.allowedProductsConfig = allowedProductsConfig;
            }

            if (config.paywallSettings && typeof config.paywallSettings === 'object') {
                comfinoPaywallData.paywallSettings = config.paywallSettings;
            }

            if (config.directRedirect) {
                comfinoPaywallData.directRedirect = true;
            }

            if (config.customPaywallCss) {
                comfinoPaywallData.customPaywallCss = config.customPaywallCss;
            }

            /* Load Comfino web frontend SDK as a plain script via DOM injection.

               Why not a dynamic import or async load? The SDK is a UMD bundle. When RequireJS's global define()
               is present (e.g. in some WP themes), UMD takes the AMD branch — it calls define() and returns its
               export to RequireJS, but skips the global assignment (window.Comfino.*).

               Solution: hide window.define before the script executes so the SDK's UMD wrapper sees no AMD
               environment, takes the global-assignment branch, and sets window.Comfino. Restore define() in
               onload/onerror. By the time the user reaches the payment step, all modules are already defined,
               so the brief window where define is hidden is safe.

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
}());
