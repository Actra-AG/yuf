<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * The access to one mailbox of an IMAP server. The password is hidden in stack traces and `var_dump()`.
 */
final readonly class ImapSettings
{
    public int $port;

    /**
     * @param int|null $port           `null` for the default port of the encryption (993 or 143)
     * @param int      $timeoutSeconds for connecting and for every answer of the server
     */
    public function __construct(
        public string $hostName,
        public string $userName,
        #[SensitiveParameter]
        public string $password,
        public ImapEncryptionEnum $encryption = ImapEncryptionEnum::IMPLICIT_TLS,
        ?int $port = null,
        public int $timeoutSeconds = 10,
    ) {
        if (trim(string: $hostName) === '' || preg_match(pattern: '/[\s\/]/', subject: $hostName) === 1) {
            throw new InvalidArgumentException(message: 'Invalid IMAP host name "' . $hostName . '".');
        }
        if ($userName === '' || $password === '') {
            throw new InvalidArgumentException(message: 'The IMAP user name and password must not be empty.');
        }
        if (preg_match(pattern: '/[\x00\r\n]/', subject: $userName . $password) === 1) {
            throw new InvalidArgumentException(
                message: 'The IMAP user name and password must not contain NUL or line breaks.',
            );
        }
        $this->port = $port ?? $encryption->getDefaultPort();
        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException(message: 'Invalid IMAP port ' . $this->port . '.');
        }
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException(message: 'The IMAP timeout must be at least 1 second.');
        }
    }

    /**
     * @return array<string, string|int>
     */
    public function __debugInfo(): array
    {
        return [
            'hostName' => $this->hostName,
            'userName' => $this->userName,
            'password' => '(hidden)',
            'encryption' => $this->encryption->value,
            'port' => $this->port,
            'timeoutSeconds' => $this->timeoutSeconds,
        ];
    }
}
