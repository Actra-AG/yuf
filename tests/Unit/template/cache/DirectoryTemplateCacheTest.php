<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\cache;

use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\compiler\TemplateCompiler;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use Override;
use PHPUnit\Framework\TestCase;

final class DirectoryTemplateCacheTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private DirectoryTemplateCache $cache;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->cache = new DirectoryTemplateCache(
            cacheDirectory: $this->workDirectory->cacheDirectory,
            templateBaseDirectory: $this->workDirectory->templateDirectory,
        );
        clearstatcache();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    private function templatePath(string $relativePath): string
    {
        return $this->workDirectory->templateDirectory . $relativePath;
    }

    private function writeTemplate(string $relativePath, int $modificationTime): string
    {
        $templateFile = $this->templatePath(relativePath: $relativePath);
        if (!is_dir(filename: dirname(path: $templateFile))) {
            mkdir(directory: dirname(path: $templateFile), recursive: true);
        }
        file_put_contents(filename: $templateFile, data: 'template');
        touch(filename: $templateFile, mtime: $modificationTime);
        clearstatcache();

        return $templateFile;
    }

    public function testNothingIsFoundBeforeTheTemplateIsStored(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);

        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testStoredCodeIsFoundAndWrittenToTheReturnedFile(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);

        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: '<?php echo 1;');

        $this->assertSame('<?php echo 1;', file_get_contents(filename: $compiledFile));
        $this->assertSame($compiledFile, $this->cache->find(templateFile: $templateFile));
    }

    public function testTheCacheKeyContainsTheFormatVersionAndTheRelativePath(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'sub/dir/page.html', modificationTime: 1_000);

        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'x');

        $this->assertSame(
            $this->workDirectory->cacheDirectory . 'v' . TemplateCompiler::FORMAT_VERSION . '/sub/dir/page.html.php',
            $compiledFile,
        );
    }

    public function testACompiledFileOfAnotherFormatVersionIsNeverUsed(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);
        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'current');
        $otherVersionFile = str_replace(
            search: '/v' . TemplateCompiler::FORMAT_VERSION . '/',
            replace: '/v' . TemplateCompiler::FORMAT_VERSION . '0/',
            subject: $compiledFile,
        );
        mkdir(directory: dirname(path: $otherVersionFile), recursive: true);
        file_put_contents(filename: $otherVersionFile, data: 'old compiler');
        unlink(filename: $compiledFile);

        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testACompiledFileIsOutdatedWhenTheTemplateIsNewer(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);
        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'x');
        touch(filename: $compiledFile, mtime: 2_000);
        clearstatcache();
        $this->assertSame($compiledFile, $this->cache->find(templateFile: $templateFile));

        touch(filename: $templateFile, mtime: 3_000);
        clearstatcache();

        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testACompiledFileIsOutdatedWhenTheTemplateHasTheSameTime(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 2_000);
        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'x');
        touch(filename: $compiledFile, mtime: 2_000);
        clearstatcache();

        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testNothingIsFoundForAMissingTemplate(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);
        $this->cache->store(templateFile: $templateFile, compiledCode: 'x');
        unlink(filename: $templateFile);
        clearstatcache();

        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testStoringAgainReplacesTheCode(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);
        $this->cache->store(templateFile: $templateFile, compiledCode: 'first');

        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'second');

        $this->assertSame('second', file_get_contents(filename: $compiledFile));
    }

    public function testStoringLeavesNoTemporaryFile(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);

        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'a');
        $this->cache->store(templateFile: $templateFile, compiledCode: 'b');

        $this->assertSame([$compiledFile], glob(pattern: dirname(path: $compiledFile) . '/*'));
    }

    public function testCacheDirectoriesAndFilesAreGroupWritable(): void
    {
        $templateFile = $this->writeTemplate(relativePath: 'a/b/page.html', modificationTime: 1_000);
        $previousUmask = umask(mask: 0);

        try {
            $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'x');
        } finally {
            umask(mask: $previousUmask);
        }

        $this->assertSame(0o775, fileperms(filename: dirname(path: $compiledFile)) & 0o777);
        $this->assertSame(0o775, fileperms(filename: dirname(path: $compiledFile, levels: 2)) & 0o777);
        $this->assertSame(0o664, fileperms(filename: $compiledFile) & 0o777);
    }

    public function testATemplateOutsideTheBaseDirectoryIsCachedUnderAHashOfItsPath(): void
    {
        $outsideFile = $this->workDirectory->cacheDirectory . 'outside.html';
        file_put_contents(filename: $outsideFile, data: 'template');

        $compiledFile = $this->cache->store(templateFile: $outsideFile, compiledCode: 'x');

        $this->assertSame(
            $this->workDirectory->cacheDirectory . 'v' . TemplateCompiler::FORMAT_VERSION . '/external/'
                . hash(algo: 'sha256', data: $outsideFile) . '.php',
            $compiledFile,
        );
    }

    public function testAPathWithParentDirectoriesNeverLeavesTheCacheDirectory(): void
    {
        $templateFile = $this->templatePath(relativePath: '../outside.html');
        file_put_contents(filename: $templateFile, data: 'template');

        $compiledFile = $this->cache->store(templateFile: $templateFile, compiledCode: 'x');

        $this->assertStringStartsWith(
            $this->workDirectory->cacheDirectory . 'v' . TemplateCompiler::FORMAT_VERSION . '/external/',
            $compiledFile,
        );
        $this->assertStringNotContainsString('..', $compiledFile);
    }

    public function testBaseDirectoryWithAndWithoutTrailingSlashIsTheSame(): void
    {
        $cache = new DirectoryTemplateCache(
            cacheDirectory: rtrim(string: $this->workDirectory->cacheDirectory, characters: '/'),
            templateBaseDirectory: rtrim(string: $this->workDirectory->templateDirectory, characters: '/'),
        );
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);

        $this->assertSame(
            $this->cache->getCompiledFile(templateFile: $templateFile),
            $cache->getCompiledFile(templateFile: $templateFile),
        );
    }

    public function testWithoutCheckingChangesAnOlderCompiledFileIsUsed(): void
    {
        $cache = new DirectoryTemplateCache(
            cacheDirectory: $this->workDirectory->cacheDirectory,
            templateBaseDirectory: $this->workDirectory->templateDirectory,
            checkTemplateChanges: false,
        );
        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);
        $compiledFile = $cache->store(templateFile: $templateFile, compiledCode: 'x');
        touch(filename: $templateFile, mtime: time() + 100);
        clearstatcache();

        $this->assertSame($compiledFile, $cache->find(templateFile: $templateFile));
        $this->assertNull($this->cache->find(templateFile: $templateFile));
    }

    public function testWithoutCheckingChangesATemplateWithoutCompiledFileIsNotFound(): void
    {
        $cache = new DirectoryTemplateCache(
            cacheDirectory: $this->workDirectory->cacheDirectory,
            templateBaseDirectory: $this->workDirectory->templateDirectory,
            checkTemplateChanges: false,
        );

        $templateFile = $this->writeTemplate(relativePath: 'page.html', modificationTime: 1_000);

        $this->assertNull($cache->find(templateFile: $templateFile));
    }
}
