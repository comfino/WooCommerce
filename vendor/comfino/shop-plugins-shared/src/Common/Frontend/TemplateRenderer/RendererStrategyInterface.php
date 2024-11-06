<?php

namespace Comfino\Common\Frontend\TemplateRenderer;

interface RendererStrategyInterface
{
    /**
     * @param string $paywallContents
     */
    public function renderPaywallTemplate($paywallContents): string;
    /**
     * @param \Throwable $exception
     */
    public function renderErrorTemplate($exception): string;
}
