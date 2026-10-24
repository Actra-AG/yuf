<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam\captcha;

use actra\yuf\api\CurlClient;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\common\JsonUtils;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\security\CspNonce;
use InvalidArgumentException;
use JsonException;
use Override;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Cloudflare Turnstile (https://developers.cloudflare.com/turnstile/): renders the widget (implicit rendering) and
 * verifies its response with the Siteverify API (`POST {siteverifyUrl}` with `secret`, `response` and `remoteip`).
 * A valid response has `success: true`, one of the `$hostnames` and, if given, the `$action`.
 *
 * A response is valid for 300 seconds and can be verified once. The errors `missing-input-secret`,
 * `invalid-input-secret`, `bad-request` and `internal-error`, a failed transfer (3 seconds to connect, 6 seconds in
 * all) and an invalid answer are `CaptchaStatusEnum::UNAVAILABLE`, every other failure is `INVALID`. The reasons never
 * contain the secret key or the response.
 */
final readonly class TurnstileCaptcha implements CaptchaProvider
{
    public const string SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    public const string SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const int MAX_RESPONSE_LENGTH = 2048;
    private const int CONNECT_TIMEOUT_IN_SECONDS = 3;
    private const int REQUEST_TIMEOUT_IN_SECONDS = 6;
    private const int MAX_ANSWER_SIZE_IN_BYTES = 65536;
    private const string ACTION_PATTERN = '/^[A-Za-z0-9_-]{1,32}$/D';
    private const string ERROR_CODE_PATTERN = '/^[a-z-]{1,64}$/D';
    private const array UNAVAILABLE_ERROR_CODES = [
        'missing-input-secret',
        'invalid-input-secret',
        'bad-request',
        'internal-error',
    ];

    /** @var ?list<string> */
    private ?array $hostnames;

    /**
     * @param string $siteKey The public key of the widget (Cloudflare dashboard)
     * @param string $secretKey The secret key of the widget, e.g. from `.env.php`
     * @param ?list<string> $hostnames The host names the response must come from (`www.example.com`), compared
     *     case-insensitively; `null` as explicit opt-out (only where the answer has another host name, e.g. the test
     *     keys of Cloudflare)
     * @param ?string $action Rendered as `data-action` and checked in the answer: up to 32 letters, digits, `_` and
     *     `-` (`contact`); `null` for none
     * @param string $siteverifyUrl Tests use a local server
     *
     * @throws InvalidArgumentException if a key is empty, `$hostnames` is an empty list or the action is invalid
     */
    public function __construct(
        private string $siteKey,
        #[SensitiveParameter]
        private string $secretKey,
        ?array $hostnames,
        private ?string $action = null,
        private string $siteverifyUrl = TurnstileCaptcha::SITEVERIFY_URL,
        private CurlClient $curlClient = new CurlClient(),
    ) {
        if ($siteKey === '' || $secretKey === '') {
            throw new InvalidArgumentException(message: 'The site key and the secret key of Turnstile are required.');
        }
        if ($hostnames === []) {
            throw new InvalidArgumentException(
                message: 'Turnstile needs at least one host name; pass null to skip the host name check.',
            );
        }
        if ($action !== null && preg_match(pattern: TurnstileCaptcha::ACTION_PATTERN, subject: $action) !== 1) {
            throw new InvalidArgumentException(
                message: 'The action of Turnstile must have 1 to 32 letters, digits, _ and -.',
            );
        }
        $this->hostnames = $hostnames === null ? null : array_map(
            callback: static fn(string $hostname): string => strtolower(string: $hostname),
            array: $hostnames,
        );
    }

    /**
     * @return list<HtmlTag>
     */
    #[Override]
    public function createWidgetTags(string $responseFieldName, ?CspNonce $cspNonce): array
    {
        $scriptAttributes = [HtmlTagAttribute::fromText(name: 'src', text: TurnstileCaptcha::SCRIPT_URL)];
        if ($cspNonce !== null) {
            $scriptAttributes[] = HtmlTagAttribute::fromText(name: 'nonce', text: $cspNonce->value);
        }
        $scriptAttributes[] = HtmlTagAttribute::fromName(name: 'async');
        $scriptAttributes[] = HtmlTagAttribute::fromName(name: 'defer');
        $widgetAttributes = [
            HtmlTagAttribute::fromText(name: 'class', text: 'cf-turnstile'),
            HtmlTagAttribute::fromText(name: 'data-sitekey', text: $this->siteKey),
            HtmlTagAttribute::fromText(name: 'data-response-field-name', text: $responseFieldName),
        ];
        if ($this->action !== null) {
            $widgetAttributes[] = HtmlTagAttribute::fromText(name: 'data-action', text: $this->action);
        }

        return [
            new HtmlTag(name: 'script', selfClosing: false, htmlTagAttributes: $scriptAttributes),
            new HtmlTag(name: 'div', selfClosing: false, htmlTagAttributes: $widgetAttributes),
        ];
    }

    #[Override]
    public function verify(string $response, ?string $remoteIp): CaptchaResult
    {
        // The documented maximum: a longer response is invalid, Cloudflare need not be asked
        if ($response === '' || strlen(string: $response) > TurnstileCaptcha::MAX_RESPONSE_LENGTH) {
            return CaptchaResult::invalid(reason: 'The response is empty or too long.');
        }
        $postData = ['secret' => $this->secretKey, 'response' => $response];
        if ($remoteIp !== null && $remoteIp !== '') {
            $postData['remoteip'] = $remoteIp;
        }
        $request = CurlPostRequest::createWithPostBody(requestTargetUrl: $this->siteverifyUrl, postData: $postData);
        $request->setHttpHeader(key: 'Accept', value: 'application/json');
        $request->setTimeoutInSeconds(
            connectTimeOut: TurnstileCaptcha::CONNECT_TIMEOUT_IN_SECONDS,
            requestTimeOut: TurnstileCaptcha::REQUEST_TIMEOUT_IN_SECONDS,
        );
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: TurnstileCaptcha::MAX_ANSWER_SIZE_IN_BYTES);
        $answer = $this->curlClient->send(request: $request);
        if ($answer->rawResponseBody === false || $answer->hasErrors()) {
            return CaptchaResult::unavailable(
                reason: 'Turnstile Siteverify failed (HTTP status ' . $answer->curlInfo['http_code'] . '): '
                    . $answer->errorMessage,
            );
        }

        return $this->evaluate(body: $answer->rawResponseBody);
    }

    private function evaluate(string $body): CaptchaResult
    {
        try {
            $data = JsonUtils::decodeJsonString(jsonString: $body, returnAssociativeArray: true);
        } catch (JsonException|UnexpectedValueException) {
            return CaptchaResult::unavailable(reason: 'The answer of Turnstile Siteverify is no JSON.');
        }
        if (!is_array(value: $data) || !array_key_exists(key: 'success', array: $data)) {
            return CaptchaResult::unavailable(reason: 'The answer of Turnstile Siteverify has no success.');
        }
        if ($data['success'] !== true) {
            return $this->evaluateErrorCodes(data: $data);
        }
        $hostname = array_key_exists(key: 'hostname', array: $data) ? $data['hostname'] : null;
        if (
            $this->hostnames !== null
            && (
                !is_string(value: $hostname)
                || !in_array(needle: strtolower(string: $hostname), haystack: $this->hostnames, strict: true)
            )
        ) {
            return CaptchaResult::invalid(reason: 'The response of Turnstile is for another host name.');
        }
        $action = array_key_exists(key: 'action', array: $data) ? $data['action'] : null;
        if ($this->action !== null && $action !== $this->action) {
            return CaptchaResult::invalid(reason: 'The response of Turnstile is for another action.');
        }

        return CaptchaResult::valid();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function evaluateErrorCodes(array $data): CaptchaResult
    {
        $errorCodes = array_key_exists(key: 'error-codes', array: $data) && is_array(value: $data['error-codes'])
            ? $data['error-codes']
            : [];
        $knownCodes = [];
        $isUnavailable = false;
        foreach ($errorCodes as $errorCode) {
            // Only codes of the documented form are logged: the answer is not trusted
            if (
                !is_string(value: $errorCode)
                || preg_match(pattern: TurnstileCaptcha::ERROR_CODE_PATTERN, subject: $errorCode) !== 1
            ) {
                continue;
            }
            $knownCodes[] = $errorCode;
            if (in_array(needle: $errorCode, haystack: TurnstileCaptcha::UNAVAILABLE_ERROR_CODES, strict: true)) {
                $isUnavailable = true;
            }
        }
        $reason = 'Turnstile rejected the response: '
            . ($knownCodes === [] ? 'no error code' : implode(separator: ', ', array: $knownCodes));

        return $isUnavailable ? CaptchaResult::unavailable(reason: $reason) : CaptchaResult::invalid(reason: $reason);
    }
}
