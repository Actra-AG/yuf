<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * Describes a request for the error log without secrets: the request line (without the query string), host, IP
 * address, user agent, referrer (without query string and fragment), a fixed list of server variables, the query
 * and post parameters with masked values, the names of the uploaded files and the names (never the values) of the
 * cookies.
 *
 * A value is masked (`***`) if the parameter name contains `password`, `token`, `secret`, `csrf`, `key` or `auth`
 * (case-insensitive), at any depth; an array under such a name is masked as a whole.
 *
 * @internal
 */
final readonly class RequestLogFormatter
{
    public const string MASK = '***';

    /** Parts of parameter names whose values are masked. */
    private const array SECRET_NAME_PARTS = ['password', 'token', 'secret', 'csrf', 'key', 'auth'];

    /**
     * The server variables that are logged. Everything else is left out: the environment of the server may contain
     * secrets, and `REQUEST_URI`, `QUERY_STRING` and `HTTP_REFERER` contain the query string, which is logged masked
     * with the query parameters.
     */
    private const array SERVER_VARIABLES = [
        'REQUEST_METHOD',
        'SERVER_PROTOCOL',
        'HTTPS',
        'HTTP_HOST',
        'SERVER_NAME',
        'SERVER_PORT',
        'REMOTE_ADDR',
        'HTTP_USER_AGENT',
        'HTTP_ACCEPT_LANGUAGE',
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
        'SCRIPT_NAME',
    ];

    public function format(HttpRequest $httpRequest): string
    {
        $lines = [
            'Request: ' . $this->singleLine(text: $httpRequest->getMethod()->value . ' ' . $httpRequest->getPath()),
            'Host: ' . $this->singleLine(text: $httpRequest->getHost()),
            'IP address: ' . $this->singleLine(text: $httpRequest->getRemoteAddress()),
            'User agent: ' . $this->singleLine(text: $httpRequest->getUserAgent()),
            'Referrer: ' . $this->singleLine(text: $this->withoutQueryAndFragment(url: $httpRequest->getReferrer())),
            '',
            'Server variables (allow-list):',
        ];
        $serverVariables = $httpRequest->getServerVariables();
        foreach (RequestLogFormatter::SERVER_VARIABLES as $name) {
            if (array_key_exists(key: $name, array: $serverVariables)) {
                $lines[] = $name . ' = ' . $this->singleLine(text: $serverVariables[$name]);
            }
        }
        $lines[] = '';
        $lines[] = 'Query parameters (secrets masked) = '
            . $this->printValues(values: $this->maskSecrets(values: $httpRequest->getQueryParameters()));
        $lines[] = '';
        $lines[] = 'Post parameters (secrets masked) = '
            . $this->printValues(values: $this->maskSecrets(values: $httpRequest->getPostParameters()));
        $lines[] = '';
        $lines[] = 'Uploaded files = '
            . $this->printValues(values: $this->maskSecrets(values: $this->withoutTemporaryPaths(
                values: $httpRequest->getRawFiles(),
            )));
        $lines[] = '';
        $lines[] = 'Cookie names = ' . $this->singleLine(
            text: implode(separator: ', ', array: array_keys(array: $httpRequest->listCookies())),
        );

        return implode(separator: PHP_EOL, array: $lines);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public function maskSecrets(array $values): array
    {
        $masked = [];
        foreach ($values as $name => $value) {
            if ($this->isSecretName(name: $name)) {
                $masked[$name] = RequestLogFormatter::MASK;
            } elseif (is_array(value: $value)) {
                $masked[$name] = $this->maskSecrets(values: $value);
            } else {
                $masked[$name] = $value;
            }
        }

        return $masked;
    }

    private function isSecretName(int|string $name): bool
    {
        $lowerCaseName = strtolower(string: (string) $name);
        foreach (RequestLogFormatter::SECRET_NAME_PARTS as $secretNamePart) {
            if (str_contains(haystack: $lowerCaseName, needle: $secretNamePart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function withoutTemporaryPaths(array $values): array
    {
        $result = [];
        foreach ($values as $name => $value) {
            if ($name === 'tmp_name') {
                continue;
            }
            $result[$name] = is_array(value: $value) ? $this->withoutTemporaryPaths(values: $value) : $value;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function printValues(array $values): string
    {
        return print_r(value: $values, return: true);
    }

    private function withoutQueryAndFragment(string $url): string
    {
        return substr(string: $url, offset: 0, length: strcspn(string: $url, characters: '?#'));
    }

    /**
     * A value from the request must not break the lines of the log (log forging).
     */
    private function singleLine(string $text): string
    {
        return preg_replace(pattern: '/[\x00-\x1F\x7F]/', replacement: '?', subject: $text) ?? '';
    }
}
