<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;

class ConfigurationRepair extends RestEndpoint
{
    /**
     * @var callable():array
     */
    private $validateCallback;
    /**
     * @var callable():array
     */
    private $repairCallback;
    /**
     * @param mixed $validateCallback
     * @param mixed $repairCallback
     */
    public function __construct(
        string $name,
        string $endpointUrl,
        $validateCallback,
        $repairCallback
    ) {
        $this->validateCallback = $validateCallback;
        $this->repairCallback = $repairCallback;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['GET', 'POST'];
    }

    /**
     * @param \ComfinoExternal\Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    public function processRequest($serverRequest, $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        $method = strtoupper($serverRequest->getMethod());

        if ($method === 'GET') {
            $validation = ($this->validateCallback)();

            return [
                'status' => 'ok',
                'action' => 'validate',
                'validation' => $validation,
            ];
        }

        if ($method === 'POST') {
            $repairStats = ($this->repairCallback)();

            return [
                'status' => 'ok',
                'action' => 'repair',
                'repair_stats' => $repairStats,
            ];
        }

        throw new InvalidEndpoint('Invalid HTTP method for configuration repair endpoint.');
    }
}
