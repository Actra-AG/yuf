<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

abstract class AbstractMailer
{
    /**
     * @param string $serverAddress The IP address of this server (`HttpRequest::getServerAddress()`): its host name
     *                              is part of the message IDs
     */
    public function __construct(private readonly string $serverAddress) {}

    abstract public function headerHasTo(): bool;

    abstract public function headerHasSubject(): bool;

    abstract public function getMaxLineLength(): int;

    abstract public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void;

    public function getServerName(): string
    {
        $serverName = gethostbyaddr(ip: $this->serverAddress);

        return $serverName === false ? $this->serverAddress : $serverName;
    }
}
