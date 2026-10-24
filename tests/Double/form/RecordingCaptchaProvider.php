<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\spam\captcha\CaptchaProvider;
use actra\yuf\form\spam\captcha\CaptchaResult;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\security\CspNonce;
use Override;

/**
 * A CAPTCHA provider that answers every verification with the prepared result and records the verifications.
 */
final class RecordingCaptchaProvider implements CaptchaProvider
{
    /** @var list<array{response: string, remoteIp: ?string}> */
    public array $verifications = [];

    public function __construct(public CaptchaResult $result) {}

    /**
     * @return list<HtmlTag>
     */
    #[Override]
    public function createWidgetTags(string $responseFieldName, ?CspNonce $cspNonce): array
    {
        return [
            new HtmlTag(name: 'div', selfClosing: false, htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'data-field', text: $responseFieldName),
                HtmlTagAttribute::fromText(name: 'data-nonce', text: $cspNonce === null ? '' : $cspNonce->value),
            ]),
        ];
    }

    #[Override]
    public function verify(string $response, ?string $remoteIp): CaptchaResult
    {
        $this->verifications[] = ['response' => $response, 'remoteIp' => $remoteIp];

        return $this->result;
    }
}
