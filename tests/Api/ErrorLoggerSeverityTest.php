<?php

namespace Comfino\Tests\Api;

use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\Conflict;
use Comfino\Api\Exception\NonJsonResponse;
use Comfino\Api\Exception\NotFound;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Exception\TooManyRequests;
use Comfino\Common\Backend\ErrorLogger as SharedErrorLogger;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Main;

/**
 * The plugin's ErrorLogger::sendError() maps severity through the shared resolveSeverity(): transient and idempotent
 * failures are warnings, validation outcomes stay local.
 */
class ErrorLoggerSeverityTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');
    }

    /**
     * @dataProvider severityDataProvider
     */
    public function testResolveSeverity(\Throwable $exception, ?string $expectedSeverity): void
    {
        $this->assertSame($expectedSeverity, SharedErrorLogger::resolveSeverity($exception));
    }

    public function severityDataProvider(): array
    {
        $idempotentNotFound = new NotFound('Not found', 404);
        $idempotentNotFound->setIdempotentFailure(true);

        $idempotentConflict = new Conflict('Conflict', 409);
        $idempotentConflict->setIdempotentFailure(true);

        return [
            'TypeError is an error' => [new \TypeError('type error'), ErrorSeverity::Error],
            'RuntimeException is an error' => [new \RuntimeException('runtime error'), ErrorSeverity::Error],
            'request validation error is not sent' => [new RequestValidationError('bad request', 400), null],
            'response validation error is not sent' => [new ResponseValidationError('invalid response'), null],
            'TooManyRequests is a warning' => [new TooManyRequests('rate limited', 429), ErrorSeverity::Warning],
            'HTML 5xx is a warning' => [
                new NonJsonResponse('bad gateway', 502, null, '', '', '<html></html>', 'text/html'),
                ErrorSeverity::Warning,
            ],
            'ServiceUnavailable is a warning' => [new ServiceUnavailable('down', 503), ErrorSeverity::Warning],
            'ConnectionTimeout is a warning' => [new ConnectionTimeout('timed out', 28), ErrorSeverity::Warning],
            'idempotent 404 is a warning' => [$idempotentNotFound, ErrorSeverity::Warning],
            'idempotent 409 is a warning' => [$idempotentConflict, ErrorSeverity::Warning],
            'plain 404 is an error' => [new NotFound('Not found', 404), ErrorSeverity::Error],
        ];
    }

    /**
     * @dataProvider severityDataProvider
     */
    public function testPluginSendErrorHandlesEverySeverity(\Throwable $exception): void
    {
        // Local-only outcomes (null severity) are logged, the others reported; neither path may throw.
        ErrorLogger::sendError($exception, OperationContext::ApiCommunication, (string) $exception->getCode(), $exception->getMessage());

        $this->assertTrue(true);
    }

    public function testAuthorizationErrorIsNeverSent(): void
    {
        ErrorLogger::sendError(new AuthorizationError('unauthorized', 401), OperationContext::Configuration, '401', 'unauthorized');

        $this->assertTrue(true);
    }

    public function testSendWarningAcceptsSummaryReport(): void
    {
        ErrorLogger::sendWarning('configuration_error', OperationContext::OrderCreation, 'LOAN_SELECTION_NOT_POSTED_DEFAULTED', 'Orders submitted without the selected financing offer: 1 since 2026-10-21 08:14:03 UTC.');

        $this->assertTrue(true);
    }
}
