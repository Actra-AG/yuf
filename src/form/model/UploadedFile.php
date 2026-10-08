<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\model;

/**
 * A file that was uploaded with a form and is kept in a `FileUploadStorage` until the form is processed.
 * Replaces `FileDataModel` (`tmp_name` is `path` now). `name` is what the browser sent: never use it as a file system
 * path. `type` is the MIME type detected from the content of the file (not the one the browser sent). `path` is the
 * location of the stored copy.
 */
final readonly class UploadedFile
{
    public function __construct(
        public string $name,
        public string $type,
        public int $size,
        public string $path,
    ) {}

    /**
     * The key of the file in `FileField::getFiles()` and the value posted to remove it (SHA-256 of the path).
     */
    public function getHash(): string
    {
        return hash(algo: 'sha256', data: $this->path);
    }
}
