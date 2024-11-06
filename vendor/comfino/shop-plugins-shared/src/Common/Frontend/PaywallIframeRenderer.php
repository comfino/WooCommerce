<?php

namespace Comfino\Common\Frontend;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;

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
            $fragments = $this->getPaywallElements($iframeUrl);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }

        return sprintf(
            '<style>%s</style>%s<script>%s</script>',
            $fragments['frontend_style'] ?? '',
            $fragments['iframe'],
            $fragments['frontend_script'] ?? ''
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @param string $iframeUrl
     */
    public function getPaywallElements($iframeUrl): array
    {
        return array_merge([
            'iframe' => sprintf(
                '<iframe id="comfino-paywall-container" src="%s" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, \'%s\', \'%s\')"></iframe>',
                $iframeUrl,
                $this->platformName,
                $this->platformVersion
            )],
            $this->getFrontendFragments(self::PAYWALL_IFRAME_FRAGMENTS)
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getPaywallFrontendStyle(): string
    {
        return $this->getFrontendFragments(self::PAYWALL_IFRAME_FRAGMENTS)['frontend_style'] ?? '';
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getPaywallFrontendScript(): string
    {
        return $this->getFrontendFragments(self::PAYWALL_IFRAME_FRAGMENTS)['frontend_script'] ?? '';
    }

    public function getPaywallFrontendStyleTimestamp(): int
    {
        try {
            return $this->extractCacheTimestamp($this->getPaywallFrontendStyle());
        } catch (InvalidArgumentException|\Throwable $exception) {
            return 0;
        }
    }

    public function getPaywallFrontendScriptTimestamp(): int
    {
        try {
            return $this->extractCacheTimestamp($this->getPaywallFrontendScript());
        } catch (InvalidArgumentException|\Throwable $exception) {
            return 0;
        }
    }

    private function extractCacheTimestamp(string $contents): int
    {
        $matches = [];

        if (preg_match('/\/\*\[cached:(\d+)\]\*\//', $contents, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
