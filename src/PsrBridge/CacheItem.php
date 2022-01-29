<?php

namespace Dburiy\PsrBridge;

use Exception;
use Throwable;
use LogicException;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Dburiy\FileCacher;

class CacheItem implements CacheItemInterface
{
    /** @var FileCacher */
    private $cacher;
    /** @var string */
    private $key;
    /** @var mixed */
    private $value;
    /** @var DateTimeInterface|null */
    private $expire_at;
    /** @var DateInterval|int|null */
    private $expire_after;

    public function __construct(FileCacher $cacher, string $key)
    {
        $this->cacher = $cacher;
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        if (!$this->isHit()) {
            return null;
        }
        return $this->cacher->get($this->key);
    }

    /**
     * @return bool
     */
    public function isHit(): bool
    {
        try {
            $meta = $this->cacher->getMetaByKey($this->getKey());
            if (
                $meta
                && $meta['ex'] != 0
                && ($meta['ex'] < microtime(true))
            ) {
                throw new Exception("expired");
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param mixed $value
     * @return CacheItemInterface
     */
    public function set($value): CacheItemInterface
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        $key = $this->getKey();
        $has_item = $this->cacher->hasItem($key);
        if (
            $this->value === null
            && $has_item
        ) {
            return $this->cacher->get($key, null, true);
        }
        return $this->value;
    }

    /**
     * Получить время истечения жизни кэша
     *
     * @return int|null
     */
    public function getExpires(): ?int
    {
        if ($this->expire_after !== null) {
            if (
                is_object($this->expire_after)
                && get_class($this->expire_after) == 'DateInterval'
            ) {
                return $this->expire_after->days*86400 + $this->expire_after->h*3600 + $this->expire_after->i*60 + $this->expire_after->s;
            }
            return $this->expire_after;
        }
        if ($this->expire_at !== null) {
            return ((int) $this->expire_at->format('U')) - ((int) (new DateTime())->format('U'));
        }
        return 0;
    }

    /**
     * @param DateTimeInterface|null $expiration
     * @return CacheItemInterface
     */
    public function expiresAt($expiration): CacheItemInterface
    {
        $this->expire_after = null;
        $this->expire_at = $expiration;
        return $this;
    }

    /**
     * @param DateInterval|int|null $time
     * @return CacheItemInterface
     */
    public function expiresAfter($time): CacheItemInterface
    {
        if ($time === null) {
            $this->expire_at = null;
            $this->expire_after = 0;
            return $this;
        }
        if (
            !is_numeric($time)
            && (get_class($time) !== 'DateInterval')
        ) {
            throw new LogicException('wrong type for time');
        }
        $this->expire_at = null;
        $this->expire_after = $time;
        return $this;
    }
}
