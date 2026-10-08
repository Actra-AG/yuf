<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

/**
 * Collects the body and the headers of one response while cURL delivers them. Stops the transfer (by returning a
 * length cURL does not expect) when the body would become larger than the limit, so a server cannot fill the memory.
 *
 * @internal
 */
final class CurlResponseCollector
{
    private string $body = '';
    private bool $isLimitExceeded = false;

    /** @var array<string, list<string>> */
    private array $headers = [];
    private ?string $lastHeaderName = null;

    public function __construct(
        private readonly int $maxBodyBytes,
    ) {}

    /**
     * @return int The length of the chunk, 0 to abort the transfer
     */
    public function appendBody(string $chunk): int
    {
        if (strlen(string: $this->body) + strlen(string: $chunk) > $this->maxBodyBytes) {
            $this->isLimitExceeded = true;

            return 0;
        }
        $this->body .= $chunk;

        return strlen(string: $chunk);
    }

    /**
     * Reads one header line of the response. The headers of an earlier response (redirect, `100 Continue`, proxy) are
     * dropped when the next status line starts.
     *
     * @return int The length of the line
     */
    public function appendHeaderLine(string $line): int
    {
        $length = strlen(string: $line);
        $trimmedLine = rtrim(string: $line, characters: "\r\n");
        if ($trimmedLine === '') {
            return $length;
        }
        if (str_starts_with(haystack: $trimmedLine, needle: 'HTTP/')) {
            $this->headers = [];
            $this->lastHeaderName = null;

            return $length;
        }
        if (($trimmedLine[0] === ' ' || $trimmedLine[0] === "\t") && $this->lastHeaderName !== null) {
            $this->appendToLastHeader(continuation: trim(string: $trimmedLine));

            return $length;
        }
        $separatorPosition = strpos(haystack: $trimmedLine, needle: ':');
        if ($separatorPosition === false || $separatorPosition === 0) {
            return $length;
        }
        $name = strtolower(string: trim(string: substr(string: $trimmedLine, offset: 0, length: $separatorPosition)));
        $values = array_key_exists(key: $name, array: $this->headers) ? $this->headers[$name] : [];
        $values[] = trim(string: substr(string: $trimmedLine, offset: $separatorPosition + 1));
        $this->headers[$name] = $values;
        $this->lastHeaderName = $name;

        return $length;
    }

    /**
     * A header line that starts with a space or tab continues the value of the line before (obsolete line folding).
     */
    private function appendToLastHeader(string $continuation): void
    {
        if ($this->lastHeaderName === null || !array_key_exists(key: $this->lastHeaderName, array: $this->headers)) {
            return;
        }
        $values = $this->headers[$this->lastHeaderName];
        $lastValue = array_pop(array: $values);
        if ($lastValue === null) {
            return;
        }
        $values[] = $lastValue . ' ' . $continuation;
        $this->headers[$this->lastHeaderName] = $values;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, list<string>> The values of the headers by lower case name
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isLimitExceeded(): bool
    {
        return $this->isLimitExceeded;
    }
}
