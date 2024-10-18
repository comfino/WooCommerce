<?php

namespace Comfino\ErrorLogger;

use Comfino\Common\Backend\Logger\StorageAdapterInterface;
use Comfino\ErrorLogger;

if (!defined('ABSPATH')) {
    exit;
}

class StorageAdapter implements StorageAdapterInterface
{
    public function save($errorPrefix, $errorMessage): void
    {
        ErrorLogger::logError($errorPrefix, $errorMessage);
    }
}
