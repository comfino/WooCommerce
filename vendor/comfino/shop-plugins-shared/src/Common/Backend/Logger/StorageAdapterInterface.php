<?php

namespace Comfino\Common\Backend\Logger;

interface StorageAdapterInterface
{
    /**
     * @param string $errorPrefix
     * @param string $errorMessage
     */
    public function save($errorPrefix, $errorMessage): void;
}
