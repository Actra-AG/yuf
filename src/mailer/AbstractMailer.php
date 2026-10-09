<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;

/**
 * Extension point: delivers a message that `AbstractMail` built (`SmtpMailer`, `MailMailer`).
 *
 * The clock, the id generator and the server name resolver are passed in so that a mailer can be tested without the
 * system clock, randomness or DNS.
 */
abstract class AbstractMailer
{
    private ?string $serverName = null;

    /**
     * @param string $serverAddress The IP address of this server (`HttpRequest::getServerAddress()`): its host name
     *                              is part of the message IDs
     */
    public function __construct(
        private readonly string $serverAddress,
        private readonly Clock $clock = new SystemClock(),
        private readonly MimeIdGenerator $mimeIdGenerator = new RandomMimeIdGenerator(),
        private readonly ServerNameResolver $serverNameResolver = new ReverseDnsServerNameResolver(cache: null),
    ) {}

    /**
     * Whether the message has a `To` header. `mail()` creates it from its first argument.
     */
    abstract public function headerHasTo(): bool;

    /**
     * Whether the message has a `Subject` header. `mail()` creates it from its second argument.
     */
    abstract public function headerHasSubject(): bool;

    /**
     * Whether the message has a `Bcc` header. SMTP must not have one: every recipient would see the blind copies.
     * `sendmail` removes it from the message `mail()` hands over.
     */
    abstract public function headerHasBcc(): bool;

    abstract public function getMaxLineLength(): int;

    abstract public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void;

    /**
     * The host name of this server: a plain host name or the server address.
     */
    public function getServerName(): string
    {
        $this->serverName ??= $this->serverNameResolver->resolve(serverAddress: $this->serverAddress);

        return $this->serverName;
    }

    public function getClock(): Clock
    {
        return $this->clock;
    }

    public function createUniqueId(): string
    {
        return $this->mimeIdGenerator->generate();
    }
}
