<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

/**
 * Internal data structure used during dependency resolution to represent a parameter that should be injected with an object from the Container. Stores the class name until it can be resolved to an actual object instance.
 * 
 * @internal
 */
class ObjectPlaceholder
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public readonly string $class,
    )
    {
    }
}