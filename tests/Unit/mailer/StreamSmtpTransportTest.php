<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\StreamSmtpTransport;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The transport against a server on the loopback address of this process (no network).
 */
final class StreamSmtpTransportTest extends TestCase
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
        $name = stream_socket_get_name(socket: $server, remote: false);
        $this->port = (int) substr(
            string: (string) $name,
            offset: (int) strrpos(haystack: (string) $name, needle: ':') + 1,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        fclose(stream: $this->server);
    }

    public function testReadsLinesAndWritesLinesWithLineBreak(): void
    {
        $transport = new StreamSmtpTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: 5);
        $peer = $this->accept();
        fwrite(stream: $peer, data: "250-first\r\n250 second\r\n");

        $this->assertTrue($transport->isOpen());
        $this->assertSame("250-first\r\n", $transport->readLine(timeoutSeconds: 5));
        $this->assertSame("250 second\r\n", $transport->readLine(timeoutSeconds: 5));

        $transport->writeLine(line: 'EHLO mail.example.com');

        $this->assertSame("EHLO mail.example.com\r\n", fgets(stream: $peer));
        $transport->close();
        $this->assertFalse($transport->isOpen());
    }

    public function testLineLongerThanTheBufferIsReadInParts(): void
    {
        $transport = new StreamSmtpTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: 5);
        $peer = $this->accept();
        fwrite(stream: $peer, data: str_repeat(string: 'a', times: 600) . "\r\n");

        $this->assertSame(str_repeat(string: 'a', times: 511), $transport->readLine(timeoutSeconds: 5));
        $this->assertSame(str_repeat(string: 'a', times: 89) . "\r\n", $transport->readLine(timeoutSeconds: 5));
        $transport->close();
    }

    public function testClosedConnectionReadsNull(): void
    {
        $transport = new StreamSmtpTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: 5);
        fclose(stream: $this->accept());

        $this->assertNull($transport->readLine(timeoutSeconds: 5));
        $this->assertFalse($transport->isOpen());
        $transport->close();
    }

    public function testSilentServerIsATimeout(): void
    {
        $transport = new StreamSmtpTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: 5);
        $peer = $this->accept();

        try {
            $transport->readLine(timeoutSeconds: 1);
            StreamSmtpTransportTest::fail('The read must time out.');
        } catch (MailerException $exception) {
            $this->assertSame('The SMTP server did not answer within 1 seconds.', $exception->getMessage());
        } finally {
            fclose(stream: $peer);
            $transport->close();
        }
    }

    public function testUnreachableServerIsAnException(): void
    {
        $port = $this->port;
        fclose(stream: $this->server);
        $transport = new StreamSmtpTransport();

        try {
            $transport->open(hostName: '127.0.0.1', port: $port, timeoutSeconds: 5);
            StreamSmtpTransportTest::fail('The connection must fail.');
        } catch (MailerException $exception) {
            $this->assertStringStartsWith('Socket connection error: 127.0.0.1 (', $exception->getMessage());
        } finally {
            $this->setUp();
        }
        $this->assertFalse($transport->isOpen());
    }

    public function testNothingCanBeWrittenToAClosedTransport(): void
    {
        $this->expectExceptionMessageIs('The connection to the SMTP server is closed.');

        new StreamSmtpTransport()->writeLine(line: 'NOOP');
    }

    public function testClosedTransportReadsNullAndHasNoTls(): void
    {
        $transport = new StreamSmtpTransport();

        $this->assertNull($transport->readLine(timeoutSeconds: 1));
        $this->assertFalse($transport->enableTls());
    }

    public function testTlsHandshakeWithAServerWithoutTlsFails(): void
    {
        $transport = new StreamSmtpTransport();
        $transport->open(hostName: '127.0.0.1', port: $this->port, timeoutSeconds: 5);
        fclose(stream: $this->accept());

        $this->assertFalse($transport->enableTls());
        $transport->close();
    }

    public function testCertificateAndHostNameAreVerified(): void
    {
        $this->assertSame(
            [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'peer_name' => 'smtp.example.com',
                    'SNI_enabled' => true,
                    'SNI_server_name' => 'smtp.example.com',
                ],
            ],
            StreamSmtpTransport::createContextOptions(hostName: 'smtp.example.com'),
        );
    }

    /**
     * @return resource
     */
    private function accept()
    {
        $peer = stream_socket_accept(socket: $this->server, timeout: 5);
        if ($peer === false) {
            throw new RuntimeException(message: 'The transport did not connect.');
        }

        return $peer;
    }
}
