<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\MailFunction;
use LogicException;
use Override;

/**
 * Remembers the arguments of the calls instead of calling `mail()`.
 */
final class RecordingMailFunction implements MailFunction
{
    /** @var list<array{to: string, subject: string, message: string, headers: string, parameters: string}> */
    public private(set) array $calls = [];

    public function __construct(private readonly bool $result = true) {}

    /**
     * @return array{to: string, subject: string, message: string, headers: string, parameters: string}
     */
    public function onlyCall(): array
    {
        if (count(value: $this->calls) !== 1) {
            throw new LogicException(message: 'Expected exactly one call, got ' . count(value: $this->calls) . '.');
        }

        return $this->calls[array_key_first(array: $this->calls)];
    }

    #[Override]
    public function send(
        string $to,
        string $subject,
        string $message,
        string $additionalHeaders,
        string $additionalParameters,
    ): bool {
        $this->calls[] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $additionalHeaders,
            'parameters' => $additionalParameters,
        ];

        return $this->result;
    }
}
