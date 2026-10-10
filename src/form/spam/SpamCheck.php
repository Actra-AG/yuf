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
 * rendering and submitting. The render time is signed (HMAC-SHA256 with the secret, the form name and a value that is
 * bound to the session of the user), so it cannot be forged, taken from another form or used in another session; a
 * missing, unsigned or manipulated value counts as expired.
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
     *
     * @param ?string $sessionBinding A value that is secret and specific to the session of the user (the CSRF token);
     *                                `null` only without session
     */
    public function signRenderTime(string $formName, ?string $sessionBinding): string
    {
        $timestamp = (string) $this->clock->now()->getTimestamp();

        return $timestamp . '.' . $this->createSignature(
            formName: $formName,
            timestamp: $timestamp,
            sessionBinding: $sessionBinding,
        );
    }

    /**
     * @param string $signedRenderTime The posted value of `signRenderTime()`
     * @param ?string $emailAddress The posted e-mail address; `null` if the form has none
     * @param ?string $sessionBinding The value of `signRenderTime()`'s argument of the current session
     */
    public function check(
        string $formName,
        string $honeypot,
        string $signedRenderTime,
        ?string $emailAddress,
        ?string $sessionBinding,
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
        $renderedAt = $this->parseRenderTime(
            formName: $formName,
            signedRenderTime: $signedRenderTime,
            sessionBinding: $sessionBinding,
        );
        if ($renderedAt === null) {
            return SpamCheckResultEnum::EXPIRED;
        }
        $seconds = $this->clock->now()->getTimestamp() - $renderedAt;
        if ($seconds < $this->minSeconds) {
            return SpamCheckResultEnum::TOO_FAST;
        }

        return $seconds > $this->maxSeconds ? SpamCheckResultEnum::EXPIRED : SpamCheckResultEnum::OK;
    }

    /**
     * Whether the posted render time is signed for this form and not expired; the minimum time is not checked. A form
     * rendered again after field errors keeps such a render time, so the minimum time does not start again.
     *
     * @param string $signedRenderTime The posted value of `signRenderTime()`
     * @param ?string $sessionBinding The binding value of the current session
     */
    public function isValidRenderTime(string $formName, string $signedRenderTime, ?string $sessionBinding): bool
    {
        $renderedAt = $this->parseRenderTime(
            formName: $formName,
            signedRenderTime: $signedRenderTime,
            sessionBinding: $sessionBinding,
        );

        return $renderedAt !== null && $this->clock->now()->getTimestamp() - $renderedAt <= $this->maxSeconds;
    }

    /**
     * @return ?int The timestamp of a validly signed render time; `null` if it is missing, unsigned or manipulated
     */
    private function parseRenderTime(string $formName, string $signedRenderTime, ?string $sessionBinding): ?int
    {
        // At most 12 digits, so the timestamp fits into an int
        if (
            preg_match(pattern: '/^(\d{1,12})\.([0-9a-f]{64})$/D', subject: $signedRenderTime, matches: $matches) !== 1
        ) {
            return null;
        }
        $signature = $this->createSignature(
            formName: $formName,
            timestamp: $matches[1],
            sessionBinding: $sessionBinding,
        );

        return hash_equals(known_string: $signature, user_string: $matches[2]) ? (int) $matches[1] : null;
    }

    private function createSignature(string $formName, string $timestamp, ?string $sessionBinding): string
    {
        // JSON keeps the parts apart, so no value can shift into another one
        return hash_hmac(
            algo: SpamCheck::HASH_ALGORITHM,
            data: json_encode(value: [$formName, $timestamp, $sessionBinding], flags: JSON_THROW_ON_ERROR),
            key: $this->secret,
        );
    }
}
