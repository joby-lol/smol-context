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
 * Exception thrown when an error occurs during container operations, such as instantiation failures, circular dependencies, or attempting to register protected container services.
 */
class ContainerException extends RuntimeException
{
}