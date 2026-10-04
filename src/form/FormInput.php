<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\form\model\UploadInput;

/**
 * Request data narrowed to the shapes a form field can read. `fromArray()` is the only place that sees `mixed`.
 * An array of strings is kept with its keys (`getMap()`); `getList()` gives its values without the keys.
 */
final readonly class FormInput
{
    /**
     * @param array<string, string> $texts
     * @param array<string, array<int|string, string>> $maps arrays of strings, with the keys of the request
     * @param array<string, true> $invalid
     * @param array<string, string> $queryTexts
     * @param array<string, true> $queryKeys
     * @param array<string, list<UploadInput>> $uploads
     * @param array<string, true> $malformedUploads
     */
    private function __construct(
        private array $texts,
        private array $maps,
        private array $invalid,
        private array $queryTexts,
        private array $queryKeys,
        private array $uploads,
        private array $malformedUploads
    ) {
    }

    /**
     * @param array<array-key, mixed> $data Posted (or GET) values; they win over `$files` with the same name
     * @param array<array-key, mixed> $files Uploaded files (`$_FILES`), read as input like the other values and as
     *                                       uploads (`getUploads()`); an entry named like a `$data` entry is no upload
     * @param array<array-key, mixed> $query Query string (`$_GET`): the sent indicator and the CSRF token fallback
     */
    public static function fromArray(array $data, array $files = [], array $query = []): FormInput
    {
        $texts = [];
        $maps = [];
        $invalid = [];
        foreach ($data + $files as $key => $value) {
            $name = (string)$key;
            if (is_string(value: $value)) {
                $texts[$name] = $value;
                continue;
            }
            $map = FormInput::toStringMap(value: $value);
            if ($map === null) {
                $invalid[$name] = true;
                continue;
            }
            $maps[$name] = $map;
        }
        $uploads = [];
        $malformedUploads = [];
        foreach ($files as $key => $entry) {
            if (array_key_exists(key: $key, array: $data) || !FormInput::looksLikeUpload(value: $entry)) {
                continue;
            }
            $upload = FormInput::toUploadList(value: $entry);
            if ($upload === null) {
                $malformedUploads[(string)$key] = true;
                continue;
            }
            $uploads[(string)$key] = $upload;
        }
        $queryTexts = [];
        $queryKeys = [];
        foreach ($query as $key => $value) {
            $queryKeys[(string)$key] = true;
            if (is_string(value: $value)) {
                $queryTexts[(string)$key] = $value;
            }
        }

        return new FormInput(
            texts: $texts,
            maps: $maps,
            invalid: $invalid,
            queryTexts: $queryTexts,
            queryKeys: $queryKeys,
            uploads: $uploads,
            malformedUploads: $malformedUploads
        );
    }

    /**
     * An entry of `$_FILES` always has the five keys. One that has at least one of them was meant as an upload, so
     * a broken one is reported (`hasMalformedUpload()`); any other value is not an upload at all and is ignored.
     */
    private static function looksLikeUpload(mixed $value): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        return array_any(
            array: ['name', 'type', 'tmp_name', 'error', 'size'],
            callback: fn(string $key): bool => array_key_exists(key: $key, array: $value)
        );
    }

    /**
     * Narrows the entry of one input in `$_FILES`: either one file (`name="file"`, the values are scalars) or several
     * (`name="file[]"`, every value is an array with the same keys).
     *
     * @return ?list<UploadInput> `null` if the structure is not exactly what PHP creates; names and types are trimmed
     */
    private static function toUploadList(mixed $value): ?array
    {
        if (!is_array(value: $value)) {
            return null;
        }
        $names = $value['name'] ?? null;
        if (!is_array(value: $names)) {
            $upload = FormInput::toUpload(
                name: $names,
                tmpName: $value['tmp_name'] ?? null,
                type: $value['type'] ?? null,
                error: $value['error'] ?? null,
                size: $value['size'] ?? null
            );

            return $upload === null ? null : [$upload];
        }
        $tmpNames = $value['tmp_name'] ?? null;
        $types = $value['type'] ?? null;
        $errors = $value['error'] ?? null;
        $sizes = $value['size'] ?? null;
        if (!is_array(value: $tmpNames) || !is_array(value: $types) || !is_array(value: $errors)
            || !is_array(value: $sizes)) {
            return null;
        }
        foreach ([$tmpNames, $types, $errors, $sizes] as $column) {
            // Every column must have exactly the entries of the names, else the structure was manipulated
            if (array_keys(array: $column) !== array_keys(array: $names)) {
                return null;
            }
        }
        $uploads = [];
        foreach ($names as $key => $name) {
            $upload = FormInput::toUpload(
                name: $name,
                tmpName: $tmpNames[$key],
                type: $types[$key],
                error: $errors[$key],
                size: $sizes[$key]
            );
            if ($upload === null) {
                return null;
            }
            $uploads[] = $upload;
        }

        return $uploads;
    }

    private static function toUpload(mixed $name, mixed $tmpName, mixed $type, mixed $error, mixed $size): ?UploadInput
    {
        if (!is_string(value: $name) || !is_string(value: $tmpName) || !is_string(value: $type)) {
            return null;
        }
        if (!is_int(value: $error) || !is_int(value: $size)) {
            return null;
        }

        return new UploadInput(
            name: trim(string: $name),
            tmpName: $tmpName,
            type: trim(string: $type),
            error: $error,
            size: $size
        );
    }

    /**
     * @return ?array<int|string, string> `null` if the value is not an array that consists only of strings
     */
    private static function toStringMap(mixed $value): ?array
    {
        if (!is_array(value: $value)) {
            return null;
        }
        $map = [];
        foreach ($value as $key => $entry) {
            if (!is_string(value: $entry)) {
                return null;
            }
            $map[$key] = $entry;
        }

        return $map;
    }

    public function getShape(string $name): InputShapeEnum
    {
        return match (true) {
            array_key_exists(key: $name, array: $this->texts) => InputShapeEnum::TEXT,
            array_key_exists(key: $name, array: $this->maps) => InputShapeEnum::LIST,
            array_key_exists(key: $name, array: $this->invalid) => InputShapeEnum::INVALID,
            default => InputShapeEnum::MISSING,
        };
    }

    /**
     * @return ?string `null` unless the shape is TEXT
     */
    public function getText(string $name): ?string
    {
        return $this->texts[$name] ?? null;
    }

    /**
     * The values of an array in their order, without the keys (`qty[a]=1&qty[b]=2` gives `['1', '2']`).
     *
     * @return ?list<string> `null` unless the shape is LIST
     */
    public function getList(string $name): ?array
    {
        $map = $this->maps[$name] ?? null;

        return $map === null ? null : array_values(array: $map);
    }

    /**
     * An array with its keys and order (`qty[123]=2` gives `[123 => '2']`). The shape is LIST, like for `getList()`.
     *
     * @return ?array<int|string, string> `null` unless the shape is LIST
     */
    public function getMap(string $name): ?array
    {
        return $this->maps[$name] ?? null;
    }

    /**
     * The files uploaded for the input `$name` (`$_FILES`), including the entries without a file (error
     * `UPLOAD_ERR_NO_FILE`: nothing was selected). Empty if there is no such input, if the same name was posted as a
     * normal value, and if the structure is malformed (see `hasMalformedUpload()`).
     *
     * @return list<UploadInput>
     */
    public function getUploads(string $name): array
    {
        return $this->uploads[$name] ?? [];
    }

    /**
     * Whether `$_FILES` has an entry for `$name` that is built like an upload but is not what PHP creates (missing
     * keys, nested or non-scalar values, lists of different lengths): manipulated input.
     */
    public function hasMalformedUpload(string $name): bool
    {
        return array_key_exists(key: $name, array: $this->malformedUploads);
    }

    public function hasQueryKey(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->queryKeys);
    }

    public function getQueryText(string $key): ?string
    {
        return $this->queryTexts[$key] ?? null;
    }
}