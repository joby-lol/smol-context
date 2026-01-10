<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\PathGuard;

/**
 * Generic interface for classes that are used to check if a file is allowed to be used in some way, be that including,
 * reading, writing, etc. The default implementations are designed to be simple and easy to use, but you can create your
 * own. The built-in interfaces based on this one are:
 *
 * * IncludeGuard (for checking if a file can be included, used in the Invoker when including files)
 * * ReadGuard (for checking if a file is allowed to be read)
 * * WriteGuard (for checking if a file is allowed to be written)
 *
 * File rules take precedence over directory rules, and after that deny rules take precedence over allow rules. This
 * means that you can allow a directory, but deny files or subdirectories within it. It also means that you can deny a
 * directory, but allow specific files within it.
 */
interface PathGuard
{
    public function check(string $filename): bool;

    public function allowDirectory(string $directory): void;

    public function denyDirectory(string $directory): void;

    public function allowFile(string $file): void;

    public function denyFile(string $file): void;
}