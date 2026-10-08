<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * The credentials of a request. Debug output (`var_dump()`, `print_r()`) and `json_encode()` never show the secret.
 *
 * @internal
 */
final readonly class CurlAuthentication
{
    private function __construct(
        public CurlAuthenticationMethodEnum $method,
        #[SensitiveParameter]
        private string $secret,
    ) {}

    /**
     * @param string $userNameAndPassword `user:password`
     */
    public static function basic(#[SensitiveParameter] string $userNameAndPassword): CurlAuthentication
    {
        if (
            $userNameAndPassword === ''
            || preg_match(pattern: '/[\x00-\x1F\x7F]/', subject: $userNameAndPassword) === 1
        ) {
            throw new InvalidArgumentException(
                message: 'The credentials for basic authentication must not be empty or contain control characters.',
            );
        }

        return new CurlAuthentication(method: CurlAuthenticationMethodEnum::BASIC, secret: $userNameAndPassword);
    }

    public static function bearer(#[SensitiveParameter] string $token): CurlAuthentication
    {
        if (preg_match(pattern: '/^[\x21-\x7E]+$/D', subject: $token) !== 1) {
            throw new InvalidArgumentException(
                message: 'A bearer token must consist of visible ASCII characters without spaces and must not be'
                . ' empty.',
            );
        }

        return new CurlAuthentication(method: CurlAuthenticationMethodEnum::BEARER, secret: $token);
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @return array{method: string}
     */
    public function __debugInfo(): array
    {
        return ['method' => $this->method->name];
    }
}
