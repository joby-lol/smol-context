<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\PathGuard;

/**
 * Generic implementation of the PathGuard interface, intended to be used for transient or other more specific uses in
 * which ReadGuard, WriteGuard, and IncludeGuard are not quite appropriate, or anywhere you might need a custom
 * PathGuard tool that is configured differently than the default ones.
 */
class GenericPathGuard implements PathGuard
{
    use PathGuardTrait;
}