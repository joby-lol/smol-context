<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

class SuppliedArguments
{

    /**
     * @var object[]
     */
    protected readonly array $objects;

    public function __construct(object ...$objects)
    {
        $this->objects = $objects;
    }

    /**
     * Get an object of the given class, if it exists in this list of objects.
     *
     * @template T of object
     * @param class-string<T> $class       the class of object to retrieve
     *
     * @return T|null
     */
    public function get(string $class): object|null
    {
        foreach ($this->objects as $object)
            if (is_a($object, $class))
                return $object;
        return null;
    }

}