<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

use Closure;
use InvalidArgumentException;
use Joby\Smol\Config\ConfigException;
use Joby\Smol\Context\Container;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

/**
 * Handles dependency injection for callables and class instantiation. Uses reflection to analyze parameters and automatically resolves dependencies from the Container, allowing functions and constructors to be invoked with their required dependencies automatically provided.
 */
class Invoker
{

    public function __construct(protected Container $container) {}

    /**
     * Instantiate a class of the given type, resolving all its dependencies using the context injection system.
     *
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws InstantiationException if an error occurs while instantiating the class
     */
    public function instantiate(string $class): object
    {
        try {
            if (!method_exists($class, '__construct'))
                $object = new $class;
            else
                $object = new $class(...$this->buildFunctionArguments([$class, '__construct']));
        }
        catch (Throwable $th) {
            throw new InstantiationException($class, $th);
        }
        assert($object instanceof $class, "The instantiated object is not of type $class.");
        return $object;
    }

    /**
     * Include a given file, parsing for an opening docblock and resolving var tags as if they were dependencies to be loaded from the container.
     *
     * Because docblock tags don't support Attributes, their equivalents are just parsed as strings. Core attributes are available by inserting strings that look like them on lines preceding a var tag. The actual Attribute classes need not be included, because this system just looks for strings that look like `#[ConfigValue("config_key")]`.
     *
     * This method will return either the output of the included file, or the value returned by it if there is one. Note that if the included script explicitly returns the integer "1" that cannot be differentiated from returning nothing at all. Generally the best practice is to return objects if you are returning anything, for unambiguous behavior. Although non-integer values are also a reasonable choice.
     *
     * @throws IncludeException if an error occurs while including the file
     */
    public function include(string $file): mixed
    {
        try {
            // clean up path
            $path = realpath($file);
            if (!$path)
                throw new RuntimeException("File $file does not exist.");
            // check that file is readable
            if (!is_readable($path))
                throw new RuntimeException("File $file is not readable.");
            // cache further operations
            $key = md5_file($path);
            /** @var array<string,ConfigPlaceholder|ObjectPlaceholder> $vars */
            $vars = $this->cache(
                "include/vars/$key",
                /**
                 * @return array<string,ConfigPlaceholder|ObjectPlaceholder>
                 */
                function () use ($path): array {
                    return IncludeFileVarParser::parse($path);
                }
            );
            // extract variables into scope, include the file and return its output
            return include_isolated($path, $this->resolvePlaceholders($vars));
        }
        catch (IncludeException $e) {
            throw $e;
        }
        catch (Throwable $th) {
            throw new IncludeException($file, $th, '');
        }
    }

    /**
     * Execute a callable, automatically instantiating any arguments it requires from the context injection system. This allows for easy execution of functions and methods with dependencies, without needing to manually resolve anything.
     *
     * @template T of mixed
     * @param callable(mixed...):T $fn
     *
     * @return T
     *
     * @throws ExecutionException if an error occurs while executing the callable
     */
    public function execute(callable $fn): mixed
    {
        try {
            if (!($fn instanceof Closure))
                $fn = Closure::fromCallable($fn);
            $reflection = new ReflectionFunction($fn);
            // call with built arguments and return result
            return $reflection->invokeArgs($this->buildFunctionArguments($fn));
        }
        catch (Throwable $th) {
            throw new ExecutionException($th);
        }
    }

    /**
     * Helper method for caching the results of expensive operations.
     *
     * @param string   $key
     * @param callable $callback
     *
     * @return mixed
     */
    protected function cache(string $key, callable $callback): mixed
    {
        return $this->container->cache->get(
            static::class . '/' . $key,
            $callback,
        );
    }

    /**
     * @param callable|array{class-string|object,string} $fn
     *
     * @return array<mixed>
     * @throws ReflectionException
     */
    protected function buildFunctionArguments(callable|array $fn): array
    {
        if (is_string($fn)) {
            $reflection = new ReflectionFunction($fn);
            $cache_key = md5($fn);
        }
        elseif ($fn instanceof Closure) {
            $reflection = new ReflectionFunction($fn);
            $cache_key = null;
        }
        elseif (is_array($fn)) {
            assert(is_string($fn[1]), 'The second element of the array must be a method name.');
            assert(is_object($fn[0]) || (is_string($fn[0]) && class_exists($fn[0])), 'The first element of the array must be a class name or an object.');
            $reflection = new ReflectionMethod($fn[0], $fn[1]);
            $cache_key = md5(serialize([$fn[0], $fn[1]]));
        }
        else {
            throw new InvalidArgumentException('The provided callable is not a valid function or method.');
        }
        if ($cache_key) {
            /** @var array<ConfigPlaceholder|ObjectPlaceholder> $args */
            $args = $this->cache(
                "buildFunctionArguments/$cache_key",
                /** @return array<ConfigPlaceholder|ObjectPlaceholder> */
                function () use ($reflection): array {
                    return $this->doBuildFunctionArguments($reflection);
                }
            );
        }
        else {
            $args = $this->doBuildFunctionArguments($reflection);
        }
        // return $args
        return $this->resolvePlaceholders($args);
    }

