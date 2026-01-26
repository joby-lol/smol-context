<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

use Attribute;

/**
 * Attribute used to indicate that a parameter in a constructor or callable should be injected with a value from the Config instead of an object from the Container. The value will be retrieved using the specified config key and automatically converted to match the parameter's type hint.
 * 
 * Supports all basic PHP types, as well as object types.
 */
#[Attribute]

class ConfigValue
{
    public function __construct(
        public readonly string $key,
    )
    {
    }
}