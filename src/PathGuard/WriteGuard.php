<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\PathGuard;

/**
 * Interface for a path guard that can be used to prevent certain files or directories from being written. This tool is
 * not used internally, but may be in the future.
 */
interface WriteGuard extends PathGuard
{
}