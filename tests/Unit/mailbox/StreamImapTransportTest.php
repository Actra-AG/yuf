<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\ImapException;
use actra\yuf\mailbox\StreamImapTransport;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The transport against a server on the loopback address of this process (no network).
 */
final class StreamImapTransportTest extends TestCase
{
    /** @var resource */
    private $server;
    private int $port;

    #[Override]
    protected function setUp(): void
    {
        $server = stream_socket_server(address: 'tcp://127.0.0.1:0', error_code: $code, error_message: $message);
        if ($server === false) {
            throw new RuntimeException(message: 'No loopback server: ' . $message);
        }
        $this->server = $server;
        $name = (string) stream_socket_get_name(socket: $server, remote: false);
        $this->port = (int) substr(string: $name, offset: (int) strrpos(haystack: $name, needle: ':') + 1);
    }

    #[Override]
    protected function tearDown(): void
    {
        fclose(stream: $this->server);
    }

    public function testReadsLinesAndLiteralsAndWritesData(): void
    {
        $transport = $this->openTransport(timeoutSeconds: 5);
        $peer = $this->accept();
        $longLine = str_repeat(string: 'a', times: 100_000);
        $literal = str_repeat(string: 'b', times: 70_000);
        fwrite(stream: $peer, data: "* OK ready\r\n" . $longLine . "\r\n" . $literal . "end\r\n");

        $this->assertTrue($transport->isOpen());
        $this->assertSame("* OK ready\r\n", $transport->readLine());
        $this->assertSame($longLine . "\r\n", $transport->readLine());
        $this->assertSame($literal, $transport->readBytes(length: 70_000));
        $this->assertSame("end\r\n", $transport->readLine());

        $transport->write(data: "A0001 NOOP\r\n");

        $this->assertSame("A0001 NOOP\r\n", fgets(stream: $peer));
        $transport->close();
        $this->assertFalse($transport->isOpen());
    }

    public function testClosedConnectionReadsNull(): void
    {
        $transport = $this->openTransport(timeoutSeconds: 5);
        $peer = $this->accept();
        fwrite(stream: $peer, data: 'abc');
        fclose(stream: $peer);

        $this->assertNull($transport->readBytes(length: 5));
        $this->assertNull($transport->readLine());
        $transport->close();
    }

    public function testSilentServerIsATimeout(): void
    {
        $transport = $this->openTransport(timeoutSeconds: 1);
        $peer = $this->accept();

        try {
            $transport->readLine();
            StreamImapTransportTest::fail('The read must time out.');
        } catch (ImapException $exception) {
            $this->assertSame('The IMAP server did not answer within 1 seconds.', $exception->getMessage());
        } finally {
            fclose(stream: $peer);
            $transport->close();
        }
    }

    public function testUnreachableServerIsAnException(): void
    {
        $port = $this->port;
        fclose(stream: $this->server);
        $transport = new StreamImapTransport();

        try {
            $transport->open(hostName: '127.0.0.1', port: $port, timeoutSeconds: 5, implicitTls: false);
            StreamImapTransportTest::fail('The connection must fail.');
        } catch (ImapException $exception) {
            $this->assertStringStartsWith(
                'Cannot connect to the IMAP server 127.0.0.1:' . $port . ' (',
                $exception->getMessage(),
            );
        } finally {
            $this->setUp();
        }
        $this->assertFalse($transport->isOpen());
    }

    public function testClosedTransportCannotWriteReadsNullAndHasNoTls(): void
    {
        $transport = new StreamImapTransport();

        $this->assertNull($transport->readLine());
        $this->assertNull($transport->readBytes(length: 1));
        $this->assertFalse($transport->enableTls());
        $this->expectException(ImapException::class);
        $this->expectExceptionMessageIs('The connection to the IMAP server is closed.');

        $transport->write(data: "A0001 NOOP\r\n");
    }

    public function testTlsHandshakeWithAServerWithoutTlsFails(): void
    {
        $transport = $this->openTransport(timeoutSeconds: 2);
        $peer = $this->accept();
        fwrite(stream: $peer, data: "A0002 OK Begin TLS negotiation now.\r\n");
        $transport->readLine();
        fclose(stream: $peer);

        $this->assertFalse($transport->enableTls());
        $transport->close();
    }

    private function openTransport(int $timeoutSeconds): StreamImapTransport
    {
        $transport = new StreamImapTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: $timeoutSeconds, implicitTls: false);

        return $transport;
    }

    /**
     * @return resource
     */
    private function accept()
    {
        $peer = stream_socket_accept(socket: $this->server, timeout: 5);
        if ($peer === false) {
            throw new RuntimeException(message: 'No connection to accept.');
        }

        return $peer;
    }
}
