<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\HostPolicy;
use Override;

/**
 * Allows the given hosts exactly as written (like a lookup in a database) and records the asked hosts.
 */
final class RecordingHostPolicy implements HostPolicy
{
    /** @var list<string> */
    public array $askedHosts = [];

    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(private readonly array $allowedHosts) {}

    #[Override]
    public function isAllowed(string $host): bool
    {
        $this->askedHosts[] = $host;

        return in_array(needle: $host, haystack: $this->allowedHosts, strict: true);
    }
}
