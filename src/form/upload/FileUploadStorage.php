<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;

/**
 * Where the files of a `FileField` are kept between the requests of one form (a failed validation shows the form
 * again, the files must not be uploaded a second time). `SessionFileUploadStorage` is the production implementation.
 * A pointer identifies the files of one field instance; it only contains `[a-zA-Z0-9_]`.
 */
interface FileUploadStorage
{
    /**
     * @return array<string, UploadedFile> The files of the pointer by hash; files that vanished are dropped
     */
    public function load(string $pointer): array;

    /**
     * @param array<string, UploadedFile> $files
     */
    public function save(string $pointer, array $files): void;

    /**
     * Takes over the uploaded file of the current request.
     *
     * @return ?UploadedFile `null` if the file is not an uploaded file of this request or cannot be stored
     */
    public function store(string $pointer, UploadInput $upload): ?UploadedFile;

    /**
     * Removes the stored file (nothing happens if it is gone already).
     */
    public function delete(UploadedFile $file): void;

    /**
     * Removes all files of the pointer, to be used after the form has been processed successfully.
     */
    public function clear(string $pointer): void;

    /**
     * Removes the files that were stored more than two days ago.
     */
    public function removeExpired(): void;
}
