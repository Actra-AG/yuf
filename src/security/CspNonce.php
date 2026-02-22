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
    private const string SESSION_INDICATOR = 'security_cspNonce';

    public static function get(): string
    {
        if (!AbstractSessionHandler::enabled()) {
            return '';
        }
        if (!array_key_exists(key: CspNonce::SESSION_INDICATOR, array: $_SESSION)) {
            $_SESSION[CspNonce::SESSION_INDICATOR] = CspNonce::generate();
        }

        return $_SESSION[CspNonce::SESSION_INDICATOR];
    }

    private static function generate(): string
    {
        return base64_encode(string: openssl_random_pseudo_bytes(length: 16));
    }
}