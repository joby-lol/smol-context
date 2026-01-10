<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

use RuntimeException;

/**
 * Parses an included PHP file for a header docblock and converts supported tags
 * into ConfigPlaceholder/ObjectPlaceholder instances.
 */
class IncludeFileVarParser
{

    /**
     * @return array<string,ConfigPlaceholder|ObjectPlaceholder>
     */
    public static function parse(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Could not read file');
        }

        $header = IncludeFileHeaderParser::parse($content);
        if ($header->docblock === null) {
            return [];
        }

        $docblock = static::stripDocblock($header->docblock);
        $lines = preg_split('/\r?\n/', $docblock) ?: [];
        $lines = array_map(
            function (string $line): string {
                return trim(preg_replace('/^\s*\*\s*/', '', $line) ?? '');
            },
            $lines,
        );

        /** @var array<string,ConfigPlaceholder|ObjectPlaceholder> $vars */
        $vars = [];
        $currentCategory = null;
        $currentConfigKey = null;

        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }

            // check for category attribute with double quotes
            if (preg_match('/#\[CategoryName\("([^"]+)"\)\]/', $line, $matches)) {
                $currentCategory = $matches[1];
                continue;
            }
            // check for category attribute with single quotes
            if (preg_match('/#\[CategoryName\(\'([^\']+)\'\)\]/', $line, $matches)) {
                $currentCategory = $matches[1];
                continue;
            }
            // check for config value attribute with double quotes
            if (preg_match('/#\[ConfigValue\("([^"]+)"\)\]/', $line, $matches)) {
                $currentConfigKey = $matches[1];
                continue;
            }
            // check for config value attribute with single quotes
            if (preg_match('/#\[ConfigValue\(\'([^\']+)\'\)\]/', $line, $matches)) {
                $currentConfigKey = $matches[1];
                continue;
            }

            // parse @var declarations
            if (!preg_match('/@var\s+([^\s]+)\s+\$([^\s]+)/', $line, $matches)) {
                continue;
            }

            $allowNull = false;
            $type = $matches[1];
            if (str_starts_with($type, '?')) {
                $allowNull = true;
                $type = substr($type, 1);
            }
            elseif (str_starts_with($type, 'null|')) {
                $allowNull = true;
                $type = substr($type, 5);
            }
            elseif (str_ends_with($type, '|null')) {
                $allowNull = true;
                $type = substr($type, 0, -5);
            }

            $types = explode('|', $type);
            $types = array_map(
                /** @return class-string */
                function (string $type) use ($header): string {
                    // return scalar types unchanged
                    if (in_array($type, ['int', 'string', 'float', 'bool', 'array', 'false'])) {
                        return $type;
                    }

                    $typeExists = static fn(string $fqcn): bool => class_exists($fqcn) || interface_exists($fqcn);

                    // absolute class name
                    if (str_starts_with($type, '\\')) {
                        $type = substr($type, 1);
                        if (!$typeExists($type)) {
                            throw new RuntimeException("Type $type does not exist.");
                        }
                        return $type;
                    }

                    // if objects are a fully qualified class name
                    if ($typeExists($type)) {
                        return $type;
                    }

                    // resolve from top-level use statements (simple single-import only)
                    if (isset($header->uses[$type])) {
                        $resolved = $header->uses[$type];
                        if (!$typeExists($resolved)) {
                            throw new RuntimeException("Type $resolved does not exist.");
                        }
                        return $resolved;
                    }

                    // resolve relative to namespace
                    if ($header->namespace) {
                        $class = $header->namespace . '\\' . $type;
                        if ($typeExists($class)) {
                            return $class;
                        }
                    }

                    throw new RuntimeException("Could not find use statement for class $type.");
                },
                $types,
            );

            sort($types);
            $varName = $matches[2];

            if ($currentConfigKey) {
                $vars[$varName] = new ConfigPlaceholder(
                    $currentConfigKey,
                    $types,
                    false,
                    null,
                    $allowNull,
                    $currentCategory ?? 'default'
                );
            }
            else {
                if (count($types) > 1) {
                    throw new RuntimeException('Cannot use union types for objects.');
                }
                /** @var class-string $singleType */
                $singleType = reset($types);
                $vars[$varName] = new ObjectPlaceholder($singleType, $currentCategory ?? 'default');
            }
        }

        return $vars;
    }

    protected static function stripDocblock(string $docblock): string
    {
        if (preg_match('/^\/\*\*(.*?)\*\/$/s', $docblock, $matches)) {
            return $matches[1];
        }
        return $docblock;
    }

}
