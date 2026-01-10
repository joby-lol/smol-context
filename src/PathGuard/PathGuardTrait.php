<?php

namespace Joby\Smol\Context\PathGuard;

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

use InvalidArgumentException;

/**
 * Default implementation of the PathGuard interface. Implemented as a trait so that it can be used by multiple classes
 * that do not inherit from each other.
 */
trait PathGuardTrait
{
    /** @var array<string> $allowed_directories */
    protected array $allowed_directories = [];
    /** @var array<string> $denied_directories */
    protected array $denied_directories = [];
    /** @var array<string> $allowed_files */
    protected array $allowed_files = [];
    /** @var array<string> $denied_files */
    protected array $denied_files = [];

    public function check(string $filename): bool
    {
        $path = realpath($filename);
        if ($path === false) return false;
        return $this->checkFile($path)
            ?? $this->checkDirectory(dirname($path))
            ?? false;
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    public function allowDirectory(string $directory): void
    {
        $directory = realpath($directory);
        if ($directory === false) throw new InvalidArgumentException("Invalid directory");
        if (!is_dir($directory)) throw new InvalidArgumentException("Directory does not exist");
        $this->allowed_directories[] = $directory;
        $this->denied_directories = array_diff($this->denied_directories, [$directory]);
    }

    public function denyDirectory(string $directory): void
    {
        $directory = realpath($directory);
        if ($directory === false) throw new InvalidArgumentException("Invalid directory");
        if (!is_dir($directory)) throw new InvalidArgumentException("Directory does not exist");
        $this->denied_directories[] = $directory;
        $this->allowed_directories = array_diff($this->allowed_directories, [$directory]);
    }

    public function allowFile(string $file): void
    {
        $file = realpath($file);
        if ($file === false) throw new InvalidArgumentException("Invalid file");
        if (!is_file($file)) throw new InvalidArgumentException("File does not exist");
        $this->allowed_files[] = $file;
        $this->denied_files = array_diff($this->denied_files, [$file]);
    }

    public function denyFile(string $file): void
    {
        $file = realpath($file);
        if ($file === false) throw new InvalidArgumentException("Invalid file");
        if (!is_file($file)) throw new InvalidArgumentException("File does not exist");
        $this->denied_files[] = $file;
        $this->allowed_files = array_diff($this->allowed_files, [$file]);
    }

    protected function checkFile(string $filename): bool|null
    {
        if (in_array($filename, $this->denied_files)) return false;
        elseif (in_array($filename, $this->allowed_files)) return true;
        else return null;
    }

    protected function checkDirectory(string $directory): bool|null
    {
        foreach ($this->denied_directories as $denied_directory) {
            if (str_starts_with($directory, $denied_directory)) return false;
        }
        foreach ($this->allowed_directories as $allowed_directory) {
            if (str_starts_with($directory, $allowed_directory)) return true;
        }
        return null;
    }
}