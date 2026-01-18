<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

use Joby\Smol\Cache\CacheInterface;
use Joby\Smol\Cache\EphemeralCache;
use Joby\Smol\Config\Config;
use Joby\Smol\Config\ConfigInterface;
use Joby\Smol\Context\Invoker\Invoker;
use Throwable;

/**
 * A container implementation that provides dependency injection and object management functionalities. This container
 * allows registrations of classes or objects, instantiation of classes on demand, and management of object
 * dependencies, optionally split across multiple named categories.
 */
class Container
{

    public readonly CacheInterface $cache;

    public readonly ConfigInterface $config;

    public readonly Invoker $invoker;

    /**
     * Array holding the classes that have been registered, including their parent classes, listed by class name, listing the class names as strings.
     *
     * The listed class names are then used to look up or instantiate objects as needed.
     *
     * @var array<class-string, class-string>
     */
    protected array $classes = [];

    /**
     * Array holding the built objects, indexed by class name. There will be multiple copies of most objects, as they are saved under all parent classes as well.
     *
     * @var array<class-string, object>
     */
    protected array $built = [];

    /**
     * List of the current dependencies that are being instantiated to detect circular dependencies.
     *
     * @var array<string, true>
     */
    protected array $instantiating = [];

    public function __construct(
        ConfigInterface|null $config = null,
        CacheInterface|null $cache = null,
    )
    {
        $this->cache = $cache ?? new EphemeralCache();
        $this->config = $config ?? new Config();
        $this->invoker = new Invoker($this);
    }

    public function __clone()
    {
        // @phpstan-ignore-next-line it's fine to assign this in __clone()
        $this->config = clone $this->config;
        // @phpstan-ignore-next-line it's fine to assign this in __clone()
        $this->invoker = new Invoker($this);
        $unique_objects = [];
        foreach ($this->built as $object) {
            $object_id = spl_object_id($object);
            if (!array_key_exists($object_id, $unique_objects))
                $unique_objects[$object_id] = clone $object;
        }
        $this->built = [];
        foreach ($unique_objects as $obj) {
            $this->register($obj);
        }
    }

    /**
     * Register a class or object to the container so that it can be retrieved later using the get() method. This will also register all parent classes and interfaces of the given class so that it can be retrieved using any of them.
     *
     * If a class string is given, it will be instantiated the first time it is requested. If an object is given, it will be saved as a built object and can be retrieved directly without instantiation.
     * 
     * NOTE: Built-in container services (Invoker, CacheInterface, Config) cannot be registered using this method.
     *
     * @param class-string|object $class    the class name or object to register
     * @param class-string|class-string[]|bool $also  optional; if true, registers under all parent classes and interfaces; if a string or array of strings, registers under those specific classes as well
     * 
     * @throws ContainerException if an error occurs while registering the class
     */
    public function register(
        string|object $class,
        string|array|bool $also = false,
    ): void
    {
        // if the class is an object, get its class name
        if (is_object($class)) {
            $object = $class;
            $class = get_class($class);
        }
        // disallow registering built-in container services
        if (
            is_a($class, Invoker::class, true)
            || is_a($class, CacheInterface::class, true)
            || is_a($class, Config::class, true)
        )
            throw new ContainerException(
                "Cannot register $class because it is provided by the container itself.",
            );
        // build list of all classes to register under
        $all_classes = [];
        // if $also is true, automatically get all parent classes and interfaces
        if ($also === true) {
            try {
                $all_classes = $this->allClasses($class);
            }
            catch (Throwable $th) {
                throw new ContainerException('Error retrieving all classes for class ' . $class . ': ' . $th->getMessage(), previous: $th);
            }
        }
        // if $also is false, only register under the given class
        elseif ($also === false) {
            $all_classes = [$class];
        }
        // if $also is a string, register under that specific class as well
        elseif (is_string($also)) {
            $all_classes = [$class, $also];
        }
        // if $also is an array, register under all those specific classes as well
        elseif (is_array($also)) {
            $all_classes = $also;
            array_unshift($all_classes, $class);
        }
        // save all classes under the class name alias list
        foreach ($all_classes as $alias_class) {
            $this->classes[$alias_class] = $class;
        }
        // if there is an object, also save it under the built objects list
        if (isset($object)) {
            foreach ($all_classes as $alias_class) {
                $this->built[$alias_class] = $object;
            }
        }
    }

    /**
     * Get an object of the given class, either by retrieving a built copy of it or by instantiating it for the first time if necessary.
     *
     * @template T of object
     * @param class-string<T> $class       the class of object to retrieve
     *
     * @return T
     *
     * @throws ContainerException Error while retrieving the entry
     * @throws NotFoundException  No entry was found for **this** class
     */
    public function get(string $class): object
    {
        // short-circuit on built-in classes
        if ($class === Invoker::class)
            return $this->invoker; // @phpstan-ignore-line this is the right class
        if ($class === CacheInterface::class)
            return $this->cache; // @phpstan-ignore-line this is the right class
        if ($class === Config::class)
            return $this->config; // @phpstan-ignore-line this is the right class
        // normal get/instantiate
        $output = $this->getBuilt($class)
            ?? $this->instantiate($class);
        // otherwise return the output
        assert($output instanceof $class);
        return $output;
    }

