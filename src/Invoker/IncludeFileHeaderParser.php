<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

use PhpToken;

/**
 * Parses the non-executable "header" portion of a PHP file for include-time metadata.
 *
 * Supported:
 * - Inline HTML before PHP open tag
 * - declare(...) statements (e.g. strict_types)
 * - namespace Foo\\Bar;
 * - single-import use statements: use Foo\\Bar; and use Foo\\Bar as Baz;
 * - first top-level docblock encountered before executable code
 *
 * Not supported:
 * - group or multi-import use statements
 * - use function/const
 * - bracketed namespaces
 * 
 * @internal
 */
class IncludeFileHeaderParser
{

    public static function parse(string $phpSource): IncludeFileHeader
    {
        $tokens = PhpToken::tokenize($phpSource);

        $namespace = null;
        /** @var array<string,string> $uses */
        $uses = [];
        $docblock = null;

        $braceLevel = 0;
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // Track brace depth so we can avoid parsing use/namespace inside blocks.
            if ($token->text === '{') {
                $braceLevel++;
            }
            elseif ($token->text === '}') {
                $braceLevel = max(0, $braceLevel - 1);
            }

            // Only parse header-level declarations.
            if ($braceLevel !== 0) {
                $i++;
                continue;
            }

            // Skip non-semantic tokens.
            if ($token->is([T_WHITESPACE, T_COMMENT])) {
                $i++;
                continue;
            }

            // Allow HTML and PHP tag switching before executable code.
            if ($token->is([T_INLINE_HTML, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG])) {
                $i++;
                continue;
            }

            if ($token->is(T_DOC_COMMENT)) {
                if ($docblock === null) {
                    $docblock = $token->text;
                }
                $i++;
                continue;
            }

            if ($token->is(T_DECLARE)) {
                $i = static::skipStatement($tokens, $i);
                continue;
            }

            if ($token->is(T_NAMESPACE)) {
                if ($namespace === null) {
                    [$parsedNamespace, $newIndex] = static::parseNamespace($tokens, $i);
                    if ($parsedNamespace !== null) {
                        $namespace = $parsedNamespace;
                    }
                    $i = $newIndex;
                    continue;
                }
                $i = static::skipStatement($tokens, $i);
                continue;
            }

            if ($token->is(T_USE)) {
                [$newUses, $newIndex] = static::parseUse($tokens, $i);
                foreach ($newUses as $alias => $fqcn) {
                    $uses[$alias] = $fqcn;
                }
                $i = $newIndex;
                continue;
            }

            // Any other token at top-level is treated as "executable code" (or at least end-of-header).
            break;
        }

        return new IncludeFileHeader($namespace, $uses, $docblock);
    }

    /**
     * Skip forward to the end of the current top-level statement (first ';').
     *
     * @param array<PhpToken> $tokens
     */
    protected static function skipStatement(array $tokens, int $startIndex): int
    {
        $i = $startIndex;
        $count = count($tokens);
        while ($i < $count) {
            if ($tokens[$i]->text === ';') {
                return $i + 1;
            }
            $i++;
        }
        return $i;
    }

    /**
     * @param array<PhpToken> $tokens
     * @return array{0:string|null,1:int} Parsed namespace (no leading slash), and next index.
     */
    protected static function parseNamespace(array $tokens, int $startIndex): array
    {
        $i = $startIndex + 1;
        $name = static::parseQualifiedName($tokens, $i);
        if ($name === null) {
            return [null, static::skipStatement($tokens, $startIndex)];
        }

        $namespace = trim($name, '\\');

        // Advance to the end of the namespace declaration.
        $count = count($tokens);
        while ($i < $count) {
            if ($tokens[$i]->text === ';') {
                return [$namespace, $i + 1];
            }
            if ($tokens[$i]->text === '{') {
                // Bracketed namespaces not supported (treat as end of header parsing).
                return [$namespace, $i + 1];
            }
            $i++;
        }

        return [$namespace, $i];
    }

    /**
     * Parse a single-import use statement.
     *
     * Supports:
     * - use Foo\\Bar;
     * - use Foo\\Bar as Baz;
     *
     * @param array<PhpToken> $tokens
     * @return array{0:array<string,string>,1:int} Map of alias => FQCN and next index.
     */
    protected static function parseUse(array $tokens, int $startIndex): array
    {
        $i = $startIndex + 1;
        $count = count($tokens);

        $i = static::skipIgnorable($tokens, $i);
        if ($i >= $count) {
            return [[], $i];
        }

        // Ignore closure "use (...)".
        if ($tokens[$i]->text === '(') {
            return [[], static::skipStatement($tokens, $startIndex)];
        }

        // Ignore use function/const.
        if ($tokens[$i]->is([T_FUNCTION, T_CONST])) {
            return [[], static::skipStatement($tokens, $startIndex)];
        }

        $fqcn = static::parseQualifiedName($tokens, $i);
        if ($fqcn === null) {
            return [[], static::skipStatement($tokens, $startIndex)];
        }

        $fqcn = ltrim($fqcn, '\\');

        // If the statement contains ',' or '{', it's a multi/group use (unsupported).
        $alias = null;
        while ($i < $count) {
            $i = static::skipIgnorable($tokens, $i);
            if ($i >= $count) {
                break;
            }

            if ($tokens[$i]->text === ',' || $tokens[$i]->text === '{') {
                return [[], static::skipStatement($tokens, $startIndex)];
            }

            if ($tokens[$i]->is(T_AS)) {
                $i++;
                $i = static::skipIgnorable($tokens, $i);
                if ($i < $count && $tokens[$i]->is(T_STRING)) {
                    $alias = $tokens[$i]->text;
                    $i++;
                    continue;
                }
                return [[], static::skipStatement($tokens, $startIndex)];
            }

            if ($tokens[$i]->text === ';') {
                $i++;
                break;
            }

            // Unexpected token; bail out by skipping statement.
            return [[], static::skipStatement($tokens, $startIndex)];
        }

        if ($alias === null) {
            $parts = explode('\\', $fqcn);
            $alias = end($parts) ?: $fqcn;
        }

        return [[$alias => $fqcn], $i];
    }

    /**
     * @param array<PhpToken> $tokens
     */
    protected static function skipIgnorable(array $tokens, int $index): int
    {
        $count = count($tokens);
        while ($index < $count && $tokens[$index]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
            $index++;
        }
        return $index;
    }

    /**
     * Parse a qualified name at the current index. Advances the index by reference.
     *
     * @param array<PhpToken> $tokens
     */
    protected static function parseQualifiedName(array $tokens, int &$index): string|null
    {
        $count = count($tokens);
        $index = static::skipIgnorable($tokens, $index);
        if ($index >= $count) {
            return null;
        }

        $parts = '';
        while ($index < $count) {
            $t = $tokens[$index];
            if ($t->is([T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $parts .= $t->text;
                $index++;
                continue;
            }
            break;
        }

        $parts = trim($parts);
        if ($parts === '') {
            return null;
        }

        return $parts;
    }

}
