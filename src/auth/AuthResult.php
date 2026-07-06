<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\html\HtmlText;

enum AuthResult: int
{
    case UNDEFINED = 0;
    case SUCCESSFUL_PASSWORD_LOGIN = 1;
    case ERROR_NO_EMAIL_ADDRESS = 2;
    case ERROR_NO_PASSWORD = 3;
    case ERROR_UNKNOWN_USER_NAME = 4;
    case ERROR_INACTIVE = 5;
    case ERROR_IP_NOT_ALLOWED = 6;
    case ERROR_OUT_TRIED = 7;
    case ERROR_WRONG_PASSWORD = 8;
    case SUCCESSFUL_SSO_LOGIN = 10;
    case ERROR_NO_PASSWORD_LOGIN_ACTIVE = 11;
    case FAILED_SSO_LOGIN = 12;
    case SUCCESSFUL_OTP_LOGIN = 13;
    case SUCCESSFUL_MICROSOFT_LOGIN = 14;

    public function render(): string
    {
        return (match ($this) {
            AuthResult::UNDEFINED => HtmlText::encoded(textContent: 'Unbekannt'),
            AuthResult::SUCCESSFUL_PASSWORD_LOGIN => HtmlText::encoded(textContent: 'Passwort-Anmeldung'),
            AuthResult::ERROR_NO_EMAIL_ADDRESS => HtmlText::encoded(textContent: 'Keine E-Mail-Adresse'),
            AuthResult::ERROR_NO_PASSWORD => HtmlText::encoded(textContent: 'Kein Passwort'),
            AuthResult::ERROR_UNKNOWN_USER_NAME => HtmlText::encoded(textContent: 'Ungültige E-Mail-Adresse'),
            AuthResult::ERROR_INACTIVE => HtmlText::encoded(textContent: 'Zugang inaktiv'),
            AuthResult::ERROR_IP_NOT_ALLOWED => HtmlText::encoded(textContent: 'IP-Adresse nicht erlaubt'),
            AuthResult::ERROR_OUT_TRIED => HtmlText::encoded(textContent: 'Zu viele fehlerhafte Versuche'),
            AuthResult::ERROR_WRONG_PASSWORD => HtmlText::encoded(textContent: 'Falsches Passwort'),
            AuthResult::SUCCESSFUL_SSO_LOGIN => HtmlText::encoded(textContent: 'SSO-Anmeldung'),
            AuthResult::ERROR_NO_PASSWORD_LOGIN_ACTIVE => HtmlText::encoded(textContent: 'Passwort-Anmeldung inaktiv'),
            AuthResult::FAILED_SSO_LOGIN => HtmlText::encoded(textContent: 'SSO fehlgeschlagen'),
            AuthResult::SUCCESSFUL_OTP_LOGIN => HtmlText::encoded(textContent: 'OTP-Anmeldung'),
            AuthResult::SUCCESSFUL_MICROSOFT_LOGIN => HtmlText::encoded(textContent: 'Microsoft-Anmeldung'),
        })->render();
    }
}