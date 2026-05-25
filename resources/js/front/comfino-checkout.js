/* Comfino web frontend SDK — legacy WooCommerce checkout init */
(function () {
    'use strict';

    // Comfino payment method configuration
    const config = window.comfinoSettings || {};

    if (!config.sdkScriptUrl || !config.authToken) {
        return;
    }

    // productTypes: null = no filter active, [] = all filtered, [...] = filtered subset to pass to bootstrapPaywall().
    if (Array.isArray(config.productTypes) && config.productTypes.length === 0) {
        // All product types filtered out for this cart — don't load the paywall SDK.
        return;
    }

    /* All paywall bootstrap options assigned directly from comfinoSettings — wp_add_inline_script + wp_json_encode
       preserves scalar types and the insertion order of associative arrays (creditors map ordering MUST survive
       end-to-end because the paywall renderer uses it literally). */
    const comfinoPaywallData = {
        authToken: config.authToken,
        loanAmount: config.loanAmount,
        platform: 'woocommerce',
        environment: config.environment,
        productTypes: config.productTypes,
        cart: config.cart,
        paywallSettings: config.paywallSettings,
        shopEnvironment: config.shopEnvironment,
        directRedirect: config.directRedirect,
        creditors: config.creditors,
        allowedProductsConfig: config.allowedProductsConfig
    };

    /* Resolve visible paywall container — guards against Elementor rendering a hidden duplicate of the checkout
       (including payment_fields()) in a builder-preview wrapper. */
    function isInVisibleContext(element)
    {
        let node = element;

        while (node && node !== document.body) {
            const computedStyle = window.getComputedStyle(node);

            if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden') {
                return false;
            }

            node = node.parentElement;
        }

        return true;
    }

    function resolvePaywallContainer()
    {
        const candidates = document.querySelectorAll('[id="comfino-paywall-container"]');

        /* Single container — return it regardless of current visibility. The payment_box on a WooCommerce checkout is
           display:none until its radio is selected; the SDK creates the iframe inside the hidden container, and it
           becomes visible as soon as the shopper picks Comfino. Filtering by visibility here would silently abort
           bootstrap when another payment method is the default. */
        if (candidates.length <= 1) {
            return candidates[0] || null;
        }

        /* Multiple containers — typical of Elementor's builder-preview rendering a duplicate hidden checkout. Pick the
           one in a visible ancestor chain so the visible checkout drives the paywall. */
        for (let i = 0; i < candidates.length; i++) {
            if (isInVisibleContext(candidates[i])) {
                return candidates[i];
            }
        }

        return null;
    }

    /* Load the Comfino web frontend SDK. Two code paths based on config.sdkScriptKind:

       - 'umd' (default today): the bundle is loaded as a classic <script>. When RequireJS's global define() is
         present (some WP themes ship it), the UMD wrapper would take the AMD branch and never populate
         window.Comfino. We temporarily clear window.define for the duration of the script load to force the
         global-assignment branch, and restore it in both onload and onerror.
       - 'module': the bundle is loaded as <script type="module">. ESM does not consult window.define, so the
         clear-and-restore dance is skipped entirely. The SDK is resolved from the returned reference, not from
         a global — once UMD is retired we can drop the window.Comfino fallback.

       Pass the resolved SDK reference through instead of relying on window.Comfino. The current UMD build still
       populates the global, so reading from window.Comfino remains a valid fallback for the 'umd' branch. */
    function loadSdk(cfg)
    {
        // Idempotency guard — bypass injection if a previous mount already resolved the SDK on this page.
        if (window.Comfino && typeof window.Comfino.bootstrapPaywall === 'function') {
            return Promise.resolve(window.Comfino);
        }

        if (window.__comfinoSdkPromise) {
            return window.__comfinoSdkPromise;
        }

        const kind = cfg.sdkScriptKind === 'module' ? 'module' : 'umd';
        const url = kind === 'module' ? (cfg.sdkScriptUrlEsm || cfg.sdkScriptUrl) : cfg.sdkScriptUrl;

        window.__comfinoSdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.setAttribute('data-comfino-sdk', '1');

            if (cfg.scriptNonce) {
                script.setAttribute('nonce', cfg.scriptNonce);
            }

            if (kind === 'module') {
                script.type = 'module';
                script.onload = () => resolve(window.Comfino);
                script.onerror = (e) => {
                    window.__comfinoSdkPromise = null;
                    reject(e);
                };
            } else {
                /* UMD branch — scope the define-clear to this single script load. Restore window.define on
                   both load AND error so a failed script tag never leaves AMD-aware modules broken. */
                const savedDefine = window.define;
                window.define = undefined;

                script.onload = () => {
                    window.define = savedDefine;
                    resolve(window.Comfino);
                };
                script.onerror = (e) => {
                    window.define = savedDefine;
                    window.__comfinoSdkPromise = null;
                    reject(e);
                };
            }

            document.head.appendChild(script);
        });

        return window.__comfinoSdkPromise;
    }

    loadSdk(config).then((sdk) => {
        if (!sdk || typeof sdk.bootstrapPaywall !== 'function') {
            return;
        }

        const container = resolvePaywallContainer();

        if (!container) {
            return;
        }

        comfinoPaywallData.container = container;

        sdk.bootstrapPaywall(comfinoPaywallData);
    }).catch(() => {
        /* Script-load failed — leave the checkout unaffected (Comfino tile won't render). The shop's
           server-side checkout will still place the order via other payment methods. */
    });

    /* Cart-refresh on `updated_checkout` is owned by the SDK's WooCommercePaywallController — it reads the
       server-authoritative #comfino-loan-amount fragment and drives the paywall reload. Plugin-side cart
       handling stays out of the way. */
}());