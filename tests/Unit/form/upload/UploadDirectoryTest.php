<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use actra\yuf\form\upload\UploadDirectory;
use DirectoryIterator;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The root directory of the uploads: created private, refused if it is a symbolic link or belongs to someone else.
 */
final class UploadDirectoryTest extends TestCase
{
    private string $baseDirectory;
    private int $previousUmask;

    #[Override]
    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-upload-dir-test-'
            . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $this->baseDirectory);
        $this->previousUmask = umask(0);
    }

    #[Override]
    protected function tearDown(): void
    {
        umask($this->previousUmask);
        $this->removeTree(path: $this->baseDirectory);
    }

    private function removeTree(string $path): void
    {
        if (is_link(filename: $path) || is_file(filename: $path)) {
            unlink(filename: $path);

            return;
        }
        if (!is_dir(filename: $path)) {
            return;
        }
        /** @var DirectoryIterator $item */
        foreach (new DirectoryIterator(directory: $path) as $item) {
            if (!$item->isDot()) {
                $this->removeTree(path: $item->getPathname());
            }
        }
        rmdir(directory: $path);
    }

    private function permissions(string $path): int
    {
        clearstatcache();

        return fileperms(filename: $path) & 0o777;
    }

    public function testRootDirectoryIsCreatedPrivateEvenWithAnOpenUmask(): void
    {
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';

        new UploadDirectory(path: $root)->ensureExists();

        $this->assertDirectoryExists($root);
        $this->assertSame(0o700, $this->permissions(path: $root));
    }

    public function testMissingParentDirectoriesAreCreatedPrivateToo(): void
    {
        $parent = $this->baseDirectory . DIRECTORY_SEPARATOR . 'app';
        $root = $parent . DIRECTORY_SEPARATOR . 'uploads';

        new UploadDirectory(path: $root)->ensureExists();

        $this->assertSame(0o700, $this->permissions(path: $parent));
        $this->assertSame(0o700, $this->permissions(path: $root));
    }

    public function testExistingRootDirectoryWithOpenPermissionsIsMadePrivate(): void
    {
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';
        mkdir(directory: $root, permissions: 0o777);

        new UploadDirectory(path: $root)->ensureExists();

        $this->assertSame(0o700, $this->permissions(path: $root));
    }

    public function testRootDirectoryThatIsASymbolicLinkIsRefused(): void
    {
        $target = $this->baseDirectory . DIRECTORY_SEPARATOR . 'target';
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';
        mkdir(directory: $target);
        symlink(target: $target, link: $root);

        $this->expectException(RuntimeException::class);

        new UploadDirectory(path: $root)->ensureExists();
    }

    public function testRootDirectoryThatIsAFileIsRefused(): void
    {
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';
        file_put_contents(filename: $root, data: 'x');

        $this->expectException(RuntimeException::class);

        new UploadDirectory(path: $root)->ensureExists();
    }

    public function testRootDirectoryOfAnotherUserIsRefused(): void
    {
        $foreignDirectory = '/usr';
        $ownUserId = function_exists(function: 'posix_geteuid') ? posix_geteuid() : getmyuid();
        if (!is_dir(filename: $foreignDirectory) || fileowner(filename: $foreignDirectory) === $ownUserId) {
            UploadDirectoryTest::markTestSkipped('No directory of another user available.');
        }

        $this->expectException(RuntimeException::class);

        new UploadDirectory(path: $foreignDirectory)->ensureExists();
    }

    public function testCheckOfAMissingRootDirectoryReportsItAndCreatesNothing(): void
    {
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';

        $this->assertFalse(new UploadDirectory(path: $root)->exists());
        $this->assertDirectoryDoesNotExist($root);
    }

    public function testCheckOfASymbolicLinkRootIsRefused(): void
    {
        $target = $this->baseDirectory . DIRECTORY_SEPARATOR . 'target';
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';
        mkdir(directory: $target);
        symlink(target: $target, link: $root);

        $this->expectException(RuntimeException::class);

        new UploadDirectory(path: $root)->exists();
    }

    public function testPointerDirectoryIsPrivateAndStoredFilesAreReadableByTheOwnerOnly(): void
    {
        $root = $this->baseDirectory . DIRECTORY_SEPARATOR . 'uploads';
        $directory = new UploadDirectory(path: $root);

        $pointerDirectory = $directory->createSubdirectory(name: 'ptr');
        $file = $pointerDirectory . DIRECTORY_SEPARATOR . 'php1';
        file_put_contents(filename: $file, data: 'x');
        chmod(filename: $file, permissions: 0o666);
        $directory->protectFile(path: $file);

        $this->assertSame($root . DIRECTORY_SEPARATOR . 'ptr', $pointerDirectory);
        $this->assertSame(0o700, $this->permissions(path: $pointerDirectory));
        $this->assertSame(0o600, $this->permissions(path: $file));
    }
}
