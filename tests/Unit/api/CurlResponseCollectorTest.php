<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlResponseCollector;
use PHPUnit\Framework\TestCase;

final class CurlResponseCollectorTest extends TestCase
{
    public function testBodyIsCollectedInChunks(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        self::assertSame(3, $collector->appendBody(chunk: 'abc'));
        self::assertSame(2, $collector->appendBody(chunk: 'de'));

        self::assertSame('abcde', $collector->getBody());
        self::assertFalse($collector->isLimitExceeded());
    }

    public function testBodyAtTheLimitIsAccepted(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 5);

        self::assertSame(3, $collector->appendBody(chunk: 'abc'));
        self::assertSame(2, $collector->appendBody(chunk: 'de'));

        self::assertSame('abcde', $collector->getBody());
        self::assertFalse($collector->isLimitExceeded());
    }

    public function testChunkBeyondTheLimitAbortsTheTransfer(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 5);
        $collector->appendBody(chunk: 'abc');

        self::assertSame(0, $collector->appendBody(chunk: 'def'));

        self::assertTrue($collector->isLimitExceeded());
        self::assertSame('abc', $collector->getBody());
    }

    public function testHeadersAreCollectedByLowerCaseName(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        self::assertSame(17, $collector->appendHeaderLine(line: "HTTP/1.1 200 OK\r\n"));
        $collector->appendHeaderLine(line: "Content-Type: text/plain; charset=utf-8\r\n");
        $collector->appendHeaderLine(line: "X-Custom:   padded value \r\n");
        $collector->appendHeaderLine(line: "X-Empty:\r\n");
        $collector->appendHeaderLine(line: "\r\n");

        self::assertSame(
            [
                'content-type' => ['text/plain; charset=utf-8'],
                'x-custom' => ['padded value'],
                'x-empty' => [''],
            ],
            $collector->getHeaders(),
        );
    }

    public function testRepeatedHeadersKeepAllValues(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "Set-Cookie: a=1\r\n");
        $collector->appendHeaderLine(line: "set-cookie: b=2\r\n");

        self::assertSame(['set-cookie' => ['a=1', 'b=2']], $collector->getHeaders());
    }

    public function testValueWithColonIsKeptWhole(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "Location: https://example.com:8443/a\r\n");

        self::assertSame(['location' => ['https://example.com:8443/a']], $collector->getHeaders());
    }

    public function testHeadersOfAnEarlierResponseAreDropped(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);
        $collector->appendHeaderLine(line: "HTTP/1.1 100 Continue\r\n");
        $collector->appendHeaderLine(line: "X-Interim: 1\r\n");
        $collector->appendHeaderLine(line: "\r\n");

        $collector->appendHeaderLine(line: "HTTP/1.1 200 OK\r\n");
        $collector->appendHeaderLine(line: "X-Final: 1\r\n");

        self::assertSame(['x-final' => ['1']], $collector->getHeaders());
    }

    public function testFoldedHeaderValueIsJoined(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "X-Long: first\r\n");
        $collector->appendHeaderLine(line: "\tsecond\r\n");

        self::assertSame(['x-long' => ['first second']], $collector->getHeaders());
    }

    public function testLinesWithoutNameAreIgnored(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        self::assertSame(8, $collector->appendHeaderLine(line: "garbage\n"));
        $collector->appendHeaderLine(line: ": no name\r\n");
        $collector->appendHeaderLine(line: " continuation without a header\r\n");

        self::assertSame([], $collector->getHeaders());
    }

    public function testHeaderLineWithLineFeedOnlyIsRead(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "X-A: b\n");

        self::assertSame(['x-a' => ['b']], $collector->getHeaders());
    }
}
