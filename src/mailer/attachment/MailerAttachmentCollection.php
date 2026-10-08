<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer\attachment;

use actra\yuf\mailer\MailerException;

final class MailerAttachmentCollection
{
    /** @var array<int|string, MailerAttachment> by file name (a numeric name becomes an int key) */
    private array $items = [];

    public function addItem(MailerAttachment $mailerAttachment): void
    {
        $fileName = $mailerAttachment->fileName;
        if (array_key_exists(key: $fileName, array: $this->items)) {
            throw new MailerException(message: 'Attachment with fileName "' . $fileName . '" already exists.');
        }
        $this->items[$fileName] = $mailerAttachment;
    }

    /**
     * @return list<MailerAttachment> in the order they were added
     */
    public function list(): array
    {
        return array_values(array: $this->items);
    }

    public function hasInlineImages(): bool
    {
        return array_any(
            array: $this->items,
            callback: static fn(MailerAttachment $mailerAttachment): bool => $mailerAttachment->dispositionInline,
        );
    }

    public function hasAttachments(): bool
    {
        return array_any(
            array: $this->items,
            callback: static fn(MailerAttachment $mailerAttachment): bool => !$mailerAttachment->dispositionInline,
        );
    }
}
