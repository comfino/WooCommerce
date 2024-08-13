<?php

namespace Comfino\Common\Frontend;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;

final class PaywallRenderer extends FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    private const PAYWALL_FRAGMENTS = ['template', 'style', 'script'];

    public function __construct(
        Client $client,
        TaggableCacheItemPoolInterface $cache,
        RendererStrategyInterface $rendererStrategy,
        ?string $cacheInvalidateUrl = null,
        ?string $configurationUrl = null
    ) {
        $this->rendererStrategy = $rendererStrategy;
        parent::__construct($client, $cache, $cacheInvalidateUrl, $configurationUrl);
    }

    /**
     * @param \Comfino\Api\Dto\Payment\LoanQueryCriteria $queryCriteria
     */
    public function renderPaywall($queryCriteria): string
    {
        try {
            $fragments = $this->getFrontendFragments(self::PAYWALL_FRAGMENTS);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }

        try {
            $paywallResponse = $this->client->getPaywall($queryCriteria, new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST));
            $paywallProductsList = $paywallResponse->paywallPage;
            $fragmentsCacheMTime = [];

            if ($paywallResponse->hasHeader('Cache-MTime') && ($fragmentsCacheMTime = json_decode($paywallResponse->getHeader('Cache-MTime'), true)) === null) {
                $fragmentsCacheMTime = [];
            }

            if (count($fragmentsCacheMTime) > 0) {
                $fragmentsCacheKeysToDelete = [];

                foreach ($fragments as $fragmentName => $fragmentContents) {
                    $matches = [];
                    $regExpPattern = '';

                    switch ($fragmentName) {
                        case 'template':
                            $regExpPattern = '/<!--\[rendered:(\d+)\]-->/';
                            break;

                        case 'style':
                        case 'script':
                            $regExpPattern = '/\/\*\[cached:(\d+)\]\*\//';
                            break;
                    }

                    if ($regExpPattern !== '' && preg_match($regExpPattern, $fragmentContents, $matches)) {
                        $storedCacheMTime = (int) $matches[1];

                        if (isset($fragmentsCacheMTime[$fragmentName]) && $storedCacheMTime < $fragmentsCacheMTime[$fragmentName]) {
                            $fragmentsCacheKeysToDelete[] = $fragmentName;
                        }
                    }
                }

                if (count($fragmentsCacheKeysToDelete) > 0) {
                    $this->deleteFragmentsCacheEntries($fragmentsCacheKeysToDelete, $this->client->getApiLanguage());
                }
            }

            return $this->rendererStrategy->renderPaywallTemplate(
                str_replace(
                    ['{PAYWALL_STYLE}', '{LOAN_AMOUNT}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $queryCriteria->loanAmount, $paywallProductsList, $fragments['script']],
                    $fragments['template']
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }
    }

    /**
     * @param string $platformCode
     * @param string $platformVersion
     * @param string $pluginVersion
     */
    public function getLogoAuthHash($platformCode, $platformVersion, $pluginVersion): string
    {
        return urlencode(base64_encode($this->getLogoAuthKey($platformCode, $platformVersion, $pluginVersion)));
    }

    /**
     * @param string $platformCode
     * @param string $platformVersion
     * @param string $pluginVersion
     * @param string $apiKey
     * @param string $widgetKey
     */
    public function getPaywallLogoAuthHash($platformCode, $platformVersion, $pluginVersion, $apiKey, $widgetKey): string
    {
        $authKey = $this->getLogoAuthKey($platformCode, $platformVersion, $pluginVersion) . $widgetKey;
        $authKey .= hash_hmac('sha3-256', $authKey, $apiKey, true);

        return urlencode(base64_encode($authKey));
    }
}
