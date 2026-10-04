<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use DirectoryIterator;
use InvalidArgumentException;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\form\upload\SessionFileUploadStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tested with a temp directory and a replaced `$_SESSION`. Not covered: a successful `store()`, because
 * `is_uploaded_file()` and `move_uploaded_file()` only accept files that PHP received in an HTTP upload of the current
 * request; the test checks that every other file is refused and left alone.
 */
final class SessionFileUploadStorageTest extends TestCase
{
    private string $rootDirectory;
    /** @var array<array-key, mixed> */
    private array $savedSession;
    /** @var array<array-key, mixed> */
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
        $this->savedServer = $_SERVER;
        $_SESSION = [];
        $this->rootDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-upload-test-' . uniqid();
        mkdir(directory: $this->rootDirectory);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
        $_SERVER = $this->savedServer;
        $this->removeTree(path: $this->rootDirectory);
    }

    private function removeTree(string $path): void
    {
        if (is_file(filename: $path)) {
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

    private function createStorage(): SessionFileUploadStorage
    {
        return new SessionFileUploadStorage(rootDirectory: $this->rootDirectory);
    }

    private function createStoredFile(
        string $pointer = 'ptr',
        string $name = 'a.txt',
        string $fileName = 'php1'
    ): UploadedFile {
        $directory = $this->rootDirectory . DIRECTORY_SEPARATOR . $pointer;
        if (!is_dir(filename: $directory)) {
            mkdir(directory: $directory);
        }
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents(filename: $path, data: 'content');

        return new UploadedFile(name: $name, type: 'text/plain', size: 7, path: $path);
    }

    public function testSavedFilesAreLoadedByHash(): void
    {
        $storage = $this->createStorage();
        $file = $this->createStoredFile();

        $storage->save(pointer: 'ptr', files: [$file->getHash() => $file]);

        $this->assertEquals([$file->getHash() => $file], $storage->load(pointer: 'ptr'));
    }

    public function testFilesAreKeptInTheSessionUnderThePointerAsPlainArrays(): void
    {
        $file = $this->createStoredFile();

        $this->createStorage()->save(pointer: 'ptr', files: [$file->getHash() => $file]);

        $this->assertSame(
            [['name' => 'a.txt', 'type' => 'text/plain', 'size' => 7, 'path' => $file->path]],
            $_SESSION['ptr']
        );
    }

    public function testNothingIsLoadedForAnUnknownPointer(): void
    {
        $this->assertSame([], $this->createStorage()->load(pointer: 'unknown'));
    }

    public function testNothingIsLoadedIfTheSessionEntryIsNoArray(): void
    {
        $_SESSION['ptr'] = 'text';

        $this->assertSame([], $this->createStorage()->load(pointer: 'ptr'));
    }

    public function testFileThatVanishedFromTheDiskIsDropped(): void
    {
        $storage = $this->createStorage();
        $kept = $this->createStoredFile(fileName: 'php1');
        $vanished = $this->createStoredFile(name: 'b.txt', fileName: 'php2');
        $storage->save(pointer: 'ptr', files: [$kept->getHash() => $kept, $vanished->getHash() => $vanished]);
        unlink(filename: $vanished->path);

        $this->assertSame([$kept->getHash()], array_keys(array: $storage->load(pointer: 'ptr')));
    }

    public function testDirectoryInPlaceOfAFileIsDropped(): void
    {
        $storage = $this->createStorage();
        $directoryPath = $this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr' . DIRECTORY_SEPARATOR . 'dir';
        mkdir(directory: $this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr');
        mkdir(directory: $directoryPath);
        $directory = new UploadedFile(name: 'a', type: 't', size: 1, path: $directoryPath);

        $storage->save(pointer: 'ptr', files: [$directory->getHash() => $directory]);

        $this->assertSame([], $storage->load(pointer: 'ptr'));
    }

    public function testFileOutsideTheRootDirectoryIsDropped(): void
    {
        $outsidePath = $this->rootDirectory . '-outside';
        file_put_contents(filename: $outsidePath, data: 'x');
        $_SESSION['ptr'] = [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $outsidePath]];

        try {
            $this->assertSame([], $this->createStorage()->load(pointer: 'ptr'));
        } finally {
            unlink(filename: $outsidePath);
        }
    }

    public function testPathWithParentDirectoryIsDropped(): void
    {
        $file = $this->createStoredFile();
        $traversal = $this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'ptr' . DIRECTORY_SEPARATOR . 'php1';
        $_SESSION['ptr'] = [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $traversal]];

        $this->assertTrue(is_file(filename: $file->path));
        $this->assertSame([], $this->createStorage()->load(pointer: 'ptr'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function brokenSessionEntryProvider(): iterable
    {
        yield 'string' => ['text'];
        yield 'int' => [5];
        yield 'null' => [null];
        yield 'object (class of an older version)' => [new stdClass()];
        yield 'empty array' => [[]];
        yield 'missing path' => [['name' => 'a', 'type' => 't', 'size' => 1]];
        yield 'name is no string' => [['name' => 1, 'type' => 't', 'size' => 1, 'path' => '/x']];
        yield 'size is no int' => [['name' => 'a', 'type' => 't', 'size' => '1', 'path' => '/x']];
        yield 'path is an array' => [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => ['/x']]];
    }

    #[DataProvider('brokenSessionEntryProvider')]
    public function testBrokenSessionEntryIsDropped(mixed $entry): void
    {
        $_SESSION['ptr'] = [$entry];

        $this->assertSame([], $this->createStorage()->load(pointer: 'ptr'));
    }

    public function testSavingReplacesTheFilesOfThePointer(): void
    {
        $storage = $this->createStorage();
        $file = $this->createStoredFile();
        $storage->save(pointer: 'ptr', files: [$file->getHash() => $file]);

        $storage->save(pointer: 'ptr', files: []);

        $this->assertSame([], $storage->load(pointer: 'ptr'));
    }

    public function testPointersAreSeparate(): void
    {
        $storage = $this->createStorage();
        $file = $this->createStoredFile(pointer: 'one');
        $storage->save(pointer: 'one', files: [$file->getHash() => $file]);

        $this->assertSame([], $storage->load(pointer: 'two'));
    }

    public function testDeleteRemovesTheFile(): void
    {
        $file = $this->createStoredFile();

        $this->createStorage()->delete(file: $file);

        $this->assertFileDoesNotExist($file->path);
    }

    public function testDeleteOfAMissingFileDoesNothing(): void
    {
        $file = new UploadedFile(
            name: 'a',
            type: 't',
            size: 1,
            path: $this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr' . DIRECTORY_SEPARATOR . 'gone'
        );

        $this->createStorage()->delete(file: $file);

        $this->assertDirectoryExists($this->rootDirectory);
    }

    public function testDeleteLeavesAFileOutsideTheRootDirectoryAlone(): void
    {
        $outsidePath = $this->rootDirectory . '-outside';
        file_put_contents(filename: $outsidePath, data: 'x');

        try {
            $this->createStorage()->delete(file: new UploadedFile(name: 'a', type: 't', size: 1, path: $outsidePath));

            $this->assertFileExists($outsidePath);
        } finally {
            unlink(filename: $outsidePath);
        }
    }

    public function testClearRemovesTheDirectoryAndTheSessionEntry(): void
    {
        $storage = $this->createStorage();
        $file = $this->createStoredFile();
        $other = $this->createStoredFile(pointer: 'other');
        $storage->save(pointer: 'ptr', files: [$file->getHash() => $file]);

        $storage->clear(pointer: 'ptr');

        $this->assertDirectoryDoesNotExist($this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr');
        $this->assertArrayNotHasKey('ptr', $_SESSION);
        $this->assertFileExists($other->path);
    }

    public function testClearWithoutADirectoryDoesNothing(): void
    {
        $this->createStorage()->clear(pointer: 'ptr');

        $this->assertDirectoryExists($this->rootDirectory);
    }

    public function testExpiredDirectoriesAreRemoved(): void
    {
        $old = $this->createStoredFile(pointer: 'old');
        $recent = $this->createStoredFile(pointer: 'recent');
        $almostExpired = $this->createStoredFile(pointer: 'almost');
        touch(filename: dirname(path: $old->path), mtime: time() - (60 * 60 * 24 * 2) - 60);
        touch(filename: dirname(path: $almostExpired->path), mtime: time() - (60 * 60 * 24 * 2) + 3600);

        $this->createStorage()->removeExpired();

        $this->assertDirectoryDoesNotExist(dirname(path: $old->path));
        $this->assertFileDoesNotExist($old->path);
        $this->assertFileExists($recent->path);
        $this->assertFileExists($almostExpired->path);
    }

    public function testRemovingExpiredDirectoriesIgnoresFilesInTheRootDirectory(): void
    {
        $filePath = $this->rootDirectory . DIRECTORY_SEPARATOR . 'plain.txt';
        file_put_contents(filename: $filePath, data: 'x');
        touch(filename: $filePath, mtime: time() - (60 * 60 * 24 * 10));

        $this->createStorage()->removeExpired();

        $this->assertFileExists($filePath);
    }

    public function testRemovingExpiredDirectoriesWithoutRootDirectoryDoesNotCreateIt(): void
    {
        $missingRoot = $this->rootDirectory . DIRECTORY_SEPARATOR . 'missing';

        new SessionFileUploadStorage(rootDirectory: $missingRoot)->removeExpired();

        $this->assertDirectoryDoesNotExist($missingRoot);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPointerProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'parent directory' => ['..'];
        yield 'path' => ['a/b'];
        yield 'traversal' => ['../x'];
        yield 'dot' => ['a.b'];
        yield 'space' => ['a b'];
        yield 'new line at the end' => ["abc\n"];
    }

    #[DataProvider('invalidPointerProvider')]
    public function testInvalidPointerIsRefusedEverywhere(string $pointer): void
    {
        $storage = $this->createStorage();
        $refused = 0;
        foreach (
            [
                fn() => $storage->load(pointer: $pointer),
                fn() => $storage->save(pointer: $pointer, files: []),
                fn() => $storage->clear(pointer: $pointer),
            ] as $call
        ) {
            try {
                $call();
            } catch (InvalidArgumentException) {
                $refused++;
            }
        }

        $this->assertSame(3, $refused);
    }

    public function testFileThatWasNotUploadedIsRefusedAndLeftAlone(): void
    {
        $tmpName = $this->rootDirectory . DIRECTORY_SEPARATOR . 'php-not-uploaded';
        file_put_contents(filename: $tmpName, data: 'content');
        $upload = new UploadInput(name: 'a.txt', tmpName: $tmpName, type: 'text/plain', error: 0, size: 7);

        $file = $this->createStorage()->store(pointer: 'ptr', upload: $upload);

        $this->assertNull($file);
        $this->assertFileExists($tmpName);
        $this->assertDirectoryDoesNotExist($this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function serverNameProvider(): iterable
    {
        yield 'host name' => ['example.test', 'example.test'];
        yield 'with dash and digits' => ['www-1.example.test', 'www-1.example.test'];
        yield 'path traversal' => ['../evil', '.._evil'];
        yield 'slash' => ['a/b', 'a_b'];
        yield 'only dots' => ['..', 'default'];
        yield 'empty' => ['', 'default'];
    }

    #[DataProvider('serverNameProvider')]
    public function testStorageOfTheCurrentRequestLivesInADirectoryNamedAfterTheSanitizedServerName(
        string $serverName,
        string $expectedDirectoryName
    ): void {
        $_SERVER['SERVER_NAME'] = $serverName;
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $expectedDirectoryName . DIRECTORY_SEPARATOR . 'yufptr';
        $existedBefore = is_dir(filename: dirname(path: $directory));
        mkdir(directory: $directory, recursive: true);
        $path = $directory . DIRECTORY_SEPARATOR . 'php1';
        file_put_contents(filename: $path, data: 'x');

        try {
            $_SESSION['yufptr'] = [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $path]];

            $this->assertCount(1, SessionFileUploadStorage::forCurrentRequest()->load(pointer: 'yufptr'));
        } finally {
            unlink(filename: $path);
            rmdir(directory: $directory);
            if (!$existedBefore) {
                rmdir(directory: dirname(path: $directory));
            }
        }
    }

    public function testStorageOfTheCurrentRequestWorksWithoutServerName(): void
    {
        unset($_SERVER['SERVER_NAME']);

        $this->assertSame([], SessionFileUploadStorage::forCurrentRequest()->load(pointer: 'ptr'));
    }
}