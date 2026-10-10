<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use DirectoryIterator;
use InvalidArgumentException;
use Override;

/**
 * Keeps the uploaded files in a private directory (`UploadDirectory`: one subdirectory per pointer, files 0600) and
 * the list of the files in the session (`yuf.uploads.<pointer>`, so a pointer cannot overwrite other session data).
 * A pointer is only valid in the session it was issued to (`issue()`, `yuf.uploadPointers`): files are stored and
 * removed for such pointers only, so a pointer from a request of another session changes nothing. It is the only form
 * class that touches the session, the file system and the clock.
 *
 * Not unit tested: `store()` needs a real upload (`is_uploaded_file()` and `move_uploaded_file()` refuse every other
 * file). Everything else is tested with a temp directory.
 */
final readonly class SessionFileUploadStorage implements FileUploadStorage
{
    private const int MAX_AGE_IN_SECONDS = 60 * 60 * 24 * 2;

    private UploadDirectory $uploadDirectory;

    /**
     * @param Session $session Keeps the list of the files of every pointer (`ViewContext::$session`)
     * @param string $rootDirectory The directory that contains one subdirectory per pointer. Required, no default:
     *                              choose a directory outside the document root that only the web server user can
     *                              use (created with mode 0700 when needed; a symbolic link or a directory of another
     *                              user is refused with a `RuntimeException`)
     * @param Clock $clock Decides which directories and pointers are expired
     */
    public function __construct(
        private Session $session,
        string $rootDirectory,
        private Clock $clock = new SystemClock(),
    ) {
        $this->uploadDirectory = new UploadDirectory(path: $rootDirectory);
    }

    #[Override]
    public function issue(string $pointer): void
    {
        $this->assertValidPointer(pointer: $pointer);
        $now = $this->clock->now()->getTimestamp();
        $oldestAllowedTime = $now - SessionFileUploadStorage::MAX_AGE_IN_SECONDS;
        $issued = [];
        $issuedPointers = $this->session->getSection(section: SessionSectionEnum::UPLOAD_POINTERS);
        foreach ($issuedPointers as $issuedPointer => $issuedAt) {
            if (is_int(value: $issuedAt) && $issuedAt >= $oldestAllowedTime) {
                $issued[$issuedPointer] = $issuedAt;
            }
        }
        $issued[$pointer] = $now;
        $this->session->setSection(section: SessionSectionEnum::UPLOAD_POINTERS, data: $issued);
    }

    #[Override]
    public function isIssued(string $pointer): bool
    {
        $issued = $this->session->getSection(section: SessionSectionEnum::UPLOAD_POINTERS);

        return array_key_exists(key: $pointer, array: $issued) && is_int(value: $issued[$pointer]);
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
        $this->assertValidPointer(pointer: $pointer);
        if (!$this->isIssued(pointer: $pointer) || !is_uploaded_file(filename: $upload->tmpName)) {
            return null;
        }
        // Creates the root directory too, if it does not exist yet
        $directory = $this->uploadDirectory->createSubdirectory(name: $pointer);
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
        $this->uploadDirectory->protectFile(path: $path);

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
        $this->assertValidPointer(pointer: $pointer);
        if (!$this->isIssued(pointer: $pointer)) {
            return;
        }
        $directory = $this->getPointerDirectory(pointer: $pointer);
        if ($this->uploadDirectory->exists() && is_dir(filename: $directory)) {
            $this->removeDirectory(path: $directory);
        }
        $uploads = $this->session->getSection(section: SessionSectionEnum::UPLOADS);
        unset($uploads[$pointer]);
        $this->session->setSection(section: SessionSectionEnum::UPLOADS, data: $uploads);
    }

    #[Override]
    public function removeExpired(): void
    {
        if (!$this->uploadDirectory->exists()) {
            return;
        }
        $oldestAllowedTime = $this->clock->now()->getTimestamp() - SessionFileUploadStorage::MAX_AGE_IN_SECONDS;
        /** @var DirectoryIterator $item */
        foreach (new DirectoryIterator(directory: $this->uploadDirectory->path) as $item) {
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

        return $this->uploadDirectory->path . DIRECTORY_SEPARATOR . $pointer;
    }

    private function isInsideRootDirectory(string $path): bool
    {
        return str_starts_with(haystack: $path, needle: $this->uploadDirectory->path . DIRECTORY_SEPARATOR)
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
