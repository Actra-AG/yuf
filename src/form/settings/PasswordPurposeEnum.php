<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\settings;

/**
 * What a password field is used for, so browsers and password managers know whether to fill in a saved password or
 * to suggest a new one.
 */
enum PasswordPurposeEnum
{
    /** Login, or confirming the current password */
    case CURRENT;
    /** Registration, password change, password reset */
    case NEW;

    public function autoComplete(): AutoCompleteValue
    {
        return match ($this) {
            PasswordPurposeEnum::CURRENT => AutoCompleteValue::CURRENT_PASSWORD,
            PasswordPurposeEnum::NEW => AutoCompleteValue::NEW_PASSWORD,
        };
    }
}