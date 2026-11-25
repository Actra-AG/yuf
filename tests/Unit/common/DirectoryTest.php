<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\Directory;
use ErrorException;
use Override;
use PHPUnit\Framework\TestCase;

final class DirectoryTest extends TestCase
{
    private string $baseDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-directory-'
            . bin2hex(string: random_bytes(length: 8));
        // Like the ErrorHandler of yuf: every warning becomes an exception
        set_error_handler(callback: static function (int $severity, string $message): never {
            throw new ErrorException(message: $message, severity: $severity);
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        foreach (['/a/b', '/a', '/file', ''] as $suffix) {
            $path = $this->baseDirectory . $suffix;
            if (is_dir(filename: $path)) {
                rmdir(directory: $path);
            } elseif (is_file(filename: $path)) {
                unlink(filename: $path);
            }
        }
    }

    public function testCreatesTheDirectoryRecursively(): void
    {
        $path = $this->baseDirectory . '/a/b';

        self::assertTrue(Directory::create(path: $path, permissions: 0o775, recursive: true));
        self::assertDirectoryExists($path);
    }

    public function testADirectoryCreatedByAParallelRequestIsNoError(): void
    {
        // The other request created the directory between the is_dir() check of the caller and mkdir()
        mkdir(directory: $this->baseDirectory . '/a/b', recursive: true);

        self::assertTrue(
            Directory::create(path: $this->baseDirectory . '/a/b', permissions: 0o775, recursive: true),
        );
    }

    public function testReturnsFalseIfTheDirectoryCannotBeCreated(): void
    {
        mkdir(directory: $this->baseDirectory);
        touch(filename: $this->baseDirectory . '/file');

        self::assertFalse(
            Directory::create(path: $this->baseDirectory . '/file', permissions: 0o775, recursive: false),
        );
    }
}
