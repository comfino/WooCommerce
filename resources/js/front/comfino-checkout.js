/* Comfino web frontend SDK — legacy WooCommerce checkout init */
(function () {
    'use strict';

    // Comfino payment method configuration
    const config = window.comfinoSettings || {};

    if (!config.sdkScriptUrl || !config.authToken || document.querySelector('script[data-comfino-sdk]')) {
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

       Why not a dynamic import or async load?  The SDK is a UMD bundle.  When RequireJS's global define()
       is present (e.g. in some WP themes), UMD takes the AMD branch — it calls define() and returns its
       export to RequireJS, but skips the global assignment (window.Comfino.*).

       Solution: hide window.define before the script executes so the SDK's UMD wrapper sees no AMD
       environment, takes the global-assignment branch, and sets window.Comfino.  Restore define() in
       onload/onerror.  By the time the user reaches the payment step, all modules are already defined,
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

        let container = document.getElementById('comfino-paywall-container');

        if (container && !isInVisibleContext(container)) {
            const candidates = document.querySelectorAll('[id="comfino-paywall-container"]');

            container = null;

            for (let i = 0; i < candidates.length; i++) {
                if (isInVisibleContext(candidates[i])) {
                    container = candidates[i];

                    break;
                }
            }
        }

        if (!container) {
            return;
        }

        comfinoPaywallData.container = container;

        window.Comfino.bootstrapPaywall(comfinoPaywallData);
    };
    script.onerror = function () {
        window.define = _amdDefine;
    };

    document.head.appendChild(script);
}());
