<?php

namespace Comfino;

use ComfinoExternal\Cache\Adapter\Common\AbstractCachePool;
use ComfinoExternal\Cache\Adapter\Filesystem\FilesystemCachePool;
use ComfinoExternal\Cache\Adapter\PHPArray\ArrayCachePool;
use ComfinoExternal\League\Flysystem\Adapter\Local;
use ComfinoExternal\League\Flysystem\Filesystem;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;

if (!defined('ABSPATH')) {
    exit;
}

final class CacheManager
{
    /** @var string */
    private static $cacheRootPath;
    /** @var FilesystemCachePool|ArrayCachePool */
    private static $cache;

    public static function init(string $pluginDirectory): void
    {
        self::$cacheRootPath = "$pluginDirectory/var";
    }

    public static function get(string $key, $default = null)
    {
        try {
            return self::getCachePool()->get($key, $default);
        } catch (\ComfinoExternal\Psr\SimpleCache\InvalidArgumentException $e) {
        }

        return $default;
    }

    public static function set(string $key, $value, int $ttl = 0, ?array $tags = null): void
    {
        try {
            $item = self::getCachePool()->getItem($key)->set($value);

            if ($ttl > 0) {
                $item->expiresAfter($ttl);
            }

            if (!empty($tags)) {
                $item->setTags($tags);
            }

            self::getCachePool()->save($item);
        } catch (InvalidArgumentException $e) {
        }
    }

    public static function getCachePool(): AbstractCachePool
    {
        if (self::$cache === null) {
            try {
                self::$cache = new FilesystemCachePool(new Filesystem(new Local(self::$cacheRootPath)));
            } catch (\Throwable $e) {
                self::$cache = new ArrayCachePool();
            }
        }

        return self::$cache;
    }
}
