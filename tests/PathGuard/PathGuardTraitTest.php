<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\PathGuard;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PathGuardTraitTest extends TestCase
{
    private string $baseDir;

    public function testCheckReturnsFalseForNonExistentFile(): void
    {
        $guard = new PathGuardTraitHarness();
        $this->assertFalse($guard->check($this->baseDir . DIRECTORY_SEPARATOR . 'does_not_exist.php'));
    }

    public function testAllowDirectoryAllowsFilesInside(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'allowedDir');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file1.php');

        $guard = new PathGuardTraitHarness();
        $guard->allowDirectory($sub);

        $this->assertTrue($guard->check($file));
    }

    public function testDenyDirectoryDeniesFilesInside(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'deniedDir');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file1.php');

        $guard = new PathGuardTraitHarness();
        $guard->denyDirectory($sub);

        $this->assertFalse($guard->check($file));
    }

    public function testFileRulePrecedence_FileDenied_OverAllowedDirectory(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'mixDir');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file1.php');

        $guard = new PathGuardTraitHarness();
        $guard->allowDirectory($sub);
        $guard->denyFile($file);

        // File rule should win -> denied
        $this->assertFalse($guard->check($file));
    }

    public function testFileRulePrecedence_FileAllowed_OverDeniedDirectory(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'mixDir2');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file1.php');

        $guard = new PathGuardTraitHarness();
        $guard->denyDirectory($sub);
        $guard->allowFile($file);

        // File rule should win -> allowed
        $this->assertTrue($guard->check($file));
    }

    public function testDenyOverridesAllowForDirectories(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'toggleDir1');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file.php');

        $guard = new PathGuardTraitHarness();
        $guard->allowDirectory($sub);
        // Now reverse: deny the same directory. This should remove it from allowed.
        $guard->denyDirectory($sub);

        $this->assertFalse($guard->check($file));
    }

    public function testAllowOverridesDenyForDirectories(): void
    {
        $sub = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'toggleDir2');
        $file = $this->touch($sub . DIRECTORY_SEPARATOR . 'file.php');

        $guard = new PathGuardTraitHarness();
        $guard->denyDirectory($sub);
        // Now allow the same directory. This should remove it from denied.
        $guard->allowDirectory($sub);

        $this->assertTrue($guard->check($file));
    }

    public function testAllowFileThenDenyFile_TogglesToDenied(): void
    {
        $file = $this->touch($this->baseDir . DIRECTORY_SEPARATOR . 'flipFile1.php');

        $guard = new PathGuardTraitHarness();
        $guard->allowFile($file);
        $this->assertTrue($guard->check($file));

        $guard->denyFile($file);
        $this->assertFalse($guard->check($file));
    }

    public function testDenyFileThenAllowFile_TogglesToAllowed(): void
    {
        $file = $this->touch($this->baseDir . DIRECTORY_SEPARATOR . 'flipFile2.php');

        $guard = new PathGuardTraitHarness();
        $guard->denyFile($file);
        $this->assertFalse($guard->check($file));

        $guard->allowFile($file);
        $this->assertTrue($guard->check($file));
    }

    public function testAllowDirectoryWithInvalidPathThrows(): void
    {
        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid directory');
        $guard->allowDirectory($this->baseDir . DIRECTORY_SEPARATOR . 'no_such_dir');
    }

    public function testDenyDirectoryWithInvalidPathThrows(): void
    {
        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid directory');
        $guard->denyDirectory($this->baseDir . DIRECTORY_SEPARATOR . 'no_such_dir');
    }

    public function testAllowDirectoryWithFilePathThrows(): void
    {
        $file = $this->touch($this->baseDir . DIRECTORY_SEPARATOR . 'not_a_dir.php');

        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory does not exist');
        $guard->allowDirectory($file);
    }

    public function testDenyDirectoryWithFilePathThrows(): void
    {
        $file = $this->touch($this->baseDir . DIRECTORY_SEPARATOR . 'not_a_dir2.php');

        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory does not exist');
        $guard->denyDirectory($file);
    }

    public function testAllowFileWithInvalidPathThrows(): void
    {
        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file');
        $guard->allowFile($this->baseDir . DIRECTORY_SEPARATOR . 'no_file_here.php');
    }

    public function testDenyFileWithInvalidPathThrows(): void
    {
        $guard = new PathGuardTraitHarness();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file');
        $guard->denyFile($this->baseDir . DIRECTORY_SEPARATOR . 'no_file_there.php');
    }

    public function testDirectoryRuleDoesNotAccidentallyAllowOutsideFiles(): void
    {
        // Sanity check: explicitly allowed directory should allow files within it,
        // but not allow a sibling directory.
        $allowedDir = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'dirA');
        $allowedFile = $this->touch($allowedDir . DIRECTORY_SEPARATOR . 'a.php');

        $otherDir = $this->mkdir($this->baseDir . DIRECTORY_SEPARATOR . 'dirB');
        $otherFile = $this->touch($otherDir . DIRECTORY_SEPARATOR . 'b.php');

        $guard = new PathGuardTraitHarness();
        $guard->allowDirectory($allowedDir);

        $this->assertTrue($guard->check($allowedFile));
        $this->assertFalse($guard->check($otherFile));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'include_guard_tests_' . uniqid();
        $this->mkdir($this->baseDir);
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->baseDir);
        parent::tearDown();
    }

    private function mkdir(string $path): string
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true) && !is_dir($path)) {
                $this->fail('Failed to create directory: ' . $path);
            }
        }
        $real = realpath($path);
        $this->assertNotFalse($real, 'realpath should resolve directory');
        return $real ?: $path;
    }

    private function touch(string $path): string
    {
        $dir = dirname($path);
        $this->mkdir($dir);
        if (file_put_contents($path, "<?php\n") === false) {
            $this->fail('Failed to create file: ' . $path);
        }
        $real = realpath($path);
        $this->assertNotFalse($real, 'realpath should resolve file');
        return $real ?: $path;
    }

    private function cleanupDir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        if (is_file($dir) || is_link($dir)) {
            @unlink($dir);
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->cleanupDir($dir . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($dir);
    }
}