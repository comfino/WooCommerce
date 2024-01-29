'use strict';

window.Comfino = {
    options: null,
    initialized: false,

    init(frontendScriptURL) {
        if (Comfino.initialized && typeof ComfinoFrontendRenderer !== 'undefined') {
            ComfinoFrontendRenderer.init(Comfino.options);

            return;
        }

        let script = document.createElement('script');

        script.onload = () => ComfinoFrontendRenderer.init(Comfino.options);
        script.src = frontendScriptURL;
        script.async = true;

        document.getElementsByTagName('head')[0].appendChild(script);

        Comfino.initialized = true;
    }
}
