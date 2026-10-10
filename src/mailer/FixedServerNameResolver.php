<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use Override;

/**
 * Answers every address with the same host name, without DNS. Use it in tests of yuf and of projects that build upon
 * yuf (e.g. a mailer double, see docs/testing.md).
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
