<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Config;

use Throwable;

/**
 * A basic implementation of the Config interface. Includes a basic key/value store, and hooks for adding callbacks to
 * optionally retrieve values from outside sources.
 */
class DefaultConfig implements Config
{
    /**
     * Default values
     *
     * @var array<string,mixed>
     */
    protected array $defaults = [];
    /**
     * Explicitly set value
     *
     * @var array<string,mixed>
     */
    protected array $values = [];
    /**
     * Values located by locators
     *
     * @var array<string,mixed>
     */
    protected array $located = [];
    /**
     * Cache to save values and save lookups/comparisons
     *
     * @var array<string,mixed>
     */
    protected array $cache = [];
    /**
     * Callbacks that may be used to locate config values that haven't been explicitly set.
     * Global locators run on any key.
     *
     * @var array<callable(string):mixed>
     */
    protected array $global_locators = [];
    /**
     * Callbacks that can locate config values, but only if they match a given prefix.
     * The locator callback will only be passed the key after the given prefix.
     * Prefix locators are higher-priority than global locators.
     *
     * @var array<string,array<callable(string):mixed>>
     */
    protected array $prefix_locators = [];

    /**
     * 
     * @param array<string,mixed> $defaults 
     * @param array<string,mixed> $values 
     * @param array<callable(string):mixed> $global_locators 
     * @param array<string,array<callable(string):mixed>> $prefix_locators 
     * @return void 
     */
    public function __construct(
        array $defaults = [],
        array $values = [],
        array $global_locators = [],
        array $prefix_locators = [],
    ) {
        $this->defaults = $defaults;
        $this->values = $values;
        $this->global_locators = $global_locators;
        $this->prefix_locators = $prefix_locators;
    }

    public function addGlobalLocator(callable $locator): void
    {
        $this->global_locators[] = $locator;
    }

    public function addPrefixLocator(string $prefix, callable $locator): void
    {
        if (!isset($this->prefix_locators[$prefix])) $this->prefix_locators[$prefix] = [];
        $this->prefix_locators[$prefix][] = $locator;
    }

    public function unset(string $key): void
    {
        unset($this->values[$key]);
        unset($this->cache[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
        unset($this->cache[$key]);
    }

    public function interpolate(string $value): string
    {
        return preg_replace_callback('/\${([^}]+)}/', function ($matches) {
            $key = $matches[1];
            if (!$this->has($key)) {
                throw new ConfigKeyNotFoundException("Config key '$key' not found, and cannot be interpolated.");
            }
            $replacement = $this->get($key);
            if (!is_scalar($replacement)) {
                throw new ConfigTypeException("Config key '$key' is not a scalar value, and cannot be interpolated.");
            }
            return (string)$replacement;
        }, $value)
            ?? throw new ConfigException("Error during interpolation of value '$value'.");
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache)
            || array_key_exists($key, $this->values)
            || array_key_exists($key, $this->defaults)
            || $this->locate($key);
    }

    public function get(string $key): mixed
    {
        try {
            return $this->cache[$key] ??= $this->doGet($key);
        } catch (ConfigException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ConfigException("Error retrieving config key '$key'", 0, $e);
        }
    }

    protected function locate(string $key): bool
    {
        if (array_key_exists($key, $this->located)) {
            return true;
        }
        foreach ($this->prefix_locators as $prefix => $locators) {
            if (str_starts_with($key, $prefix)) {
                foreach ($locators as $locator) {
                    $value = $locator(substr($key, strlen($prefix)));
                    if ($value !== null) {
                        $this->located[$key] = $value;
                        return true;
                    }
                }
            }
        }
        foreach ($this->global_locators as $locator) {
            $value = $locator($key);
            if ($value !== null) {
                $this->located[$key] = $value;
                return true;
            }
        }
        return false;
    }

    protected function doGet(string $key): mixed
    {
        if (array_key_exists($key, $this->values)) {
            $value = $this->values[$key];
        } elseif ($this->locate($key)) {
            $value = $this->located[$key];
        } elseif (array_key_exists($key, $this->defaults)) {
            $value = $this->defaults[$key];
        } else {
            throw new ConfigKeyNotFoundException("Config key '$key' not found.");
        }
        while ($value instanceof ConfigValue) {
            $value = $value->value($this);
        }
        return $value;
    }
}