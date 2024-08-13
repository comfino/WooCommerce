<?php

namespace Comfino\Common\Frontend\TemplateRenderer;

interface RendererStrategyInterface
{
    /**
     * @param string $paywallContents
     */
    public function renderPaywallTemplate($paywallContents): string;
    /**
     * @param ComfinoExternal\\Throwable $exception
     */
    public function renderErrorTemplate($exception): string;
}
