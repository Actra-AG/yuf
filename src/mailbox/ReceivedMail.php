<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use actra\yuf\mailer\MailerAddress;
use DateTimeImmutable;

/**
 * A message read from a mailbox, created by `MimeMessageParser` from the raw message. All texts are UTF-8.
 */
final readonly class ReceivedMail
{
    /**
     * @param string|null                 $messageId the header `Message-ID` as sent (with `<>`), `null` if missing
     * @param DateTimeImmutable|null      $date      the header `Date`, `null` if missing or invalid
     * @param MailerAddress|null          $from      the first address of `From`, `null` if missing or invalid
     * @param array<string, list<string>> $headers   all headers by lower case name, unfolded and RFC 2047 decoded
     * @param string                      $textBody  the text of the message: the `text/plain` parts, otherwise a text
     *                                               version of the `text/html` part; attachments are not included
     */
    public function __construct(
        public ?string $messageId,
        public ?DateTimeImmutable $date,
        public ?MailerAddress $from,
        public string $subject,
        public array $headers,
        public string $textBody,
        public string $rawMessage,
    ) {}

    /**
     * @return string|null the first value of the header (case-insensitive name), `null` if the message has none
     */
    public function getHeader(string $name): ?string
    {
        $key = strtolower(string: $name);

        return array_key_exists(key: $key, array: $this->headers) ? array_first(array: $this->headers[$key]) : null;
    }
}
