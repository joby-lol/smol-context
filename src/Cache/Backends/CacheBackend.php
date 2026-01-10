<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache\Backends;

use Psr\SimpleCache\CacheInterface;

interface CacheBackend extends CacheInterface
{
    /**
     * For testing purposes - allows setting the current time, set to null to use the actual time.
     */
    public function setCurrentTime(?int $time): void;
}