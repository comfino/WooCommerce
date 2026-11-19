/*
 * ComfinoPay product widget loader.
 *
 * The widget config block is part of the (possibly full-page-cached) product page, so the per-visitor values (track ID,
 * logging token) are fetched from an uncached endpoint and merged into the config before the CDN widget script reads it.
 * The widget still loads, without them, when the request fails.
 *
 * Reads two attributes of the config element (#comfino-widget-config):
 *   data-widget-script-url - CDN product widget script
 *   data-session-url - uncached endpoint returning {trackId, loggingToken}
 */
(() => {
    'use strict';

    const configElement = document.getElementById('comfino-widget-config');

    if (!configElement) {
        return;
    }

    const widgetScriptUrl = configElement.getAttribute('data-widget-script-url');
    const sessionUrl = configElement.getAttribute('data-session-url');

    const loadWidget = () => {
        if (!widgetScriptUrl) {
            return;
        }

        const script = document.createElement('script');
        script.src = widgetScriptUrl;
        script.defer = true;

        document.head.appendChild(script);
    };

    if (!sessionUrl) {
        loadWidget();

        return;
    }

    fetch(sessionUrl, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}})
        .then((response) => (response.ok ? response.json() : {}))
        .then((values) => {
            const config = JSON.parse(configElement.textContent);

            ['trackId', 'loggingToken'].forEach((key) => {
                if (values && typeof values[key] === 'string') {
                    config[key] = values[key];
                }
            });

            configElement.textContent = JSON.stringify(config);
        })
        .catch(() => {})
        .then(loadWidget);
})();
