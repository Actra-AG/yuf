<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\field\StringInputField;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The text must equal the current value of another field (e.g. a password and its confirmation). Add the compared
 * field to the form before the field with this rule: the fields are read in the order they were added, and the other
 * field must hold its input when this rule runs. The values are compared exactly (a password is not normalized).
 */
final class EqualsFieldRule extends StringRule
{
    public function __construct(private readonly StringInputField $otherField, HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    #[Override]
    public function validate(string $value): bool
    {
        return hash_equals(known_string: $this->otherField->getValueAsString(), user_string: $value);
    }
}
