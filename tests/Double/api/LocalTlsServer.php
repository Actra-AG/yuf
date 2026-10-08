<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\api;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * An HTTPS server on the loopback address with a self-signed certificate (a separate process, see `tls-server.php`).
 * No client trusts the certificate, so it is the server for the tests of the certificate verification.
 */
final class LocalTlsServer
{
    /** @var resource */
    private $process;

    /** @var resource */
    private $output;

    private readonly string $certificatePath;
    public readonly int $port;

    public function __construct()
    {
        $this->certificatePath = $this->createSelfSignedCertificate();
        // @phpstan-ignore disallowed.function (starts PHP with fixed arguments for a TLS server on loopback)
        $process = proc_open(
            command: [PHP_BINARY, __DIR__ . '/tls-server.php'],
            descriptor_spec: [0 => ['null'], 1 => ['pipe', 'w'], 2 => ['null']],
            pipes: $pipes,
            env_vars: ['YUF_TLS_CERTIFICATE' => $this->certificatePath],
        );
        if ($process === false) {
            throw new RuntimeException(message: 'The local TLS server could not be started.');
        }
        $this->process = $process;
        if (!array_key_exists(key: 1, array: $pipes)) {
            throw new RuntimeException(message: 'The local TLS server has no output.');
        }
        $this->output = $pipes[1];
        stream_set_timeout(stream: $this->output, seconds: 10);
        $line = fgets(stream: $this->output);
        if ($line === false || !str_starts_with(haystack: $line, needle: 'READY ')) {
            throw new RuntimeException(message: 'The local TLS server does not listen.');
        }
        $this->port = (int) substr(string: $line, offset: 6);
    }

    public function __destruct()
    {
        proc_terminate(process: $this->process);
        fclose(stream: $this->output);
        proc_close(process: $this->process);
        unlink(filename: $this->certificatePath);
    }

    public function url(): string
    {
        return 'https://127.0.0.1:' . $this->port . '/';
    }

    private function createSelfSignedCertificate(): string
    {
        $key = openssl_pkey_new(options: ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException(message: 'No private key could be generated.');
        }
        // The key is a by-reference parameter of openssl_csr_new(), so it gets a copy
        $keyForRequest = $key;
        $request = openssl_csr_new(distinguished_names: ['commonName' => '127.0.0.1'], private_key: $keyForRequest);
        if (!$request instanceof OpenSSLCertificateSigningRequest) {
            throw new RuntimeException(message: 'No certificate request could be generated.');
        }
        $certificate = openssl_csr_sign(csr: $request, ca_certificate: null, private_key: $key, days: 1);
        if (!$certificate instanceof OpenSSLCertificate) {
            throw new RuntimeException(message: 'No certificate could be generated.');
        }
        $certificatePem = '';
        $keyPem = '';
        openssl_x509_export(certificate: $certificate, output: $certificatePem);
        openssl_pkey_export(key: $key, output: $keyPem);
        if (!is_string(value: $certificatePem) || !is_string(value: $keyPem)) {
            throw new RuntimeException(message: 'The certificate could not be exported.');
        }
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'yuf-tls-');
        if ($path === false) {
            throw new RuntimeException(message: 'No temporary file could be created.');
        }
        file_put_contents(filename: $path, data: $certificatePem . $keyPem);

        return $path;
    }
}
