<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\DirectoryPathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryPathResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'document root placeholder with parent' => ['{DOCUMENT_ROOT}../', '/srv/site/'];
        yield 'base directory placeholder' => ['{BASE_DIRECTORY}/app/', '/srv/site/app/'];
        yield 'app directory placeholder' => ['{APP_DIRECTORY}cache/', '/srv/site/app/cache/'];
        yield 'trailing separator is added' => ['{APP_DIRECTORY}logs', '/srv/site/app/logs/'];
        yield 'double separators' => ['/srv//other///dir', '/srv/other/dir/'];
        yield 'dots' => ['/srv/./a/../b/', '/srv/b/'];
        yield 'parent of the root stays the root' => ['/../../x', '/x/'];
        yield 'plain absolute path' => ['/var/data/', '/var/data/'];
    }

    #[DataProvider('pathProvider')]
    public function testPathIsResolved(string $path, string $expected): void
    {
        $resolved = DirectoryPathResolver::resolve(
            path: $path,
            documentRoot: '/srv/site/public/',
            baseDirectory: '/srv/site/',
            appDirectory: '/srv/site/app/',
        );

        $this->assertSame($expected, $resolved);
    }

    public function testPlaceholdersOfAnUnresolvedBaseAreEmpty(): void
    {
        $resolved = DirectoryPathResolver::resolve(
            path: '{BASE_DIRECTORY}/app/',
            documentRoot: '/srv/public/',
            baseDirectory: '',
            appDirectory: '',
        );

        $this->assertSame('/app/', $resolved);
    }
}
