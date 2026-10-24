<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam\captcha;

/**
 * The outcome of a CAPTCHA verification (`CaptchaResult::$status`).
 */
enum CaptchaStatusEnum
{
    /** The visitor solved the challenge for this site */
    case VALID;
    /** No, an expired, reused or foreign response, or a response the provider rejects */
    case INVALID;
    /** The provider could not verify the response (unreachable, error of the provider, wrong configuration) */
    case UNAVAILABLE;
}
