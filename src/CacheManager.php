<?php

namespace Comfino;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Psr\Cache\InvalidArgumentException;

if (!defined('ABSPATH')) {
    exit;
}

class CacheManager
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
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
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
