<?php

namespace Comfino\TemplateRenderer;

use Comfino\Api\ApiClient;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Main;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PluginRendererStrategy implements RendererStrategyInterface
{
    public function renderPaywallTemplate($paywallContents): string
    {
        return str_replace('</head>', '<link rel="stylesheet" href="https://widget.comfino.pl/css/paywall.css"><script src="https://widget.comfino.pl/paywall.min.js"></script></head>', $paywallContents);
    }

    public function renderErrorTemplate($exception, $frontendRenderer): string
    {
        $userErrorMessage = 'There was a technical problem. Please try again in a moment and it should work!';

        Main::debugLog(
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
                'exception_class' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
                'url' => $url,
                'request_body' => $requestBody,
                'response_body' => $responseBody,
                'is_debug_mode' => ApiClient::isDevEnv(),
            ]
        );
    }
}
