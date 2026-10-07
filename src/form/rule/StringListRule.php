<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\FormRule;

/**
 * A rule for the list of values of a multi options field. Called for a non-empty list only; a pure predicate.
 */
abstract class StringListRule extends FormRule
{
    /**
     * @param list<string> $values
     */
    abstract public function validate(array $values): bool;
}
