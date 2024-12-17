<?php

namespace Comfino\Common\Frontend;

final class PaywallContents
{
    /**
     * @var string
     * @readonly
     */
    public $paywallBody;
    /**
     * @var string
     * @readonly
     */
    public $paywallHash;
    /**
     * @param string $paywallBody
     * @param string $paywallHash
     */
    public function __construct(string $paywallBody, string $paywallHash)
    {
        $this->paywallBody = $paywallBody;
        $this->paywallHash = $paywallHash;
    }
}
