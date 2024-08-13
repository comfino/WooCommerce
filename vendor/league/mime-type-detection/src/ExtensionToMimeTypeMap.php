<?php

declare (strict_types=1);
namespace ComfinoExternal\League\MimeTypeDetection;

interface ExtensionToMimeTypeMap
{
    public function lookupMimeType(string $extension): ?string;
}
