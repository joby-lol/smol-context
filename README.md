# smolContext

A lightweight dependency injection container with config integration and docblock-driven file inclusion.

## Installation

```bash
composer require joby/smol-context
```

## About

smolContext provides a simple, static dependency injection container for PHP. Services are registered and automatically instantiated with their dependencies when retrieved.

**Key features:**

- **Static API**: Access the container globally via `Context` without passing it around
- **Automatic dependency resolution**: Constructor and callable parameters are injected automatically
- **Per-call dependency overrides**: Supply objects for a single execution, instantiation, or file inclusion without changing container registrations
- **Explicit registration**: Services register only under specified class names for predictable behavior
- **Config integration**: Inject config values alongside objects using `#[ConfigValue]`
- **Docblock file inclusion**: Include PHP files with variables injected from docblock annotations
- **Context stack**: Push/pop container scopes for testing, sub-requests, or rollback workflows

## Basic Usage

### Registering and Retrieving Services

```php
use Joby\Smol\Context\Context;

// Register a class (lazy-loaded on first get)
Context::register(App\UserService::class);

// Register a concrete instance
Context::register(new App\Logger());

// Get registered services
$users = Context::get(App\UserService::class);
$logger = Context::get(App\Logger::class);

// Services are cached - same instance every time
assert($logger === Context::get(App\Logger::class));
```

### Registration Aliasing

By default, services are registered only under their exact class name. This explicit behavior prevents surprises and makes dependencies clear. However, you can optionally register a service under additional class names using the `also` parameter.

```php
use Joby\Smol\Context\Context;

// Default: Only retrievable by exact class name
Context::register(App\Services\MysqlDatabase::class);
Context::get(App\Services\MysqlDatabase::class); // ✓ Works
Context::get(App\Contracts\DatabaseInterface::class); // ✗ Throws exception

// Register under a specific interface
Context::register(
    App\Services\MysqlDatabase::class,
    also: App\Contracts\DatabaseInterface::class
);
Context::get(App\Services\MysqlDatabase::class); // ✓ Works
Context::get(App\Contracts\DatabaseInterface::class); // ✓ Works (same instance)

// Register under multiple interfaces/classes
Context::register(
    App\Services\RedisCache::class,
    also: [
        App\Contracts\CacheInterface::class,
        App\Contracts\Storage::class
    ]
);
Context::get(App\Services\RedisCache::class); // ✓ Works
Context::get(App\Contracts\CacheInterface::class); // ✓ Works (same instance)
Context::get(App\Contracts\Storage::class); // ✓ Works (same instance)

// Register under ALL parent classes and interfaces
Context::register(
    App\Services\FileLogger::class,
    also: true
);
// Now retrievable by FileLogger, Logger, LoggerInterface, etc.
```

**When to use `also`:**

- `false` (default): For concrete implementations you'll reference directly
- `string`: When injecting via a specific interface or parent class (such as replacing something with a child class)
- `array`: When the same instance should satisfy multiple contracts
- `true`: For widely-used services accessed via various type hints (use sparingly)

### Autowiring

`registerNamespace()` allows entire namespaces to be registered for autowiring use. Any class in an entire namespace (including sub-namespaces) can be added with a single line.

```php
// Register all classes under a namespace
$container->registerNamespace('App\\Services');

// Classes in the namespace or child namespaces are now auto-wired on demand
$mailer = $container->get(App\Services\Mailer\SmtpMailer::class);
```

Explicit single-class registrations via `register()` will override namespace registrations, so it is always possible to override classes with your own custom sub-classes.

#### Autowiring control attributes

Autowiring may also be controlled via attributes.

`Joby\Smol\Context\Autowire` marks a class as explicitly autowireable.

`Joby\Smol\Context\NoAutowire` marks a class as explicitly non-autowireable, and takes precedence over `registerNamespace()`

#### Autowiring explicit exclusion

Any class may also be explicitly excluded from autowiring at the container level using `noAutowire()`. This method has the highest priority, and will override both `registerNamespace()` and the presence of an `Autowire` attribute.

#### Autowiring and explicit registration

Explicit registration overrides all autowiring exclusion. Even if a class is excluded from autowiring, explicitly adding it via `register()` will make it available to the container for injection.

### Creating Transient Objects

Build objects without caching them in the container:

```php
// Each call creates a new instance
$parser1 = Context::new(App\Parser::class);
$parser2 = Context::new(App\Parser::class);

assert($parser1 !== $parser2);
```

