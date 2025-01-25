<?php

namespace Comfino\TemplateRenderer;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Main;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PluginRendererStrategy implements RendererStrategyInterface
{
    /**
     * @param \Throwable $exception
     * @param \Comfino\Common\Frontend\FrontendRenderer $frontendRenderer
     */
    public function renderErrorTemplate($exception, $frontendRenderer): string
    {
        $userErrorMessage = __('There was a technical problem. Please try again in a moment and it should work!', 'comfino-payment-gateway');

        DebugLogger::logEvent(
            '[API_ERROR]',
            'renderErrorTemplate',
            [
                'exception' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
                // '$fullDocumentStructure' => $this->fullDocumentStructure,
            ]
        );

        if ($exception instanceof HttpErrorExceptionInterface) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();

            if ($exception instanceof ResponseValidationError || $exception instanceof ServiceUnavailable) {
                $responseBody = $exception->getResponseBody();
            } else {
                if ($exception instanceof AccessDenied && $exception->getCode() === 404) {
                    $showMessage = true;
                    $userErrorMessage = $exception->getMessage();
                }

                $responseBody = '';
            }

            $templateName = 'api-error';
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = '';
            $templateName = 'api-error';
        } else {
            $url = '';
            $requestBody = '';
            $responseBody = '';
            $templateName = 'error';
        }

        return TemplateManager::renderView(
            $templateName,
            'front',
            [
                'language' => Main::getShopLanguage(),
                'title' => $userErrorMessage,
                'styles' => FrontendManager::registerExternalStyles($frontendRenderer->getStyles()),
                'scripts' => FrontendManager::includeExternalScripts($frontendRenderer->getScripts()),
                'error_details' => FrontendHelper::prepareErrorDetails(
                    $userErrorMessage,
                    ConfigManager::isDevEnv(),
                    $exception,
                    $url,
                    $requestBody,
                    $responseBody
                ),
            ],
            false
        );
    }
}
