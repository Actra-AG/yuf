<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * The texts of the results of a login (`AuthResultEnum::label()`), e.g. for a list of login attempts. Plain text,
 * encoded when it is rendered. The defaults are German; `AuthResultMessages::english()` has the English
 * ones. Pass an own instance for another language.
 */
final readonly class AuthResultMessages
{
    public function __construct(
        public string $undefined = 'Unbekannt',
        public string $successfulPasswordLogin = 'Passwort-Anmeldung',
        public string $errorNoEmailAddress = 'Keine E-Mail-Adresse',
        public string $errorNoPassword = 'Kein Passwort',
        public string $errorUnknownUserName = 'Ungültige E-Mail-Adresse',
        public string $errorInactive = 'Zugang inaktiv',
        public string $errorIpNotAllowed = 'IP-Adresse nicht erlaubt',
        public string $errorOutTried = 'Zu viele fehlerhafte Versuche',
        public string $errorWrongPassword = 'Falsches Passwort',
        public string $successfulSsoLogin = 'SSO-Anmeldung',
        public string $errorNoPasswordLoginActive = 'Passwort-Anmeldung inaktiv',
        public string $failedSsoLogin = 'SSO fehlgeschlagen',
        public string $successfulOtpLogin = 'OTP-Anmeldung',
        public string $successfulMicrosoftLogin = 'Microsoft-Anmeldung',
    ) {}

    public static function english(): AuthResultMessages
    {
        return new AuthResultMessages(
            undefined: 'Unknown',
            successfulPasswordLogin: 'Password login',
            errorNoEmailAddress: 'No email address',
            errorNoPassword: 'No password',
            errorUnknownUserName: 'Invalid email address',
            errorInactive: 'Access inactive',
            errorIpNotAllowed: 'IP address not allowed',
            errorOutTried: 'Too many failed attempts',
            errorWrongPassword: 'Wrong password',
            successfulSsoLogin: 'SSO login',
            errorNoPasswordLoginActive: 'Password login inactive',
            failedSsoLogin: 'SSO failed',
            successfulOtpLogin: 'OTP login',
            successfulMicrosoftLogin: 'Microsoft login',
        );
    }
}
