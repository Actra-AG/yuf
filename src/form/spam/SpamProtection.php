<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam;

use actra\yuf\clock\Clock;
use actra\yuf\form\component\field\EmailField;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * The spam protection of a public form, added with `Form::addSpamProtection()`: a honeypot text field that must stay
 * empty (hidden by the CSS of the project) and a hidden field with the signed render time, checked by `SpamCheck` in
 * `Form::validate()`.
 */
final readonly class SpamProtection
{
    public SpamCheck $spamCheck;

    /**
     * @param string $secret At least 32 bytes, e.g. from `.env.php`; never from the request
     * @param ?EmailField $emailField The field whose address is checked against `$rejectedEmailAddresses`
     * @param list<string> $rejectedEmailAddresses E-mail addresses no submission may use (e.g. the own address of the
     *     project), compared case-insensitively; an empty list as explicit opt-out
     * @param string $honeypotName Name (and id) of the honeypot field
     * @param string $renderTimeName Name of the hidden field with the signed render time
     * @param int $minSeconds A submission earlier than this after rendering is too fast
     * @param int $maxSeconds A submission later than this after rendering is expired
     *
     * @throws InvalidArgumentException if the secret is too short, the time range is invalid or there are rejected
     *     addresses without e-mail field
     */
    public function __construct(
        Clock $clock,
        #[SensitiveParameter]
        string $secret,
        public ?EmailField $emailField,
        array $rejectedEmailAddresses,
        public string $honeypotName = 'internal',
        public string $renderTimeName = 'timestamp',
        int $minSeconds = 3,
        int $maxSeconds = 3600,
    ) {
        if ($emailField === null && $rejectedEmailAddresses !== []) {
            throw new InvalidArgumentException(message: 'Rejected e-mail addresses need an e-mail field.');
        }
        $this->spamCheck = new SpamCheck(
            clock: $clock,
            secret: $secret,
            rejectedEmailAddresses: $rejectedEmailAddresses,
            minSeconds: $minSeconds,
            maxSeconds: $maxSeconds,
        );
    }
}
