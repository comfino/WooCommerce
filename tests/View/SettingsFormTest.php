<?php

namespace Comfino\Tests\View;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\Forbidden;
use Comfino\Api\Exception\NotFound;
use Comfino\Main;
use Comfino\Tests\TestState;
use Comfino\View\SettingsForm;

/**
 * "API key is not valid" only for a 401 or the API's own JSON 403; the tracking consent switch is hidden.
 */
class SettingsFormTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        TestState::reset();
    }

    public function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testUnauthorizedShowsInvalidKeyMessage(): void
    {
        $this->assertSame('API key KEY is not valid.', $this->getKeyMessage(new AuthorizationError('Unauthorized', 401)));
    }

    public function testApiJsonForbiddenShowsInvalidKeyMessage(): void
    {
        $forbidden = new AccessDenied('Forbidden', 403, null, 'https://api/test', '', '{"errors":["Access denied."]}');

        $this->assertSame('API key KEY is not valid.', $this->getKeyMessage($forbidden));
    }

    public function testHtmlForbiddenOrNotFoundShowsConnectionMessage(): void
    {
        $htmlForbidden = new Forbidden('Forbidden', 403, null, 'https://api/test', '', '<html><body>WAF</body></html>');
        $notFound = new NotFound('Not Found', 404, null, 'https://api/test', '', '{"errors":[]}');

        foreach ([$htmlForbidden, $notFound] as $exception) {
            $this->assertContains('could not be reached', $this->getKeyMessage($exception), get_class($exception));
        }
    }

    public function testTrackingConsentFieldIsHidden(): void
    {
        $this->assertArrayNotHasKey('paywall_tracking_consent', SettingsForm::getFormFields('payment_settings'));
        $this->assertArrayHasKey('paywall_direct_redirect', SettingsForm::getFormFields('payment_settings'));
    }

    private function getKeyMessage(\Throwable $exception): string
    {
        $method = new \ReflectionMethod(SettingsForm::class, 'getApiKeyCheckErrorMessage');
        $method->setAccessible(true);

        return $method->invoke(null, $exception, 'KEY', 'Test');
    }
}
