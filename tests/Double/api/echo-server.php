<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

/**
 * Router script of PHP's built-in web server, started by `LocalHttpServer` for the tests of `src/api/` (loopback only,
 * no network). `/echo` answers with what the server received, the other paths give a prepared response.
 */

$serverValue = static function (string $key): string {
    if (!array_key_exists(key: $key, array: $_SERVER) || !is_string(value: $_SERVER[$key])) {
        throw new RuntimeException(message: 'Missing server variable ' . $key);
    }

    return $_SERVER[$key];
};
$method = $serverValue('REQUEST_METHOD');
$uri = $serverValue('REQUEST_URI');
$path = (string) parse_url(url: $uri, component: PHP_URL_PATH);
$query = [];
parse_str(string: (string) parse_url(url: $uri, component: PHP_URL_QUERY), result: $query);

if (str_starts_with(haystack: $path, needle: '/status/')) {
    http_response_code(response_code: (int) substr(string: $path, offset: 8));
    header(header: 'Content-Type: text/plain');
    echo 'status ' . substr(string: $path, offset: 8);

    return;
}

if ($path === '/redirect') {
    http_response_code(response_code: array_key_exists(key: 'code', array: $query) ? (int) $query['code'] : 302);
    header(header: 'Location: http://127.0.0.2:1/echo');

    return;
}

if ($path === '/headers') {
    header(header: 'X-Single: one');
    header(header: 'X-Multi: a', replace: false);
    header(header: 'X-Multi: b', replace: false);
    header(header: 'X-Empty:');
    echo 'with headers';

    return;
}

if ($path === '/big') {
    $bytes = array_key_exists(key: 'bytes', array: $query) ? (int) $query['bytes'] : 0;
    if (array_key_exists(key: 'chunked', array: $query)) {
        // Flushing before the end of the script makes the server answer without Content-Length
        for ($sent = 0; $sent < $bytes; $sent += 500) {
            echo str_repeat(string: 'x', times: min(500, $bytes - $sent));
            flush();
        }

        return;
    }
    echo str_repeat(string: 'x', times: $bytes);

    return;
}

if ($path === '/json') {
    header(header: 'Content-Type: application/json');
    echo '{"name":"yuf","list":[1,2],"nested":{"ok":true}}';

    return;
}

if ($path === '/json-list') {
    header(header: 'Content-Type: application/json');
    echo '[1,2,3]';

    return;
}

if ($path === '/bad-json') {
    echo '{"name":';

    return;
}

if ($path === '/xml') {
    header(header: 'Content-Type: text/xml');
    echo '<?xml version="1.0"?><root><item id="1"><![CDATA[a & b]]></item></root>';

    return;
}

if ($path === '/empty') {
    http_response_code(response_code: 204);

    return;
}

header(header: 'Content-Type: application/json');
echo json_encode(
    value: [
        'method' => $method,
        'uri' => $uri,
        'headers' => getallheaders(),
        'body' => (string) file_get_contents(filename: 'php://input'),
    ],
    flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
);
