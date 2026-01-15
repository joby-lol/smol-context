<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

/**
 * The main static context injector. This works similarly to a normal dependency injection container, but it is static
 * and does not require any instantiation or configuration. It is designed to be used in a static context so that it
 * can be used anywhere in the codebase without needing to pass it around.
 *
 * It is also designed to gracefully allow stepping into and out of additional contexts, which are kept on a stack so
 * that when you end a context, you revert to the prior one. This is useful for things like atomic rollbacks, building
 * additional requests without impacting the main one, etc.
 *
 * There are also global functions for accessing the main functionality in an even more convenient way. They are simply
 * aliases of the main methods of this class and the Invoker:
 *
 * Get an object:
 * Context::get() => ctx()
 *
 * Register a class or object:
 * Context::register() => ctx_register()
 *
 * Execute a callable with injectable dependencies:
 * Context::get(Invoker::class)->execute() => ctx_execute()
 *
 * Include a file with injectable type-hinted variables:
 * Context::get(Invoker::class)->include() => ctx_include()
 */
class Context
{
    /**
     * @var array<Container>
     */
    protected static array $stack = [];
    protected static Container|null $current;

    /**
     * Get an object of the given class, either by retrieving a built copy of it or by instantiating it for the first time if necessary.
     *
     * @template T of object
     * @param class-string<T> $class    the class of object to retrieve
     *
     * @return T
     *
     * @throws NotFoundException  No entry was found for **this** identifier
     * @throws ContainerException Error while retrieving the entry
     */
    public static function get(string $class): object
    {
        return static::container()->get($class);
    }

    /**
     * Build a new object of the given class. It will not be cached or stored anywhere else.
     *
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function new(string $class): object
    {
        return static::container()->invoker->instantiate($class);
    }

    /**
     * Entirely reset the context, clearing the current Container as well as the stack.
     */
    public static function reset(): void
    {
        static::$stack = [];
        static::$current = null;
    }

    /**
     * Get the current container, creating a new one if there is not one.
     */
    public static function container(): Container
    {
        return static::$current ??= static::createContainer();
    }

    /**
     * Open a new context from an arbitrary Container.
     */
    public static function openFromContainer(Container $container): void
    {
        static::$stack[] = static::container();
        static::$current = $container;
    }

    /**
     * Begin a new context from a clone of the current Container.
     */
    public static function openFromClone(): void
    {
        static::$stack[] = static::container();
        static::$current = clone static::container();
    }

    /**
     * Begin a new context from a brand new empty Container.
     */
    public static function openEmpty(): void
    {
        static::$stack[] = static::container();
        static::$current = static::createContainer();
    }

    /**
     * Close the current context and revert to the prior one on the stack.
     */
    public static function close(): void
    {
        static::$current = array_pop(static::$stack);
    }

    /**
     * Register a class or object to the context so that it can be retrieved later using the get() method. This will also register all parent classes and interfaces of the given class so that it can be retrieved using any of them.
     * 
     * If a class is given, it will be instantiated the first time it is requested. If an object is given, it will be saved as a built object and can be retrieved directly without instantiation.
     *
     * @param class-string|object $class    the class name or object to register
     *
     * @throws ContainerException if an error occurs while registering the class
     */
    public static function register(string|object $class): void
    {
        static::container()->register($class);
    }

    /**
     * Check if a class is registered in the context, without instantiating it. This is useful for checking if a class is available without the overhead of instantiation.
     *
     * @param class-string $class
     */
    public static function has(string $class): bool
    {
        return static::container()->has($class);
    }

    /**
     * Create a new empty Container. This is in its own method so that it can be overridden by child classes if you want to create your own domain-specific Context class based on this one.
     */
    protected static function createContainer(): Container
    {
        return new Container();
    }
}