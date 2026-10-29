<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use InvalidArgumentException;
use LogicException;
use Override;

/**
 * Reads and moves the messages of one mailbox over IMAP (RFC 9051, also IMAP4rev1 servers), without `ext-imap`. The
 * connection is always encrypted (implicit TLS or STARTTLS, certificate verified) before the credentials are sent; the
 * login uses `AUTHENTICATE PLAIN` if the server offers it, otherwise `LOGIN`.
 */
final class StreamImapMailbox implements ImapMailbox
{
    private ?ImapConnection $connection = null;
    private ?ImapCapabilities $capabilities = null;
    private ?string $selectedFolder = null;

    public function __construct(
        private readonly ImapTransport $transport = new StreamImapTransport(),
        private readonly MimeMessageParser $mimeMessageParser = new MimeMessageParser(),
    ) {}

    #[Override]
    public function open(ImapSettings $settings): void
    {
        $this->close();
        $isImplicitTls = $settings->encryption === ImapEncryptionEnum::IMPLICIT_TLS;
        $this->transport->open(
            hostName: $settings->hostName,
            port: $settings->port,
            timeoutSeconds: $settings->timeoutSeconds,
            implicitTls: $isImplicitTls,
        );
        $connection = new ImapConnection(transport: $this->transport);
        $connection->hide(value: $settings->password);
        try {
            $connection->readGreeting();
            if (!$isImplicitTls) {
                StreamImapMailbox::startTls(connection: $connection, transport: $this->transport);
            }
            StreamImapMailbox::login(connection: $connection, settings: $settings);
            $this->capabilities = ImapCapabilities::fromResponse(response: $connection->command(name: 'CAPABILITY'));
        } catch (ImapException $imapException) {
            $this->transport->close();

            throw $imapException;
        }
        $this->connection = $connection;
    }

    #[Override]
    public function selectFolder(string $name): void
    {
        $this->selectedFolder = null;
        $this->getConnection()->command(name: 'SELECT', arguments: [ImapArgument::folderName(name: $name)]);
        $this->selectedFolder = $name;
    }

    #[Override]
    public function findUids(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(message: 'The limit must be at least 1.');
        }
        $response = $this->getSelectedConnection()->command(name: 'UID SEARCH', arguments: ['UNDELETED']);
        $uids = [];
        foreach ($response->findUntagged(type: 'SEARCH') as $line) {
            foreach (array_slice(array: explode(separator: ' ', string: $line->getText()), offset: 2) as $word) {
                if (preg_match(pattern: '/^[1-9]\d*$/D', subject: $word) === 1) {
                    $uids[] = (int) $word;
                }
            }
        }
        sort(array: $uids);

        return array_slice(array: array_values(array: array_unique(array: $uids)), offset: 0, length: $limit);
    }

    #[Override]
    public function fetchMessage(int $uid): ReceivedMail
    {
        StreamImapMailbox::checkUid(uid: $uid);
        $response = $this->getSelectedConnection()->command(
            name: 'UID FETCH',
            arguments: [(string) $uid, '(UID BODY.PEEK[])'],
        );
        foreach ($response->findUntagged(type: 'FETCH') as $line) {
            if (preg_match(pattern: '/\bUID ' . $uid . '\b/i', subject: $line->getText()) !== 1) {
                continue;
            }
            $rawMessage = $line->findLiteralAfter(pattern: 'BODY\[\]');
            if ($rawMessage !== null) {
                return $this->mimeMessageParser->parse(rawMessage: $rawMessage);
            }
        }

        throw new ImapException(
            message: 'The folder "' . $this->selectedFolder . '" has no message with UID ' . $uid . '.',
            serverResponse: $response->statusLine,
        );
    }

    #[Override]
    public function moveMessage(int $uid, string $targetFolder): void
    {
        StreamImapMailbox::checkUid(uid: $uid);
        $connection = $this->getSelectedConnection();
        $folderArgument = ImapArgument::folderName(name: $targetFolder);
        $capabilities = $this->capabilities ?? throw new LogicException(message: 'The mailbox is not open.');
        if ($capabilities->has(capability: ImapCapabilityEnum::MOVE)) {
            $connection->command(name: 'UID MOVE', arguments: [(string) $uid, $folderArgument]);

            return;
        }
        // Without UID EXPUNGE, EXPUNGE would also remove other messages marked as deleted
        if (!$capabilities->has(capability: ImapCapabilityEnum::UIDPLUS)) {
            throw new ImapException(
                message: 'The IMAP server supports neither MOVE nor UIDPLUS: cannot move messages.',
            );
        }
        $connection->command(name: 'UID COPY', arguments: [(string) $uid, $folderArgument]);
        $connection->command(name: 'UID STORE', arguments: [(string) $uid, '+FLAGS.SILENT', '(\Deleted)']);
        $connection->command(name: 'UID EXPUNGE', arguments: [(string) $uid]);
    }

    #[Override]
    public function close(): void
    {
        $connection = $this->connection;
        $this->connection = null;
        $this->capabilities = null;
        $this->selectedFolder = null;
        if ($connection === null) {
            return;
        }
        try {
            if ($this->transport->isOpen()) {
                $connection->logout();
            }
        } finally {
            $this->transport->close();
        }
    }

    private static function startTls(ImapConnection $connection, ImapTransport $transport): void
    {
        $capabilities = ImapCapabilities::fromResponse(response: $connection->command(name: 'CAPABILITY'));
        if (!$capabilities->has(capability: ImapCapabilityEnum::STARTTLS)) {
            throw new ImapException(message: 'The IMAP server does not offer STARTTLS. Nothing was sent.');
        }
        $connection->command(name: 'STARTTLS');
        if (!$transport->enableTls()) {
            throw new ImapException(message: 'The TLS handshake with the IMAP server failed. Nothing was sent.');
        }
    }

    private static function login(ImapConnection $connection, ImapSettings $settings): void
    {
        $capabilities = ImapCapabilities::fromResponse(response: $connection->command(name: 'CAPABILITY'));
        if ($capabilities->has(capability: ImapCapabilityEnum::AUTH_PLAIN)) {
            $connection->authenticatePlain(
                credentials: "\0" . $settings->userName . "\0" . $settings->password,
                hasInitialResponse: $capabilities->has(capability: ImapCapabilityEnum::SASL_IR),
            );

            return;
        }
        if ($capabilities->has(capability: ImapCapabilityEnum::LOGINDISABLED)) {
            throw new ImapException(message: 'The IMAP server offers neither AUTH=PLAIN nor LOGIN.');
        }
        $connection->command(
            name: 'LOGIN',
            arguments: [
                ImapArgument::string(value: $settings->userName),
                ImapArgument::string(value: $settings->password),
            ],
        );
    }

    private static function checkUid(int $uid): void
    {
        if ($uid < 1) {
            throw new InvalidArgumentException(message: 'Invalid UID ' . $uid . '.');
        }
    }

    private function getConnection(): ImapConnection
    {
        return $this->connection ?? throw new LogicException(message: 'The mailbox is not open: call open() first.');
    }

    private function getSelectedConnection(): ImapConnection
    {
        $connection = $this->getConnection();
        if ($this->selectedFolder === null) {
            throw new LogicException(message: 'No folder is selected: call selectFolder() first.');
        }

        return $connection;
    }
}
