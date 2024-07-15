<?php

namespace Comfino\Common\Frontend;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;

final class PaywallIframeRenderer extends FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    /**
     * @readonly
     * @var string
     */
    private $platformName;
    /**
     * @readonly
     * @var string
     */
    private $platformVersion;
    private const PAYWALL_IFRAME_FRAGMENTS = ['frontend_style', 'frontend_script'];

    public function __construct(Client $client, TaggableCacheItemPoolInterface $cache, RendererStrategyInterface $rendererStrategy, string $platformName, string $platformVersion, ?string $cacheInvalidateUrl = null, ?string $configurationUrl = null)
    {
        $this->rendererStrategy = $rendererStrategy;
        $this->platformName = $platformName;
        $this->platformVersion = $platformVersion;
        parent::__construct($client, $cache, $cacheInvalidateUrl, $configurationUrl);
    }

    /**
     * @param string $iframeUrl
     */
    public function renderPaywallIframe($iframeUrl): string
    {
        try {
            $fragments = $this->getFrontendFragments(self::PAYWALL_IFRAME_FRAGMENTS);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }

        return sprintf(
            '<style>%s</style><iframe id="comfino-paywall-container" src="%s" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, \'%s\', \'%s\')"></iframe><script>%s</script>',
            $fragments['frontend_style'] ?? '',
            $iframeUrl,
            $this->platformName,
            $this->platformVersion,
            $fragments['frontend_script'] ?? ''
        );
    }
}
