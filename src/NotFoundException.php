<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

use RuntimeException;

/**
 * Exception thrown when a requested class is not registered in the container.
 */
class NotFoundException extends RuntimeException
{
}