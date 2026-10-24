<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam;

use actra\yuf\core\Logger;
use actra\yuf\form\spam\captcha\CaptchaFailModeEnum;
use actra\yuf\form\spam\captcha\CaptchaProvider;
use actra\yuf\form\spam\captcha\CaptchaStatusEnum;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\security\CspNonce;

/**
 * The CAPTCHA of a public form (`SpamProtection::$captcha`): the widget of the provider (e.g. `TurnstileCaptcha`) in
 * the form and the verification of its response, which `Form::validate()` runs after all other spam rules, so a
 * submit that fails them costs no request to the provider.
 */
final readonly class FormCaptcha
{
    /**
     * @param CaptchaFailModeEnum $failMode Block or allow the submit when the provider cannot verify the response
     * @param Logger $logger Logs why the provider could not verify a response
     * @param ?CspNonce $cspNonce The nonce of the request for the script of the widget
     *     (`$viewContext->content->cspNonce`); `null` without nonce in the Content-Security-Policy
     * @param string $fieldName Name of the field in which the widget posts its response
     */
    public function __construct(
        public CaptchaProvider $provider,
        public CaptchaFailModeEnum $failMode,
        public Logger $logger,
        public ?CspNonce $cspNonce,
        public string $fieldName = 'captcha',
    ) {}

    /**
     * @return HtmlTag `<div class="form-captcha">` with the widget of the provider
     */
    public function createWidgetTag(): HtmlTag
    {
        $containerTag = new HtmlTag(name: 'div', selfClosing: false, htmlTagAttributes: [
            HtmlTagAttribute::fromText(name: 'class', text: 'form-captcha'),
        ]);
        $widgetTags = $this->provider->createWidgetTags(
            responseFieldName: $this->fieldName,
            cspNonce: $this->cspNonce,
        );
        foreach ($widgetTags as $widgetTag) {
            $containerTag->addTag(htmlTag: $widgetTag);
        }

        return $containerTag;
    }

    /**
     * Verifies the posted response with the provider (an empty response without request) and applies the fail mode.
     *
     * @param ?string $remoteIp The IP address of the visitor, `null` if unknown
     *
     * @return CaptchaStatusEnum `VALID`, `INVALID`, or `UNAVAILABLE` with `CaptchaFailModeEnum::CLOSED` only
     */
    public function check(string $response, ?string $remoteIp): CaptchaStatusEnum
    {
        if ($response === '') {
            return CaptchaStatusEnum::INVALID;
        }
        $result = $this->provider->verify(response: $response, remoteIp: $remoteIp);
        if ($result->status !== CaptchaStatusEnum::UNAVAILABLE) {
            return $result->status;
        }
        $this->logger->logMessage(message: 'The CAPTCHA could not be verified: ' . $result->reason);

        return $this->failMode === CaptchaFailModeEnum::OPEN
            ? CaptchaStatusEnum::VALID
            : CaptchaStatusEnum::UNAVAILABLE;
    }
}
