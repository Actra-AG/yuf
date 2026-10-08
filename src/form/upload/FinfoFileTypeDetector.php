<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use finfo;
use Override;

/**
 * Detects the MIME type from the content of the file with libmagic (`ext-fileinfo`).
 */
final readonly class FinfoFileTypeDetector implements FileTypeDetector
{
    #[Override]
    public function detectMimeType(string $path): ?string
    {
        if (!is_file(filename: $path) || !is_readable(filename: $path)) {
            return null;
        }
        $mimeType = new finfo(flags: FILEINFO_MIME_TYPE)->file(filename: $path);

        return $mimeType === false ? null : strtolower(string: $mimeType);
    }
}
