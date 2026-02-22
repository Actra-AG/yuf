<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

readonly class DbQueryData
{
    public function __construct(
        public string $query,
        public array $params
    ) {
    }
}