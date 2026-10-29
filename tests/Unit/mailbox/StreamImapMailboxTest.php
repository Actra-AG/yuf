<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\ImapEncryptionEnum;
use actra\yuf\mailbox\ImapException;
use actra\yuf\mailbox\ImapSettings;
use actra\yuf\mailbox\StreamImapMailbox;
use actra\yuf\tests\Double\mailbox\FakeImapTransport;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The IMAP dialogue with a scripted server (RFC 9051, Dovecot-like answers).
 */
final class StreamImapMailboxTest extends TestCase
{
    private const string PASSWORD = 'secret-pass';
    private const string GREETING = "* OK [CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN] Dovecot ready.\r\n";
    private const string CAPABILITIES_AFTER_LOGIN = "* CAPABILITY IMAP4rev1 SASL-IR UIDPLUS MOVE IDLE\r\n";
    private const string MESSAGE = "Message-ID: <a@example.com>\r\nSubject: Test\r\n\r\nBody\r\n";

    public function testDialogueWithImplicitTls(): void
    {
        $transport = new FakeImapTransport(answers: [
            StreamImapMailboxTest::GREETING,
            "* CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN\r\nA0001 OK done\r\n",
            "A0002 OK [CAPABILITY IMAP4rev1 MOVE] Logged in\r\n",
            StreamImapMailboxTest::CAPABILITIES_AFTER_LOGIN . "A0003 OK done\r\n",
            "* FLAGS (\\Seen)\r\n* 2 EXISTS\r\n* OK [UIDVALIDITY 1] UIDs valid\r\n"
            . "A0004 OK [READ-WRITE] Select completed.\r\n",
            "* SEARCH 9 7\r\nA0005 OK Search completed.\r\n",
            '* 1 FETCH (UID 7 BODY[] {' . strlen(string: StreamImapMailboxTest::MESSAGE) . "}\r\n"
            . StreamImapMailboxTest::MESSAGE . ")\r\nA0006 OK Fetch completed.\r\n",
            "* OK [COPYUID 1 7 3] Moved UIDs.\r\n* 1 EXPUNGE\r\nA0007 OK Move completed.\r\n",
            "* BYE Logging out\r\nA0008 OK Logout completed.\r\n",
        ]);
        $mailbox = new StreamImapMailbox(transport: $transport);

        $mailbox->open(settings: StreamImapMailboxTest::settings());
        $mailbox->selectFolder(name: 'INBOX');
        $uids = $mailbox->findUids(limit: 10);
        $mail = $mailbox->fetchMessage(uid: 7);
        $mailbox->moveMessage(uid: 7, targetFolder: 'INBOX.Processed');
        $mailbox->close();

        $this->assertSame([7, 9], $uids);
        $this->assertSame('<a@example.com>', $mail->messageId);
        $this->assertSame('Test', $mail->subject);
        $this->assertSame('Body', $mail->textBody);
        $this->assertSame(StreamImapMailboxTest::MESSAGE, $mail->rawMessage);
        $this->assertSame(
            [
                'open tls imap.example.com:993',
                'write A0001 CAPABILITY',
                'write A0002 AUTHENTICATE PLAIN ' . StreamImapMailboxTest::credentials(),
                'write A0003 CAPABILITY',
                'write A0004 SELECT "INBOX"',
                'write A0005 UID SEARCH UNDELETED',
                'write A0006 UID FETCH 7 (UID BODY.PEEK[])',
                'write A0007 UID MOVE 7 "INBOX.Processed"',
                'write A0008 LOGOUT',
                'close',
            ],
            $transport->events,
        );
    }

    public function testStartTlsBeforeTheLoginWithoutInitialResponse(): void
    {
        $transport = new FakeImapTransport(answers: [
            "* OK ready\r\n",
            "* CAPABILITY IMAP4rev1 STARTTLS LOGINDISABLED\r\nA0001 OK done\r\n",
            "A0002 OK Begin TLS negotiation now.\r\n",
            "* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nA0003 OK done\r\n",
            "+ \r\n",
            "A0004 OK Logged in\r\n",
            StreamImapMailboxTest::CAPABILITIES_AFTER_LOGIN . "A0005 OK done\r\n",
        ]);

        new StreamImapMailbox(transport: $transport)->open(
            settings: StreamImapMailboxTest::settings(encryption: ImapEncryptionEnum::STARTTLS),
        );

        $this->assertSame(
            [
                'open imap.example.com:143',
                'write A0001 CAPABILITY',
                'write A0002 STARTTLS',
                'tls',
                'write A0003 CAPABILITY',
                'write A0004 AUTHENTICATE PLAIN',
                'write ' . StreamImapMailboxTest::credentials(),
                'write A0005 CAPABILITY',
            ],
            $transport->events,
        );
    }

