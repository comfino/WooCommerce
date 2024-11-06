<?php

namespace ComfinoExternal\League\Flysystem\Plugin;

use ComfinoExternal\League\Flysystem\FilesystemInterface;
use ComfinoExternal\League\Flysystem\PluginInterface;
abstract class AbstractPlugin implements PluginInterface
{
    /**
     * @var FilesystemInterface
     */
    protected $filesystem;
    /**
     * Set the Filesystem object.
     *
     * @param FilesystemInterface $filesystem
     */
    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }
}
