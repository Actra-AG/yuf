<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * The texts of the results of a login (`AuthResultEnum::label()`), e.g. for a list of login attempts. Plain text,
 * encoded when it is rendered. The defaults are German; `AuthResultMessages::english()`, `french()` and `italian()`
 * have the English, French and Italian ones. Pass an own instance for another language.
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
        public string $errorRateLimited = 'Zu viele Anmeldeversuche',
        public string $errorWrongCode = 'Falscher Code',
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
            errorRateLimited: 'Too many login attempts',
            errorWrongCode: 'Wrong code',
        );
    }

    public static function french(): AuthResultMessages
    {
        return new AuthResultMessages(
            undefined: 'Inconnu',
            successfulPasswordLogin: 'Connexion par mot de passe',
            errorNoEmailAddress: 'Aucune adresse e-mail',
            errorNoPassword: 'Aucun mot de passe',
            errorUnknownUserName: 'Adresse e-mail non valable',
            errorInactive: 'Accès inactif',
            errorIpNotAllowed: 'Adresse IP non autorisée',
            errorOutTried: 'Trop de tentatives erronées',
            errorWrongPassword: 'Mot de passe erroné',
            successfulSsoLogin: 'Connexion SSO',
            errorNoPasswordLoginActive: 'Connexion par mot de passe inactive',
            failedSsoLogin: 'Échec SSO',
            successfulOtpLogin: 'Connexion OTP',
            successfulMicrosoftLogin: 'Connexion Microsoft',
            errorRateLimited: 'Trop de tentatives de connexion',
            errorWrongCode: 'Code erroné',
        );
    }

    public static function italian(): AuthResultMessages
    {
        return new AuthResultMessages(
            undefined: 'Sconosciuto',
            successfulPasswordLogin: 'Accesso con password',
            errorNoEmailAddress: 'Nessun indirizzo e-mail',
            errorNoPassword: 'Nessuna password',
            errorUnknownUserName: 'Indirizzo e-mail non valido',
            errorInactive: 'Accesso inattivo',
            errorIpNotAllowed: 'Indirizzo IP non consentito',
            errorOutTried: 'Troppi tentativi errati',
            errorWrongPassword: 'Password errata',
            successfulSsoLogin: 'Accesso SSO',
            errorNoPasswordLoginActive: 'Accesso con password inattivo',
            failedSsoLogin: 'SSO non riuscito',
            successfulOtpLogin: 'Accesso OTP',
            successfulMicrosoftLogin: 'Accesso Microsoft',
            errorRateLimited: 'Troppi tentativi di accesso',
            errorWrongCode: 'Codice errato',
        );
    }
}
