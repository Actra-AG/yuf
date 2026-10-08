<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\html\HtmlText;

enum AuthResultEnum: int
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
            AuthResultEnum::UNDEFINED => HtmlText::fromHtml(html: 'Unbekannt'),
            AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN => HtmlText::fromHtml(html: 'Passwort-Anmeldung'),
            AuthResultEnum::ERROR_NO_EMAIL_ADDRESS => HtmlText::fromHtml(html: 'Keine E-Mail-Adresse'),
            AuthResultEnum::ERROR_NO_PASSWORD => HtmlText::fromHtml(html: 'Kein Passwort'),
            AuthResultEnum::ERROR_UNKNOWN_USER_NAME => HtmlText::fromHtml(html: 'Ungültige E-Mail-Adresse'),
            AuthResultEnum::ERROR_INACTIVE => HtmlText::fromHtml(html: 'Zugang inaktiv'),
            AuthResultEnum::ERROR_IP_NOT_ALLOWED => HtmlText::fromHtml(html: 'IP-Adresse nicht erlaubt'),
            AuthResultEnum::ERROR_OUT_TRIED => HtmlText::fromHtml(html: 'Zu viele fehlerhafte Versuche'),
            AuthResultEnum::ERROR_WRONG_PASSWORD => HtmlText::fromHtml(html: 'Falsches Passwort'),
            AuthResultEnum::SUCCESSFUL_SSO_LOGIN => HtmlText::fromHtml(html: 'SSO-Anmeldung'),
            AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE => HtmlText::fromHtml(html: 'Passwort-Anmeldung inaktiv'),
            AuthResultEnum::FAILED_SSO_LOGIN => HtmlText::fromHtml(html: 'SSO fehlgeschlagen'),
            AuthResultEnum::SUCCESSFUL_OTP_LOGIN => HtmlText::fromHtml(html: 'OTP-Anmeldung'),
            AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN => HtmlText::fromHtml(html: 'Microsoft-Anmeldung'),
        })->render();
    }
}
