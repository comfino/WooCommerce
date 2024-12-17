<?php

namespace Comfino\Common\Frontend\TemplateRenderer;

use Comfino\Common\Frontend\FrontendRenderer;

interface RendererStrategyInterface
{
    /**
     * @param \Throwable $exception
     * @param \Comfino\Common\Frontend\FrontendRenderer $frontendRenderer
     */
    public function renderErrorTemplate($exception, $frontendRenderer): string;
}
