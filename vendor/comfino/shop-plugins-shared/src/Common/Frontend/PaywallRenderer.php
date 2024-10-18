<?php

namespace Comfino\Common\Frontend;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;
use Comfino\Shop\Order\CartInterface;

final class PaywallRenderer extends FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    /**
     * @readonly
     * @var string|null
     */
    private $paywallApiOrigin;
    private const PAYWALL_FRAGMENTS = ['template', 'style', 'script'];

    public function __construct(
        Client $client,
        TaggableCacheItemPoolInterface $cache,
        RendererStrategyInterface $rendererStrategy,
        ?string $cacheInvalidateUrl = null,
        ?string $configurationUrl = null,
        ?string $paywallApiOrigin = null
    ) {
        $this->rendererStrategy = $rendererStrategy;
        $this->paywallApiOrigin = $paywallApiOrigin;
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
                            // Stored contents timestamp are less than received from Cache-MTime header - add this item to the list of keys to delete from cache.
                            $fragmentsCacheKeysToDelete[] = $fragmentName;
                        }
                    }
                }

                if (count($fragmentsCacheKeysToDelete) > 0) {
                    // Delete specified cache items to reload actual versions of resources.
                    $this->deleteFragmentsCacheEntries($fragmentsCacheKeysToDelete, $this->client->getApiLanguage());
                    // Reload deleted items from API.
                    $fragments = array_merge($fragments, $this->getFrontendFragments($fragmentsCacheKeysToDelete));
                }
            }

            $paywallApiOrigin = $this->paywallApiOrigin;

            if ($paywallResponse->hasHeader('Paywall-Api-Origin') && $paywallResponse->getHeader('Paywall-Api-Origin') !== $paywallApiOrigin) {
                $paywallApiOrigin = $paywallResponse->getHeader('Paywall-Api-Origin');
            }

            return $this->rendererStrategy->renderPaywallTemplate(
                str_replace(
                    ['{PAYWALL_STYLE}', '{PAYWALL_API_ORIGIN}', '{LOAN_AMOUNT}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $paywallApiOrigin, $queryCriteria->loanAmount, $paywallProductsList, $fragments['script']],
                    $fragments['template']
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }
    }

    /**
     * @param int $loanAmount
     * @param \Comfino\Api\Dto\Payment\LoanTypeEnum $loanType
     * @param \Comfino\Shop\Order\CartInterface $cart
     */
    public function getPaywallItemDetails($loanAmount, $loanType, $cart): PaywallItemDetails
    {
        try {
            $response = $this->client->getPaywallItemDetails($loanAmount, $loanType, $cart);

            return new PaywallItemDetails($response->productDetails, $response->listItemData);
        } catch (\Throwable $e) {
            return new PaywallItemDetails($this->rendererStrategy->renderErrorTemplate($e), '');
        }
    }
}
