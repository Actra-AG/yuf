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

        $this->assertSame(3, $collector->appendBody(chunk: 'abc'));
        $this->assertSame(2, $collector->appendBody(chunk: 'de'));

        $this->assertSame('abcde', $collector->getBody());
        $this->assertFalse($collector->isLimitExceeded());
    }

    public function testBodyAtTheLimitIsAccepted(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 5);

        $this->assertSame(3, $collector->appendBody(chunk: 'abc'));
        $this->assertSame(2, $collector->appendBody(chunk: 'de'));

        $this->assertSame('abcde', $collector->getBody());
        $this->assertFalse($collector->isLimitExceeded());
    }

    public function testChunkBeyondTheLimitAbortsTheTransfer(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 5);
        $collector->appendBody(chunk: 'abc');

        $this->assertSame(0, $collector->appendBody(chunk: 'def'));

        $this->assertTrue($collector->isLimitExceeded());
        $this->assertSame('abc', $collector->getBody());
    }

    public function testHeadersAreCollectedByLowerCaseName(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $this->assertSame(17, $collector->appendHeaderLine(line: "HTTP/1.1 200 OK\r\n"));
        $collector->appendHeaderLine(line: "Content-Type: text/plain; charset=utf-8\r\n");
        $collector->appendHeaderLine(line: "X-Custom:   padded value \r\n");
        $collector->appendHeaderLine(line: "X-Empty:\r\n");
        $collector->appendHeaderLine(line: "\r\n");

        $this->assertSame(
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

        $this->assertSame(['set-cookie' => ['a=1', 'b=2']], $collector->getHeaders());
    }

    public function testValueWithColonIsKeptWhole(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "Location: https://example.com:8443/a\r\n");

        $this->assertSame(['location' => ['https://example.com:8443/a']], $collector->getHeaders());
    }

    public function testHeadersOfAnEarlierResponseAreDropped(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);
        $collector->appendHeaderLine(line: "HTTP/1.1 100 Continue\r\n");
        $collector->appendHeaderLine(line: "X-Interim: 1\r\n");
        $collector->appendHeaderLine(line: "\r\n");

        $collector->appendHeaderLine(line: "HTTP/1.1 200 OK\r\n");
        $collector->appendHeaderLine(line: "X-Final: 1\r\n");

        $this->assertSame(['x-final' => ['1']], $collector->getHeaders());
    }

    public function testFoldedHeaderValueIsJoined(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "X-Long: first\r\n");
        $collector->appendHeaderLine(line: "\tsecond\r\n");

        $this->assertSame(['x-long' => ['first second']], $collector->getHeaders());
    }

    public function testLinesWithoutNameAreIgnored(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $this->assertSame(8, $collector->appendHeaderLine(line: "garbage\n"));
        $collector->appendHeaderLine(line: ": no name\r\n");
        $collector->appendHeaderLine(line: " continuation without a header\r\n");

        $this->assertSame([], $collector->getHeaders());
    }

    public function testHeaderLineWithLineFeedOnlyIsRead(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);

        $collector->appendHeaderLine(line: "X-A: b\n");

        $this->assertSame(['x-a' => ['b']], $collector->getHeaders());
    }
}
