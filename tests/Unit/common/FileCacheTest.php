<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\FileCache;
use actra\yuf\tests\Double\clock\AdjustableClock;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $directory;
    private AdjustableClock $clock;
    private FileCache $cache;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-file-cache-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR . 'values';
        $this->clock = new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-10-09 12:00:00 UTC'));
        $this->cache = new FileCache(directory: $this->directory, clock: $this->clock);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        if (is_dir(filename: $this->directory)) {
            rmdir(directory: $this->directory);
            rmdir(directory: dirname(path: $this->directory));
        }
    }

    public function testMissingKeyIsNull(): void
    {
        $this->assertNull($this->cache->get(key: 'missing'));
    }

    public function testValueIsKeptUntilItExpires(): void
    {
        $this->cache->set(key: 'a', value: 'value of a', lifetimeInSeconds: 60);

        $this->assertSame('value of a', $this->cache->get(key: 'a'));
        $this->clock->advanceSeconds(seconds: 59);
        $this->assertSame('value of a', new FileCache(directory: $this->directory, clock: $this->clock)->get(key: 'a'));
        $this->clock->advanceSeconds(seconds: 1);
        $this->assertNull($this->cache->get(key: 'a'));
    }

    public function testFilesAreReadableByTheOwnerOnlyAndDoNotNameTheKey(): void
    {
        $this->cache->set(key: 'secret key', value: 'v', lifetimeInSeconds: 60);

        $files = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertSame(hash(algo: 'sha256', data: 'secret key') . '.json', basename(path: $files[0]));
        $this->assertSame(0o600, fileperms(filename: $files[0]) & 0o777);
        $this->assertSame(0o700, fileperms(filename: $this->directory) & 0o777);
    }

    public function testValueIsReplacedAndDeleted(): void
    {
        $this->cache->set(key: 'a', value: 'first', lifetimeInSeconds: 60);
        $this->cache->set(key: 'a', value: 'second', lifetimeInSeconds: 60);

        $this->assertSame('second', $this->cache->get(key: 'a'));
        $this->cache->delete(key: 'a');
        $this->assertNull($this->cache->get(key: 'a'));
    }

    public function testBrokenFileIsNull(): void
    {
        $this->cache->set(key: 'a', value: 'v', lifetimeInSeconds: 60);
        file_put_contents(
            filename: $this->directory . DIRECTORY_SEPARATOR . hash(algo: 'sha256', data: 'a') . '.json',
            data: '{"value":',
        );

        $this->assertNull($this->cache->get(key: 'a'));
    }

    public function testLifetimeBelowOneSecondThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->set(key: 'a', value: 'v', lifetimeInSeconds: 0);
    }
}
