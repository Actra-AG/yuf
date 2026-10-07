<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use LogicException;

/**
 * A `getRequiredValueAs...()` getter was called on a field without a value. These getters are only valid for a
 * required field after a successful `validate()`, so this is a programming error, not a user input error.
 */
final class FormFieldValueMissingException extends LogicException
{
    public static function forField(string $fieldName, bool $isRequired, string $nullableGetter): self
    {
        if (!$isRequired) {
            return new self(
                message: 'Field ' . $fieldName . ' is not required and has no value: add a required rule or use '
                . $nullableGetter . '() for an optional field.',
            );
        }

        return new self(
            message: 'Field ' . $fieldName . ' has no value: the getter for a required value is only valid after a '
            . 'successful validation.',
        );
    }
}
