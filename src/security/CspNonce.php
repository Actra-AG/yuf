<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use actra\yuf\session\AbstractSessionHandler;

class CspNonce
{
    public const string SESSION_INDICATOR = 'security_cspNonce';

    public static function get(): string
    {
        if (!AbstractSessionHandler::enabled()) {
            return '';
        }
        $cspNonce = $_SESSION[CspNonce::SESSION_INDICATOR] ?? null;
        if (!is_string(value: $cspNonce)) {
            $cspNonce = CspNonce::generate();
            $_SESSION[CspNonce::SESSION_INDICATOR] = $cspNonce;
        }

        return $cspNonce;
    }

    private static function generate(): string
    {
        return base64_encode(string: openssl_random_pseudo_bytes(length: 16));
    }
}
