<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

enum AuthMethod: string
{
    case PASSWORD = 'password';
    case SSO = 'sso';
    case OTP = 'otp';
    case MICROSOFT = 'microsoft';

    public function getSuccessAuthResult(): AuthResult
    {
        return match ($this) {
            AuthMethod::PASSWORD => AuthResult::SUCCESSFUL_PASSWORD_LOGIN,
            AuthMethod::SSO => AuthResult::SUCCESSFUL_SSO_LOGIN,
            AuthMethod::OTP => AuthResult::SUCCESSFUL_OTP_LOGIN,
            AuthMethod::MICROSOFT => AuthResult::SUCCESSFUL_MICROSOFT_LOGIN,
        };
    }
}