    /**
     * @param ReflectionFunction|ReflectionMethod $reflection
     *
     * @return array<ConfigPlaceholder|ObjectPlaceholder>
     * @throws ReflectionException
     */
    protected function doBuildFunctionArguments(ReflectionFunction|ReflectionMethod $reflection): array
    {
        $parameters = $reflection->getParameters();
        /** @var array<ConfigPlaceholder|ObjectPlaceholder> $args */
        $args = [];
        foreach ($parameters as $param) {
            // get the type hint of the parameter
            $type = (string) $param->getType();
            assert(!empty($type), "The parameter {$param->getName()} does not have a type hint.");
            // look for a ConfigValue attribute and use it to get a value from Config if it exists
            $attr = $param->getAttributes(ConfigValue::class);
            if (count($attr) > 0) {
                $attr = $attr[0]->newInstance();
                $types = $param->getType() instanceof ReflectionUnionType
                    ? $param->getType()->getTypes()
                    : [$param->getType()];
                foreach ($types as $type) {
                    if ($type?->allowsNull())
                        $types[] = 'null';
                }
                $types = array_map(fn($type) => ltrim((string) $type, '?'), $types);
                $args[] = new ConfigPlaceholder(
                    $attr->key,
                    $types,
                    $param->isOptional(),
                    $param->isOptional() ? $param->getDefaultValue() : null,
                    $param->allowsNull(),
                );
                continue;
            }
            // get value and add it to the args list
            assert(class_exists($type), "The class $type does not exist for parameter {$param->getName()}.");
            $args[] = new ObjectPlaceholder(
                $type,
            );
        }
        return $args;
    }

    /**
     * @template TKey
     * @param array<TKey,ConfigPlaceholder|ObjectPlaceholder> $args
     * @return array<TKey,mixed>
     */
    protected function resolvePlaceholders(array $args): array
    {
        return array_map(
            function (ConfigPlaceholder|ObjectPlaceholder $arg): mixed {
                // resolve config placeholder
                if ($arg instanceof ConfigPlaceholder)
                    return $this->buildConfigValue($arg);
                // resolve object placeholder
                else
                    return $this->container->get($arg->class);
            },
            $args,
        );
    }

    protected function buildConfigValue(ConfigPlaceholder $placeholder): mixed
    {
        $config = $this->container->config;
        // first check that a value exists
        if (!$config->has($placeholder->key) && !$placeholder->is_optional)
            throw new ConfigException('Config value ' . $placeholder->key . ' is required but not found.');
        // if it doesn't exist, and is optional, return the default value
        if (!$config->has($placeholder->key))
            return $placeholder->default;
        // If there are no types, return as a string
        if (empty($placeholder->valid_types))
            return $config->getString($placeholder->key);
        // Otherwise, first try to match any object types
        foreach ($placeholder->valid_types as $type) {
            if (class_exists($type)) {
                try {
                    return $config->getObject($placeholder->key, $type);
                }
                catch (ConfigException $e) {
                    // continue trying other types
                    continue;
                }
            }
        }
        // next try the various scalar types if they're in the valid types
        if (in_array('string', $placeholder->valid_types, true)) {
            try {
                return $config->getString($placeholder->key);
            }
            catch (ConfigException $e) {
                // continue trying other types
            }
        }
        if (in_array('int', $placeholder->valid_types, true)) {
            try {
                return $config->getInt($placeholder->key);
            }
            catch (ConfigException $e) {
                // continue trying other types
            }
        }
        if (in_array('float', $placeholder->valid_types, true)) {
            try {
                return $config->getFloat($placeholder->key);
            }
            catch (ConfigException $e) {
                // continue trying other types
            }
        }
        if (in_array('bool', $placeholder->valid_types, true)) {
            try {
                return $config->getBool($placeholder->key);
            }
            catch (ConfigException $e) {
                // continue trying other types
            }
        }
        // finally try array type (there's no getArray, so we have to fudge it)
        if (in_array('array', $placeholder->valid_types, true)) {
            try {
                $value = $config->getRaw($placeholder->key);
                if (is_array($value))
                    return $value;
            }
            catch (ConfigException $e) {
                // continue trying other types
            }
        }
        // if we get here, no types matched
        throw new ConfigException(
            'Config value ' . $placeholder->key . ' could not be resolved to any of the expected types: ' .
            implode(', ', $placeholder->valid_types) .
            '.'
        );
    }

}

/**
 * Includes a PHP file in an isolated scope with extracted variables. Note that if the included script explicitly
 * returns the integer "1" that cannot be differentiated from returning nothing at all. Generally the best practice is
 * to return objects if you are returning anything, for unambiguous behavior. Although non-integer values are also a
 * reasonable choice.
 *
 * @param string              $path The path to the PHP file to be included.
 * @param array<string,mixed> $vars An associative array of variables to extract and make available in the included
 *                                  file's scope.
 *
 * @return mixed|string Returns the result of the included file if it was not the integer value 1, otherwise returns
 *                      the results of output buffering during execution.
 *
 * @throws IncludeException If an error occurs during the execution of the included file.
 */
function include_isolated(string $path, array $vars): mixed
{
    ob_start();
    try {
        extract($vars);
        $return = include $path;
    }
    catch (Throwable $th) {
        $buffer = ob_get_contents() ?: 'Error: output buffering content unavailable.';
        ob_end_clean();
        throw new IncludeException($path, $th, $buffer);
    }
    $buffer = ob_get_contents();
    ob_end_clean();
    if ($return === 1)
        return $buffer;
    return $return;
}
