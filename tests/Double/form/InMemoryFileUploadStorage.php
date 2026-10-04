<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\form\upload\FileUploadStorage;

/**
 * A file upload storage without session and file system. It records what the field asked for, so tests can
 * check it, and can simulate a file that vanished and a storage that fails.
 */
final class InMemoryFileUploadStorage implements FileUploadStorage
{
    /** @var array<string, array<string, UploadedFile>> */
    private array $filesByPointer = [];
    /** @var list<string> */
    private array $vanishedPaths = [];
    /** @var list<UploadInput> */
    private array $storedUploads = [];
    /** @var list<UploadedFile> */
    private array $deletedFiles = [];
    /** @var list<string> */
    private array $clearedPointers = [];
    private int $expiredRemovalCount = 0;
    private int $counter = 0;
    private bool $failStoring = false;

    /**
     * Puts files into the storage as if an earlier request had uploaded them.
     */
    public function preload(string $pointer, UploadedFile ...$files): void
    {
        foreach ($files as $file) {
            $this->filesByPointer[$pointer][$file->getHash()] = $file;
        }
    }

    /**
     * The file does not exist any more (the next `load()` drops it).
     */
    public function vanish(UploadedFile $file): void
    {
        $this->vanishedPaths[] = $file->path;
    }

    public function failStoring(): void
    {
        $this->failStoring = true;
    }

    public function load(string $pointer): array
    {
        return array_filter(
            array: $this->filesByPointer[$pointer] ?? [],
            callback: fn(UploadedFile $file): bool => !in_array(
                needle: $file->path,
                haystack: $this->vanishedPaths,
                strict: true
            )
        );
    }

    public function save(string $pointer, array $files): void
    {
        $this->filesByPointer[$pointer] = $files;
    }

    public function store(string $pointer, UploadInput $upload): ?UploadedFile
    {
        if ($this->failStoring) {
            return null;
        }
        $this->storedUploads[] = $upload;

        return new UploadedFile(
            name: $upload->name,
            type: $upload->type,
            size: $upload->size,
            path: 'memory://' . $pointer . '/' . ++$this->counter
        );
    }

    public function delete(UploadedFile $file): void
    {
        $this->deletedFiles[] = $file;
    }

    public function clear(string $pointer): void
    {
        $this->clearedPointers[] = $pointer;
        unset($this->filesByPointer[$pointer]);
    }

    public function removeExpired(): void
    {
        $this->expiredRemovalCount++;
    }

    /**
     * @return array<string, UploadedFile> The files saved for the pointer, without the vanished check of `load()`
     */
    public function getSavedFiles(string $pointer): array
    {
        return $this->filesByPointer[$pointer] ?? [];
    }

    /**
     * @return list<UploadInput>
     */
    public function getStoredUploads(): array
    {
        return $this->storedUploads;
    }

    /**
     * @return list<UploadedFile>
     */
    public function getDeletedFiles(): array
    {
        return $this->deletedFiles;
    }

    /**
     * @return list<string>
     */
    public function getClearedPointers(): array
    {
        return $this->clearedPointers;
    }

    public function getExpiredRemovalCount(): int
    {
        return $this->expiredRemovalCount;
    }
}