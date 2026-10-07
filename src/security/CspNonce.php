<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * The nonce of the Content Security Policy: a new random value for every request, the same for all calls within the
 * request (header and inline scripts and styles of the response).
 */
class CspNonce
{
    private static ?string $nonce = null;

    public static function get(): string
    {
        return CspNonce::$nonce ??= base64_encode(string: random_bytes(length: 16));
    }
}
