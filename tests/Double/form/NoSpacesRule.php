<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\rule\StringRule;
use Override;

/**
 * A project rule as the migration guide shows it: it extends a typed base and is a pure predicate.
 */
final class NoSpacesRule extends StringRule
{
    #[Override]
    public function validate(string $value): bool
    {
        return !str_contains(haystack: $value, needle: ' ');
    }
}
