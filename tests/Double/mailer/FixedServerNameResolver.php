<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\ServerNameResolver;
use Override;

/**
 * Answers every address with the same host name.
 */
final readonly class FixedServerNameResolver implements ServerNameResolver
{
    public function __construct(private string $serverName = 'mail.example.com') {}

    #[Override]
    public function resolve(string $serverAddress): string
    {
        return $this->serverName;
    }
}
