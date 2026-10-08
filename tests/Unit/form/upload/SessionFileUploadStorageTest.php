<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use actra\yuf\clock\FixedClock;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\form\upload\SessionFileUploadStorage;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use DateTimeImmutable;
use DirectoryIterator;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tested with a temp directory and an `ArraySessionStorage`; the file lists live in `yuf.uploads.<pointer>`. Not
 * covered: a successful `store()`, because `is_uploaded_file()` and `move_uploaded_file()` only accept files that PHP
 * received in an HTTP upload of the current request; the test checks that every other file is refused and left alone.
 */
final class SessionFileUploadStorageTest extends TestCase
{
    private string $rootDirectory;
    private ArraySessionStorage $storage;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
        $this->rootDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-upload-test-' . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $this->rootDirectory);
    }

    #[Override]
    protected function tearDown(): void
    {
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

    /**
     * @param array<array-key, mixed>|string|int|null $value
     */
    private function seedPointer(string $pointer, array|string|int|null $value): void
    {
        $this->session->setSection(section: SessionSectionEnum::UPLOADS, data: [$pointer => $value]);
    }

    private function createStorage(): SessionFileUploadStorage
    {
        return new SessionFileUploadStorage(session: $this->session, rootDirectory: $this->rootDirectory);
    }

    private function createStoredFile(
        string $pointer = 'ptr',
        string $name = 'a.txt',
        string $fileName = 'php1',
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

    public function testStorageLayoutIsTheListOfPlainArraysUnderThePointerInTheUploadsSection(): void
    {
        $file = $this->createStoredFile();

        $this->createStorage()->save(pointer: 'ptr', files: [$file->getHash() => $file]);

        $this->assertSame(
            [
                'yuf' => [
                    'uploads' => [
                        'ptr' => [['name' => 'a.txt', 'type' => 'text/plain', 'size' => 7, 'path' => $file->path]],
                    ],
                ],
            ],
            $this->storage->all(),
        );
    }

    public function testNothingIsLoadedForAnUnknownPointer(): void
    {
        $this->assertSame([], $this->createStorage()->load(pointer: 'unknown'));
    }

    public function testNothingIsLoadedIfTheSessionEntryIsNoArray(): void
    {
        $this->seedPointer(pointer: 'ptr', value: 'text');

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
        $this->seedPointer(
            pointer: 'ptr',
            value: [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $outsidePath]],
        );

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
        $this->seedPointer(pointer: 'ptr', value: [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $traversal]]);

        $this->assertTrue(is_file(filename: $file->path));
        $this->assertSame([], $this->createStorage()->load(pointer: 'ptr'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>|string|int|null}>
     */
    public static function brokenSessionEntryProvider(): iterable
    {
        yield 'string' => ['text'];
        yield 'int' => [5];
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'missing path' => [['name' => 'a', 'type' => 't', 'size' => 1]];
        yield 'name is no string' => [['name' => 1, 'type' => 't', 'size' => 1, 'path' => '/x']];
        yield 'size is no int' => [['name' => 'a', 'type' => 't', 'size' => '1', 'path' => '/x']];
        yield 'path is an array' => [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => ['/x']]];
    }

    /**
     * @param array<array-key, mixed>|string|int|null $entry
     */
    #[DataProvider('brokenSessionEntryProvider')]
    public function testBrokenSessionEntryIsDropped(array|string|int|null $entry): void
    {
        $this->seedPointer(pointer: 'ptr', value: [$entry]);

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
            path: $this->rootDirectory . DIRECTORY_SEPARATOR . 'ptr' . DIRECTORY_SEPARATOR . 'gone',
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
        $this->assertSame([], $this->storage->all());
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

    public function testDirectoryIsExpiredOnlyWhenOlderThanTwoDaysAccordingToTheClock(): void
    {
        $now = 1_800_000_000;
        $twoDays = 60 * 60 * 24 * 2;
        $exactlyAtLimit = $this->createStoredFile(pointer: 'atlimit');
        $beyondLimit = $this->createStoredFile(pointer: 'beyond');
        touch(filename: dirname(path: $exactlyAtLimit->path), mtime: $now - $twoDays);
        touch(filename: dirname(path: $beyondLimit->path), mtime: $now - $twoDays - 1);

        new SessionFileUploadStorage(
            session: $this->session,
            rootDirectory: $this->rootDirectory,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . $now)),
        )->removeExpired();

        $this->assertFileExists($exactlyAtLimit->path);
        $this->assertFileDoesNotExist($beyondLimit->path);
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

        new SessionFileUploadStorage(session: $this->session, rootDirectory: $missingRoot)->removeExpired();

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

        $file = $this->createStorage()->store(pointer: 'ptr', upload: $upload, detectedType: 'text/plain');

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
    public function testStorageOfTheRequestLivesInADirectoryNamedAfterTheSanitizedServerName(
        string $serverName,
        string $expectedDirectoryName,
    ): void {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $expectedDirectoryName . DIRECTORY_SEPARATOR . 'yufptr';
        $existedBefore = is_dir(filename: dirname(path: $directory));
        mkdir(directory: $directory, recursive: true);
        $path = $directory . DIRECTORY_SEPARATOR . 'php1';
        file_put_contents(filename: $path, data: 'x');

        try {
            $this->seedPointer(
                pointer: 'yufptr',
                value: [['name' => 'a', 'type' => 't', 'size' => 1, 'path' => $path]],
            );

            $this->assertCount(1, SessionFileUploadStorage::forHttpRequest(
                session: $this->session,
                httpRequest: HttpRequestFactory::create(serverName: $serverName),
            )->load(pointer: 'yufptr'));
        } finally {
            unlink(filename: $path);
            rmdir(directory: $directory);
            if (!$existedBefore) {
                rmdir(directory: dirname(path: $directory));
            }
        }
    }

    public function testStorageOfTheRequestWorksWithoutServerName(): void
    {
        $httpRequest = HttpRequestFactory::create(serverName: '');

        $this->assertSame(
            [],
            SessionFileUploadStorage::forHttpRequest(
                session: $this->session,
                httpRequest: $httpRequest,
            )->load(pointer: 'ptr'),
        );
    }

    /**
     * Fix of v4.30.0: a pointer such as `table` or `csrftoken` overwrote the data of yuf (and `clear()` deleted it).
     */
    public function testPointerNamedLikeOtherSessionDataCannotCollideWithIt(): void
    {
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: ['items' => ['sortColumn' => 'name']]);
        $this->session->setSection(section: SessionSectionEnum::CSRF, data: ['token' => 'token']);
        $this->session->set(key: 'tables', value: 'project data');
        $storage = $this->createStorage();
        $file = $this->createStoredFile(pointer: 'tables');

        $storage->save(pointer: 'tables', files: [$file->getHash() => $file]);
        $storage->clear(pointer: 'tables');

        $this->assertSame(
            ['items' => ['sortColumn' => 'name']],
            $this->session->getSection(section: SessionSectionEnum::TABLES),
        );
        $this->assertSame(['token' => 'token'], $this->session->getSection(section: SessionSectionEnum::CSRF));
        $this->assertSame('project data', $this->session->getString(key: 'tables'));
        $this->assertSame([], $this->session->getSection(section: SessionSectionEnum::UPLOADS));
    }

    public function testSavingAPointerKeepsTheOtherPointers(): void
    {
        $storage = $this->createStorage();
        $first = $this->createStoredFile(pointer: 'first');
        $second = $this->createStoredFile(pointer: 'second');

        $storage->save(pointer: 'first', files: [$first->getHash() => $first]);
        $storage->save(pointer: 'second', files: [$second->getHash() => $second]);

        $this->assertEquals([$first->getHash() => $first], $storage->load(pointer: 'first'));
        $this->assertEquals([$second->getHash() => $second], $storage->load(pointer: 'second'));
    }

    public function testLoadingDoesNotWriteIntoTheSession(): void
    {
        $this->createStorage()->load(pointer: 'ptr');

        $this->assertSame([], $this->storage->all());
    }
}