    public function testServerWithoutStartTlsGetsNoCredentials(): void
    {
        $transport = new FakeImapTransport(
            answers: ["* OK ready\r\n", "* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nA0001 OK done\r\n"],
        );

        $exception = StreamImapMailboxTest::openFails(
            transport: $transport,
            settings: StreamImapMailboxTest::settings(encryption: ImapEncryptionEnum::STARTTLS),
        );

        $this->assertSame('The IMAP server does not offer STARTTLS. Nothing was sent.', $exception->getMessage());
        $this->assertSame(['open imap.example.com:143', 'write A0001 CAPABILITY', 'close'], $transport->events);
    }

    public function testFailedTlsHandshakeGetsNoCredentials(): void
    {
        $transport = new FakeImapTransport(
            answers: ["* OK ready\r\n", "* CAPABILITY IMAP4rev1 STARTTLS\r\nA0001 OK done\r\n", "A0002 OK go\r\n"],
            tlsSucceeds: false,
        );

        $exception = StreamImapMailboxTest::openFails(
            transport: $transport,
            settings: StreamImapMailboxTest::settings(encryption: ImapEncryptionEnum::STARTTLS),
        );

        $this->assertSame('The TLS handshake with the IMAP server failed. Nothing was sent.', $exception->getMessage());
        $this->assertSame('close', array_last(array: $transport->events));
        $this->assertCount(2, $transport->writtenData());
    }

    public function testLoginWithQuotedStringsAndLiteral(): void
    {
        $transport = new FakeImapTransport(answers: [
            "* OK ready\r\n",
            "* CAPABILITY IMAP4rev1\r\nA0001 OK done\r\n",
            "+ Ready for literal data\r\n",
            "A0002 OK Logged in\r\n",
            StreamImapMailboxTest::CAPABILITIES_AFTER_LOGIN . "A0003 OK done\r\n",
        ]);

        new StreamImapMailbox(transport: $transport)->open(settings: new ImapSettings(
            hostName: 'imap.example.com',
            userName: 'us"er\\name',
            password: 'pässword',
        ));

        $this->assertSame(
            ['A0001 CAPABILITY', 'A0002 LOGIN "us\\"er\\\\name" {9}', 'pässword', 'A0003 CAPABILITY'],
            $transport->writtenData(),
        );
    }

    public function testServerWithoutLoginAndPlainIsRefused(): void
    {
        $transport = new FakeImapTransport(
            answers: ["* OK ready\r\n", "* CAPABILITY IMAP4rev1 LOGINDISABLED\r\nA0001 OK done\r\n"],
        );

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame('The IMAP server offers neither AUTH=PLAIN nor LOGIN.', $exception->getMessage());
    }

    public function testRejectedLoginNeverContainsThePassword(): void
    {
        $transport = new FakeImapTransport(answers: [
            "* OK ready\r\n",
            "* CAPABILITY IMAP4rev1\r\nA0001 OK done\r\n",
            'A0002 NO [AUTHENTICATIONFAILED] Authentication failed for "user@example.com" "'
            . StreamImapMailboxTest::PASSWORD . "\"\r\n",
        ]);

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame(
            'A0002 NO [AUTHENTICATIONFAILED] Authentication failed for "user@example.com" "(hidden)"',
            $exception->serverResponse,
        );
        $this->assertStringStartsWith('The IMAP server rejected LOGIN. Server: A0002 NO', $exception->getMessage());
        $this->assertStringNotContainsString(StreamImapMailboxTest::PASSWORD, $exception->getMessage());
        $this->assertSame('close', array_last(array: $transport->events));
    }

