<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

/**
 * Adapted from libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of libphonenumber
 * (https://github.com/google/libphonenumber), see LICENSE and NOTICE in src/phone/. Changed by Actra AG: reduced to
 * parsing, formatting and validation, rewritten to PHP 8.5 and the Actra coding standard (see NOTICE).
 *
 * @author Joshua Gigg and contributors (libphonenumber-for-php)
 * @author The Libphonenumber Authors (libphonenumber)
 */

namespace actra\yuf\phone;

use InvalidArgumentException;

/**
 * Thrown when a text cannot be parsed to a phone number; `error` says why.
 */
final class PhoneParseException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly PhoneParseErrorEnum $error)
    {
        parent::__construct(message: $message, code: $error->value);
    }
}
