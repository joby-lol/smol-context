# smolContext

A lightweight, static dependency injection container for PHP that combines simplicity with powerful features.

## What is it?

smolContext is a PHP library that provides a global static dependency injection container. It allows you to:

- Register and retrieve services/objects from anywhere in your codebase
- Automatically resolve dependencies when instantiating objects
- Execute callables with automatically injected dependencies
- Include files with docblock-based dependency injection

## Who is it for?

This library is ideal for:

- Developers who want a simple, no-configuration dependency injection solution
- Projects where passing a container through every layer is impractical
- Applications that need a balance between the simplicity of global access and the power of dependency injection
- Developers who appreciate the convenience of global functions for common operations

## Installation

```bash
composer require joby/smol-context
```

## Usage Examples

The easiest way to use this library is with a handful of global functions that are registered via Composer.
These will generally cover most use cases.

### Registering Objects and Classes

```php
// Register a class (lazy-loaded)
ctx_register(UserService::class);

// Register an object instance
$logger = new Logger();
ctx_register($logger);
```

### Retrieving Objects

```php
// Get a service from the container
$userService = ctx(UserService::class);
```

### Instantiating transient objects with Dependency Injection

```php
// Define a class with type-hinted dependencies
// Important: ctx_new will throw an exception if it can't resolve *everything* for the constructor
class SomeParserOrSomething {
    public function __construct(protected Logger $logger) {
    }
}

// Instantiate a transient object that is not saved in the container
$parser = ctx_new(SomeParserOrSomething::class);
```

### Executing Callables with Dependency Injection

```php
// Define a function with type-hinted dependencies
function processUser(UserService $userService, Logger $logger) {
    $logger->log('Processing user...');
    return $userService->process();
}

// Execute the function with automatically resolved dependencies
$result = ctx_execute('processUser');
```

### Including Files with Dependency Injection

First create a file with docblock dependencies:

```php
/**
 * @var UserService $userService
 * @var Logger $logger
 */

// these services all magically exist when the file is included via ctx_include()
$logger->log('Generating user report');
return $userService->generateReport();
```

Then you can include that file, and the docblock will be parsed to inject dependencies.

```php
// Include the file with dependencies automatically injected
$report = ctx_include('/path/to/user_report.php');
```

## Built-in Configuration System

The library includes a configuration system that allows you to inject config values as dependencies:

```php
// Set configuration values
$config = ctx(Config::class);
$config->set('app.name', 'My Application');
$config->set('db.host', 'localhost');

// Use config values as dependencies in functions
function generateReport(
    #[ConfigValue('app.name')] string $appName,
    #[ConfigValue('db.host')] string $dbHost
) {
    echo "Generating report for $appName using database at $dbHost";
}

// Execute with config values automatically injected
ctx_execute('generateReport');
```

You can also use config values in included files:

```php
// file: report.php
/**
 * #[ConfigValue('app.name')]
 * @var string $appName
 * 
 * #[ConfigValue('db.host')]
 * @var string $dbHost
 */

echo "Generating report for $appName using database at $dbHost";

// Include with config values automatically injected
ctx_include('report.php');
```

The configuration system supports:

- Type validation (string, int, bool, array, etc.)
- Optional parameters with default values
- Nullable parameters
- Union types
- String interpolation with `$config->interpolate("Value from ${config.key}")`

## Advanced Documentation

For more detailed information about the internal components and advanced features of the smolContext library,
please see the [Advanced Documentation](README_advanced.md).

## License

This project is licensed under the [MIT License](LICENSE).
