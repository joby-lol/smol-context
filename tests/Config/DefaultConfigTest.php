<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Config;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class DefaultConfigTest extends TestCase
{
    public function testGettingAndSetting(): void
    {
        $c = new DefaultConfig(
            ['a' => 'default_a'],
            ['a' => 'initial_a']
        );
        $this->assertTrue($c->has('a'));
        $this->assertEquals('initial_a', $c->get('a'));
        $c->set('a', 'new_a');
        $this->assertTrue($c->has('a'));
        $this->assertEquals('new_a', $c->get('a'));
        $c->unset('a');
        $this->assertTrue($c->has('a'));
        $this->assertEquals('default_a', $c->get('a'));
    }

    public function testInterpolatedValues(): void
    {
        $config = new DefaultConfig();

        // Basic interpolation
        $config->set('name', 'John');
        $config->set('greeting', new InterpolatedValue('Hello, ${name}!'));
        $this->assertEquals('Hello, John!', $config->get('greeting'));

        // Multiple interpolations
        $config->set('first', 'John');
        $config->set('last', 'Doe');
        $config->set('full_name', new InterpolatedValue('${first} ${last}'));
        $this->assertEquals('John Doe', $config->get('full_name'));

        // Nested interpolation
        $config->set('path', '/api');
        $config->set('version', 'v1');
        $config->set('base_url', new InterpolatedValue('${path}/${version}'));
        $config->set('endpoint', new InterpolatedValue('${base_url}/users'));
        $this->assertEquals('/api/v1/users', $config->get('endpoint'));

        // Mixed literal and interpolated content
        $config->set('port', '8080');
        $config->set('url', new InterpolatedValue('http://localhost:${port}/api'));
        $this->assertEquals('http://localhost:8080/api', $config->get('url'));
    }

    public function testInterpolationWithObjectValue(): void
    {
        $config = new DefaultConfig();

        // Create a test object
        $config->set('object_value', new class() {
            public function __toString()
            {
                return 'object string';
            }
        });

        $config->set('with_object', new InterpolatedValue('Value: ${object_value}'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'object_value' is not a scalar value, and cannot be interpolated.");
        $config->get('with_object');
    }

    public function testDirectInterpolationMethod(): void
    {
        $config = new DefaultConfig();
        $config->set('key', 'value');

        // Test direct call to interpolate()
        $this->assertEquals(
            'Test value here',
            $config->interpolate('Test ${key} here')
        );

        // Test with a missing key
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'missing' not found, and cannot be interpolated.");
        $config->interpolate('Test ${missing} here');
    }

    public function testBasicLocator(): void
    {
        $storage = [];
        $config = new DefaultConfig(
            global_locators: [
                function (string $key) use (&$storage) {
                    if (array_key_exists($key, $storage)) {
                        if (is_null($storage[$key])) return new NullValue();
                        else return $storage[$key];
                    }
                    return null;
                }
            ]
        );

        // Test basic locator functionality
        $storage['test_key'] = 'test_value';
        $this->assertTrue($config->has('test_key'));
        $this->assertEquals('test_value', $config->get('test_key'));

        // Test with a null value
        $storage['null_key'] = null;
        $this->assertTrue($config->has('null_key'));
        $this->assertNull($config->get('null_key'));

        // Test cache persistence
        $storage['test_key'] = 'changed_value';
        $this->assertEquals('test_value', $config->get('test_key')); // Should return cached value
    }

    public function testInterpolationWithNonScalarValues(): void
    {
        $config = new DefaultConfig();

        // Test with array value
        $config->set('array_value', ['foo', 'bar']);
        $config->set('bad_greeting1', new InterpolatedValue('Hello, ${array_value}!'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'array_value' is not a scalar value, and cannot be interpolated.");
        $config->get('bad_greeting1');
    }

    public function testInterpolationWithMultipleErrors(): void
    {
        $config = new DefaultConfig();

        // Set up a value with multiple interpolations where one is missing
        // and another is non-scalar
        $config->set('array_value', ['foo', 'bar']);
        $config->set('complex_value', new InterpolatedValue('${nonexistent} ${array_value}'));

        // Should throw exception about the first error encountered (missing key)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'nonexistent' not found, and cannot be interpolated.");
        $config->get('complex_value');
    }

    public function testInterpolationWithNestedErrors(): void
    {
        $config = new DefaultConfig();

        // Set up nested interpolated values where the inner one fails
        $config->set('inner', new InterpolatedValue('${nonexistent}'));
        $config->set('outer', new InterpolatedValue('Hello, ${inner}!'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'nonexistent' not found, and cannot be interpolated.");
        $config->get('outer');
    }

    public function testValidScalarInterpolation(): void
    {
        $config = new DefaultConfig();

        // Test all scalar types
        $config->set('string_val', 'hello');
        $config->set('int_val', 42);
        $config->set('float_val', 3.14);
        $config->set('bool_val', true);
        $config->set('null_val', null);

        // String interpolation
        $this->assertEquals(
            'Value: hello',
            $config->interpolate('Value: ${string_val}')
        );

        // Integer interpolation
        $this->assertEquals(
            'Number: 42',
            $config->interpolate('Number: ${int_val}')
        );

        // Float interpolation
        $this->assertEquals(
            'Pi: 3.14',
            $config->interpolate('Pi: ${float_val}')
        );

        // Boolean interpolation
        $this->assertEquals(
            'Boolean: 1',
            $config->interpolate('Boolean: ${bool_val}')
        );

        // Null interpolation should throw an exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'null_val' is not a scalar value, and cannot be interpolated.");
        $this->assertEquals(
            'Null: ',
            $config->interpolate('Null: ${null_val}')
        );
    }

    public function testInterpolationWithNonexistentKey(): void
    {
        $config = new DefaultConfig();
        $config->set('greeting', new InterpolatedValue('Hello, ${nonexistent}!'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config key 'nonexistent' not found, and cannot be interpolated.");
        $config->get('greeting');
    }

    // Test prefix locators specifically
    public function testPrefixLocators(): void
    {
        $config = new DefaultConfig(
            prefix_locators: [
                'db.' => [function (string $key) {
                    return match ($key) {
                        'host' => 'localhost',
                        'port' => 3306,
                        default => null
                    };
                }],
                'cache.' => [function (string $key) {
                    return match ($key) {
                        'enabled' => true,
                        default => null
                    };
                }]
            ]
        );

        $this->assertTrue($config->has('db.host'));
        $this->assertEquals('localhost', $config->get('db.host'));
        $this->assertEquals(3306, $config->get('db.port'));
        $this->assertTrue($config->get('cache.enabled'));
        $this->assertFalse($config->has('db.nonexistent'));
    }

    // Test prefix and global locator priority
    public function testLocatorPriority(): void
    {
        $config = new DefaultConfig(
            global_locators: [
                function (string $key) {
                    return 'global';
                }
            ],
            prefix_locators: [
                'test.' => [function (string $key) {
                    return 'prefix';
                }]
            ]
        );

        // Prefix locator should take priority
        $this->assertEquals('prefix', $config->get('test.key'));
        // Global locator should be used for non-prefixed keys
        $this->assertEquals('global', $config->get('other.key'));
    }

    // Test that locators are not called again for cached values
    public function testLocatorCaching(): void
    {
        $callCount = 0;
        $config = new DefaultConfig(
            global_locators: [
                function (string $key) use (&$callCount) {
                    $callCount++;
                    return 'value';
                }
            ]
        );

        $config->get('test.key');
        $config->get('test.key');
        $this->assertEquals(1, $callCount, 'Locator should only be called once due to caching');
    }

    // Test exception when locator throws
    public function testLocatorException(): void
    {
        $config = new DefaultConfig(
            global_locators: [
                function (string $key) {
                    throw new RuntimeException('Locator error');
                }
            ]
        );

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Error retrieving config key');
        $config->get('test.key');
    }

    // Test ConfigValue resolution in locators
    public function testConfigValueInLocators(): void
    {
        $config = new DefaultConfig(
            global_locators: [
                function (string $key) {
                    return new InterpolatedValue('Value: ${other.key}');
                }
            ]
        );

        $config->set('other.key', 'test');
        $this->assertEquals('Value: test', $config->get('any.key'));
    }


}