    /**
     * Check if a class is registered in the context, without instantiating it. This is useful for checking if a class is available without the overhead of instantiation.
     *
     * @param class-string $id
     */
    public function has(
        string $id,
    ): bool
    {
        // short-circuit on built-in classes
        if ($id === Invoker::class)
            return true;
        if ($id === CacheInterface::class)
            return true;
        if ($id === Config::class)
            return true;
        // check if the class is registered
        return isset($this->classes[$id]);
    }

    /**
     * Build a new object of the given class. It will not be cached or stored anywhere else.
     *
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     */
    public function new(string $class): object
    {
        return $this->invoker->instantiate($class);
    }

    /**
     * Include a PHP file, with dependency injection for any docblock-injected parameters.
     *
     * @param string $file the path to the PHP file to include
     * @return mixed the return value of the included file, or its output if it does not return anything
     */
    public function include(string $file): mixed
    {
        return $this->invoker->include($file);
    }

    /**
     * Execute a callable, with dependency injection for any docblock-injected parameters.
     * 
     * @template T of mixed
     * @param callable(mixed...):T $callable the callable to execute
     * @return T the return value of the callable
     */
    public function execute(callable $callable): mixed
    {
        return $this->invoker->execute($callable);
    }

    /**
     * Get all the classes and interfaces that a given class inherits from or implements, including itself. This is used to ensure that all classes are retrievable even if they extend the class that is being requested.
     *
     * @param class-string $class
     *
     * @return array<class-string>
     */
    protected function allClasses(string $class): array
    {
        return $this->cache->get(
            static::class . '/allClasses/' . md5($class),
            /**
             * @return array<class-string>
             */
            function () use ($class): array {
                return array_merge(
                    [$class],                    // start with the class itself
                    class_parents($class) ?: [], // add all parent classes
                    class_implements($class) ?: [] // add all interfaces implemented by the class
                );
            }
        );
    }

    /**
     * Get the built copy of the given class if it exists.
     *
     * @template T of object
     * @param class-string<T> $class    the class of object to retrieve
     *
     * @return T|null
     */
    protected function getBuilt(string $class): object|null
    {
        // if the class is not registered, return null
        if (!$this->has($class)) {
            return null;
        }
        // return null if the built object does not exist
        if (!isset($this->built[$class])) {
            return null;
        }
        // return the built object
        assert(
            $this->built[$class] instanceof $class,
            sprintf(
                "The built object for class %s is not of the expected type (got a %s).",
                $class,
                get_class($this->built[$class]),
            ),
        );
        return $this->built[$class];
    }

    /**
     * Instantiate the given class if it has not been instantiated yet. Returns the built object when finished.
     *
     * @template T of object
     * @param class-string<T> $class    the class of object to instantiate
     *
     * @return T
     *
     * @throws ContainerException Error while instantiating
     * @throws NotFoundException No entry found for **this** identifier
     */
    protected function instantiate(string $class): object
    {
        // if the class is not registered, return null
        if (!isset($this->classes[$class])) {
            throw new NotFoundException(
                "The class $class is not registered in the context. " .
                "Did you forget to call " . get_called_class() . "::register() to register it?"
            );
        }
        // get the actual class name from the registered classes
        $actual_class = $this->classes[$class];
        // check for circular dependencies
        $dependency_key = $actual_class;
        if (isset($this->instantiating[$dependency_key])) {
            throw new ContainerException(
                "Circular dependency detected when instantiating $class. " .
                implode(' -> ', array_keys($this->instantiating))
            );
        }
        // Mark this class as currently being instantiated
        $this->instantiating[$dependency_key] = true;
        // instantiate the class and save it under the built objects
        try {
            $built = $this->get(Invoker::class)->instantiate($actual_class);
        }
        catch (Throwable $th) {
            throw new ContainerException('Error instantiating class ' . $class . ': ' . $th->getMessage(), previous: $th);
        }
        // save the built object under all parent classes and interfaces
        try {
            $all_classes = $this->allClasses($actual_class);
        }
        catch (Throwable $th) {
            throw new ContainerException('Error retrieving all classes for class ' . $class . ': ' . $th->getMessage(), previous: $th);
        }
        assert($built instanceof $class);
        foreach ($all_classes as $alias_class) {
            $this->built[$alias_class] = $built;
        }
        // clean up list of what is currently instantiating
        unset($this->instantiating[$dependency_key]);
        // return the output
        return $built;
    }

}
