<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class StrictTypesTest extends TestCase
{
    // The .gitignore whitelists tracked files, so only the tracked directories are scanned (local directories are not)
    private const array DIRECTORIES = ['docs', 'example', 'src', 'tests'];
    // Paths relative to the project root with generated code or without PHP code
    private const array EXCLUDED = [
        'example/app/cache',
        // Empty file to test the language file loader
        'tests/Fixture/localeEmpty.lang.php',
    ];

    public function testEveryPhpFileDeclaresStrictTypes(): void
    {
        $root = dirname(path: __DIR__, levels: 2);
        $missing = [];
        foreach (new FilesystemIterator(directory: $root) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && !$this->declaresStrictTypes(file: $file)) {
                $missing[] = $file->getPathname();
            }
        }
        foreach (StrictTypesTest::DIRECTORIES as $directory) {
            $files = new RecursiveIteratorIterator(
                iterator: new RecursiveCallbackFilterIterator(
                    iterator: new RecursiveDirectoryIterator(
                        directory: $root . '/' . $directory,
                        flags: FilesystemIterator::SKIP_DOTS,
                    ),
                    callback: static fn(SplFileInfo $file): bool => !in_array(
                        needle: substr(string: $file->getPathname(), offset: strlen(string: $root) + 1),
                        haystack: StrictTypesTest::EXCLUDED,
                        strict: true,
                    ),
                ),
            );
            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && !$this->declaresStrictTypes(file: $file)) {
                    $missing[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $missing);
    }

    private function declaresStrictTypes(SplFileInfo $file): bool
    {
        if ($file->getExtension() !== 'php') {
            return true;
        }
        $code = file_get_contents(filename: $file->getPathname());

        return $code !== false && preg_match(pattern: '/^declare\(strict_types=1\);$/m', subject: $code) === 1;
    }
}
