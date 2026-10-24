<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam\captcha;

/**
 * What a form does when the CAPTCHA provider cannot verify a response (`CaptchaStatusEnum::UNAVAILABLE`): `CLOSED`
 * blocks the submit, `OPEN` lets it pass with the other spam rules only. The error is logged either way.
 */
enum CaptchaFailModeEnum
{
    /** Allow: for forms where a lost request does more harm than some spam */
    case OPEN;
    /** Block: for forms that cost money, send many mails or are attacked a lot */
    case CLOSED;
}
