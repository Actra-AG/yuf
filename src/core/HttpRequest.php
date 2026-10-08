<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\common\StringUtils;
use actra\yuf\form\AmountParser;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * An immutable snapshot of the HTTP request: nothing here reads a superglobal after the creation.
 *
 * `Core` creates the request of the process with `fromGlobals()` and hands it to everything that needs it
 * (`Core::$httpRequest`, `ViewContext::$httpRequest`). `fromGlobals()` is the boundary for `Core`, bootstrap files and
 * CLI scripts, not for logic classes. Tests and other callers build a request with the constructor.
 *
 * Input values are only available explicitly from the query string (`getQuery…()`) or from the posted form data
 * (`getPost…()`). Strings are trimmed, a missing or not matching value is `null`. Integer and float getters accept
 * only a well-formed number, never a prefix of one (`'12abc'`, `''` and `'1.5'` are no integers).
 *
 * Note: `getQuery()` is the raw query string (`a=1&b=2`), `getQueryString()` the value of one query parameter.
 */
final readonly class HttpRequest
{
    public const int SSL_PORT = 443;

    private string $host;
    /** @var array<string, string> Header name (lowercase) => value */
    private array $headers;
    /** @var list<string> */
    private array $browserLanguagesByQuality;

    /**
     * @param string $host The host name as the client sent it (with port, if it did): `HTTP_HOST`, else `SERVER_NAME`
     * @param string $uri The request URI with the query string
     * @param string $queryString The raw query string without "?"
     * @param int $port The port of the server, 0 if unknown
     * @param array<string, string> $headers The request headers; names are case-insensitive
     * @param array<string, string> $cookies
     * @param array<array-key, mixed> $queryParameters `$_GET`
     * @param array<array-key, mixed> $postParameters `$_POST`
     * @param array<array-key, mixed> $uploadedFiles `$_FILES`
     * @param array<string, string> $serverVariables `$_SERVER` (strings only), for the error log and the debug page
     *
     * @throws InvalidArgumentException if the host is empty
     */
    public function __construct(
        string $host,
        private RequestMethodEnum $method = RequestMethodEnum::GET,
        private string $uri = '/',
        private string $queryString = '',
        private ProtocolEnum $protocol = ProtocolEnum::HTTPS,
        private int $port = 0,
        private string $serverName = '',
        private string $serverAddress = '',
        private string $remoteAddress = '',
        array $headers = [],
        private array $cookies = [],
        private array $queryParameters = [],
        private array $postParameters = [],
        private array $uploadedFiles = [],
        private string $body = '',
        private array $serverVariables = [],
    ) {
        if ($host === '') {
            throw new InvalidArgumentException(message: 'The host of a request must not be empty.');
        }
        $this->host = $host;
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower(string: $name)] = $value;
        }
        $this->headers = $normalizedHeaders;
        $this->browserLanguagesByQuality = HttpRequest::parseBrowserLanguages(
            acceptLanguage: array_key_exists(key: 'accept-language', array: $normalizedHeaders)
                ? $normalizedHeaders['accept-language']
                : '',
        );
    }

    /**
     * The request of the current PHP process: reads `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, the request
     * headers and the body (`php://input`) once.
     *
     * @throws UnexpectedValueException if this is no web request: the request method, the request URI or the host
     *                                  is missing
     * @throws UnsupportedRequestMethodException if the request method is unknown
     */
    public static function fromGlobals(): HttpRequest
    {
        $server = HttpRequest::onlyStrings(values: $_SERVER);

        return new HttpRequest(
            host: HttpRequest::readHost(server: $server),
            method: HttpRequest::readMethod(server: $server),
            uri: HttpRequest::requireValue(server: $server, key: 'REQUEST_URI'),
            queryString: HttpRequest::findValue(server: $server, key: 'QUERY_STRING') ?? '',
            protocol: HttpRequest::readProtocol(server: $server),
            port: HttpRequest::readPort(server: $server),
            serverName: HttpRequest::findValue(server: $server, key: 'SERVER_NAME') ?? '',
            serverAddress: HttpRequest::findValue(server: $server, key: 'SERVER_ADDR') ?? '',
            remoteAddress: HttpRequest::findValue(server: $server, key: 'REMOTE_ADDR') ?? '',
            headers: HttpRequest::readHeaders(server: $server),
            cookies: HttpRequest::onlyStrings(values: $_COOKIE),
            queryParameters: $_GET,
            postParameters: $_POST,
            uploadedFiles: $_FILES,
            body: (string) file_get_contents(filename: 'php://input'),
            serverVariables: $server,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, string> The entries with a string value
     */
    private static function onlyStrings(array $values): array
    {
        $strings = [];
        foreach ($values as $key => $value) {
            if (is_string(value: $value)) {
                $strings[(string) $key] = $value;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, string> $server
     */
    private static function findValue(array $server, string $key): ?string
    {
        return array_key_exists(key: $key, array: $server) ? $server[$key] : null;
    }

    /**
     * @param array<string, string> $server
     */
    private static function requireValue(array $server, string $key): string
    {
        return HttpRequest::findValue(server: $server, key: $key) ?? throw new UnexpectedValueException(
            message: 'The server variable ' . $key . ' is not defined: this is no web request.',
        );
    }

    /**
     * @param array<string, string> $server
     */
    private static function readHost(array $server): string
    {
        foreach (['HTTP_HOST', 'SERVER_NAME'] as $key) {
            $host = HttpRequest::findValue(server: $server, key: $key);
            if ($host !== null && $host !== '') {
                return $host;
            }
        }

        throw new UnexpectedValueException(
            message: 'HTTP_HOST and SERVER_NAME are not defined: this is no web request.',
        );
    }

    /**
     * @param array<string, string> $server
     */
    private static function readMethod(array $server): RequestMethodEnum
    {
        $method = HttpRequest::requireValue(server: $server, key: 'REQUEST_METHOD');

        return RequestMethodEnum::tryFrom(value: $method) ?? throw new UnsupportedRequestMethodException(
            message: 'Unsupported request method: ' . $method,
        );
    }

    /**
     * The `HTTPS` server variable decides if it exists (`on` or `1` is https, anything else http); without it, the
     * port 443 means https.
     *
     * @param array<string, string> $server
     */
    private static function readProtocol(array $server): ProtocolEnum
    {
        $https = HttpRequest::findValue(server: $server, key: 'HTTPS');
        if ($https !== null) {
            return in_array(needle: strtolower(string: $https), haystack: ['on', '1'], strict: true)
                ? ProtocolEnum::HTTPS
                : ProtocolEnum::HTTP;
        }

        return HttpRequest::readPort(server: $server) === HttpRequest::SSL_PORT
            ? ProtocolEnum::HTTPS
            : ProtocolEnum::HTTP;
    }

    /**
     * @param array<string, string> $server
     */
    private static function readPort(array $server): int
    {
        $port = HttpRequest::findValue(server: $server, key: 'SERVER_PORT');

        return $port !== null && preg_match(pattern: '/^\d{1,5}$/', subject: $port) === 1 ? (int) $port : 0;
    }

    /**
     * The headers of the server variables (`HTTP_USER_AGENT` is `user-agent`); `getallheaders()`, where it exists,
     * adds the ones the web server hides from the variables (the `Authorization` header with FastCGI).
     *
     * @param array<string, string> $server
     *
     * @return array<string, string>
     */
    private static function readHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with(haystack: $key, needle: 'HTTP_')) {
                $headers[HttpRequest::toHeaderName(serverKey: substr(string: $key, offset: 5))] = $value;
            }
        }
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            $value = HttpRequest::findValue(server: $server, key: $key);
            if ($value !== null) {
                $headers[HttpRequest::toHeaderName(serverKey: $key)] = $value;
            }
        }
        if (function_exists(function: 'getallheaders')) {
            foreach (HttpRequest::onlyStrings(values: getallheaders()) as $name => $value) {
                $headerName = strtolower(string: $name);
                if (!array_key_exists(key: $headerName, array: $headers)) {
                    $headers[$headerName] = $value;
                }
            }
        }
        $redirectedAuthorization = HttpRequest::findValue(server: $server, key: 'REDIRECT_HTTP_AUTHORIZATION');
        if ($redirectedAuthorization !== null && !array_key_exists(key: 'authorization', array: $headers)) {
            $headers['authorization'] = $redirectedAuthorization;
        }

        return $headers;
    }

    private static function toHeaderName(string $serverKey): string
    {
        return strtolower(string: str_replace(search: '_', replace: '-', subject: $serverKey));
    }

    /**
     * The language codes of the `Accept-Language` header without the region (`de-CH` is `de`), best quality first;
     * equal qualities keep their order, every language only once. Wildcards and empty entries are skipped.
     *
     * @return list<string>
     */
    private static function parseBrowserLanguages(string $acceptLanguage): array
    {
        $entries = [];
        foreach (explode(separator: ',', string: $acceptLanguage) as $position => $range) {
            $parts = explode(separator: ';', string: $range);
            $languageCode = trim(string: explode(separator: '-', string: $parts[0])[0]);
            if ($languageCode === '' || $languageCode === '*') {
                continue;
            }
            $entries[] = [
                'code' => $languageCode,
                'quality' => HttpRequest::parseQuality(parameters: array_slice(array: $parts, offset: 1)),
                'position' => $position,
            ];
        }
        usort(
            array: $entries,
            callback: fn(array $a, array $b): int => [$b['quality'], $a['position']]
                <=> [$a['quality'], $b['position']],
        );
        $languages = [];
        foreach ($entries as $entry) {
            if (!in_array(needle: $entry['code'], haystack: $languages, strict: true)) {
                $languages[] = $entry['code'];
            }
        }

        return $languages;
    }

    /**
     * @param list<string> $parameters The parameters after the language range (`q=0.8`)
     *
     * @return int The quality in thousandths (0 to 1000), 1000 if not given, 0 if it is no number
     */
    private static function parseQuality(array $parameters): int
    {
        foreach ($parameters as $parameter) {
            if (preg_match(pattern: '/^\s*q\s*=\s*(.*?)\s*$/i', subject: $parameter, matches: $matches) !== 1) {
                continue;
            }
            if (preg_match(pattern: '/^\d+(\.\d+)?$/', subject: $matches[1]) !== 1) {
                return 0;
            }

            return max(0, min(1000, (int) round(num: (float) $matches[1] * 1000)));
        }

        return 1000;
    }

    public function getMethod(): RequestMethodEnum
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * The path of the URI without the query string, as sent (not decoded).
     */
    public function getPath(): string
    {
        return StringUtils::beforeFirst(str: $this->uri, before: '?');
    }

    /**
     * The raw query string without "?".
     */
    public function getQuery(): string
    {
        return $this->queryString;
    }

    public function getProtocol(): ProtocolEnum
    {
        return $this->protocol;
    }

    public function isSsl(): bool
    {
        return $this->protocol === ProtocolEnum::HTTPS;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int The port of the server, 0 if unknown
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * The URL of the request, with the protocol of the request or the given one.
     */
    public function getUrl(?ProtocolEnum $protocol = null): string
    {
        return ($protocol ?? $this->protocol)->value . '://' . $this->host . $this->uri;
    }

    /**
     * @return string `SERVER_NAME`, empty if unknown
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @return string `SERVER_ADDR`, empty if unknown
     */
    public function getServerAddress(): string
    {
        return $this->serverAddress;
    }

    /**
     * @return string `REMOTE_ADDR`, empty if unknown
     */
    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function getUserAgent(): string
    {
        return $this->getHeader(name: 'User-Agent') ?? '';
    }

    public function getReferrer(): string
    {
        return $this->getHeader(name: 'Referer') ?? '';
    }

    /**
     * @return list<string> The languages the browser accepts, best first (see `Accept-Language`)
     */
    public function listBrowserLanguagesByQuality(): array
    {
        return $this->browserLanguagesByQuality;
    }

    /**
     * @param string $name The header name, case-insensitive
     */
    public function getHeader(string $name): ?string
    {
        $headerName = strtolower(string: $name);

        return array_key_exists(key: $headerName, array: $this->headers) ? $this->headers[$headerName] : null;
    }

    /**
     * The token of an `Authorization: Bearer <token>` header (scheme case-insensitive); `null` without a token.
     */
    public function getBearerToken(): ?string
    {
        $authorization = $this->getHeader(name: 'Authorization');
        if ($authorization === null) {
            return null;
        }
        if (preg_match(pattern: '/^bearer\s+(.*)$/is', subject: $authorization, matches: $matches) !== 1) {
            return null;
        }
        $token = trim(string: $matches[1]);

        return $token === '' ? null : $token;
    }

    public function getCookie(string $name): ?string
    {
        return array_key_exists(key: $name, array: $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * All cookies, for the error log; read a cookie with `getCookie()`.
     *
     * @return array<string, string>
     */
    public function listCookies(): array
    {
        return $this->cookies;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function hasQueryValue(string $name): bool
    {
        return array_key_exists(key: $name, array: $this->queryParameters);
    }

    public function hasPostValue(string $name): bool
    {
        return array_key_exists(key: $name, array: $this->postParameters);
    }

    public function getQueryString(string $name): ?string
    {
        return HttpRequest::readString(data: $this->queryParameters, name: $name);
    }

    public function getPostString(string $name): ?string
    {
        return HttpRequest::readString(data: $this->postParameters, name: $name);
    }

    public function getQueryInteger(string $name): ?int
    {
        return HttpRequest::readInteger(data: $this->queryParameters, name: $name);
    }

    public function getPostInteger(string $name): ?int
    {
        return HttpRequest::readInteger(data: $this->postParameters, name: $name);
    }

    public function getQueryFloat(string $name): ?float
    {
        return HttpRequest::readFloat(data: $this->queryParameters, name: $name);
    }

    public function getPostFloat(string $name): ?float
    {
        return HttpRequest::readFloat(data: $this->postParameters, name: $name);
    }

    /**
     * @return ?array<array-key, mixed> The array as sent (not trimmed, nested values unchecked)
     */
    public function getQueryArray(string $name): ?array
    {
        return HttpRequest::readArray(data: $this->queryParameters, name: $name);
    }

    /**
     * @return ?array<array-key, mixed> The array as sent (not trimmed, nested values unchecked)
     */
    public function getPostArray(string $name): ?array
    {
        return HttpRequest::readArray(data: $this->postParameters, name: $name);
    }

    /**
     * All query parameters as sent, for code that narrows them itself (forms) and for logs.
     *
     * @return array<array-key, mixed>
     */
    public function getQueryParameters(): array
    {
        return $this->queryParameters;
    }

    /**
     * All posted form data as sent, for code that narrows them itself (forms) and for logs.
     *
     * @return array<array-key, mixed>
     */
    public function getPostParameters(): array
    {
        return $this->postParameters;
    }

    /**
     * The upload data as PHP created it (`$_FILES`), for code that narrows it itself (forms) and for logs. Use
     * `getFile()` and `getFiles()` to read uploads.
     *
     * @return array<array-key, mixed>
     */
    public function getRawFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * The variables of the server (`$_SERVER`, strings only), for the error log and the debug page.
     *
     * @return array<string, string>
     */
    public function getServerVariables(): array
    {
        return $this->serverVariables;
    }

    /**
     * The uploaded file of a field with one file (`name="field"`); `null` if there is none or if the field takes
     * several files (see `getFiles()`).
     *
     * @return ?array{name: string, type: string, tmp_name: string, error: int, size: int}
     */
    public function getFile(string $name): ?array
    {
        $entry = array_key_exists(key: $name, array: $this->uploadedFiles) ? $this->uploadedFiles[$name] : null;
        if (!is_array(value: $entry) || is_array(value: HttpRequest::field(entry: $entry, key: 'name'))) {
            return null;
        }
        $files = HttpRequest::normalizeFiles(entry: $entry);

        return $files === [] ? null : $files[0];
    }

    /**
     * The uploaded files of a field, a field with one file (`name="field"`) and a field with several (`name="field[]"`)
     * alike, each with the five keys PHP provides. Empty if the field is missing or its structure is not what PHP
     * creates.
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function getFiles(string $name): array
    {
        $entry = array_key_exists(key: $name, array: $this->uploadedFiles) ? $this->uploadedFiles[$name] : null;
        if (!is_array(value: $entry)) {
            return [];
        }

        return HttpRequest::normalizeFiles(entry: $entry);
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private static function normalizeFiles(array $entry): array
    {
        $names = HttpRequest::field(entry: $entry, key: 'name');
        $types = HttpRequest::field(entry: $entry, key: 'type');
        $tmpNames = HttpRequest::field(entry: $entry, key: 'tmp_name');
        $errors = HttpRequest::field(entry: $entry, key: 'error');
        $sizes = HttpRequest::field(entry: $entry, key: 'size');
        if (!is_array(value: $names)) {
            $file = HttpRequest::createFile(
                name: $names,
                type: $types,
                tmpName: $tmpNames,
                error: $errors,
                size: $sizes,
            );

            return $file === null ? [] : [$file];
        }
        if (
            !is_array(value: $types)
            || !is_array(value: $tmpNames)
            || !is_array(value: $errors)
            || !is_array(value: $sizes)
        ) {
            return [];
        }
        $files = [];
        foreach ($names as $key => $name) {
            $file = HttpRequest::createFile(
                name: $name,
                type: HttpRequest::field(entry: $types, key: $key),
                tmpName: HttpRequest::field(entry: $tmpNames, key: $key),
                error: HttpRequest::field(entry: $errors, key: $key),
                size: HttpRequest::field(entry: $sizes, key: $key),
            );
            if ($file === null) {
                return [];
            }
            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private static function field(array $entry, int|string $key): mixed
    {
        return array_key_exists(key: $key, array: $entry) ? $entry[$key] : null;
    }

    /**
     * @return ?array{name: string, type: string, tmp_name: string, error: int, size: int}
     */
    private static function createFile(
        mixed $name,
        mixed $type,
        mixed $tmpName,
        mixed $error,
        mixed $size,
    ): ?array {
        if (!is_string(value: $name) || !is_string(value: $type) || !is_string(value: $tmpName)) {
            return null;
        }
        if (!is_int(value: $error) || !is_int(value: $size)) {
            return null;
        }

        return ['name' => $name, 'type' => $type, 'tmp_name' => $tmpName, 'error' => $error, 'size' => $size];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readString(array $data, string $name): ?string
    {
        if (!array_key_exists(key: $name, array: $data) || !is_scalar(value: $data[$name])) {
            return null;
        }

        return trim(string: (string) $data[$name]);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readInteger(array $data, string $name): ?int
    {
        $value = HttpRequest::readString(data: $data, name: $name);
        if ($value === null || preg_match(pattern: '/^-?\d+$/', subject: $value) !== 1) {
            return null;
        }

        return AmountParser::toInt(value: $value);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readFloat(array $data, string $name): ?float
    {
        $value = HttpRequest::readString(data: $data, name: $name);
        if ($value === null || preg_match(pattern: '/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', subject: $value) !== 1) {
            return null;
        }
        $float = (float) $value;

        return is_finite(num: $float) ? $float : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return ?array<array-key, mixed>
     */
    private static function readArray(array $data, string $name): ?array
    {
        if (!array_key_exists(key: $name, array: $data) || !is_array(value: $data[$name])) {
            return null;
        }

        return $data[$name];
    }
}
