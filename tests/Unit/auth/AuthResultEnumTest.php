<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthMethodEnum;
use actra\yuf\auth\AuthResultEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The values are stored in the log of login attempts of the projects: they must not change.
 */
final class AuthResultEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{int, AuthResultEnum}>
     */
    public static function storedValueProvider(): iterable
    {
        yield 'UNDEFINED' => [0, AuthResultEnum::UNDEFINED];
        yield 'SUCCESSFUL_PASSWORD_LOGIN' => [1, AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN];
        yield 'ERROR_NO_EMAIL_ADDRESS' => [2, AuthResultEnum::ERROR_NO_EMAIL_ADDRESS];
        yield 'ERROR_NO_PASSWORD' => [3, AuthResultEnum::ERROR_NO_PASSWORD];
        yield 'ERROR_UNKNOWN_USER_NAME' => [4, AuthResultEnum::ERROR_UNKNOWN_USER_NAME];
        yield 'ERROR_INACTIVE' => [5, AuthResultEnum::ERROR_INACTIVE];
        yield 'ERROR_IP_NOT_ALLOWED' => [6, AuthResultEnum::ERROR_IP_NOT_ALLOWED];
        yield 'ERROR_OUT_TRIED' => [7, AuthResultEnum::ERROR_OUT_TRIED];
        yield 'ERROR_WRONG_PASSWORD' => [8, AuthResultEnum::ERROR_WRONG_PASSWORD];
        yield 'SUCCESSFUL_SSO_LOGIN' => [10, AuthResultEnum::SUCCESSFUL_SSO_LOGIN];
        yield 'ERROR_NO_PASSWORD_LOGIN_ACTIVE' => [11, AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE];
        yield 'FAILED_SSO_LOGIN' => [12, AuthResultEnum::FAILED_SSO_LOGIN];
        yield 'SUCCESSFUL_OTP_LOGIN' => [13, AuthResultEnum::SUCCESSFUL_OTP_LOGIN];
        yield 'SUCCESSFUL_MICROSOFT_LOGIN' => [14, AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN];
    }

    #[DataProvider('storedValueProvider')]
    public function testStoredValuesStayTheSame(int $value, AuthResultEnum $expected): void
    {
        $this->assertSame($expected, AuthResultEnum::from($value));
        $this->assertSame($value, $expected->value);
    }

    public function testEveryResultHasAGermanLabel(): void
    {
        foreach (AuthResultEnum::cases() as $authResult) {
            $this->assertNotSame('', $authResult->render());
        }
        $this->assertSame('Falsches Passwort', AuthResultEnum::ERROR_WRONG_PASSWORD->render());
    }

    /**
     * @return iterable<string, array{string, AuthMethodEnum}>
     */
    public static function authMethodProvider(): iterable
    {
        yield 'PASSWORD' => ['password', AuthMethodEnum::PASSWORD];
        yield 'SSO' => ['sso', AuthMethodEnum::SSO];
        yield 'OTP' => ['otp', AuthMethodEnum::OTP];
        yield 'MICROSOFT' => ['microsoft', AuthMethodEnum::MICROSOFT];
    }

    #[DataProvider('authMethodProvider')]
    public function testAuthMethodValuesStayTheSame(string $value, AuthMethodEnum $expected): void
    {
        $this->assertSame($expected, AuthMethodEnum::from($value));
    }
}
