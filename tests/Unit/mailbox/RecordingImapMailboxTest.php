<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\ImapException;
use actra\yuf\mailbox\ImapSettings;
use actra\yuf\mailbox\MimeMessageParser;
use actra\yuf\mailbox\ReceivedMail;
use actra\yuf\mailbox\RecordingImapMailbox;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RecordingImapMailboxTest extends TestCase
{
    public function testReadsAndMovesMessagesAndRecordsTheEvents(): void
    {
        $first = RecordingImapMailboxTest::mail(subject: 'First');
        $second = RecordingImapMailboxTest::mail(subject: 'Second');
        $mailbox = new RecordingImapMailbox(folders: ['INBOX' => [5 => $second, 2 => $first], 'Archive' => []]);
        $third = RecordingImapMailboxTest::mail(subject: 'Third');
        $thirdUid = $mailbox->addMessage(folder: 'INBOX', receivedMail: $third);

        $mailbox->open(settings: RecordingImapMailboxTest::settings());
        $mailbox->selectFolder(name: 'INBOX');
        $uids = $mailbox->findUids(limit: 2);
        $fetched = $mailbox->fetchMessage(uid: 2);
        $mailbox->moveMessage(uid: 2, targetFolder: 'Archive');
        $mailbox->close();

        $this->assertSame(6, $thirdUid);
        $this->assertSame([2, 5], $uids);
        $this->assertSame($first, $fetched);
        $this->assertSame(['INBOX' => [5 => $second, 6 => $third], 'Archive' => [7 => $first]], $mailbox->folders);
        $this->assertSame(
            ['open imap.example.com:993', 'select INBOX', 'find 2', 'fetch 2', 'move 2 Archive', 'close'],
            $mailbox->events,
        );
    }

    public function testMissingFolderAndUidAreImapExceptions(): void
    {
        $mailbox = new RecordingImapMailbox();
        $mailbox->open(settings: RecordingImapMailboxTest::settings());

        try {
            $mailbox->selectFolder(name: 'Missing');
            RecordingImapMailboxTest::fail('The folder must be missing.');
        } catch (ImapException $exception) {
            $this->assertSame('The folder "Missing" does not exist.', $exception->getMessage());
        }
        $mailbox->selectFolder(name: 'INBOX');

        $this->expectException(ImapException::class);
        $this->expectExceptionMessageIs('The folder "INBOX" has no message with UID 1.');

        $mailbox->fetchMessage(uid: 1);
    }

    public function testOpenCanFail(): void
    {
        $mailbox = new RecordingImapMailbox(openException: new ImapException(message: 'Cannot connect.'));

        $this->expectException(ImapException::class);
        $this->expectExceptionMessageIs('Cannot connect.');

        $mailbox->open(settings: RecordingImapMailboxTest::settings());
    }

    public function testMailboxMustBeOpen(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The mailbox is not open: call open() first.');

        new RecordingImapMailbox()->selectFolder(name: 'INBOX');
    }

    private static function settings(): ImapSettings
    {
        return new ImapSettings(hostName: 'imap.example.com', userName: 'user', password: 'pass');
    }

    private static function mail(string $subject): ReceivedMail
    {
        return new MimeMessageParser()->parse(rawMessage: 'Subject: ' . $subject . "\r\n\r\nText");
    }
}
