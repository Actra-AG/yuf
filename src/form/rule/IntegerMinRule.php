<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;
use Override;

/**
 * The value is at least `$min`.
 */
class IntegerMinRule extends IntegerRule
{
    public function __construct(protected int $min, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    #[Override]
    public function validate(int $value): bool
    {
        return $value >= $this->min;
    }
}
