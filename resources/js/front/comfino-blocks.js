/* Comfino web frontend SDK — WooCommerce Blocks checkout init */
(function () {
    'use strict';

    // Resolve the Blocks payment-method registration API; bail if absent (this page is not a Blocks checkout).
    const registerPaymentMethod = window.wc && window.wc.wcBlocksRegistry
        ? window.wc.wcBlocksRegistry.registerPaymentMethod
        : null;

    if (!registerPaymentMethod) {
        return;
    }

    const { createElement, Fragment, useEffect } = window.wp.element;

    // Comfino payment method configuration
    const config = window.wc.wcSettings.getSetting('comfino_data', {});

    /* Content component for Blocks checkout. Registers onPaymentSetup so the selected loanType/loanTerm — written
       into the hidden inputs by the SDK on every UPDATE_PAYMENT_STATE — are forwarded as paymentMethodData in the
       /wc/store/checkout REST request, which is what populates $_POST for process_payment(). The handler body is
       built by WooCommercePaywallController.createBlocksPaymentSetupHandler so input IDs and the response envelope
       stay in the SDK; the wrapper here resolves window.Comfino lazily because useEffect can run before the SDK
       script is loaded (Comfino not yet selected when checkout mounts). */
    function ComfinoContent(props) {
        const eventRegistration = props.eventRegistration;
        const emitResponse = props.emitResponse;

        useEffect(function () {
            return eventRegistration.onPaymentSetup(function () {
                /* Resolve the SDK helper at fire-time. By the time onPaymentSetup runs (i.e., the user pressed
                   "Place order" with Comfino selected), the SDK script has been injected and Comfino is on window.
                   If for any reason it isn't, fall back to a SUCCESS response with empty payment-method data —
                   WooCommerce will then reject server-side via the API rather than crashing the checkout. */
                if (window.Comfino && typeof window.Comfino.WooCommercePaywallController === 'function') {
                    return window.Comfino.WooCommercePaywallController.createBlocksPaymentSetupHandler(emitResponse)();
                }

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {paymentMethodData: {comfino_loan_type: '', comfino_loan_term: '0'}}
                };
            });
        }, [eventRegistration.onPaymentSetup, emitResponse.responseTypes.SUCCESS]);

        return createElement(
            Fragment,
            null,
            createElement('input', {id: 'comfino-loan-type', name: 'comfino_loan_type', type: 'hidden', defaultValue: ''}),
            createElement('input', {id: 'comfino-loan-term', name: 'comfino_loan_term', type: 'hidden', defaultValue: ''}),
            createElement('div', {id: 'comfino-paywall-container'})
        );
    }

    const ComfinoPaymentContent = {
        name: 'comfino',
        label: config.label || 'Comfino',
        content: createElement(ComfinoContent),
        edit: createElement('div', null, 'Comfino'),
        canMakePayment: () => true,
        ariaLabel: config.ariaLabel || 'Comfino payment method',
        supports: {features: config.supports || []},
        paymentMethodId: 'comfino',
        savedTokenComponent: null,
    };

    registerPaymentMethod(ComfinoPaymentContent);

    // Watch for payment method selection.
    if (window.wc && window.wc.wcBlocksData && window.wc.wcBlocksData.PAYMENT_STORE_KEY) {
        const select = window.wp.data.select(window.wc.wcBlocksData.PAYMENT_STORE_KEY);

        /* Bootstrap the SDK the first time Comfino becomes active. Container re-renders are reconciled by the
           SDK's MutationObserver (BasePaywallController.startSpaObserver), which compares container identity and
           also works in direct-redirect mode where there is no iframe. */
        window.wp.data.subscribe(function () {
            if (select.getActivePaymentMethod() !== 'comfino') {
                return;
            }

            if (document.querySelector('script[data-comfino-sdk]')) {
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
                wcBlocksActive: true,
                productTypes: config.productTypes,
                cart: config.cart,
                paywallSettings: config.paywallSettings,
                shopEnvironment: config.shopEnvironment,
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

    /* Cart-total refresh for the Blocks store is owned by the SDK's WooCommercePaywallController when
       `wcBlocksActive: true` is set on the bootstrap data — it subscribes to wc-blocks-data CART_STORE_KEY and
       drives paywall reloads. Plugin-side cart handling stays out of the way. */
}());
