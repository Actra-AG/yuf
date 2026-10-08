<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * The reason why an email address (or its domain) is not accepted.
 */
final readonly class EmailAddressError
{
    public function __construct(public EmailAddressErrorEnum $code, public string $message) {}
}
