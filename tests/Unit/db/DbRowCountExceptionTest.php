<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbRowCountException;
use PHPUnit\Framework\TestCase;

final class DbRowCountExceptionTest extends TestCase
{
    public function testMessageNamesTheRowCountAndTheSql(): void
    {
        $exception = DbRowCountException::moreThanOneRow(rowCount: 3, sql: 'SELECT id FROM users');

        $this->assertStringContainsString('returned 3 rows', $exception->getMessage());
        $this->assertStringContainsString('SELECT id FROM users', $exception->getMessage());
    }
}
