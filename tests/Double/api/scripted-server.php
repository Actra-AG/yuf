<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

/**
 * Router script of PHP's built-in web server, started by `ScriptedHttpServer` (loopback only, no network). Answers
 * every path with the status and body that the test prepared in `routes.json` (404 for other paths) and appends every
 * request, as `echo-server.php` shows it, to `requests.jsonl`. Both files are in the directory of the environment
 * variable `YUF_SCRIPTED_DIR` (not in `$_SERVER`: `variables_order`).
 */

$serverValue = static function (string $key): string {
    if (!array_key_exists(key: $key, array: $_SERVER) || !is_string(value: $_SERVER[$key])) {
        throw new RuntimeException(message: 'Missing server variable ' . $key);
    }

    return $_SERVER[$key];
};
$directory = getenv(name: 'YUF_SCRIPTED_DIR');
if (!is_string(value: $directory)) {
    throw new RuntimeException(message: 'Missing environment variable YUF_SCRIPTED_DIR');
}
$uri = $serverValue('REQUEST_URI');
$path = (string) parse_url(url: $uri, component: PHP_URL_PATH);

file_put_contents(
    filename: $directory . '/requests.jsonl',
    data: json_encode(
        value: [
            'method' => $serverValue('REQUEST_METHOD'),
            'uri' => $uri,
            'headers' => getallheaders(),
            'body' => (string) file_get_contents(filename: 'php://input'),
        ],
        flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
    ) . "\n",
    flags: FILE_APPEND | LOCK_EX,
);

$routes = json_decode(
    json: (string) file_get_contents(filename: $directory . '/routes.json'),
    associative: true,
    flags: JSON_THROW_ON_ERROR,
);
$route = is_array(value: $routes) && array_key_exists(key: $path, array: $routes) ? $routes[$path] : null;
if (
    !is_array(value: $route)
    || !array_key_exists(key: 'status', array: $route)
    || !array_key_exists(key: 'body', array: $route)
    || !is_int(value: $route['status'])
    || !is_string(value: $route['body'])
) {
    http_response_code(response_code: 404);
    echo '{}';

    return;
}
http_response_code(response_code: $route['status']);
header(header: 'Content-Type: application/json');
echo $route['body'];
