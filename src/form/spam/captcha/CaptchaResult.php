<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam\captcha;

/**
 * The result of `CaptchaProvider::verify()`: the status and, if it is not valid, why (for the log: never a secret or
 * the response of the visitor).
 */
final readonly class CaptchaResult
{
    private function __construct(
        public CaptchaStatusEnum $status,
        public string $reason,
    ) {}

    public static function valid(): CaptchaResult
    {
        return new CaptchaResult(status: CaptchaStatusEnum::VALID, reason: '');
    }

    public static function invalid(string $reason): CaptchaResult
    {
        return new CaptchaResult(status: CaptchaStatusEnum::INVALID, reason: $reason);
    }

    public static function unavailable(string $reason): CaptchaResult
    {
        return new CaptchaResult(status: CaptchaStatusEnum::UNAVAILABLE, reason: $reason);
    }
}
