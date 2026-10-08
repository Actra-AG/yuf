<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use InvalidArgumentException;

/**
 * One kind of file a `FileField` accepts: the MIME types the content may be detected as (`FileTypeDetector`) and the
 * extensions the client file name may have. An upload is accepted if both fit: the detected MIME type is one of the
 * `mimeTypes` and the extension of the file name (lower case, after the last dot) is one of the `extensions`. What
 * the client says about the type is ignored.
 *
 * Several MIME types per entry cover files that libmagic reports differently depending on their content: a CSV is
 * `text/csv` or `text/plain`, an Office file is its own type or, if the ZIP container is not recognised, plain
 * `application/zip` (the extension still has to fit).
 *
 * There is deliberately no named constructor for SVG: an SVG file can contain scripts and is a stored XSS risk when
 * it is served again. Anyone who needs it builds the entry with the constructor and takes care of the output.
 */
final readonly class UploadFileType
{
    private const string MIME_TYPE_PATTERN = '/^[a-z\d][a-z\d!#$&^_.+-]*\/[a-z\d][a-z\d!#$&^_.+-]*$/D';
    private const string EXTENSION_PATTERN = '/^[a-z\d]+$/D';

    /**
     * @param list<string> $mimeTypes The detected MIME types that are accepted (lower case, e.g. `application/pdf`)
     * @param list<string> $extensions The accepted extensions (lower case, without dot, e.g. `jpg`)
     * @throws InvalidArgumentException If a list is empty or contains an invalid value
     */
    public function __construct(public array $mimeTypes, public array $extensions)
    {
        UploadFileType::assertValid(
            values: $this->mimeTypes,
            pattern: UploadFileType::MIME_TYPE_PATTERN,
            label: 'MIME type',
        );
        UploadFileType::assertValid(
            values: $this->extensions,
            pattern: UploadFileType::EXTENSION_PATTERN,
            label: 'extension',
        );
    }

    public static function pdf(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['application/pdf'], extensions: ['pdf']);
    }

    public static function jpeg(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['image/jpeg'], extensions: ['jpg', 'jpeg']);
    }

    public static function png(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['image/png'], extensions: ['png']);
    }

    public static function gif(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['image/gif'], extensions: ['gif']);
    }

    public static function webp(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['image/webp'], extensions: ['webp']);
    }

    public static function plainText(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['text/plain'], extensions: ['txt']);
    }

    public static function csv(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['text/csv', 'text/plain'], extensions: ['csv']);
    }

    public static function docx(): UploadFileType
    {
        return new UploadFileType(
            mimeTypes: ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            extensions: ['docx'],
        );
    }

    public static function xlsx(): UploadFileType
    {
        return new UploadFileType(
            mimeTypes: ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            extensions: ['xlsx'],
        );
    }

    public static function pptx(): UploadFileType
    {
        return new UploadFileType(
            mimeTypes: ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            extensions: ['pptx'],
        );
    }

    public static function zip(): UploadFileType
    {
        return new UploadFileType(mimeTypes: ['application/zip'], extensions: ['zip']);
    }

    /**
     * @param string $detectedMimeType The type found in the content of the file, never the one the client sent
     * @param string $fileName The name of the file the client sent
     */
    public function accepts(string $detectedMimeType, string $fileName): bool
    {
        $extension = UploadFileType::extensionOf(fileName: $fileName);

        return $extension !== ''
            && in_array(needle: strtolower(string: $detectedMimeType), haystack: $this->mimeTypes, strict: true)
            && in_array(needle: $extension, haystack: $this->extensions, strict: true);
    }

    /**
     * The extension of the file name in lower case, an empty string without one.
     */
    private static function extensionOf(string $fileName): string
    {
        $position = strrpos(haystack: $fileName, needle: '.');

        return $position === false ? '' : strtolower(string: substr(string: $fileName, offset: $position + 1));
    }

    /**
     * @param list<string> $values
     */
    private static function assertValid(array $values, string $pattern, string $label): void
    {
        if ($values === []) {
            throw new InvalidArgumentException(message: 'An upload file type needs at least one ' . $label . '.');
        }
        foreach ($values as $value) {
            if (preg_match(pattern: $pattern, subject: $value) !== 1) {
                throw new InvalidArgumentException(
                    message: 'The ' . $label . ' "' . $value . '" must be lower case without special characters.',
                );
            }
        }
    }
}
