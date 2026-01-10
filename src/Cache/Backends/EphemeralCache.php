<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache\Backends;

use DateInterval;
use DateTimeImmutable;

class EphemeralCache extends AbstractCacheBackend
{

    /**
     * Array of cached data, indexed by key, with each value containing a tuple of an expiration timestamp and a cached data value.
     *
     * @var array<string,array{positive-int,mixed}>
     */
    protected array $data = [];

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->data[$key]) && $this->data[$key][0] >= $this->getCurrentTime()) {
            return $this->data[$key][1];
        }
        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $ttl = $ttl ?? $this->default_ttl;
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable('@' . $this->getCurrentTime());
            $ttl = $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }
        $expires = $this->getCurrentTime() + $ttl;
        assert($expires > 0);
        $this->data[$key] = [$expires, $value];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key][0] >= $this->getCurrentTime();
    }

}
