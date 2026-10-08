<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;
use Override;

final class RegexRule extends StringRule
{
    public function __construct(
        protected string $pattern,
        HtmlText $errorMessage,
    ) {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    #[Override]
    public function validate(string $value): bool
    {
        return preg_match(pattern: $this->pattern, subject: $value) === 1;
    }
}
