<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbConnectionException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DbConnectionExceptionTest extends TestCase
{
    public function testMessageAndCodeAreKeptWithoutThePreviousError(): void
    {
        $exception = DbConnectionException::fromThrowable(
            throwable: new PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002),
        );

        $this->assertSame('SQLSTATE[HY000] [2002] Connection refused', $exception->getMessage());
        $this->assertSame(2002, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('unknownTimeZoneProvider')]
    public function testUnknownTimeZoneExplainsTheTimeZoneTables(string $message): void
    {
        $exception = DbConnectionException::fromThrowable(throwable: new RuntimeException($message));

        $this->assertStringStartsWith('The database has no time zone tables: load them', $exception->getMessage());
        $this->assertStringEndsWith($message, $exception->getMessage());
    }

    /**
     * @return array<string, array{non-empty-string}>
     */
    public static function unknownTimeZoneProvider(): array
    {
        return [
            'when connecting' => ["SQLSTATE[HY000] [1298] Unknown or incorrect time zone: 'Europe/Zurich'"],
            'after connecting' => ["SQLSTATE[HY000]: General error: 1298 Unknown or incorrect time zone: 'UTC'"],
        ];
    }

    public function testOtherErrorNumbersAreNotMistakenForTheTimeZone(): void
    {
        $message = 'SQLSTATE[HY000] [12980] Something else';

        $this->assertSame(
            $message,
            DbConnectionException::fromThrowable(throwable: new RuntimeException($message))->getMessage(),
        );
    }
}
