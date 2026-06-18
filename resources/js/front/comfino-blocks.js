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
       stay in the SDK; the wrapper here resolves the SDK lazily — useEffect can run before the SDK script is
       loaded (Comfino not yet selected when checkout mounts). Prefer the cached promise on window over reading
       window.Comfino directly so the ESM branch (which doesn't populate the global) still resolves. */
    function resolveSdk() {
        if (window.Comfino && typeof window.Comfino.WooCommercePaywallController === 'function') {
            return Promise.resolve(window.Comfino);
        }

        if (window.__comfinoSdkPromise) {
            return window.__comfinoSdkPromise;
        }

        return Promise.resolve(null);
    }

    function ComfinoContent(props) {
        const eventRegistration = props.eventRegistration;
        const emitResponse = props.emitResponse;

        useEffect(function () {
            return eventRegistration.onPaymentSetup(function () {
                /* Resolve the SDK at fire-time. By the time onPaymentSetup runs (i.e., the user pressed
                   "Place order" with Comfino selected), the SDK script has been injected. If for any reason it
                   isn't, fall back to a SUCCESS response with empty payment-method data — WooCommerce will then
                   reject server-side via the API rather than crashing the checkout. */
                return resolveSdk().then(function (sdk) {
                    if (sdk && typeof sdk.WooCommercePaywallController === 'function') {
                        return sdk.WooCommercePaywallController.createBlocksPaymentSetupHandler(emitResponse)();
                    }

                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {paymentMethodData: {comfino_loan_type: '', comfino_loan_term: '0'}}
                    };
                });
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

    /* WooCommerce Blocks requires `label` and `ariaLabel` on the registered payment method. The SDK's
       DefaultPaymentMethodItemRenderer stamps the Comfino logo over this tile, so the literal strings here
       only matter as accessibility fallback before the SDK boots — no need to plumb them through PHP. */
    const ComfinoPaymentContent = {
        name: 'comfino',
        label: 'Comfino',
        content: createElement(ComfinoContent),
        edit: createElement('div', null, 'Comfino'),
        canMakePayment: () => true,
        ariaLabel: 'Comfino payment method',
        supports: {features: config.supports || []},
        paymentMethodId: 'comfino',
        savedTokenComponent: null,
    };

    registerPaymentMethod(ComfinoPaymentContent);

    /* Load the Comfino web frontend SDK. Two code paths based on config.sdkScriptKind:

       - 'umd' (default today): the bundle is loaded as a classic <script>. When RequireJS's global define()
         is present (some WP themes ship it), the UMD wrapper would take the AMD branch and never populate
         window.Comfino. We temporarily clear window.define for the duration of the script load to force
         the global-assignment branch, and restore it in both onload and onerror.
       - 'module': the bundle is loaded as <script type="module">. ESM does not consult window.define, so
         the clear-and-restore dance is skipped entirely. The SDK is resolved from the returned reference,
         not from a global — once UMD is retired we can drop the window.Comfino fallback.

       Pass the resolved SDK reference through instead of relying on window.Comfino. The current UMD build
       still populates the global, so reading from window.Comfino remains a valid fallback for the 'umd'
       branch. */
    function loadSdk(cfg)
    {
        if (window.Comfino && typeof window.Comfino.bootstrapPaywall === 'function') {
            return Promise.resolve(window.Comfino);
        }

        if (window.__comfinoSdkPromise) {
            return window.__comfinoSdkPromise;
        }

        const kind = cfg.sdkScriptKind === 'module' ? 'module' : 'umd';
        const url = kind === 'module' ? (cfg.sdkScriptUrlEsm || cfg.sdkScriptUrl) : cfg.sdkScriptUrl;

        window.__comfinoSdkPromise = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = url;
            script.setAttribute('data-comfino-sdk', '1');

            if (cfg.scriptNonce) {
                script.setAttribute('nonce', cfg.scriptNonce);
            }

            if (kind === 'module') {
                script.type = 'module';
                script.onload = function () { resolve(window.Comfino); };
                script.onerror = function (error) {
                    window.__comfinoSdkPromise = null;

                    reject(error);
                };
            } else {
                const savedDefine = window.define;
                window.define = undefined;

                script.onload = function () {
                    window.define = savedDefine;

                    resolve(window.Comfino);
                };
                script.onerror = function (error) {
                    window.define = savedDefine;
                    window.__comfinoSdkPromise = null;

                    reject(error);
                };
            }

            document.head.appendChild(script);
        });

        return window.__comfinoSdkPromise;
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
       survive end-to-end because the paywall renderer uses it literally).

       Bootstrap happens on page load (not gated on first Comfino activation): the SDK's DefaultPaymentMethodItemRenderer
       stamps the Comfino logo + class hooks onto the per-method tile during render(), and that has to happen before
       the shopper clicks Comfino — otherwise the tile shows as "Comfino" text without a logo until selection. The
       SDK adapter's subscribePaymentMethodSelection takes over from there: activate() lazily creates the iframe on
       first selection (the React-managed `<div id="comfino-paywall-container">` only mounts inside the accordion
       content when Comfino is active, so iframe creation can't happen until then anyway — PaywallManager.activate()
       waits for the container via MutationObserver). */
    const comfinoPaywallData = {
        authToken: config.authToken,
        loggingToken: config.loggingToken || '',
        trackId: config.trackId || '',
        loanAmount: config.loanAmount,
        platform: 'woocommerce',
        environment: config.environment,
        wcBlocksActive: true,
        productTypes: config.productTypes,
        productTypeNames: config.productTypeNames,
        cart: config.cart,
        paywallSettings: config.paywallSettings,
        shopEnvironment: config.shopEnvironment,
        directRedirect: config.directRedirect,
        creditors: config.creditors,
        allowedProductsConfig: config.allowedProductsConfig,
        paymentMethodItem: { auth: config.paymentMethodAuth || '' }
    };

    loadSdk(config).then(function (sdk) {
        if (sdk && typeof sdk.bootstrapPaywall === 'function') {
            sdk.bootstrapPaywall(comfinoPaywallData);
        }
    }).catch(function () {
        /* Script load failed — leave the checkout unaffected. */
    });

    /* Cart-total refresh for the Blocks store is owned by the SDK's WooCommercePaywallController when
       `wcBlocksActive: true` is set on the bootstrap data — it subscribes to wc-blocks-data CART_STORE_KEY and
       drives paywall reloads. Plugin-side cart handling stays out of the way. */
}());
