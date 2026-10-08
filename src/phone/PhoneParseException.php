<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

use InvalidArgumentException;

/**
 * Thrown when a text cannot be parsed to a phone number; `error` says why.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 */
final class PhoneParseException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly PhoneParseErrorEnum $error)
    {
        parent::__construct(message: $message, code: $error->value);
    }
}
