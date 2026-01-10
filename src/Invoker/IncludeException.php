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

class IncludeException extends RuntimeException
{
    public function __construct(public readonly string $include_path, public readonly Throwable $include_exception, public readonly string $output_buffer)
    {
        parent::__construct(
            sprintf('%s including %s', get_class($include_exception), $this->include_path),
            previous: $include_exception
        );
    }
}