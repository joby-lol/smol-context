<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

use Joby\Smol\Context\TestClasses\TestClassA;
use Joby\Smol\Context\TestClasses\TestClassB;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{

    /**
     * Test that the reset method properly clears the stack and current container.
     */
    public function testReset(): void
    {
        // First, reset and create a context
        Context::reset();
        $originalContainer = Context::container();

        // Open a new context
        Context::openEmpty();
        $newContainer = Context::container();
        $this->assertNotSame($originalContainer, $newContainer);

        // Reset the context
        Context::reset();

        // Verify that the context is reset (new container created)
        $resetContainer = Context::container();
        $this->assertNotSame($originalContainer, $resetContainer);
        $this->assertNotSame($newContainer, $resetContainer);
    }

    /**
     * Test that the new() method works correctly.
     */
    public function testNew(): void
    {
        Context::reset();
        $a = Context::new(TestClassA::class);
        $b = Context::new(TestClassA::class);
        $c = Context::new(TestClassA::class);
        $this->assertInstanceOf(TestClassA::class, $a);
        $this->assertInstanceOf(TestClassA::class, $b);
        $this->assertInstanceOf(TestClassA::class, $c);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($b, $c);
    }

    /**
     * Test that the container method returns a Container instance and creates one if none exists.
     */
    public function testContainer(): void
    {
        // Get the container, which should create a new one
        $container = Context::container();

        // Verify that we got a Container instance
        $this->assertInstanceOf(Container::class, $container);

        // Verify that getting the container again returns the same instance
        $this->assertSame($container, Context::container());
    }

    /**
     * Test opening a new context from an arbitrary Container.
     */
    public function testOpenFromContainer(): void
    {
        // Create a container
        $container = new Container();

        // Open a context from this container
        Context::openFromContainer($container);

        // Verify that the current container is the one we provided
        $this->assertSame($container, Context::container());

        // Open a new context and then close it
        $newContainer = new Container();
        Context::openFromContainer($newContainer);
        $this->assertSame($newContainer, Context::container());
        Context::close();

        // Verify we're back to the original container
        $this->assertSame($container, Context::container());
    }

    /**
     * Test opening a new context from a clone of the current Container.
     */
    public function testOpenFromClone(): void
    {
        // Get the original container
        $originalContainer = Context::container();
        $this->assertFalse($originalContainer->has(TestClassA::class));
        $originalContainer->register(new TestClassA());
        $this->assertTrue($originalContainer->has(TestClassA::class));

        // Open a new context from a clone
        Context::openFromClone();
        $clonedContainer = Context::container();

        // The cloned container should also have TestClassA registered
        $this->assertTrue($clonedContainer->has(TestClassA::class));

        // Verify that the new container is not the same as the original
        $this->assertNotSame($originalContainer, $clonedContainer);

        // Close the context and verify we're back to the original container
        Context::close();
        $this->assertSame($originalContainer, Context::container());
    }

    /**
     * Test opening a new empty context.
     */
    public function testOpenEmpty(): void
    {
        // Get the original container
        $originalContainer = Context::container();
        $this->assertFalse($originalContainer->has(TestClassA::class));
        $originalContainer->register(new TestClassA());
        $this->assertTrue($originalContainer->has(TestClassA::class));

        // Open a new empty context
        Context::openEmpty();
        $emptyContainer = Context::container();
        $this->assertFalse($emptyContainer->has(TestClassA::class));

        // Verify that the new container is not the same as the original
        $this->assertNotSame($originalContainer, $emptyContainer);

        // Close the context and verify we're back to the original container
        Context::close();
        $this->assertSame($originalContainer, Context::container());
    }

    /**
     * Test closing a context.
     */
    public function testClose(): void
    {
        // Get the original container
        $originalContainer = Context::container();

        // Open multiple new contexts
        Context::openEmpty();
        $secondContainer = Context::container();
        $this->assertNotSame($originalContainer, $secondContainer);

        Context::openEmpty();
        $thirdContainer = Context::container();
        $this->assertNotSame($originalContainer, $thirdContainer);
        $this->assertNotSame($secondContainer, $thirdContainer);

        // Close the third context
        Context::close();

        // Verify we're back to the second container
        $this->assertSame($secondContainer, Context::container());
        $this->assertNotSame($originalContainer, Context::container());

        // Close the second context
        Context::close();

        // Verify we're back to the original container
        $this->assertSame($originalContainer, Context::container());
    }

    /**
     * Test the get method for retrieving objects from the context.
     */
    public function testGet(): void
    {
        // Create a mock Container
        $mockContainer = $this->createMock(Container::class);

        // Create test objects
        $testObjectA = new TestClassA();
        $testObjectB = new TestClassB();

        // Set up expectations for the mock Container's get method
        $mockContainer->expects($this->exactly(4))
            ->method('get')
            ->willReturnMap([
                [TestClassA::class, $testObjectA],
                [TestClassB::class, $testObjectB],
            ]);

        // Inject the mock Container into the Context
        Context::reset();
        Context::openFromContainer($mockContainer);

        // Get an instance of the A class
        $instanceA = Context::get(TestClassA::class);

        // Verify that we got the expected instance
        $this->assertSame($testObjectA, $instanceA);

        // Verify that getting the class again returns the same instance
        $this->assertSame($testObjectA, Context::get(TestClassA::class));

        // Get an instance of the B class
        $instanceB = Context::get(TestClassB::class);
        $this->assertSame($testObjectB, $instanceB);
        $this->assertSame($testObjectB, Context::get(TestClassB::class));
    }

    /**
     * Test the register method for registering classes in the context.
     */
    public function testRegister(): void
    {
        // Create a mock Container
        $mockContainer = $this->createMock(Container::class);

        // Create test object
        $object = new TestClassB();

        // Set up expectations for the mock Container's register method
        $mockContainer->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(function ($class) use ($object) {
                static $calls = 0;
                $calls++;

                // Verify the correct arguments for each call
                if ($calls === 1) {
                    $this->assertSame(TestClassA::class, $class);
                }
                else if ($calls === 2) {
                    $this->assertSame($object, $class);
                }
                else if ($calls === 3) {
                    $this->assertSame(TestClassA::class, $class);
                }

                return null;
            });

        // Inject the mock Container into the Context
        Context::reset();
        Context::openFromContainer($mockContainer);

        // Register a class
        Context::register(TestClassA::class);

        // Register an object
        Context::register($object);
    }

    /**
     * Test the isRegistered method for checking if a class is registered.
     */
    public function testIsRegistered(): void
    {
        // Create a mock Container
        $mockContainer = $this->createMock(Container::class);

        // Set up expectations for the mock Container's isRegistered method
        $mockContainer->method('has')
            ->willReturnCallback(function ($class) {
                static $calls = 0;
                $calls++;

                if ($class === TestClassA::class) {
                    // First call should return false, subsequent calls should return true
                    return $calls > 1;
                }
                else if ($class === TestClassB::class) {
                    // Always return false for TestClassB
                    return false;
                }

                return false;
            });

        // Inject the mock Container into the Context
        Context::reset();
        Context::openFromContainer($mockContainer);

        // Initially, no classes are registered
        $this->assertFalse(Context::has(TestClassA::class));
        $this->assertFalse(Context::has(TestClassB::class));

        // We need to mock the register method too, even though we're not testing it here
        $mockContainer->expects($this->once())
            ->method('register')
            ->with(TestClassA::class);

        // Register a class
        Context::register(TestClassA::class);

        // Verify that the class is now registered
        $this->assertTrue(Context::has(TestClassA::class));
        $this->assertFalse(Context::has(TestClassB::class));
    }

    /**
     * Test that nested contexts work correctly with multiple levels.
     */
    public function testNestedContexts(): void
    {
        // Get the root context container
        $rootContainer = Context::container();

        // Open a new context
        Context::openFromClone();
        $middleContainer = Context::container();

        // Verify the middle container is different from the root
        $this->assertNotSame($rootContainer, $middleContainer);

        // Open another nested context
        Context::openFromClone();
        $deepContainer = Context::container();

        // Verify the deep container is different from both root and middle
        $this->assertNotSame($rootContainer, $deepContainer);
        $this->assertNotSame($middleContainer, $deepContainer);

        // Close the deepest context
        Context::close();

        // Verify that we're back to the middle context
        $this->assertSame($middleContainer, Context::container());
        $this->assertNotSame($rootContainer, Context::container());

        // Close the middle context
        Context::close();

        // Verify that we're back to the root context
        $this->assertSame($rootContainer, Context::container());
    }

    /**
     * Reset the Context before each test to ensure a clean state.
     */
    protected function setUp(): void
    {
        Context::reset();
    }

}
