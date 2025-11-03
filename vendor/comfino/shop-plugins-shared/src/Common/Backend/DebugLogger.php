<?php

declare(strict_types=1);

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;

class DebugLogger
{
    /**
     * @readonly
     * @var \Comfino\Api\SerializerInterface
     */
    private $serializer;
    /**
     * @readonly
     * @var string
     */
    private $logFilePath;
    /**
     * @var $this|null
     */
    private static $instance;

    /**
     * @param \Comfino\Api\SerializerInterface $serializer
     * @param string $logFilePath
     */
    public static function getInstance($serializer, $logFilePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($serializer, $logFilePath);
        }

        return self::$instance;
    }

    private function __construct(SerializerInterface $serializer, string $logFilePath)
    {
        $this->serializer = $serializer;
        $this->logFilePath = $logFilePath;
    }

    /**
     * @param string $eventPrefix
     * @param string $eventMessage
     * @param mixed[]|null $parameters
     */
    public function logEvent($eventPrefix, $eventMessage, $parameters = null): void
    {
        if (!empty($parameters)) {
            $preparedParameters = [];

            foreach ($parameters as $name => $value) {
                if (is_array($value)) {
                    $value = $this->serializer->serialize($value);
                } elseif (is_bool($value)) {
                    $value = ($value ? 'true' : 'false');
                }

                $preparedParameters[] = "$name=$value";
            }

            $eventMessage .= (($eventMessage !== '' ? ': ' : '') . implode(', ', $preparedParameters));
        }

        FileUtils::append($this->logFilePath, '[' . date('Y-m-d H:i:s') . "] $eventPrefix: $eventMessage\n");
    }

    /**
     * @param int $numLines
     */
    public function getDebugLog($numLines): string
    {
        return implode('', FileUtils::readLastLines($this->logFilePath, $numLines));
    }
}
