<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthMethodEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthResultMessages;
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
            $this->assertNotSame('', $authResult->label(messages: new AuthResultMessages())->render());
        }
        $this->assertSame(
            'Falsches Passwort',
            AuthResultEnum::ERROR_WRONG_PASSWORD->label(messages: new AuthResultMessages())->render(),
        );
    }

    /**
     * @return iterable<string, array{AuthResultEnum, string, string}>
     */
    public static function textProvider(): iterable
    {
        yield 'UNDEFINED' => [AuthResultEnum::UNDEFINED, 'Unbekannt', 'Unknown'];
        yield 'SUCCESSFUL_PASSWORD_LOGIN' => [
            AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN,
            'Passwort-Anmeldung',
            'Password login',
        ];
        yield 'ERROR_NO_EMAIL_ADDRESS' => [
            AuthResultEnum::ERROR_NO_EMAIL_ADDRESS,
            'Keine E-Mail-Adresse',
            'No email address',
        ];
        yield 'ERROR_NO_PASSWORD' => [AuthResultEnum::ERROR_NO_PASSWORD, 'Kein Passwort', 'No password'];
        yield 'ERROR_UNKNOWN_USER_NAME' => [
            AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
            'Ungültige E-Mail-Adresse',
            'Invalid email address',
        ];
        yield 'ERROR_INACTIVE' => [AuthResultEnum::ERROR_INACTIVE, 'Zugang inaktiv', 'Access inactive'];
        yield 'ERROR_IP_NOT_ALLOWED' => [
            AuthResultEnum::ERROR_IP_NOT_ALLOWED,
            'IP-Adresse nicht erlaubt',
            'IP address not allowed',
        ];
        yield 'ERROR_OUT_TRIED' => [
            AuthResultEnum::ERROR_OUT_TRIED,
            'Zu viele fehlerhafte Versuche',
            'Too many failed attempts',
        ];
        yield 'ERROR_WRONG_PASSWORD' => [AuthResultEnum::ERROR_WRONG_PASSWORD, 'Falsches Passwort', 'Wrong password'];
        yield 'SUCCESSFUL_SSO_LOGIN' => [AuthResultEnum::SUCCESSFUL_SSO_LOGIN, 'SSO-Anmeldung', 'SSO login'];
        yield 'ERROR_NO_PASSWORD_LOGIN_ACTIVE' => [
            AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE,
            'Passwort-Anmeldung inaktiv',
            'Password login inactive',
        ];
        yield 'FAILED_SSO_LOGIN' => [AuthResultEnum::FAILED_SSO_LOGIN, 'SSO fehlgeschlagen', 'SSO failed'];
        yield 'SUCCESSFUL_OTP_LOGIN' => [AuthResultEnum::SUCCESSFUL_OTP_LOGIN, 'OTP-Anmeldung', 'OTP login'];
        yield 'SUCCESSFUL_MICROSOFT_LOGIN' => [
            AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN,
            'Microsoft-Anmeldung',
            'Microsoft login',
        ];
    }

    #[DataProvider('textProvider')]
    public function testLabelUsesTheGivenMessagesAndTheDefaultsAreGerman(
        AuthResultEnum $authResult,
        string $german,
        string $english,
    ): void {
        $this->assertSame($german, $authResult->label(messages: new AuthResultMessages())->render());
        $this->assertSame($english, $authResult->label(messages: AuthResultMessages::english())->render());
    }

    public function testLabelEncodesThePlainTextOfTheMessages(): void
    {
        $messages = new AuthResultMessages(errorWrongPassword: 'Wrong <b>password</b> & more');

        $this->assertSame(
            'Wrong &lt;b&gt;password&lt;/b&gt; &amp; more',
            AuthResultEnum::ERROR_WRONG_PASSWORD->label(messages: $messages)->render(),
        );
    }

    public function testEveryCaseHasAMessage(): void
    {
        $this->assertCount(
            count(value: AuthResultEnum::cases()),
            iterator_to_array(iterator: AuthResultEnumTest::textProvider()),
        );
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
