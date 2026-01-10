<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

/**
 * Class for holding the requested class name and category of a parameter that must be resolved before passing it to
 * wherever it is being injected.
 */
readonly class ObjectPlaceholder
{
    /**
     * @param class-string $class
     * @param string       $category
     */
    public function __construct(
        public string $class,
        public string $category = 'default',
    )
    {
    }
}