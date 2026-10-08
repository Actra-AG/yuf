<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\table;

use actra\yuf\table\filter\TableFilter;

/**
 * Makes the protected session methods of `TableFilter` callable (a project can use them in its own filter).
 */
final class ExposingTableFilter extends TableFilter
{
    public function read(string $index): ?string
    {
        return $this->getFromSession(index: $index);
    }

    public function write(string $index, string $value): void
    {
        $this->saveToSession(index: $index, value: $value);
    }
}
