<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\Core;

readonly class SessionSettingsModel
{
    public string $savePath;

    public function __construct(
        string $savePath = '{default}',
        public string $individualName = '',
        public int $maxLifeTime = 3600,
        public int $gcProbability = 1,
        public int $gcDivisor = 100000,
        public bool $isSameSiteStrict = true
    ) {
        $this->savePath = str_replace(
            search: '{default}',
            replace: Core::get()->cacheDirectory . 'sessions',
            subject: $savePath
        );
    }
}