<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

/**
 * Internal data structure used during dependency resolution to represent a parameter that should be injected with a value from the Config. Stores the config key, allowed types, and default value information until it can be resolved to an actual value.
 * 
 * @internal
 */
class ConfigPlaceholder
{
    /**
     * @param array<string> $valid_types
     */
    public function __construct(
        public readonly string $key,
        public readonly array $valid_types,
        public readonly bool $is_optional,
        public readonly mixed $default,
        public readonly bool $allows_null,
    )
    {
    }
}