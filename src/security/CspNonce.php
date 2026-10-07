<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use InvalidArgumentException;

/**
 * The nonce of the Content Security Policy: a new random value for every request. `Core` creates one object per
 * request and passes it on (header, inline scripts and styles of the response).
 */
final readonly class CspNonce
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException(message: 'The CSP nonce must not be empty.');
        }
    }

    public static function create(): CspNonce
    {
        return new CspNonce(value: base64_encode(string: random_bytes(length: 16)));
    }
}
