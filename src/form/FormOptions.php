<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\html\HtmlText;

final class FormOptions
{
    /** @var array<int|string, HtmlText> PHP turns the keys of numeric strings (`'7'`) into integers in an array */
    private array $data = [];

    public function __construct() {}

    public function addItem(string $key, HtmlText $htmlText): void
    {
        $this->data[$key] = $htmlText;
    }

    /**
     * Adds an option with an integer key (e.g. a database id). The key is rendered, posted and compared as its decimal
     * text, so it is the same option as `addItem(key: '7', ...)`.
     */
    public function addIntItem(int $key, HtmlText $htmlText): void
    {
        $this->data[$key] = $htmlText;
    }

    public function exists(string $key): bool
    {
        return array_key_exists(
            key: $key,
            array: $this->data,
        );
    }

    /**
     * The key as integer if it is strictly integer-formatted (same rules as `DbRow::getInt()`: optional minus, digits,
     * nothing else, within the integer range), else `null`.
     */
    public static function toIntKey(string $key): ?int
    {
        return preg_match(pattern: '/^-?\d+$/D', subject: $key) === 1 ? AmountParser::toInt(value: $key) : null;
    }

    /**
     * The keys as strings, in the order of the items (never integers, unlike the keys of a PHP array).
     *
     * @return list<string>
     */
    public function getKeys(): array
    {
        return array_map(
            callback: static fn(int|string $key): string => (string) $key,
            array: array_keys(array: $this->data),
        );
    }

    /**
     * The options in the order they were added, with the key as string.
     *
     * @return list<FormOption>
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->data as $key => $htmlText) {
            $items[] = new FormOption(key: (string) $key, htmlText: $htmlText);
        }

        return $items;
    }
}
