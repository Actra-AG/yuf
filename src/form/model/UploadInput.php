<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\model;

/**
 * One file of the raw upload data of the request (`$_FILES`), as narrowed by `FormInput::getUploads()`.
 * `error` is one of the `UPLOAD_ERR_*` constants. Every value comes from the client or PHP and is not checked (name
 * and type are trimmed).
 */
final readonly class UploadInput
{
    public function __construct(
        public string $name,
        public string $tmpName,
        public string $type,
        public int $error,
        public int $size
    ) {
    }
}