<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

use RuntimeException;
use Throwable;

class InstantiationException extends RuntimeException
{
    public function __construct(string $class, Throwable $previous)
    {
        parent::__construct(
            sprintf('Exception of type %s thrown while instantiating %s: %s', get_class($previous), $class, $previous->getMessage()),
            previous: $previous
        );
    }
}