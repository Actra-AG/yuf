<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\FormRule;

/**
 * A rule for the text of a field. Called for a non-empty text only; a pure predicate (it does not change the value).
 * Custom rules for text fields extend this class (or an existing rule).
 */
abstract class StringRule extends FormRule
{
    abstract public function validate(string $value): bool;
}
