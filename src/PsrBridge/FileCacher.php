<?php

namespace Dburiy\PsrBridge;

use Dburiy\FileCacher as DburiyFileCacher;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class FileCacher implements CacheItemPoolInterface
{
    /** @var DburiyFileCacher */
    private $cacher;
    /** @var array<object> */
    private $deferred = [];

    public function __construct(DburiyFileCacher $cacher)
    {
        $this->cacher = $cacher;
    }

    /**
     * @param string $key
     * @return CacheItemInterface|CacheItem
     */
    public function getItem($key): CacheItemInterface
    {
        return new CacheItem($this->cacher, $key);
    }

    /**
     * @param array<string> $keys
     * @return array<object>
     */
    public function getItems(array $keys = array()): array
    {
        $items = [];
        foreach ($keys as $key) {
            $items[] = new CacheItem($this->cacher, $key);
        }
        return $items;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasItem($key): bool
    {
        return $this->cacher->hasItem($key);
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->cacher->clean();
        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function deleteItem($key): bool
    {
        $this->cacher->delete($key);
        return true;
    }

    /**
     * @param string[] $keys
     * @return bool
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->cacher->delete($key);
        }
        return true;
    }

    /**
     * @param CacheItem $item
     * @return bool
     * @throws CacheException
     */
    public function save(CacheItemInterface $item): bool
    {
        if (isset($this->deferred[$key = $item->getKey()])) {
            unset($this->deferred[$key]);
        }
        $value = $item->getValue();
        $expires = $item->getExpires() ?? 0;
        if ($expires < 0) {
            throw new CacheException('wrong expires ' . json_encode(['expires' => $expires], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        if (is_null($value)) {
            throw new CacheException('value is null');
        }
        return $this->cacher->set($key, $value, $expires);
    }

    /**
     * @param CacheItemInterface|CacheItem $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (isset($this->deferred[$item->getKey()])) {
            return true;
        }
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    /**
     * @return bool
     * @throws CacheException
     */
    public function commit(): bool
    {
        /**
         * @var string $n
         * @var CacheItem $item
         */
        foreach ($this->deferred as $n => $item) {
            $this->save($item);
            unset($this->deferred[$n]);
        }
        return true;
    }
}
