<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context\Cache;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CacheWrapperTest extends TestCase
{
    public function testCacheHelperMethod()
    {
        // test in case where value is not available
        $backend = $this->createMock(CacheInterface::class);
        $wrapper = new CacheWrapper($backend);
        $backend->expects($this->once())->method('get')->with('foo')->willReturn(new NoValue());
        $backend->expects($this->once())->method('set')->with('foo', 'bar')->willReturn(true);
        $this->assertEquals('bar', $wrapper->cache('foo', fn() => 'bar'));
        // test in case where value is already available
        $backend = $this->createMock(CacheInterface::class);
        $wrapper = new CacheWrapper($backend);
        $backend->expects($this->once())->method('get')->with('foo')->willReturn('bar');
        $backend->expects($this->never())->method('set');
        $this->assertEquals('bar', $wrapper->cache('foo', fn() => throw new Exception('should not be called')));
        // confirm that it passes through custom TTL when setting
        $backend = $this->createMock(CacheInterface::class);
        $wrapper = new CacheWrapper($backend);
        $backend->expects($this->once())->method('get')->with('foo')->willReturn(new NoValue());
        $backend->expects($this->once())->method('set')->with('foo', 'bar', 123)->willReturn(true);
        $this->assertEquals('bar', $wrapper->cache('foo', fn() => 'bar', 123));
    }

    public function testPassesThroughGet()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('get')->with('foo')->willReturn('bar');
        $wrapper = new CacheWrapper($backend);
        $this->assertEquals('bar', $wrapper->get('foo'));
    }

    public function testPassesThroughSet()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('set')->with('foo', 'bar')->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->set('foo', 'bar'));
    }

    public function testPassesThroughDelete()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('delete')->with('foo')->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->delete('foo'));
    }

    public function testPassesThroughClear()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('clear')->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->clear());
    }

    public function testPassesThroughGetMultiple()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('getMultiple')->with(['foo', 'bar'])->willReturn(['foo' => 'bar']);
        $wrapper = new CacheWrapper($backend);
        $this->assertEquals(['foo' => 'bar'], $wrapper->getMultiple(['foo', 'bar']));
    }

    public function testPassesThroughSetMultiple()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('setMultiple')->with(['foo' => 'bar', 'baz' => 'qux'])->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->setMultiple(['foo' => 'bar', 'baz' => 'qux']));
    }

    public function testPassesThroughDeleteMultiple()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('deleteMultiple')->with(['foo', 'bar'])->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->deleteMultiple(['foo', 'bar']));
    }

    public function testPassesThroughHas()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())->method('has')->with('foo')->willReturn(true);
        $wrapper = new CacheWrapper($backend);
        $this->assertTrue($wrapper->has('foo'));
    }
}