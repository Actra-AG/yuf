<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

use InvalidArgumentException;

/**
 * Thrown when a text cannot be parsed to a phone number; `error` says why.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 */
final class PhoneParseException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly PhoneParseErrorEnum $error)
    {
        parent::__construct(message: $message, code: $error->value);
    }
}
