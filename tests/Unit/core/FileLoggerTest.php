<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\FileLogger;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileLoggerTest extends TestCase
{
    private const int TICKET_MODIFIED = 1_800_000_000;
    private string $logDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-logger-test-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
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

    private function logTwice(int $secondsAfterFirstModification): FileLogger
    {
        $message = 'something happened';
        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: $message) . '.txt';
        file_put_contents(filename: $ticket, data: 'old');
        touch(filename: $ticket, mtime: FileLoggerTest::TICKET_MODIFIED);
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
            clock: new FixedClock(
                now: new DateTimeImmutable(
                    datetime: '@' . (FileLoggerTest::TICKET_MODIFIED + $secondsAfterFirstModification),
                ),
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
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . FileLoggerTest::TICKET_MODIFIED)),
        );
        $logger->logMessage(message: 'first time');

        $this->assertTrue($logger->lastIssueIsNew());
    }

    public function testLogEntryStartsWithTheTimestampOfTheClock(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-03-04 05:06:07.123456')),
        );
        $logger->logMessage(message: 'stamped');

        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: 'stamped') . '.txt';
        $this->assertStringStartsWith(
            '2026-03-04 05:06:07,12345600' . PHP_EOL . 'stamped',
            (string) file_get_contents(filename: $ticket),
        );
    }

    public function testLogEntryContainsTheDataOfTheRequestWithoutSecrets(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(
                cookies: ['session' => 'cookie-value'],
                queryParameters: ['q' => 'query-value', 'apiKey' => 'query-secret'],
                postParameters: ['p' => 'post-value', 'password' => 'post-secret'],
                uploadedFiles: ['f' => ['name' => 'file-name', 'tmp_name' => '/tmp/phpXYZ']],
                serverVariables: ['SERVER_NAME' => 'server-value', 'SECRET_ENV' => 'environment-secret'],
            ),
        );
        $logger->logMessage(message: 'with request');

        $content = $this->readTicket(message: 'with request');
        $this->assertStringContainsString('Request: GET /', $content);
        $this->assertStringContainsString('SERVER_NAME = server-value', $content);
        $this->assertStringContainsString('[q] => query-value', $content);
        $this->assertStringContainsString('[p] => post-value', $content);
        $this->assertStringContainsString('[name] => file-name', $content);
        $this->assertStringContainsString('Cookie names = session', $content);
        $this->assertStringNotContainsString('cookie-value', $content);
        $this->assertStringNotContainsString('query-secret', $content);
        $this->assertStringNotContainsString('post-secret', $content);
        $this->assertStringNotContainsString('environment-secret', $content);
        $this->assertStringNotContainsString('/tmp/phpXYZ', $content);
    }

    public function testLogExceptionWritesTheExceptionAndTheRequest(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
        );

        $logger->logException(throwable: new RuntimeException(message: 'broken', code: 7));

        $files = glob(pattern: $this->logDirectory . 'ticket_*.txt');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $content = (string) file_get_contents(filename: $files[0]);
        $this->assertStringContainsString('RuntimeException: (7) "broken"', $content);
        $this->assertStringContainsString('thrown in file: ' . __FILE__, $content);
        $this->assertStringContainsString('Request: GET /', $content);
        $this->assertTrue($logger->lastIssueIsNew());
    }

    public function testLogExceptionLogsThePreviousException(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
        );

        $logger->logException(
            throwable: new LogicException(
                message: 'wrapper',
                previous: new RuntimeException(message: 'the cause'),
            ),
        );

        $files = glob(pattern: $this->logDirectory . 'ticket_*.txt');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $content = (string) file_get_contents(filename: $files[0]);
        $this->assertStringContainsString('"the cause"', $content);
        $this->assertStringNotContainsString('wrapper', $content);
    }

    public function testSameIssueIsWrittenIntoTheSameTicketFile(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
        );

        $logger->logMessage(message: 'again');
        $logger->logMessage(message: 'again');

        $files = glob(pattern: $this->logDirectory . 'ticket_*.txt');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertSame(2, substr_count(haystack: (string) file_get_contents(filename: $files[0]), needle: 'again'));
        $this->assertFalse($logger->lastIssueIsNew());
    }

    public function testLogDirectoryWithoutTrailingSeparatorIsAccepted(): void
    {
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: rtrim(string: $this->logDirectory, characters: DIRECTORY_SEPARATOR),
            httpRequest: HttpRequestFactory::create(),
        );

        $logger->logMessage(message: 'no slash');

        $this->assertFileExists($this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: 'no slash') . '.txt');
    }

    public function testMissingLogDirectoryThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Log directory does not exist: ' . $this->logDirectory . 'missing');

        new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory . 'missing',
            httpRequest: HttpRequestFactory::create(),
        );
    }

    public function testTicketFileOverTheMaximumSizeIsMovedToTheNextFreeNumber(): void
    {
        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: 'rotate') . '.txt';
        file_put_contents(filename: $ticket, data: str_repeat(string: 'x', times: 50));
        file_put_contents(filename: $ticket . '.1', data: 'older');
        file_put_contents(filename: $ticket . '.3', data: 'oldest');
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
            maxLogSize: 50,
        );

        $logger->logMessage(message: 'rotate');

        $this->assertSame(str_repeat(string: 'x', times: 50), file_get_contents(filename: $ticket . '.4'));
        $this->assertSame('older', file_get_contents(filename: $ticket . '.1'));
        $content = (string) file_get_contents(filename: $ticket);
        $this->assertStringStartsNotWith('x', $content);
        $this->assertStringContainsString('rotate', $content);
    }

    public function testTicketFileUnderTheMaximumSizeIsNotMoved(): void
    {
        $ticket = $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: 'keep') . '.txt';
        file_put_contents(filename: $ticket, data: 'x');
        $logger = new FileLogger(
            logEmailRecipient: '',
            logDirectory: $this->logDirectory,
            httpRequest: HttpRequestFactory::create(),
            maxLogSize: 50,
        );

        $logger->logMessage(message: 'keep');

        $this->assertFileDoesNotExist($ticket . '.1');
        $this->assertStringStartsWith('x', (string) file_get_contents(filename: $ticket));
    }

    private function readTicket(string $message): string
    {
        return (string) file_get_contents(
            filename: $this->logDirectory . 'ticket_' . hash(algo: 'sha256', data: $message) . '.txt',
        );
    }
}
