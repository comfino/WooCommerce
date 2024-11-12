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
    public const PAYWALL_FRAGMENT_TEMPLATE = 'template';
    public const PAYWALL_FRAGMENT_STYLE = 'style';
    public const PAYWALL_FRAGMENT_SCRIPT = 'script';

    private const PAYWALL_FRAGMENTS = [self::PAYWALL_FRAGMENT_TEMPLATE, self::PAYWALL_FRAGMENT_STYLE, self::PAYWALL_FRAGMENT_SCRIPT];

    /**
     * @var mixed[]|null
     */
    protected $headMetaTags;

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
     * @param HeadMetaTag[]|null $headMetaTags
     * @param \Comfino\Api\Dto\Payment\LoanQueryCriteria $queryCriteria
     */
    public function renderPaywall($queryCriteria, $headMetaTags = null): string
    {
        $this->headMetaTags = $headMetaTags;

        try {
            $fragments = $this->getFrontendFragments(self::PAYWALL_FRAGMENTS);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e, $this);
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
                        case self::PAYWALL_FRAGMENT_TEMPLATE:
                            $regExpPattern = '/<!--\[rendered:(\d+)\]-->/';
                            break;

                        case self::PAYWALL_FRAGMENT_STYLE:
                        case self::PAYWALL_FRAGMENT_SCRIPT:
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
                    [
                        '{HEAD_META}',
                        '{PAYWALL_STYLE}',
                        '{PAYWALL_API_ORIGIN}',
                        '{LOAN_AMOUNT}',
                        '{PAYWALL_PRODUCTS_LIST}',
                        '{PAYWALL_SCRIPT}',
                    ],
                    [
                        $this->renderHeadMetaTags(),
                        $fragments[self::PAYWALL_FRAGMENT_STYLE],
                        $paywallApiOrigin,
                        $queryCriteria->loanAmount,
                        $paywallProductsList,
                        $fragments[self::PAYWALL_FRAGMENT_SCRIPT]
                    ],
                    $fragments[self::PAYWALL_FRAGMENT_TEMPLATE]
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e, $this);
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
            return new PaywallItemDetails($this->rendererStrategy->renderErrorTemplate($e, $this), '');
        }
    }

    /**
     * @param string $fragment
     */
    public function getFrontendFragment($fragment): string
    {
        try {
            return $this->getFrontendFragments([$fragment])[$fragment] ?? '';
        } catch (\Throwable $exception) {
            return '';
        }
    }

    public function getHeadMetaTags(): ?array
    {
        return $this->headMetaTags;
    }

    public function renderHeadMetaTags(): string
    {
        if (empty($this->headMetaTags)) {
            return '';
        }

        return
            implode(
                "\n",
                array_filter(
                    array_map(
                        static function ($headMetaTag): ?string {
                            if (!($headMetaTag instanceof HeadMetaTag)) {
                                return null;
                            }

                            $metaTag = '<meta ';

                            if ($headMetaTag->name !== null) {
                                $metaTag .= 'name="' . htmlentities(strip_tags($headMetaTag->name), ENT_QUOTES) . '" ';
                            }

                            if ($headMetaTag->httpEquiv !== null) {
                                $metaTag .= 'http-equiv="' . htmlentities(strip_tags($headMetaTag->httpEquiv), ENT_QUOTES) . '" ';
                            }

                            if ($headMetaTag->content !== null) {
                                $metaTag .= ' content="' . htmlentities(strip_tags($headMetaTag->content), ENT_QUOTES) . '" ';
                            }

                            if ($headMetaTag->itemProp !== null) {
                                $metaTag .= ' itemprop="' . htmlentities(strip_tags($headMetaTag->itemProp), ENT_QUOTES) . '" ';
                            }

                            $metaTag .= '>';

                            return $metaTag;
                        },
                        $this->headMetaTags
                    )
                )
            );
    }
}
