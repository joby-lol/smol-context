<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache\Backends;

use DateInterval;
use Psr\SimpleCache\InvalidArgumentException;

abstract class AbstractCacheBackend implements CacheBackend
{
    protected int $default_ttl;
    /** @var positive-int|null */
    protected ?int $current_time = null;

    public function __construct(int $default_ttl = 3600)
    {
        $this->default_ttl = $default_ttl;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string,mixed> $values 
     * @throws InvalidArgumentException 
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * For testing purposes - allows setting the current time
     * @param positive-int|null $time
     */
    public function setCurrentTime(int|null $time): void
    {
        $this->current_time = $time;
    }

    /**
     * Get the current timestamp, either real or simulated
     * @return positive-int
     */
    protected function getCurrentTime(): int
    {
        return $this->current_time ?? time();
    }
}