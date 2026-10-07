<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

readonly class SessionSettings
{
    public function __construct(
        public ?string $savePath = null,
        public string $individualName = '',
        public int $maxLifeTime = 3600,
        public int $gcProbability = 1,
        public int $gcDivisor = 100000,
        public bool $isSameSiteStrict = true,
    ) {}
}
