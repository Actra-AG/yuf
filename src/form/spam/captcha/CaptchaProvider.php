<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam\captcha;

use actra\yuf\html\HtmlTag;
use actra\yuf\security\CspNonce;

/**
 * A CAPTCHA service (e.g. `TurnstileCaptcha`): renders its widget into the form and verifies the response the widget
 * posts. Used through `FormCaptcha`, which applies the fail mode and logs.
 */
interface CaptchaProvider
{
    /**
     * The widget: its script and the element it renders into, rendered in this order into `<div class="form-captcha">`.
     * The widget posts its response in a field named `$responseFieldName`. No inline scripts or styles.
     *
     * @param ?CspNonce $cspNonce The nonce of the request for the script tag; `null` without nonce in the
     *                            Content-Security-Policy
     *
     * @return list<HtmlTag>
     */
    public function createWidgetTags(string $responseFieldName, ?CspNonce $cspNonce): array;

    /**
     * Verifies the posted response of the widget with the provider. Must not throw on errors of the provider or the
     * network: they are `CaptchaStatusEnum::UNAVAILABLE`.
     *
     * @param string $response The posted value of the response field, not empty
     * @param ?string $remoteIp The IP address of the visitor, `null` if unknown
     */
    public function verify(string $response, ?string $remoteIp): CaptchaResult;
}
