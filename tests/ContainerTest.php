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
use Joby\Smol\Context\Invoker\Invoker;
use Joby\Smol\Context\TestClasses\CircularClassA;
use Joby\Smol\Context\TestClasses\CircularClassB;
use Joby\Smol\Context\TestClasses\TestClass_requires_A_and_B;
use Joby\Smol\Context\TestClasses\TestClassA;
use Joby\Smol\Context\TestClasses\TestClassA1;
use Joby\Smol\Context\TestClasses\TestClassB;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    /**
     * Verify that the default bare Context returns a Invoker as its invoker. This
     * may be extended at some point to include other default built-in things ... if they
     * ever come to exist.
     */
    public function testDefaults(): void
    {
        $con = new Container();
        $this->assertInstanceOf(
            Invoker::class,
            $con->get(Invoker::class)
        );
        $this->assertInstanceOf(
            Config::class,
            $con->get(Config::class)
        );
        $this->assertInstanceOf(
            EphemeralCache::class,
            $con->get(CacheInterface::class)
        );
    }

    /**
     * Test to verify that basic registration and instantiation/retrieval works, including
     * classes that require dependency injection. Also verifies that the same objects are
     * being returned.
     */
    public function testBasicRegistrationAndRetrieval(): void
    {
        $con = new Container();
        // basic classes
        $this->assertFalse($con->has(TestClassA::class));
        $con->register(TestClassA::class);
        $this->assertTrue($con->has(TestClassA::class));
        $this->assertInstanceOf(
            TestClassA::class,
            $a = $con->get(TestClassA::class)
        );
        $con->register(TestClassB::class);
        $this->assertInstanceOf(
            TestClassB::class,
            $b = $con->get(TestClassB::class)
        );
        // class that depends on A and B
        $con->register(TestClass_requires_A_and_B::class);
        $this->assertInstanceOf(
            TestClass_requires_A_and_B::class,
            $c = $con->get(TestClass_requires_A_and_B::class)
        );
        // check that the dependencies were injected correctly
        $this->assertEquals(
            $a,
            $c->a
        );
        $this->assertEquals(
            $b,
            $c->b
        );
    }

    /**
     * Test to verify that if you register a class with Context, it can also be retrieved
     * by getting any of its parent classes.
     */
    public function testRegisteringChildClasses(): void
    {
        $con = new Container();
        $con->register(TestClassA1::class);
        $this->assertInstanceOf(
            TestClassA1::class,
            $a1 = $con->get(TestClassA1::class)
        );
        $this->assertInstanceOf(
            TestClassA1::class,
            $a = $con->get(TestClassA::class)
        );
        $this->assertEquals(
            $a1,
            $a
        );
    }

    /**
     * Test to verify that circular dependencies are detected and an exception is thrown.
     */
    public function testCircularDependencyDetection(): void
    {
        $con = new Container();
        // Register both classes that form a circular dependency
        $con->register(CircularClassA::class);
        $con->register(CircularClassB::class);

        try {
            // Try to get one of the classes, which should trigger the circular dependency detection
            $con->get(CircularClassA::class);
            $this->fail('Expected exception was not thrown');
        } catch (ContainerException $e) {
            // Verify the exception message contains the expected text
            $this->assertStringContainsString('Circular dependency detected', $e->getPrevious()->getMessage());

            // Verify the dependency chain is included in the message
            $this->assertStringContainsString(CircularClassA::class, $e->getPrevious()->getMessage());
        }
    }
}