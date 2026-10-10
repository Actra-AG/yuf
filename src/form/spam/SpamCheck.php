<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam;

use actra\yuf\clock\Clock;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * The spam rules of a public form, without the form: a rejected e-mail address, a filled honeypot and the time between
 * rendering and submitting. The render time is signed (HMAC-SHA256 with the secret and the form name), so it cannot be
 * forged or taken from another form; a missing, unsigned or manipulated value counts as expired.
 */
final readonly class SpamCheck
{
    private const int MIN_SECRET_LENGTH = 32;
    private const string HASH_ALGORITHM = 'sha256';

    /** @var list<string> */
    private array $rejectedEmailAddresses;

    /**
     * @param string $secret At least 32 bytes, e.g. from `.env.php`; never from the request
     * @param list<string> $rejectedEmailAddresses E-mail addresses no submission may use (e.g. the own address of the
     *     project), compared case-insensitively; an empty list rejects none
     * @param int $minSeconds A submission earlier than this after rendering is too fast
     * @param int $maxSeconds A submission later than this after rendering is expired
     *
     * @throws InvalidArgumentException if the secret is too short or the time range is invalid
     */
    public function __construct(
        private Clock $clock,
        #[SensitiveParameter]
        private string $secret,
        array $rejectedEmailAddresses,
        private int $minSeconds = 3,
        private int $maxSeconds = 3600,
    ) {
        if (strlen(string: $secret) < SpamCheck::MIN_SECRET_LENGTH) {
            throw new InvalidArgumentException(
                message: 'The secret of the spam check must have at least ' . SpamCheck::MIN_SECRET_LENGTH . ' bytes.',
            );
        }
        if ($minSeconds < 0 || $maxSeconds <= $minSeconds) {
            throw new InvalidArgumentException(
                message: 'Invalid time range of the spam check: ' . $minSeconds . ' to ' . $maxSeconds . ' seconds.',
            );
        }
        $this->rejectedEmailAddresses = array_map(
            callback: static fn(string $emailAddress): string => mb_strtolower(string: trim(string: $emailAddress)),
            array: $rejectedEmailAddresses,
        );
    }

    /**
     * The current time with its signature (`<timestamp>.<signature>`), the value of the hidden field.
     */
    public function signRenderTime(string $formName): string
    {
        $timestamp = (string) $this->clock->now()->getTimestamp();

        return $timestamp . '.' . $this->createSignature(formName: $formName, timestamp: $timestamp);
    }

    /**
     * @param string $signedRenderTime The posted value of `signRenderTime()`
     * @param ?string $emailAddress The posted e-mail address; `null` if the form has none
     */
    public function check(
        string $formName,
        string $honeypot,
        string $signedRenderTime,
        ?string $emailAddress,
    ): SpamCheckResultEnum {
        if (
            $emailAddress !== null
            && in_array(
                needle: mb_strtolower(string: trim(string: $emailAddress)),
                haystack: $this->rejectedEmailAddresses,
                strict: true,
            )
        ) {
            return SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS;
        }
        if ($honeypot !== '') {
            return SpamCheckResultEnum::HONEYPOT_FILLED;
        }
        // At most 12 digits, so the timestamp fits into an int
        if (
            preg_match(pattern: '/^(\d{1,12})\.([0-9a-f]{64})$/D', subject: $signedRenderTime, matches: $matches) !== 1
        ) {
            return SpamCheckResultEnum::EXPIRED;
        }
        $signature = $this->createSignature(formName: $formName, timestamp: $matches[1]);
        if (!hash_equals(known_string: $signature, user_string: $matches[2])) {
            return SpamCheckResultEnum::EXPIRED;
        }
        $seconds = $this->clock->now()->getTimestamp() - (int) $matches[1];
        if ($seconds < $this->minSeconds) {
            return SpamCheckResultEnum::TOO_FAST;
        }

        return $seconds > $this->maxSeconds ? SpamCheckResultEnum::EXPIRED : SpamCheckResultEnum::OK;
    }

    private function createSignature(string $formName, string $timestamp): string
    {
        return hash_hmac(
            algo: SpamCheck::HASH_ALGORITHM,
            data: $formName . '|' . $timestamp,
            key: $this->secret,
        );
    }
}
