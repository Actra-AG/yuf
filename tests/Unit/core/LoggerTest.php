<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\Logger;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private const int TICKET_MODIFIED = 1_800_000_000;
    private string $logDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-logger-test-' . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->logDirectory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->logDirectory . '*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->logDirectory);
    }

    private function logTwice(int $secondsAfterFirstModification): Logger
    {
        $message = 'something happened';
        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: $message) . '.txt';
        file_put_contents(filename: $ticket, data: 'old');
        touch(filename: $ticket, mtime: LoggerTest::TICKET_MODIFIED);
        $logger = new Logger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            clock: new FixedClock(
                now: new DateTimeImmutable(datetime: '@' . (LoggerTest::TICKET_MODIFIED + $secondsAfterFirstModification)),
            ),
        );
        $logger->logMessage(message: $message);

        return $logger;
    }

    public function testKnownIssueIsNotNewExactlyAfter24Hours(): void
    {
        $this->assertFalse($this->logTwice(secondsAfterFirstModification: 86400)->lastIssueIsNew());
    }

    public function testKnownIssueIsNewAgainOneSecondAfter24Hours(): void
    {
        $this->assertTrue($this->logTwice(secondsAfterFirstModification: 86401)->lastIssueIsNew());
    }

    public function testUnknownIssueIsNew(): void
    {
        $logger = new Logger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . LoggerTest::TICKET_MODIFIED)),
        );
        $logger->logMessage(message: 'first time');

        $this->assertTrue($logger->lastIssueIsNew());
    }

    public function testLogEntryStartsWithTheTimestampOfTheClock(): void
    {
        $logger = new Logger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-03-04 05:06:07.123456')),
        );
        $logger->logMessage(message: 'stamped');

        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: 'stamped') . '.txt';
        $this->assertStringStartsWith('2026-03-04 05:06:07,12345600' . PHP_EOL . 'stamped', (string) file_get_contents(filename: $ticket));
    }
}