    public function testRejectedAuthenticateNeverContainsTheCredentials(): void
    {
        $credentials = StreamImapMailboxTest::credentials();
        $transport = new FakeImapTransport(answers: [
            StreamImapMailboxTest::GREETING,
            "* CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN\r\nA0001 OK done\r\n",
            'A0002 BAD Invalid ' . $credentials . "\r\n",
        ]);

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame('A0002 BAD Invalid (hidden)', $exception->serverResponse);
    }

    public function testGreetingOtherThanOkIsRefused(): void
    {
        $transport = new FakeImapTransport(answers: ["* PREAUTH logged in as admin\r\n"]);

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame(
            'The IMAP server did not greet with OK. Server: * PREAUTH logged in as admin',
            $exception->getMessage(),
        );
        $this->assertSame(['open tls imap.example.com:993', 'close'], $transport->events);
    }

    public function testUnreachableServer(): void
    {
        $transport = new FakeImapTransport(answers: [], openFails: true);

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame('Cannot connect to the IMAP server imap.example.com.', $exception->getMessage());
    }

    public function testServerClosingTheConnection(): void
    {
        $transport = new FakeImapTransport(
            answers: [StreamImapMailboxTest::GREETING, "* BYE Server shutting down\r\n"],
        );

        $exception = StreamImapMailboxTest::openFails(transport: $transport);

        $this->assertSame('The IMAP server closed the connection.', $exception->getMessage());
    }

    public function testFindUidsSortsLimitsAndJoinsSearchResponses(): void
    {
        [$mailbox] = StreamImapMailboxTest::selectedMailbox(answers: [
            "* SEARCH 12 3\r\n* SEARCH 5 3\r\nA0005 OK done\r\n",
        ]);

        $this->assertSame([3, 5], $mailbox->findUids(limit: 2));
    }

    public function testFindUidsOfAnEmptyFolder(): void
    {
        [$mailbox] = StreamImapMailboxTest::selectedMailbox(answers: ["* SEARCH\r\nA0005 OK done\r\n"]);

        $this->assertSame([], $mailbox->findUids(limit: 10));
    }

    public function testFetchSkipsOtherResponsesAndReadsTheUidAfterTheLiteral(): void
    {
        $length = strlen(string: StreamImapMailboxTest::MESSAGE);
        [$mailbox] = StreamImapMailboxTest::selectedMailbox(answers: [
            "* 3 FETCH (FLAGS (\\Seen))\r\n"
            . '* 1 FETCH (BODY[] {' . $length . "}\r\n" . StreamImapMailboxTest::MESSAGE . " UID 7)\r\n"
            . "A0005 OK done\r\n",
        ]);

        $this->assertSame('Test', $mailbox->fetchMessage(uid: 7)->subject);
    }

    public function testFetchOfAMissingMessage(): void
    {
        [$mailbox] = StreamImapMailboxTest::selectedMailbox(answers: ["A0005 OK Fetch completed.\r\n"]);

        $this->expectException(ImapException::class);
        $this->expectExceptionMessageIs(
            'The folder "INBOX" has no message with UID 8. Server: A0005 OK Fetch completed.',
        );

        $mailbox->fetchMessage(uid: 8);
    }

    public function testMoveWithoutMoveCapability(): void
    {
        [$mailbox, $transport] = StreamImapMailboxTest::selectedMailbox(
            answers: ["A0005 OK copied\r\n", "A0006 OK stored\r\n", "* 1 EXPUNGE\r\nA0007 OK expunged\r\n"],
            capabilities: "* CAPABILITY IMAP4rev1 UIDPLUS\r\n",
        );

        $mailbox->moveMessage(uid: 7, targetFolder: 'Erledigt/Ä');

        $this->assertSame(
            [
                'A0005 UID COPY 7 "Erledigt/&AMQ-"',
                'A0006 UID STORE 7 +FLAGS.SILENT (\\Deleted)',
                'A0007 UID EXPUNGE 7',
            ],
            array_slice(array: $transport->writtenData(), offset: 4),
        );
    }

    public function testMoveWithoutMoveAndUidplusIsRefused(): void
    {
        [$mailbox, $transport] = StreamImapMailboxTest::selectedMailbox(
            answers: [],
            capabilities: "* CAPABILITY IMAP4rev1\r\n",
        );

        try {
            $mailbox->moveMessage(uid: 7, targetFolder: 'Archive');
            StreamImapMailboxTest::fail('The move must be refused.');
        } catch (ImapException $exception) {
            $this->assertSame(
                'The IMAP server supports neither MOVE nor UIDPLUS: cannot move messages.',
                $exception->getMessage(),
            );
        }
        $this->assertCount(4, $transport->writtenData());
    }

