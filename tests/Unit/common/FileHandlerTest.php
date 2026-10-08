<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\FileHandler;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\ResponseSender;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileHandlerTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-files-' . bin2hex(
            string: random_bytes(length: 8),
        );
        mkdir(directory: $this->directory);
        mkdir(directory: $this->directory . DIRECTORY_SEPARATOR . 'upload');
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['upload', ''] as $subDirectory) {
            $path = rtrim(string: $this->directory . DIRECTORY_SEPARATOR . $subDirectory, characters: '/\\');
            $files = glob(pattern: $path . DIRECTORY_SEPARATOR . '*');
            foreach ($files === false ? [] : $files as $file) {
                if (is_file(filename: $file)) {
                    unlink(filename: $file);
                }
            }
        }
        rmdir(directory: $this->directory . DIRECTORY_SEPARATOR . 'upload');
        rmdir(directory: $this->directory);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function extensionProvider(): iterable
    {
        yield 'simple' => ['report.pdf', 'pdf'];
        yield 'last dot counts' => ['archive.tar.gz', 'gz'];
        yield 'no extension' => ['README', ''];
        yield 'empty' => ['', ''];
        yield 'dot at the end' => ['name.', ''];
        yield 'hidden file' => ['.htaccess', 'htaccess'];
        yield 'case is kept' => ['Photo.JPG', 'JPG'];
        yield 'dot in a directory name' => ['dir.d/file', 'd/file'];
    }

    #[DataProvider('extensionProvider')]
    public function testGetExtension(string $filename, string $expected): void
    {
        $this->assertSame($expected, FileHandler::getExtension(filename: $filename));
    }

    public function testRemoveFileRemovesTheFileOfTheToken(): void
    {
        $path = $this->createFile(name: 'upload/abc123.pdf');

        FileHandler::removeFile(directory: $this->directory . '/upload/', token: 'abc123', filename: 'My Report.pdf');

        $this->assertFileDoesNotExist($path);
    }

    public function testRemoveFileAcceptsADirectoryWithoutTrailingSeparator(): void
    {
        $path = $this->createFile(name: 'upload/abc123.pdf');

        FileHandler::removeFile(directory: $this->directory . '/upload', token: 'abc123', filename: 'x.pdf');

        $this->assertFileDoesNotExist($path);
    }

    public function testRemoveFileKeepsOtherFiles(): void
    {
        $other = $this->createFile(name: 'upload/abc123.png');

        FileHandler::removeFile(directory: $this->directory . '/upload/', token: 'abc123', filename: 'x.pdf');

        $this->assertFileExists($other);
    }

    public function testRemoveFileIgnoresAMissingFile(): void
    {
        FileHandler::removeFile(directory: $this->directory . '/upload/', token: 'nothing', filename: 'x.pdf');

        $this->assertDirectoryExists($this->directory . '/upload');
    }

    public function testRemoveFileNeverRemovesADirectory(): void
    {
        mkdir(directory: $this->directory . '/upload/abc123.d');

        FileHandler::removeFile(directory: $this->directory . '/upload/', token: 'abc123', filename: 'x.d');

        $this->assertDirectoryExists($this->directory . '/upload/abc123.d');
        rmdir(directory: $this->directory . '/upload/abc123.d');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function traversalProvider(): iterable
    {
        yield 'slash in the token' => ['../secret', 'x.pdf'];
        yield 'backslash in the token' => ['..\\secret', 'x.pdf'];
        yield 'empty token' => ['', 'x.pdf'];
        yield 'null byte in the token' => ["abc\0", 'x.pdf'];
        yield 'slash in the extension' => ['abc', 'x.pdf/../../secret'];
        yield 'backslash in the extension' => ['abc', 'x.pdf\\..\\secret'];
        yield 'dots and slash in the extension' => ['abc', 'x../../secret'];
    }

    #[DataProvider('traversalProvider')]
    public function testRemoveFileRejectsPathsOutsideOfTheDirectory(string $token, string $filename): void
    {
        $secret = $this->createFile(name: 'secret.pdf');
        $this->createFile(name: 'secret');

        $this->expectException(InvalidArgumentException::class);

        try {
            FileHandler::removeFile(directory: $this->directory . '/upload/', token: $token, filename: $filename);
        } finally {
            $this->assertFileExists($secret);
            $this->assertFileExists($this->directory . '/secret');
        }
    }

    public function testRenderFileSize(): void
    {
        $path = $this->createFile(name: 'size.bin', content: str_repeat(string: 'x', times: 1536));

        $this->assertSame('1.5 KB', FileHandler::renderFileSize(filePath: $path));
    }

    public function testRenderFileSizeOfAnEmptyFile(): void
    {
        $path = $this->createFile(name: 'empty.bin');

        $this->assertSame('0 B', FileHandler::renderFileSize(filePath: $path));
    }

    public function testRenderFileSizeOfAMissingFile(): void
    {
        $this->assertSame('0 KB', FileHandler::renderFileSize(filePath: $this->directory . '/missing.bin'));
    }

    public function testRenderFileSizeOfADirectory(): void
    {
        $this->assertSame('0 KB', FileHandler::renderFileSize(filePath: $this->directory));
    }

    private function createFile(string $name, string $content = ''): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents(filename: $path, data: $content);

        return $path;
    }

    public function testOutputSendsTheFileThroughTheSender(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'note.txt';
        file_put_contents(filename: $path, data: 'Hello');

        try {
            $sentResponse = RecordingResponseSender::capture(
                action: static fn(ResponseSender $sender) => new FileHandler(path: $path)->output(
                    httpRequest: HttpRequestFactory::create(),
                    responseSender: $sender,
                ),
            );
        } finally {
            unlink(filename: $path);
        }

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $sentResponse->httpStatusCode);
        $this->assertSame('note.txt', basename(path: $sentResponse->getContentFilePath() ?? ''));
    }

    public function testOutputOfAMissingFileSendsA404(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'missing.txt';

        $sentResponse = RecordingResponseSender::capture(
            action: static fn(ResponseSender $sender) => new FileHandler(path: $path)->output(
                httpRequest: HttpRequestFactory::create(),
                responseSender: $sender,
            ),
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $sentResponse->httpStatusCode);
    }
}
