<?php

namespace Comfino\Common\Frontend;

final class HeadMetaTag
{
    /**
     * @readonly
     * @var string|null
     */
    public $name;
    /**
     * @readonly
     * @var string|null
     */
    public $httpEquiv;
    /**
     * @readonly
     * @var string|null
     */
    public $content;
    /**
     * @readonly
     * @var string|null
     */
    public $itemProp;
    public function __construct(?string $name = null, ?string $httpEquiv = null, ?string $content = null, ?string $itemProp = null)
    {
        $this->name = $name;
        $this->httpEquiv = $httpEquiv;
        $this->content = $content;
        $this->itemProp = $itemProp;
    }
}
