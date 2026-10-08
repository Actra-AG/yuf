<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use Override;

/**
 * The value is at most `$max` (decimal strings, compared without float rounding).
 */
final class DecimalMaxRule extends DecimalRule
{
    protected string $max;

    /**
     * @param string $max A decimal string such as `'0.05'`.
     * @throws InvalidArgumentException If the limit is not a decimal string.
     */
    public function __construct(string $max, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
        $this->max = DecimalRule::toLimit(decimal: $max);
    }

    #[Override]
    public function validate(string $value): bool
    {
        return DecimalRule::compare(left: $value, right: $this->max) <= 0;
    }
}
