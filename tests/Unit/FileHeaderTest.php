<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileHeaderTest extends TestCase
{
    private const string EXPECTED_HEADER = <<<'PHP'
        <?php
        /**
         * @copyright Actra AG - https://www.actra.ch
         * @license   MIT
         */

        declare(strict_types=1);

        PHP;

    public function testEveryPhpFileStartsWithCopyrightHeaderAndStrictTypesDeclaration(): void
    {
        $invalidFilePaths = [];
        foreach (['src', 'tests', 'example'] as $directoryName) {
            foreach ($this->listPhpFilePaths(directoryPath: __DIR__ . '/../../' . $directoryName) as $filePath) {
                if (str_contains(haystack: $filePath, needle: '/example/app/cache/')) {
                    // Compiled templates, generated at runtime
                    continue;
                }
                $content = (string)file_get_contents(filename: $filePath);
                if (!str_starts_with(haystack: $content, needle: FileHeaderTest::EXPECTED_HEADER)) {
                    $invalidFilePaths[] = $filePath;
                }
            }
        }

        $this->assertSame([], $invalidFilePaths);
    }

    /**
     * @return list<string>
     */
    private function listPhpFilePaths(string $directoryPath): array
    {
        $filePaths = [];
        $iterator = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(directory: $directoryPath, flags: FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $filePaths[] = $file->getPathname();
            }
        }

        return $filePaths;
    }
}
