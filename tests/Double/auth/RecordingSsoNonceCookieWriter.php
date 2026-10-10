<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use actra\yuf\auth\SsoNonceCookieWriter;
use Override;

/**
 * Records the cookies instead of sending them.
 */
final class RecordingSsoNonceCookieWriter implements SsoNonceCookieWriter
{
    /** @var list<array{name: string, value: string, maxAge: int, path: string}> */
    public array $setCookies = [];
    /** @var list<array{name: string, path: string}> */
    public array $deletedCookies = [];

    #[Override]
    public function set(string $name, string $value, int $maxAgeInSeconds, string $path): void
    {
        $this->setCookies[] = ['name' => $name, 'value' => $value, 'maxAge' => $maxAgeInSeconds, 'path' => $path];
    }

    #[Override]
    public function delete(string $name, string $path): void
    {
        $this->deletedCookies[] = ['name' => $name, 'path' => $path];
    }
}
