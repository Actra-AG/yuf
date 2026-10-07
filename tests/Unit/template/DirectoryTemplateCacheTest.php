<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\clock\FixedClock;
use actra\yuf\template\template\DirectoryTemplateCache;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;

final class DirectoryTemplateCacheTest extends TestCase
{
    private string $cachePath;

    #[Override]
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-template-cache-test-' . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->cachePath);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->cachePath . '*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->cachePath);
    }

    private function createCache(): DirectoryTemplateCache
    {
        return new DirectoryTemplateCache(
            cachePath: $this->cachePath,
            templateBaseDirectory: '/templates/',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1800000000')),
        );
    }

    public function testNewCacheEntryHasTheTimeOfTheClock(): void
    {
        $entry = $this->createCache()->addCachedTplFile(
            tplFile: '/templates/page.html',
            currentCacheEntry: null,
            compiledTemplateContent: 'compiled',
        );

        $this->assertSame(1_800_000_000, $entry->changeTime);
        $this->assertSame('page.php', $entry->path);
        $this->assertFileExists($this->cachePath . 'page.php');
    }

    public function testUpdatedCacheEntryHasTheTimeOfTheClock(): void
    {
        $cache = $this->createCache();
        $cache->addCachedTplFile(tplFile: '/templates/page.html', currentCacheEntry: null, compiledTemplateContent: 'a');

        $entry = $cache->addCachedTplFile(tplFile: '/templates/page.html', currentCacheEntry: null, compiledTemplateContent: 'b');

        $this->assertSame(1_800_000_000, $entry->changeTime);
        $this->assertSame('b', file_get_contents(filename: $this->cachePath . 'page.php'));
    }
}
