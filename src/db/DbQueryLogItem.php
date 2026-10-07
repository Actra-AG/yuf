<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;

class DbQueryLogItem
{
    private float $start;
    private ?float $end = null;

    public function __construct(
        public private(set) readonly string $sqlQuery,
        public private(set) readonly array $params,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->start = $this->nowAsFloat();
    }

    public function confirmFinishedExecution(): void
    {
        $this->end = $this->nowAsFloat();
    }

    public function getExecutionTime(): float
    {
        return $this->end - $this->start;
    }

    private function nowAsFloat(): float
    {
        return (float) $this->clock->now()->format(format: 'U.u');
    }
}
