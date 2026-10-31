<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use DateTimeImmutable;

/**
 * What a `SentMailListener` learns about a sent mail: the time (from the clock of the mailer), the subject and the
 * addresses (lower case, punycode encoded domain, without display names).
 */
final readonly class SentMail
{
    /**
     * @param list<string> $toEmails
     * @param list<string> $ccEmails
     * @param list<string> $bccEmails
     */
    public function __construct(
        public DateTimeImmutable $sentAt,
        public string $fromEmail,
        public array $toEmails,
        public array $ccEmails,
        public array $bccEmails,
        public string $subject,
    ) {}

    public static function fromMail(AbstractMail $abstractMail, DateTimeImmutable $sentAt): SentMail
    {
        $mailerAddressCollection = $abstractMail->mailerAddressCollection;

        return new SentMail(
            sentAt: $sentAt,
            fromEmail: $abstractMail->fromAddress->getPunyEncodedEmail(),
            toEmails: SentMail::listEmails(
                mailerAddressCollection: $mailerAddressCollection,
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO,
            ),
            ccEmails: SentMail::listEmails(
                mailerAddressCollection: $mailerAddressCollection,
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_CC,
            ),
            bccEmails: SentMail::listEmails(
                mailerAddressCollection: $mailerAddressCollection,
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_BCC,
            ),
            subject: $abstractMail->getSubject(),
        );
    }

    /**
     * @return list<string>
     */
    private static function listEmails(
        MailerAddressCollection $mailerAddressCollection,
        MailerAddressKindEnum $mailerAddressKindEnum,
    ): array {
        return array_map(
            callback: static fn(MailerAddress $mailerAddress): string => $mailerAddress->getPunyEncodedEmail(),
            array: $mailerAddressCollection->list(mailerAddressKindEnum: $mailerAddressKindEnum),
        );
    }
}
