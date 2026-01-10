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

class ExecutionException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            sprintf('Exception of type %s thrown during execution: %s', get_class($previous), $previous->getMessage()),
            previous: $previous
        );
    }
}