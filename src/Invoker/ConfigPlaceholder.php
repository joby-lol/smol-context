<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

/**
 * Class for holding the requested config key, and valid types of a config key that is being requested for injection, which must be resolved before passing it along. Also holds default value for use if there is no config set for the given key.
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