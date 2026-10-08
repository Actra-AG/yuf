<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use LogicException;

/**
 * Header and body of a message as handed to the mailer. The PHP version of `X-Mailer` is replaced by `X`, everything
 * else is exact (see `CapturingMailer`).
 */
final readonly class CapturedMessage
{
    public function __construct(public string $header, public string $body) {}

    public function normalizedHeader(): string
    {
        $normalized = preg_replace(
            pattern: '/^X-Mailer: [^\r\n]*/m',
            replacement: 'X-Mailer: PHP/X',
            subject: $this->header,
        );

        return $normalized ?? throw new LogicException(message: 'Normalizing failed.');
    }
}
