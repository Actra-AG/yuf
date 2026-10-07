<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

enum AuthMethodEnum: string
{
    case PASSWORD = 'password';
    case SSO = 'sso';
    case OTP = 'otp';
    case MICROSOFT = 'microsoft';

    public function getSuccessAuthResult(): AuthResultEnum
    {
        return match ($this) {
            AuthMethodEnum::PASSWORD => AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN,
            AuthMethodEnum::SSO => AuthResultEnum::SUCCESSFUL_SSO_LOGIN,
            AuthMethodEnum::OTP => AuthResultEnum::SUCCESSFUL_OTP_LOGIN,
            AuthMethodEnum::MICROSOFT => AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN,
        };
    }
}
