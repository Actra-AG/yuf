<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

/**
 * Script of `LocalTlsServer`: a TLS server on the loopback address that answers one request. The certificate is in
 * the environment variable `YUF_TLS_CERTIFICATE` (path of a PEM file with certificate and key).
 */

ini_set(option: 'display_errors', value: '0');
$certificatePath = getenv(name: 'YUF_TLS_CERTIFICATE');
if (!is_string(value: $certificatePath)) {
    exit(1);
}
$context = stream_context_create(options: ['ssl' => ['local_cert' => $certificatePath, 'verify_peer' => false]]);
$server = stream_socket_server(
    address: 'tls://127.0.0.1:0',
    error_code: $errorCode,
    error_message: $errorMessage,
    flags: STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    context: $context,
);
if ($server === false) {
    exit(1);
}
$name = (string) stream_socket_get_name(socket: $server, remote: false);
echo 'READY ' . substr(string: $name, offset: (int) strrpos(haystack: $name, needle: ':') + 1) . "\n";
$client = stream_socket_accept(socket: $server, timeout: 10);
if ($client !== false) {
    fread(stream: $client, length: 8192);
    fwrite(
        stream: $client,
        data: "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok",
    );
    fclose(stream: $client);
}
