<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * @internal
 */
final class MailerHeaderCollection
{
    /** @var list<MailerHeader> */
    private array $items = [];

    public function addItem(MailerHeader $mailerHeader): void
    {
        $this->items[] = $mailerHeader;
    }

    /**
     * @return list<MailerHeader>
     */
    public function list(): array
    {
        return $this->items;
    }
}
