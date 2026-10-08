<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use DirectoryIterator;
use InvalidArgumentException;
use Override;

/**
 * Keeps the uploaded files in a directory below the temp directory (one directory per pointer) and the list of the
 * files in the session (`yuf.uploads.<pointer>`, so a pointer cannot overwrite other session data). It is the only
 * form class that touches the session, the file system and the clock.
 *
 * Not unit tested: `store()` needs a real upload (`is_uploaded_file()` and `move_uploaded_file()` refuse every other
 * file). Everything else is tested with a temp directory.
 */
final readonly class SessionFileUploadStorage implements FileUploadStorage
{
    private const int MAX_AGE_IN_SECONDS = 60 * 60 * 24 * 2;

    /**
     * @param Session $session Keeps the list of the files of every pointer (`ViewContext::$session`)
     * @param string $rootDirectory The directory that contains one subdirectory per pointer (created when needed)
     * @param Clock $clock Decides which directories are expired
     */
    public function __construct(
        private Session $session,
        private string $rootDirectory,
        private Clock $clock = new SystemClock(),
    ) {}

    /**
     * The storage below `<temp directory>/<SERVER_NAME>`, as in yuf v3. Characters of the server name that are not
     * allowed in a directory name are replaced (the name can be derived from the `Host` header).
     */
    public static function forHttpRequest(
        Session $session,
        HttpRequest $httpRequest,
        Clock $clock = new SystemClock(),
    ): SessionFileUploadStorage {
        $directoryName = preg_replace(
            pattern: '/[^a-zA-Z\d._-]/',
            replacement: '_',
            subject: $httpRequest->getServerName(),
        ) ?? '';
        if (trim(string: $directoryName, characters: '.') === '') {
            $directoryName = 'default';
        }

        return new SessionFileUploadStorage(
            session: $session,
            rootDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . $directoryName,
            clock: $clock,
        );
    }

    #[Override]
    public function load(string $pointer): array
    {
        $this->assertValidPointer(pointer: $pointer);
        $section = $this->session->getSection(section: SessionSectionEnum::UPLOADS);
        $storedFiles = array_key_exists(key: $pointer, array: $section) ? $section[$pointer] : null;
        if (!is_array(value: $storedFiles)) {
            return [];
        }
        $files = [];
        foreach ($storedFiles as $storedFile) {
            $file = $this->toUploadedFile(storedFile: $storedFile);
            if ($file !== null && is_file(filename: $file->path)) {
                $files[$file->getHash()] = $file;
            }
        }

        return $files;
    }

    #[Override]
    public function save(string $pointer, array $files): void
    {
        $this->assertValidPointer(pointer: $pointer);
        $storedFiles = [];
        foreach ($files as $file) {
            $storedFiles[] = [
                'name' => $file->name,
                'type' => $file->type,
                'size' => $file->size,
                'path' => $file->path,
            ];
        }
        $this->session->setSection(
            section: SessionSectionEnum::UPLOADS,
            data: [...$this->session->getSection(section: SessionSectionEnum::UPLOADS), $pointer => $storedFiles],
        );
    }

    #[Override]
    public function store(string $pointer, UploadInput $upload, string $detectedType): ?UploadedFile
    {
        if (!is_uploaded_file(filename: $upload->tmpName)) {
            return null;
        }
        $directory = $this->getPointerDirectory(pointer: $pointer);
        // Creates the root directory too, if it does not exist yet
        if (!is_dir(filename: $directory) && !mkdir(directory: $directory, recursive: true)
            && !is_dir(filename: $directory)) {
            return null;
        }
        // The name of the stored file is made of the name PHP gave to the temporary file, never of the client name.
        // If it exists already, we add a counter and increment it until we get a "free" file name
        $counter = 0;
        $path = $baseFilePath = $directory . DIRECTORY_SEPARATOR . basename(path: $upload->tmpName);
        while (file_exists(filename: $path)) {
            $counter++;
            $path = $baseFilePath . $counter;
        }
        // "move" (copy-del) it to the store, creating a new file pointer, therefore it does not get deleted after
        // the script execution
        if (!move_uploaded_file(from: $upload->tmpName, to: $path)) {
            return null;
        }

        return new UploadedFile(name: $upload->name, type: $detectedType, size: $upload->size, path: $path);
    }

    #[Override]
    public function delete(UploadedFile $file): void
    {
        if ($this->isInsideRootDirectory(path: $file->path) && is_file(filename: $file->path)) {
            unlink(filename: $file->path);
        }
    }

    #[Override]
    public function clear(string $pointer): void
    {
        $directory = $this->getPointerDirectory(pointer: $pointer);
        if (is_dir(filename: $directory)) {
            $this->removeDirectory(path: $directory);
        }
        $uploads = $this->session->getSection(section: SessionSectionEnum::UPLOADS);
        unset($uploads[$pointer]);
        $this->session->setSection(section: SessionSectionEnum::UPLOADS, data: $uploads);
    }

    #[Override]
    public function removeExpired(): void
    {
        if (!is_dir(filename: $this->rootDirectory)) {
            return;
        }
        $oldestAllowedTime = $this->clock->now()->getTimestamp() - SessionFileUploadStorage::MAX_AGE_IN_SECONDS;
        /** @var DirectoryIterator $item */
        foreach (new DirectoryIterator(directory: $this->rootDirectory) as $item) {
            if (!$item->isDot() && $item->isDir() && $item->getMTime() < $oldestAllowedTime) {
                $this->removeDirectory(path: $item->getPathname());
            }
        }
    }

    private function assertValidPointer(string $pointer): void
    {
        // The pointer becomes part of a file system path
        if (preg_match(pattern: '/^[a-zA-Z\d_]+$/D', subject: $pointer) !== 1) {
            throw new InvalidArgumentException(
                message: 'The upload pointer "' . $pointer . '" may only contain letters, digits and underscores.',
            );
        }
    }

    private function getPointerDirectory(string $pointer): string
    {
        $this->assertValidPointer(pointer: $pointer);

        return $this->rootDirectory . DIRECTORY_SEPARATOR . $pointer;
    }

    private function isInsideRootDirectory(string $path): bool
    {
        return str_starts_with(haystack: $path, needle: $this->rootDirectory . DIRECTORY_SEPARATOR)
            && !in_array(needle: '..', haystack: explode(separator: DIRECTORY_SEPARATOR, string: $path), strict: true);
    }

    /**
     * Narrows one entry of the session (data of the user's session, not trusted blindly) to a file of this storage.
     */
    private function toUploadedFile(mixed $storedFile): ?UploadedFile
    {
        if (!is_array(value: $storedFile)) {
            return null;
        }
        $name = array_key_exists(key: 'name', array: $storedFile) ? $storedFile['name'] : null;
        $type = array_key_exists(key: 'type', array: $storedFile) ? $storedFile['type'] : null;
        $size = array_key_exists(key: 'size', array: $storedFile) ? $storedFile['size'] : null;
        $path = array_key_exists(key: 'path', array: $storedFile) ? $storedFile['path'] : null;
        if (!is_string(value: $name) || !is_string(value: $type) || !is_int(value: $size) || !is_string(value: $path)) {
            return null;
        }
        if (!$this->isInsideRootDirectory(path: $path)) {
            return null;
        }

        return new UploadedFile(name: $name, type: $type, size: $size, path: $path);
    }

    private function removeDirectory(string $path): void
    {
        /** @var DirectoryIterator $item */
        foreach (new DirectoryIterator(directory: $path) as $item) {
            if ($item->isFile()) {
                unlink(filename: $item->getPathname());
            }
        }
        rmdir(directory: $path);
    }
}
