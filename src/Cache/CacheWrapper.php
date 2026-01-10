<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * A wrapper allowing any implementation of the FIG Simple Cache PSR to be wrapped and used as a
 * ContextInjection-compatible cache.
 */
class CacheWrapper implements Cache
{
    public function __construct(
        protected CacheInterface $backend
    )
    {
    }

    /**
     * Get a value from the underlying system if it exists, or execute the callback to generate and set it if necessary.
     *
     * @param string                $key
     * @param callable              $callback
     * @param DateInterval|int|null $ttl
     *
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function cache(string $key, callable $callback, DateInterval|int|null $ttl = null): mixed
    {
        $value = $this->backend->get($key, new NoValue());
        if ($value instanceof NoValue) {
            $value = $callback();
            $this->backend->set($key, $value, $ttl);
        }
        return $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->backend->get($key, $default);
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->backend->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->backend->delete($key);
    }

    public function clear(): bool
    {
        return $this->backend->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->backend->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return $this->backend->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->backend->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->backend->has($key);
    }
}