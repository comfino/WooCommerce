<?php

namespace Comfino\Common\Frontend\TemplateRenderer;

use Comfino\Common\Frontend\FrontendRenderer;

interface RendererStrategyInterface
{
    /**
     * @param string $paywallContents
     */
    public function renderPaywallTemplate($paywallContents): string;
    /**
     * @param \Throwable $exception
     * @param \Comfino\Common\Frontend\FrontendRenderer $frontendRenderer
     */
    public function renderErrorTemplate($exception, $frontendRenderer): string;
}
