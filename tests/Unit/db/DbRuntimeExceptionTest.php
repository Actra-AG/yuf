<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbRuntimeException;
use LogicException;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DbRuntimeExceptionTest extends TestCase
{
    public function testMessageContainsTheOriginalMessageAndTheSql(): void
    {
        $exception = new DbRuntimeException(throwable: new PDOException(message: 'Syntax error'), sql: 'SELECT ?');

        $this->assertStringContainsString('Syntax error;', $exception->getMessage());
        $this->assertStringContainsString('SQL-String: "SELECT ?"', $exception->getMessage());
    }

    public function testPreviousIsTheOriginalThrowable(): void
    {
        $original = new PDOException(message: 'Syntax error');

        $exception = new DbRuntimeException(throwable: $original, sql: 'SELECT 1');

        $this->assertSame($original, $exception->getPrevious());
    }

    public function testCodeOfAPdoExceptionIsTheDriverErrorCode(): void
    {
        $original = new PDOException(message: 'Duplicate entry');
        $original->errorInfo = ['23000', 1062, 'Duplicate entry'];

        $exception = new DbRuntimeException(throwable: $original, sql: 'INSERT');

        $this->assertSame(1062, $exception->getCode());
    }

    public function testCodeOfAPdoExceptionWithoutErrorInfoIsZero(): void
    {
        $original = new PDOException(message: 'Broken', code: 7);

        $exception = new DbRuntimeException(throwable: $original, sql: 'SELECT 1');

        $this->assertSame(0, $exception->getCode());
    }

    public function testCodeOfOtherThrowablesIsKept(): void
    {
        $original = new LogicException(message: 'Broken', code: 42);

        $exception = new DbRuntimeException(throwable: $original, sql: 'SELECT 1');

        $this->assertSame(42, $exception->getCode());
    }

    public function testMessageNamesTheParametersWithoutTheirValues(): void
    {
        $exception = new DbRuntimeException(
            throwable: new PDOException(message: 'Broken'),
            sql: 'SELECT ?, ?',
            parameters: ['anna@example.com', 7],
        );

        $this->assertStringNotContainsString('anna@example.com', $exception->getMessage());
        $this->assertStringContainsString('SQL-Parameters: 2 bound values', $exception->getMessage());
    }

    public function testMessageSaysThereAreNoParameters(): void
    {
        $exception = new DbRuntimeException(throwable: new PDOException(message: 'Broken'), sql: 'SELECT 1');

        $this->assertStringContainsString('SQL-Parameters: none', $exception->getMessage());
    }
}
