<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache\Backends;

use DateInterval;
use PHPUnit\Framework\TestCase;

class AbstractCacheBackendTest extends TestCase
{
    public function testGetMultipleUsesGetAndDefault(): void
    {
        $backend = new class() extends AbstractCacheBackend {
            /** @var array<string,mixed> */
            public array $values = [];
            /** @var list<array{string,mixed}> */
            public array $getCalls = [];

            public function clear(): bool
            {
                $this->values = [];
                return true;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                $this->getCalls[] = [$key, $default];
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
            {
                $this->values[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->values[$key]);
                return true;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }
        };

        $backend->values = ['a' => 1];

        $result = $backend->getMultiple(['a', 'b'], 'fallback');

        $this->assertSame(['a' => 1, 'b' => 'fallback'], $result);
        $this->assertSame([['a', 'fallback'], ['b', 'fallback']], $backend->getCalls);
    }

    public function testSetMultipleCallsSetForEachKeyAndPassesTtl(): void
    {
        $ttl = 123;

        $backend = new class() extends AbstractCacheBackend {
            /** @var list<array{string,mixed,DateInterval|int|null}> */
            public array $setCalls = [];

            public function clear(): bool
            {
                return true;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
            {
                $this->setCalls[] = [$key, $value, $ttl];
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $ok = $backend->setMultiple(['k1' => 'v1', 'k2' => 'v2'], $ttl);

        $this->assertTrue($ok);
        $this->assertSame([
            ['k1', 'v1', $ttl],
            ['k2', 'v2', $ttl],
        ], $backend->setCalls);
    }

    public function testDeleteMultipleCallsDeleteForEachKey(): void
    {
        $backend = new class() extends AbstractCacheBackend {
            /** @var list<string> */
            public array $deleteCalls = [];

            public function clear(): bool
            {
                return true;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                $this->deleteCalls[] = $key;
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $ok = $backend->deleteMultiple(['a', 'b', 'c']);

        $this->assertTrue($ok);
        $this->assertSame(['a', 'b', 'c'], $backend->deleteCalls);
    }
}
