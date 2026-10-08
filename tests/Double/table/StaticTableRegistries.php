<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\table;

use actra\yuf\table\filter\AbstractTableFilterField;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\table\SmartTable;
use ReflectionProperty;

/**
 * Empties the static identifier registries of `SmartTable`, `TableFilter` and `AbstractTableFilterField` through
 * reflection (they have no reset method), so a test can build the same table or filter again to simulate the next
 * request of the user. Removed with the registries in docs/session/plan.md, step 2.
 */
final class StaticTableRegistries
{
    public static function reset(): void
    {
        foreach ([SmartTable::class, TableFilter::class, AbstractTableFilterField::class] as $class) {
            new ReflectionProperty(class: $class, property: 'instances')->setValue(null, []);
        }
    }
}
