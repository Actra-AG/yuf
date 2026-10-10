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

    /**
     * The text of the result in the texts of the given messages (plain text, encoded when rendered).
     */
    public function label(AuthResultMessages $messages): HtmlText
    {
        return HtmlText::fromText(
            text: match ($this) {
                AuthResultEnum::UNDEFINED => $messages->undefined,
                AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN => $messages->successfulPasswordLogin,
                AuthResultEnum::ERROR_NO_EMAIL_ADDRESS => $messages->errorNoEmailAddress,
                AuthResultEnum::ERROR_NO_PASSWORD => $messages->errorNoPassword,
                AuthResultEnum::ERROR_UNKNOWN_USER_NAME => $messages->errorUnknownUserName,
                AuthResultEnum::ERROR_INACTIVE => $messages->errorInactive,
                AuthResultEnum::ERROR_IP_NOT_ALLOWED => $messages->errorIpNotAllowed,
                AuthResultEnum::ERROR_OUT_TRIED => $messages->errorOutTried,
                AuthResultEnum::ERROR_WRONG_PASSWORD => $messages->errorWrongPassword,
                AuthResultEnum::SUCCESSFUL_SSO_LOGIN => $messages->successfulSsoLogin,
                AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE => $messages->errorNoPasswordLoginActive,
                AuthResultEnum::FAILED_SSO_LOGIN => $messages->failedSsoLogin,
                AuthResultEnum::SUCCESSFUL_OTP_LOGIN => $messages->successfulOtpLogin,
                AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN => $messages->successfulMicrosoftLogin,
            },
        );
    }
}
