<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

/**
 * A part of an SQL query together with the values of its "?" placeholders.
 *
 * Values are `float|int|string|null`, as PDO binds them all as strings or NULL: convert booleans to `0` / `1` (a
 * bound `false` would become the empty string).
 *
 * @phpstan-type SqlParameters list<float|int|string|null>
 */
final readonly class DbQueryData
{
    /**
     * @param SqlParameters $params
     */
    public function __construct(
        public string $query,
        public array $params,
    ) {}
}
