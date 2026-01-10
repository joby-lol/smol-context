<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache;

use Joby\Smol\Context\Cache\Backends\EphemeralCache;
use Psr\SimpleCache\CacheInterface;

class DefaultCache extends CacheWrapper
{

    public function __construct(CacheInterface|null $cache = null)
    {
        parent::__construct($cache ?? new EphemeralCache());
    }
}