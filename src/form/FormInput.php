<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * Request data narrowed to the shapes a form field can read. `fromArray()` is the only place that sees `mixed`.
 */
final readonly class FormInput
{
    /**
     * @param array<string, string> $texts
     * @param array<string, list<string>> $lists
     * @param array<string, true> $invalid
     * @param array<string, string> $queryTexts
     * @param array<string, true> $queryKeys
     */
    private function __construct(
        private array $texts,
        private array $lists,
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
        $lists = [];
        $invalid = [];
        foreach ($data + $files as $key => $value) {
            $name = (string)$key;
            if (is_string(value: $value)) {
                $texts[$name] = $value;
                continue;
            }
            $list = FormInput::toStringList(value: $value);
            if ($list === null) {
                $invalid[$name] = true;
                continue;
            }
            $lists[$name] = $list;
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
            lists: $lists,
            invalid: $invalid,
            queryTexts: $queryTexts,
            queryKeys: $queryKeys
        );
    }

    /**
     * @return ?list<string> `null` if the value is not an array that consists only of strings
     */
    private static function toStringList(mixed $value): ?array
    {
        if (!is_array(value: $value)) {
            return null;
        }
        $list = [];
        foreach ($value as $entry) {
            if (!is_string(value: $entry)) {
                return null;
            }
            $list[] = $entry;
        }

        return $list;
    }

    public function getShape(string $name): InputShapeEnum
    {
        return match (true) {
            array_key_exists(key: $name, array: $this->texts) => InputShapeEnum::TEXT,
            array_key_exists(key: $name, array: $this->lists) => InputShapeEnum::LIST,
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
     * @return ?list<string> `null` unless the shape is LIST
     */
    public function getList(string $name): ?array
    {
        return $this->lists[$name] ?? null;
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