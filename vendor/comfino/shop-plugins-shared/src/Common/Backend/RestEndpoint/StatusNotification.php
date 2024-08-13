<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Comfino\Common\Shop\Order\StatusManager;
use Psr\Http\Message\ServerRequestInterface;

final class StatusNotification extends RestEndpoint
{
    /**
     * @readonly
     * @var ComfinoExternal\\Comfino\Common\Shop\Order\StatusManager
     */
    private $statusManager;
    /**
     * @readonly
     * @var mixed[]
     */
    private $forbiddenStatuses;
    /**
     * @readonly
     * @var mixed[]
     */
    private $ignoredStatuses;
    public function __construct(
        string $name,
        string $endpointUrl,
        StatusManager $statusManager,
        array $forbiddenStatuses,
        array $ignoredStatuses
    ) {
        $this->statusManager = $statusManager;
        $this->forbiddenStatuses = $forbiddenStatuses;
        $this->ignoredStatuses = $ignoredStatuses;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     * @inheritDoc
     */
    public function processRequest($serverRequest, $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $this->getParsedRequestBody($serverRequest))) {
            throw new InvalidRequest('Invalid request payload.');
        }

        if (!isset($requestPayload['status'])) {
            throw new InvalidRequest('Status must be set.');
        }

        if (in_array($requestPayload['status'], $this->ignoredStatuses, true)) {
            return null;
        }

        if (!isset($requestPayload['externalId'])) {
            throw new InvalidRequest('External ID must be set.');
        }

        if (in_array($requestPayload['status'], $this->forbiddenStatuses, true)) {
            throw new InvalidRequest('Invalid status "' . $requestPayload['status'] . '".');
        }

        try {
            $this->statusManager->setOrderStatus($requestPayload['externalId'], $requestPayload['status']);
        } catch (\Throwable $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return null;
    }
}
