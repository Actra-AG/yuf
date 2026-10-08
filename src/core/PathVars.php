<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\NotFoundException;
use actra\yuf\form\AmountParser;

/**
 * Typed access to the path variables of a file name (`subscription-42.html` → `[0 => 'subscription', 1 => '42']`).
 * A missing or invalid value in a required getter is a wrong URL, so it throws a `NotFoundException` (404).
 */
final readonly class PathVars
{
    /**
     * @param array<int, string> $values Path variable number => raw value, as in `ResolvedRoute::$pathVars`.
     */
    public function __construct(private array $values) {}

    /**
     * The trimmed value, `null` if missing.
     */
    public function get(int $nr): ?string
    {
        return array_key_exists(key: $nr, array: $this->values) ? trim(string: $this->values[$nr]) : null;
    }

    /**
     * Strictly integer-formatted values only (same rules as `DbRow::getInt()`): optional minus, digits, nothing else
     * (no "+", no spaces). Anything else, including values outside the integer range, returns `null`.
     */
    public function getAsInt(int $nr): ?int
    {
        $value = $this->values[$nr] ?? null;
        if ($value === null || preg_match(pattern: '/^-?\d+$/', subject: $value) !== 1) {
            return null;
        }

        return AmountParser::toInt(value: $value);
    }

    public function getRequiredAsInt(int $nr): int
    {
        return $this->getAsInt(nr: $nr) ?? throw new NotFoundException(
            message: 'Path variable ' . $nr . ' is missing or not an integer',
        );
    }

    /**
     * Returns the trimmed value (as `get()`); a missing or empty value throws a `NotFoundException`.
     */
    public function getRequiredAsString(int $nr): string
    {
        $value = $this->get(nr: $nr);
        if ($value === null || $value === '') {
            throw new NotFoundException(message: 'Path variable ' . $nr . ' is missing or empty');
        }

        return $value;
    }
}
