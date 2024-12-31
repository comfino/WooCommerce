<?php

namespace Comfino\Common\Frontend;

use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Shop\Order\CartInterface;

final class PaywallRenderer extends FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Api\Client
     */
    private $client;
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    public function __construct(Client $client, RendererStrategyInterface $rendererStrategy)
    {
        $this->client = $client;
        $this->rendererStrategy = $rendererStrategy;
    }

    /**
     * @param \Comfino\Api\Dto\Payment\LoanQueryCriteria $queryCriteria
     * @param string|null $recalculationUrl
     */
    public function getPaywall($queryCriteria, $recalculationUrl = null): PaywallContents
    {
        try {
            $paywallResponse = $this->client->getPaywall($queryCriteria, $recalculationUrl);

            return new PaywallContents($paywallResponse->paywallBody, $paywallResponse->paywallHash);
        } catch (\Throwable $e) {
            return new PaywallContents($this->rendererStrategy->renderErrorTemplate($e, $this), '');
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
     * @param string $paywallContents
     * @param string $apiKey
     */
    public function getPaywallHash($paywallContents, $apiKey): string
    {
        return hash_hmac('sha3-256', $paywallContents, $apiKey);
    }

    /**
     * @return string[]
     */
    public function getStyles(): array
    {
        return ['paywall.css'];
    }

    /**
     * @return string[]
     */
    public function getScripts(): array
    {
        return ['paywall.js'];
    }
}
