<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

final class MailerAddressCollection
{
    /** @var array<string, MailerAddress> by punycode encoded address */
    private array $items = [];

    public function addItem(MailerAddress $mailerAddress): void
    {
        $email = $mailerAddress->getPunyEncodedEmail();
        if (array_key_exists(key: $email, array: $this->items)) {
            throw new MailerException(
                message: 'The address is already a recipient (' . $mailerAddress->mailerAddressKindEnum->value . ').',
            );
        }

        $this->items[$email] = $mailerAddress;
    }

    public function getHeaderString(
        MailerAddressKindEnum $mailerAddressKindEnum,
        int $maxLineLength,
        MailerCharsetEnum $defaultCharSet,
    ): string {
        $listAsCommaSeparatedString = $this->listAsCommaSeparatedString(
            mailerAddressKindEnum: $mailerAddressKindEnum,
            maxLineLength: $maxLineLength,
            defaultCharSet: $defaultCharSet,
        );

        return $listAsCommaSeparatedString === '' ? '' : MailerHeader::createRaw(
            name: $mailerAddressKindEnum->value,
            value: $listAsCommaSeparatedString,
        );
    }

    public function listAsCommaSeparatedString(
        MailerAddressKindEnum $mailerAddressKindEnum,
        int $maxLineLength,
        MailerCharsetEnum $defaultCharSet,
    ): string {
        $formattedAddresses = [];
        foreach ($this->list(mailerAddressKindEnum: $mailerAddressKindEnum) as $mailerAddress) {
            $formattedAddresses[] = $mailerAddress->getFormattedAddressForMailer(
                maxLineLength: $maxLineLength,
                defaultCharSet: $defaultCharSet,
            );
        }

        return implode(separator: ', ', array: $formattedAddresses);
    }

    public function has(MailerAddressKindEnum $mailerAddressKindEnum): bool
    {
        return array_any(
            array: $this->items,
            callback: static fn(MailerAddress $mailerAddress): bool => $mailerAddress->mailerAddressKindEnum
                === $mailerAddressKindEnum,
        );
    }

    /**
     * @return list<MailerAddress> in the order they were added
     */
    public function list(MailerAddressKindEnum $mailerAddressKindEnum): array
    {
        return array_values(
            array: array_filter(
                array: $this->items,
                callback: static fn(MailerAddress $mailerAddress): bool => $mailerAddress->mailerAddressKindEnum
                    === $mailerAddressKindEnum,
            ),
        );
    }
}
