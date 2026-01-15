<?php

/*
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Invoker;

use Joby\Smol\Context\Config\Config;
use Joby\Smol\Context\Config\DefaultConfig;
use Joby\Smol\Context\Container;
use Joby\Smol\Context\TestClasses\TestClass_requires_A_and_B;
use Joby\Smol\Context\TestClasses\TestClassA;
use Joby\Smol\Context\TestClasses\TestClassB;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DefaultInvokerTest extends TestCase
{

    public function testEmptyInstantiation(): void
    {
        // Note that we really only test really basic instantiation here,
        // because more complex instantiation with dependencies is tested in
        // ContextTest.
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $this->assertInstanceOf(
            TestClassA::class,
            $inv->instantiate(TestClassA::class),
        );
        $this->assertInstanceOf(
            TestClassB::class,
            $inv->instantiate(TestClassB::class),
        );
    }

    public function testExecutionFromString(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $this->assertEquals(
            'Hello, world!',
            $inv->execute('Joby\Smol\Context\Invoker\testFunction'),
        );
    }

    public function testEmptyExecution(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $this->assertEquals(
            'Hello, world!',
            $inv->execute(function () {
                return 'Hello, world!';
            }),
        );
        $this->assertEquals(
            'Hello, world!',
            $inv->execute(testFunction(...)),
        );
        $this->assertEquals(
            'TestClassA static string',
            $inv->execute(TestClassA::getStaticString(...)),
        );
        $this->assertEquals(
            'TestClassA instance string',
            $inv->execute((new TestClassA())->getInstanceString(...)),
        );
    }

    public function testExecutionWithDependencies(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $a = new TestClassA();
        $b = new TestClassB();
        $con->register($a);
        $con->register($b);
        $this->assertEquals(
            $a,
            $inv->execute(function (TestClassA $a): TestClassA {
                return $a;
            }),
        );
        $this->assertEquals(
            $b,
            $inv->execute(function (TestClassB $b): TestClassB {
                return $b;
            }),
        );
        // now change the context to use new instances, and it should return those
        $a2 = new TestClassA();
        $b2 = new TestClassB();
        $con->register($a2);
        $con->register($b2);
        $this->assertEquals(
            $a2,
            $inv->execute(function (TestClassA $a): TestClassA {
                return $a;
            }),
        );
        $this->assertEquals(
            $b2,
            $inv->execute(function (TestClassB $b): TestClassB {
                return $b;
            }),
        );
        $this->assertNotEquals(
            $a,
            $inv->execute(function (TestClassA $a): TestClassA {
                return $a;
            }),
        );
        $this->assertNotEquals(
            $b,
            $inv->execute(function (TestClassB $b): TestClassB {
                return $b;
            }),
        );
    }

    public function testInstantiationWithDependencies(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $a = new TestClassA();
        $b = new TestClassB();
        $con->register($a);
        $con->register($b);
        $c = $inv->instantiate(TestClass_requires_A_and_B::class);
        $this->assertInstanceOf(
            TestClass_requires_A_and_B::class,
            $c,
        );
        // check that the dependencies were injected correctly
        $this->assertEquals($a, $c->a);
        $this->assertEquals($b, $c->b);
    }

    public function testParameterConfigValueAttribute(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $config = $con->get(Config::class);
        $config->set('test_key', 'test_value');
        $this->assertEquals(
            'test_value',
            $inv->execute(function (#[ConfigValue('test_key')] string $value) {
                return $value;
            }),
        );
    }

    public function testOptionalConfigParameters(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);

        // Test with default value when config key doesn't exist
        $result = $inv->execute(function (#[ConfigValue('missing.key')] string $param = 'default value') {
            return $param;
        });

        $this->assertEquals('default value', $result);

        // Test that config value overrides default when present
        $config = $con->get(Config::class);
        $config->set('existing.key', 'config value');

        $result = $inv->execute(function (#[ConfigValue('existing.key')] string $param = 'default value') {
            return $param;
        });

        $this->assertEquals('config value', $result);
    }

    public function testConfigTypeValidation(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);

        // Set up test values
        $con->config->set('string.key', 'string value');
        $con->config->set('int.key', 42);
        $con->config->set('bool.key', true);
        $con->config->set('array.key', ['test']);

        // Test correct types pass validation
        $this->assertEquals('string value', $inv->execute(
            fn(#[ConfigValue('string.key')] string $param) => $param
        ));

        $this->assertEquals(42, $inv->execute(
            fn(#[ConfigValue('int.key')] int $param) => $param
        ));

        $this->assertTrue($inv->execute(
            fn(#[ConfigValue('bool.key')] bool $param) => $param
        ));

        $this->assertEquals(['test'], $inv->execute(
            fn(#[ConfigValue('array.key')] array $param) => $param
        ));
    }

    public function testConfigTypeValidationFailures(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $config = $con->get(Config::class);

        // Set string value but try to use as int
        $config->set('wrong.type', 'not an integer');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config value from "wrong.type" expected to be of type int, got string');

        $inv->execute(function (#[ConfigValue('wrong.type')] int $param) {
        });
    }

    public function testNullableConfigParameters(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $config = $con->get(Config::class);

        // Test that null is allowed for nullable parameters
        $config->set('nullable.key', null);

        $result = $inv->execute(function (#[ConfigValue('nullable.key')] ?string $param) {
            return $param;
        });

        $this->assertNull($result);
    }

    public function testMissingRequiredConfigParameter(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config value for key missing.key does not exist.');

        $inv->execute(function (#[ConfigValue('missing.key')] string $param) {
        });
    }

    public function testUnionTypesWithConfig(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $config = $con->get(Config::class);

        // Test int|string union type with int value
        $config->set('union.key', 42);
        $result = $inv->execute(function (#[ConfigValue('union.key')] int|string $param) {
            return $param;
        });
        $this->assertEquals(42, $result);

        // Test int|string union type with string value
        $config->set('union.key', 'test');
        $result = $inv->execute(function (#[ConfigValue('union.key')] int|string $param) {
            return $param;
        });
        $this->assertEquals('test', $result);

        // Test int|string union type with an invalid value
        $config->set('union.key', true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config value from "union.key" expected to be of type int|string, got bool');
        $inv->execute(function (#[ConfigValue('union.key')] int|string $param) {
        });
    }

    public function testNullableUnionTypes(): void
    {
        $con = new Container();
        $inv = new DefaultInvoker($con);
        $config = $con->get(Config::class);

        // Test null with a nullable union type
        $config->set('nullable.union.key', null);
        $result = $inv->execute(function (#[ConfigValue('nullable.union.key')] null|int|string $param) {
            return $param;
        });
        $this->assertNull($result);

        // Test int with a nullable union type
        $config->set('nullable.union.key', 42);
        $result = $inv->execute(function (#[ConfigValue('nullable.union.key')] null|int|string $param) {
            return $param;
        });
        $this->assertEquals(42, $result);
    }

}

function testFunction(): string
{
    return 'Hello, world!';
}
