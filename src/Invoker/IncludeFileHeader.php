<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

/**
 * Parsed metadata from the non-executable "header" portion of an included PHP file.
 */
class IncludeFileHeader
{

    /**
     * @param array<string,string> $uses Map of alias => fully qualified class name (no leading backslash)
     */
    public function __construct(
        public readonly string|null $namespace,
        public readonly array $uses,
        public readonly string|null $docblock,
    ) {}

}
