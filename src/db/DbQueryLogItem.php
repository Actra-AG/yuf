<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use LogicException;

/**
 * One logged query: the SQL, the bound values and how long the execution took.
 *
 * @phpstan-import-type SqlParameters from DbQueryData
 */
final class DbQueryLogItem
{
    private float $start;
    private ?float $end = null;

    /**
     * @param SqlParameters $params
     */
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

    /**
     * @throws LogicException If the execution has not been confirmed as finished.
     */
    public function getExecutionTime(): float
    {
        if ($this->end === null) {
            throw new LogicException(
                message: 'The execution of the query is not finished: call confirmFinishedExecution() first.',
            );
        }

        return $this->end - $this->start;
    }

    private function nowAsFloat(): float
    {
        return (float) $this->clock->now()->format(format: 'U.u');
    }
}
