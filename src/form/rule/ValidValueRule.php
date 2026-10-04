<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\html\HtmlText;

class ValidValueRule extends StringRule
{
    /**
     * @param list<string> $validValues
     */
    public function __construct(protected array $validValues, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(string $value): bool
    {
        return in_array(needle: $value, haystack: $this->validValues, strict: true);
    }
}