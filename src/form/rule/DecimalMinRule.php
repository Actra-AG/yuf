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
 * The value is at least `$min` (decimal strings, compared without float rounding).
 */
final class DecimalMinRule extends DecimalRule
{
    protected string $min;

    /**
     * @param string $min A decimal string such as `'0.05'`.
     * @throws InvalidArgumentException If the limit is not a decimal string.
     */
    public function __construct(string $min, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
        $this->min = DecimalRule::toLimit(decimal: $min);
    }

    #[Override]
    public function validate(string $value): bool
    {
        return DecimalRule::compare(left: $value, right: $this->min) >= 0;
    }
}
