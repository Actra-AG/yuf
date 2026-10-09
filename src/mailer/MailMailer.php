<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\FileCache;
use Override;

/**
 * Sends with the PHP function `mail()`: the `sendmail` of the server delivers the message.
 */
final class MailMailer extends AbstractMailer
{
    /**
     * @param ?FileCache $serverNameCache Keeps the host name of the server (reverse DNS, used in the message IDs) for
     *                                    a day: `$core->fileCache`; `null` looks it up per mailer
     * @param ?ServerNameResolver $serverNameResolver Default: reverse DNS with the `serverNameCache`
     */
    public function __construct(
        string $serverAddress,
        ?FileCache $serverNameCache,
        private readonly MailFunction $mailFunction = new NativeMailFunction(),
        Clock $clock = new SystemClock(),
        MimeIdGenerator $mimeIdGenerator = new RandomMimeIdGenerator(),
        ?ServerNameResolver $serverNameResolver = null,
    ) {
        parent::__construct(
            serverAddress: $serverAddress,
            clock: $clock,
            mimeIdGenerator: $mimeIdGenerator,
            serverNameResolver: $serverNameResolver ?? new ReverseDnsServerNameResolver(cache: $serverNameCache),
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return false;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return false;
    }

    #[Override]
    public function headerHasBcc(): bool
    {
        return true;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return MailerConstants::MAIL_MAX_LINE_LENGTH;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $sentToSendmail = $this->mailFunction->send(
            to: $abstractMail->mailerAddressCollection->listAsCommaSeparatedString(
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO,
                maxLineLength: $this->getMaxLineLength(),
                defaultCharSet: $abstractMail->charSet,
            ),
            subject: $abstractMail->getSubjectForHeader(maxLineLength: $this->getMaxLineLength()),
            message: $mailMimeBody->getMimeBody(),
            additionalHeaders: $mailMimeHeader->getMimeHeader() . MailerConstants::CRLF . MailerConstants::CRLF,
            additionalParameters: $this->envelopeSenderParameter(
                senderEmail: $abstractMail->sender->getPunyEncodedEmail(),
            ),
        );
        if (!$sentToSendmail) {
            throw new MailerException(message: 'The mail() function did not accept the message.');
        }
    }

    /**
     * The `-f` argument of `sendmail` sets the envelope sender. It is only passed on if every character is harmless in
     * a shell (letters, digits and `@_-.`), otherwise the default sender of the server is used.
     */
    private function envelopeSenderParameter(string $senderEmail): string
    {
        return MailMailer::isShellSafe(string: $senderEmail) ? '-f' . $senderEmail : '';
    }

    private static function isShellSafe(string $string): bool
    {
        // Every other character has a special meaning in at least one common shell, including = and +. A full stop
        // has a special meaning in cmd.exe, but its impact is negligible here
        return preg_match(pattern: '/^[A-Za-z0-9@_.-]+$/D', subject: $string) === 1;
    }
}