    public function testMissingFolderIsAnExceptionWithTheServerResponse(): void
    {
        $transport = new FakeImapTransport(answers: [
            StreamImapMailboxTest::GREETING,
            "* CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN\r\nA0001 OK done\r\n",
            "A0002 OK Logged in\r\n",
            StreamImapMailboxTest::CAPABILITIES_AFTER_LOGIN . "A0003 OK done\r\n",
            "A0004 NO [NONEXISTENT] Mailbox doesn't exist: Missing\r\n",
        ]);
        $mailbox = new StreamImapMailbox(transport: $transport);
        $mailbox->open(settings: StreamImapMailboxTest::settings());

        try {
            $mailbox->selectFolder(name: 'Missing');
            StreamImapMailboxTest::fail('The folder must be missing.');
        } catch (ImapException $exception) {
            $this->assertSame("A0004 NO [NONEXISTENT] Mailbox doesn't exist: Missing", $exception->serverResponse);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('No folder is selected: call selectFolder() first.');

        $mailbox->findUids(limit: 1);
    }

    public function testMailboxMustBeOpen(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The mailbox is not open: call open() first.');

        new StreamImapMailbox(transport: new FakeImapTransport(answers: []))->selectFolder(name: 'INBOX');
    }

    public function testLiteralLargerThanTheMaximumIsRefused(): void
    {
        [$mailbox] = StreamImapMailboxTest::selectedMailbox(answers: ["* 1 FETCH (UID 7 BODY[] {99999999999}\r\n"]);

        $this->expectException(ImapException::class);
        $this->expectExceptionMessageIs('The IMAP server sent 99999999999 bytes, more than the maximum of 67108864.');

        $mailbox->fetchMessage(uid: 7);
    }

    public function testCloseOfAClosedMailboxDoesNothingAndToleratesAServerThatHangsUp(): void
    {
        [$mailbox, $transport] = StreamImapMailboxTest::selectedMailbox(answers: ["* BYE bye\r\n"]);

        $mailbox->close();
        $mailbox->close();

        $this->assertSame(['write A0005 LOGOUT', 'close'], array_slice(array: $transport->events, offset: -2));
    }

    private static function settings(ImapEncryptionEnum $encryption = ImapEncryptionEnum::IMPLICIT_TLS): ImapSettings
    {
        return new ImapSettings(
            hostName: 'imap.example.com',
            userName: 'user@example.com',
            password: StreamImapMailboxTest::PASSWORD,
            encryption: $encryption,
        );
    }

    private static function credentials(): string
    {
        return base64_encode(string: "\0user@example.com\0" . StreamImapMailboxTest::PASSWORD);
    }

    private static function openFails(FakeImapTransport $transport, ?ImapSettings $settings = null): ImapException
    {
        try {
            new StreamImapMailbox(transport: $transport)->open(
                settings: $settings ?? StreamImapMailboxTest::settings(),
            );
        } catch (ImapException $exception) {
            return $exception;
        }
        StreamImapMailboxTest::fail('open() must fail.');
    }

    /**
     * @param list<string> $answers the answers after `SELECT`, the next tag is `A0005`
     *
     * @return array{StreamImapMailbox, FakeImapTransport}
     */
    private static function selectedMailbox(
        array $answers,
        string $capabilities = StreamImapMailboxTest::CAPABILITIES_AFTER_LOGIN,
    ): array {
        $transport = new FakeImapTransport(answers: [
            StreamImapMailboxTest::GREETING,
            "* CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN\r\nA0001 OK done\r\n",
            "A0002 OK Logged in\r\n",
            $capabilities . "A0003 OK done\r\n",
            "A0004 OK [READ-WRITE] Select completed.\r\n",
            ...$answers,
        ]);
        $mailbox = new StreamImapMailbox(transport: $transport);
        $mailbox->open(settings: StreamImapMailboxTest::settings());
        $mailbox->selectFolder(name: 'INBOX');

        return [$mailbox, $transport];
    }
}
