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
 * At most `$maxCount` selected values (replaces `MaxLengthRule` on a list of values).
 */
final class MaxCountRule extends StringListRule
{
    public function __construct(protected int $maxCount, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    #[Override]
    public function validate(array $values): bool
    {
        return count(value: $values) <= $this->maxCount;
    }
}
