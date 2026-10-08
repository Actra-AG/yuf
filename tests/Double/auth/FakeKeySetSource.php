<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use actra\yuf\auth\JsonWebKeySetSource;
use Override;

/**
 * A key set source without network: answers with a fixed key set and counts the downloads.
 */
final class FakeKeySetSource implements JsonWebKeySetSource
{
    public int $downloads = 0;

    public function __construct(public string $json) {}

    #[Override]
    public function download(): string
    {
        $this->downloads++;

        return $this->json;
    }
}
