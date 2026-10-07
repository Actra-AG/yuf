<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\FormRule;

/**
 * A rule for the value of an `IntegerField`. Called for a parsed, non-empty value only; a pure predicate.
 */
abstract class IntegerRule extends FormRule
{
    abstract public function validate(int $value): bool;
}
