<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

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
     */
    private function __construct(
        private array $texts,
        private array $maps,
        private array $invalid,
        private array $queryTexts,
        private array $queryKeys
    ) {
    }

    /**
     * @param array<array-key, mixed> $data Posted (or GET) values; they win over `$files` with the same name
     * @param array<array-key, mixed> $files Uploaded files (`$_FILES`), read as input like the other values
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
            queryKeys: $queryKeys
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

    public function hasQueryKey(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->queryKeys);
    }

    public function getQueryText(string $key): ?string
    {
        return $this->queryTexts[$key] ?? null;
    }
}