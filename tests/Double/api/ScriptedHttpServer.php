<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\api;

use JsonException;
use OutOfBoundsException;
use RuntimeException;

/**
 * A `LocalHttpServer` that answers every path with a prepared status and body (`respond()`, can be changed between
 * requests) and records the requests it received (`requests()`).
 */
final class ScriptedHttpServer
{
    private readonly LocalHttpServer $server;
    private readonly string $directory;

    /** @var array<string, array{status: int, body: string}> */
    private array $routes = [];

    public function __construct()
    {
        $directory = sys_get_temp_dir() . '/yuf-scripted-server-' . bin2hex(string: random_bytes(length: 8));
        if (!mkdir(directory: $directory, permissions: 0o700)) {
            throw new RuntimeException(message: 'The directory of the scripted server cannot be created.');
        }
        $this->directory = $directory;
        $this->writeRoutes();
        $this->server = new LocalHttpServer(
            script: 'scripted-server.php',
            environment: ['YUF_SCRIPTED_DIR' => $directory],
        );
    }

    public function __destruct()
    {
        // The test is over: no request is running any more
        foreach (['routes.json', 'requests.jsonl'] as $fileName) {
            if (is_file(filename: $this->directory . '/' . $fileName)) {
                unlink(filename: $this->directory . '/' . $fileName);
            }
        }
        rmdir(directory: $this->directory);
    }

    public function url(string $pathAndQuery): string
    {
        return $this->server->url(pathAndQuery: $pathAndQuery);
    }

    /**
     * Answers requests of the path from now on with this status and body (JSON).
     */
    public function respond(string $path, int $status, string $body): void
    {
        $this->routes[$path] = ['status' => $status, 'body' => $body];
        $this->writeRoutes();
    }

    /**
     * @return list<EchoedRequest> the requests received so far, the first one first
     */
    public function requests(): array
    {
        $file = $this->directory . '/requests.jsonl';
        if (!is_file(filename: $file)) {
            return [];
        }
        $lines = file(filename: $file, flags: FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException(message: 'The recorded requests cannot be read.');
        }
        $requests = [];
        foreach ($lines as $line) {
            try {
                $requests[] = EchoedRequest::fromJson(json: $line);
            } catch (JsonException $exception) {
                throw new RuntimeException(message: 'The recorded request is no JSON.', previous: $exception);
            }
        }

        return $requests;
    }

    /**
     * @param int $index Position in `requests()`, the first request is 0
     */
    public function request(int $index): EchoedRequest
    {
        return $this->requests()[$index] ?? throw new OutOfBoundsException(message: 'No request ' . $index . '.');
    }

    private function writeRoutes(): void
    {
        file_put_contents(
            filename: $this->directory . '/routes.json',
            data: json_encode(value: $this->routes, flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
        );
    }
}
