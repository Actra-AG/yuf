<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;

class MinLengthRule extends StringRule
{
    public function __construct(protected int $minLength, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(string $value): bool
    {
        return mb_strlen(string: $value) >= $this->minLength;
    }
}
