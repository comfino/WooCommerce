/* Comfino web frontend SDK — legacy WooCommerce checkout init */
(function () {
    'use strict';

    // Comfino payment method configuration
    const config = window.comfinoSettings || {};

    if (!config.sdkScriptUrl || !config.authToken || document.querySelector('script[data-comfino-sdk]')) {
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
        directRedirect: config.directRedirect,
        creditors: config.creditors,
        allowedProductsConfig: config.allowedProductsConfig
    };

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

    /* After WooCommerce refreshes the checkout on cart/shipping changes, reload the paywall with
       the updated cart total sourced from the refreshed #comfino-loan-amount fragment. */
    document.body.addEventListener('updated_checkout', function () {
        const totalEl = document.getElementById('comfino-loan-amount');

        if (totalEl && window.ComfinoPaywallInit) {
            const newTotal = parseInt(totalEl.value, 10);

            if (!isNaN(newTotal) && newTotal > 0) {
                window.ComfinoPaywallInit.reload(newTotal);
            }
        }
    });
}());
