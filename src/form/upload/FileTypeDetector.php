<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

/**
 * Finds out what kind of file an uploaded file is, from its content. `FinfoFileTypeDetector` is the production
 * implementation; tests pass a double so that no real file is needed.
 */
interface FileTypeDetector
{
    /**
     * @return ?string The MIME type in lower case (e.g. `application/pdf`), `null` if the file cannot be read or
     *                 examined
     */
    public function detectMimeType(string $path): ?string;
}
