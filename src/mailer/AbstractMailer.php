<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

abstract class AbstractMailer
{
    abstract public function headerHasTo(): bool;

    abstract public function headerHasSubject(): bool;

    abstract public function getMaxLineLength(): int;

    abstract public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody
    ): void;

    public function getServerName(): string
    {
        return gethostbyaddr(ip: $_SERVER['SERVER_ADDR']);
    }
}