Constructor dependencies can also be supplied for an individual instance. Matching supplied objects take precedence over the container's registrations:

```php
$temporaryLogger = new App\Logger();
$generator = Context::new(App\ReportGenerator::class, $temporaryLogger);
```

### Checking for Services

```php
if (Context::has(App\UserService::class)) {
    $users = Context::get(App\UserService::class);
}
```

## Executing Callables with Injection

Execute callables with automatic parameter injection:

```php
use Joby\Smol\Context\Context;

Context::register(App\UserService::class);
Context::register(App\Logger::class);

$result = Context::execute(
    function (App\UserService $users, App\Logger $logger): string {
        $logger->log('Processing...');
        return $users->process();
    }
);
```

Type-hinted object parameters are automatically resolved from the container.

### Supplying Per-Call Overrides

Pass one or more objects after a callable to use them instead of matching objects from the container for that invocation:

```php
$temporaryLogger = new App\Logger();

$result = Context::execute(
    function (App\UserService $users, App\Logger $logger): string {
        $logger->log('Processing with a temporary logger...');
        return $users->process();
    },
    $temporaryLogger,
);
```

Here, `$logger` receives `$temporaryLogger`, while `$users` is still resolved from the container. Overrides are matched by type (including parent classes and interfaces), not by parameter position. If several supplied objects match the same parameter, the first matching object wins. Unrelated objects are ignored, and dependencies without matching overrides are resolved normally.

Overrides are **limited to the individual call**. They do not register objects or modify the container, so subsequent calls without overrides use the original dependencies. They affect object dependency injection; `#[ConfigValue]` parameters continue to resolve from configuration.

## Config Integration

Every container includes a config service (backed by `joby/smol-config`). Inject config values using the `#[ConfigValue]` attribute:

```php
use Joby\Smol\Context\Context;
use Joby\Smol\Context\ConfigValue;
use Joby\Smol\Config\Sources\ArraySource;

// Add config source
$runtime = new ArraySource();
$runtime['name'] = 'My Application';
$runtime['host'] = 'localhost';
Context::container()->config->addSource('app', $runtime);
Context::container()->config->addSource('db', $runtime);

// Inject config values into callables
$result = Context::execute(
    function (
        #[ConfigValue('app/name')] string $appName,
        #[ConfigValue('db/host')] string $dbHost,
    ): string {
        return "{$appName} @ {$dbHost}";
    }
);
```

### Mixing Config and Object Injection

```php
use Joby\Smol\Config\Sources\ArraySource;

Context::register(App\Logger::class);

$config = new ArraySource();
$config['debug'] = true;
Context::container()->config->addSource('app', $config);

Context::execute(
    function (
        App\Logger $logger,
        #[ConfigValue('app/debug')] bool $debug,
    ): void {
        if ($debug) {
            $logger->enableDebugMode();
        }
    }
);
```

## Including Files with Docblock Injection

Include PHP files with variables injected from docblock annotations. This is useful for templates, scripts, or configuration files that need access to services.

### The Include File

Create a file with dependencies declared in its opening docblock (`report.php`):

```php
<?php

use App\UserService;
use App\Logger;

/**
 * @var UserService $users
 * @var Logger $logger
 */

$logger->log('Generating report...');
return $users->generateReport();
```

### Including the File

```php
use Joby\Smol\Context\Context;

Context::register(App\UserService::class);
Context::register(App\Logger::class);

$report = Context::include(__DIR__ . '/report.php');
```

You can also supply objects for docblock-declared variables on a single include:

```php
$temporaryLogger = new App\Logger();
$report = Context::include(__DIR__ . '/report.php', $temporaryLogger);
```

In this example, `$logger` is the supplied instance and `$users` is resolved from the container. As with callable execution, the override does not change container registrations or affect later includes.

### Config Injection in Included Files

Docblocks don't support real PHP attributes, so config injection uses a string that looks like an attribute on the line immediately before `@var`. This isn't actually an attribute, and you don't even need to formally `use` the attribute class, it's just so that the syntax is familiar.

```php
<?php

use App\Logger;

/**
 * #[ConfigValue("app/name")]
 * @var string $appName
 *
 * @var Logger $logger
 */

$logger->log("Report for {$appName}");
```

### Type Resolution

Object types can be:
- Fully qualified: `@var \App\UserService $users`
- Imported via `use`: `@var UserService $users`
- Resolved relative to the file's namespace

## Context Stack

The context actually maintains an internal stack of containers, allowing temporary scopes for testing, isolated operations, or rollback workflows.

