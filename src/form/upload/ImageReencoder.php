<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

/**
 * Writes a stored image again from its decoded pixels (`FileField(imageReencoder: ...)`), so metadata (EXIF, GPS
 * position) and data appended to the image (polyglot files) are dropped. `GdImageReencoder` is the implementation of
 * yuf; tests pass a double.
 */
interface ImageReencoder
{
    /**
     * Whether images of the detected MIME type are re-encoded. Files of other types are stored as uploaded.
     */
    public function supports(string $mimeType): bool;

    /**
     * Replaces the file with the re-encoded image, in the same format.
     *
     * @param string $mimeType The detected MIME type, one that `supports()` accepts
     *
     * @return ?int The size of the new file in bytes; `null` if the file is no valid image of the type or too large
     *              (it is then rejected)
     */
    public function reencode(string $path, string $mimeType): ?int;
}
