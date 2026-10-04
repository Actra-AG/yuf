<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;

/**
 * The value is at most `$max`.
 */
class FloatMaxRule extends FloatRule
{
    public function __construct(protected float $max, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(float $value): bool
    {
        return $value <= $this->max;
    }
}