### Cloning the Current Container

```php
use Joby\Smol\Context\Context;

Context::register(new App\Logger());
$loggerA = Context::get(App\Logger::class);

// Create isolated scope by cloning
Context::openFromClone();
Context::register(new App\Logger());
$loggerB = Context::get(App\Logger::class);
Context::close();

$loggerC = Context::get(App\Logger::class);

// Back to original scope
assert($loggerA === $loggerC);
assert($loggerA !== $loggerB);
```

### Starting with an Empty Container

```php
Context::openEmpty();
// Fresh container with no services
Context::register(App\TestLogger::class);
// ... test code ...
Context::close();
```

### Using a Custom Container

```php
$container = new Container();
$container->register(App\MockService::class);

Context::openFromContainer($container);
// Use the custom container
Context::close();
```

### Resetting Completely

```php
// Clear stack and current container
Context::reset();
```

## Usage Patterns

### Request-Scoped Services

```php
// Register services at application bootstrap
Context::register(App\Database::class);
Context::register(App\Logger::class);

// Use throughout request handling
$router->add(
    new ExactMatcher('users'),
    function (Request $request) {
        $db = Context::get(App\Database::class);
        return Response::json($db->getUsers());
    }
);
```

### Interface-Based Dependency Injection

```php
// Register implementations under their interfaces
Context::register(
    App\Services\PdoDatabase::class,
    also: App\Contracts\DatabaseInterface::class
);

Context::register(
    App\Services\RedisCache::class,
    also: App\Contracts\CacheInterface::class
);

// Route handlers can depend on interfaces
$router->add(
    new ExactMatcher('users'),
    function (
        App\Contracts\DatabaseInterface $db,
        App\Contracts\CacheInterface $cache
    ) {
        // Dependencies injected automatically
        return Response::json($db->getUsers());
    }
);
```

### Background Jobs

```php
// Clone context for job isolation
Context::openFromClone();

try {
    $queue->add(function () {
        $mailer = Context::get(App\Mailer::class);
        $mailer->sendWelcomeEmail();
    });
} finally {
    Context::close();
}
```

### Template Rendering

Create template files that get services injected (`templates/email.php`):

```php
<?php

use App\Config;

/**
 * #[ConfigValue("app/name")]
 * @var string $appName
 *
 * #[ConfigValue("app/url")]
 * @var string $appUrl
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($appName) ?></title>
</head>
<body>
    <h1>Welcome to <?= htmlspecialchars($appName) ?></h1>
    <p>Visit us at <a href="<?= htmlspecialchars($appUrl) ?>"><?= htmlspecialchars($appUrl) ?></a></p>
</body>
</html>
```

Render templates with automatic injection:

```php
use Joby\Smol\Config\Sources\ArraySource;

$appConfig = new ArraySource();
$appConfig['name'] = 'My App';
$appConfig['url'] = 'https://example.com';
Context::container()->config->addSource('app', $appConfig);

$html = Context::include(__DIR__ . '/templates/email.php');
```

## API Reference

### Static Context Methods

- `Context::register(string|object $classOrObject, string|array|bool $also = false): void` - Register a class or instance
- `Context::get(string $class): object` - Retrieve a service (cached)
- `Context::new(string $class, object ...$override_args): object` - Create a new instance (not cached), with optional dependency overrides
- `Context::execute(callable $callable, object ...$override_args): mixed` - Execute a callable with dependency injection and optional overrides
- `Context::include(string $file, object ...$override_args): mixed` - Include a PHP file with dependency injection and optional overrides
- `Context::has(string $class): bool` - Check if service is registered
- `Context::container(): Container` - Access the current container

### Invoker Methods

The underlying `Invoker` also accepts the same per-call overrides:

- `Invoker::instantiate(string $class, object ...$override_args): object` - Instantiate a class with injected constructor dependencies
- `Invoker::execute(callable $fn, object ...$override_args): mixed` - Execute a callable with injected parameters
- `Invoker::include(string $file, object ...$override_args): mixed` - Include a PHP file with docblock-injected variables

### Stack Operations

- `Context::openFromClone(): void` - Clone current container and push it
- `Context::openEmpty(): void` - Create empty container and push it
- `Context::openFromContainer(Container $c): void` - Use custom container
- `Context::close(): void` - Pop stack and restore previous container
- `Context::reset(): void` - Clear stack and container

## Requirements

Fully tested on PHP 8.3+, static analysis for PHP 8.1+.

## License

MIT License - See [LICENSE](LICENSE) file for details.
