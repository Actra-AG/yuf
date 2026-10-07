<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;

/**
 * At least `$minCount` selected values (replaces `MinLengthRule` on a list of values).
 */
class MinCountRule extends StringListRule
{
    public function __construct(protected int $minCount, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(array $values): bool
    {
        return count(value: $values) >= $this->minCount;
    }
}
