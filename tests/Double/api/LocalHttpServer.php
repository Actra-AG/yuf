<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\api;

use RuntimeException;

/**
 * A web server on the loopback address (PHP's built-in server in a separate process) that answers from
 * `echo-server.php`. cURL blocks the process of the test, so the server cannot live in it.
 */
final class LocalHttpServer
{
    /** @var resource */
    private $process;

    public readonly int $port;

    /**
     * @param string $script Router script in this directory
     * @param array<string, string> $environment Environment variables of the server process (the router script reads
     *                                           them with `getenv()`)
     */
    public function __construct(string $script = 'echo-server.php', array $environment = [])
    {
        $this->port = LocalHttpServer::findFreePort();
        // @phpstan-ignore disallowed.function (starts PHP's built-in web server with fixed arguments, loopback only)
        $process = proc_open(
            command: [
                PHP_BINARY,
                '-S',
                '127.0.0.1:' . $this->port,
                __DIR__ . '/' . $script,
            ],
            descriptor_spec: [0 => ['null'], 1 => ['null'], 2 => ['null']],
            pipes: $pipes,
            env_vars: $environment === [] ? null : $environment,
        );
        if ($process === false) {
            throw new RuntimeException(message: 'The local web server could not be started.');
        }
        $this->process = $process;
        $this->waitUntilListening();
    }

    public function __destruct()
    {
        proc_terminate(process: $this->process);
        proc_close(process: $this->process);
    }

    public function url(string $pathAndQuery): string
    {
        return 'http://127.0.0.1:' . $this->port . $pathAndQuery;
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server(address: 'tcp://127.0.0.1:0', error_code: $code, error_message: $message);
        if ($socket === false) {
            throw new RuntimeException(message: 'No loopback socket: ' . $message);
        }
        $name = (string) stream_socket_get_name(socket: $socket, remote: false);
        fclose(stream: $socket);

        return (int) substr(string: $name, offset: (int) strrpos(haystack: $name, needle: ':') + 1);
    }

    private function waitUntilListening(): void
    {
        // A refused connection while the server starts is expected and not worth a warning
        set_error_handler(callback: static fn(): bool => true);
        try {
            for ($attempt = 0; $attempt < 500; $attempt++) {
                $connection = fsockopen(hostname: '127.0.0.1', port: $this->port, timeout: 1);
                if ($connection !== false) {
                    fclose(stream: $connection);

                    return;
                }
                usleep(microseconds: 10000);
            }
        } finally {
            restore_error_handler();
        }

        throw new RuntimeException(message: 'The local web server does not listen.');
    }
}
