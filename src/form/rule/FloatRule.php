<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\FormRule;

/**
 * A rule for the value of a `FloatField`. Called for a parsed, non-empty value only; a pure predicate.
 */
abstract class FloatRule extends FormRule
{
    abstract public function validate(float $value): bool;
}