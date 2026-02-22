<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

class DbQueryLogItem
{
    private float $start;
    private ?float $end = null;

    public function __construct(
        private(set) readonly string $sqlQuery,
        private(set) readonly array $params
    ) {
        $this->start = microtime(as_float: true);
    }

    public function confirmFinishedExecution(): void
    {
        $this->end = microtime(as_float: true);
    }

    public function getExecutionTime(): float
    {
        return $this->end - $this->start;
    }
}