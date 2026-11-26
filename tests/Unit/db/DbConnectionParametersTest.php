<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbSettings;
use InvalidArgumentException;
use PDO;
use Pdo\Mysql;
use PHPUnit\Framework\TestCase;

/**
 * The MySQL specific part of the connection setup, tested without a server.
 */
final class DbConnectionParametersTest extends TestCase
{
    private function create(
        ?string $timeNamesLanguage,
        bool $sqlSafeUpdates,
        ?string $charset = null,
        ?string $timeZone = null,
    ): DbSettings {
        return new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: 'not-a-real-password',
            timeZone: $timeZone,
            charset: $charset,
            timeNamesLanguage: $timeNamesLanguage,
            sqlSafeUpdates: $sqlSafeUpdates,
        );
    }

    public function testMysqlDsnContainsHostDatabaseAndCharset(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: null, sqlSafeUpdates: false, charset: 'latin1'),
        );

        $this->assertSame('mysql:host=db.example.com;dbname=app;charset=latin1', $parameters->dsn);
    }

    public function testMysqlUserAndPasswordAreNotPartOfTheDsn(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: 'de_CH', sqlSafeUpdates: true),
        );

        $this->assertSame('app_user', $parameters->userName);
        $this->assertSame('not-a-real-password', $parameters->password);
        $this->assertStringNotContainsString('app_user', $parameters->dsn);
        $this->assertStringNotContainsString('not-a-real-password', $parameters->dsn);
    }

    public function testDefaultSettingsSetTheTimeNamesAndSwitchOnSafeUpdates(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: 'de_CH', sqlSafeUpdates: true),
        );

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 3, Mysql::ATTR_INIT_COMMAND => "SET lc_time_names='de_CH', sql_safe_updates=1"],
            $parameters->options,
        );
    }

    public function testSafeUpdatesAreSwitchedOnByDefault(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: new DbSettings(
                hostName: 'db.example.com',
                databaseName: 'app',
                userName: 'app_user',
                password: 'not-a-real-password',
                timeZone: null,
                timeNamesLanguage: null,
            ),
        );

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 3, Mysql::ATTR_INIT_COMMAND => 'SET sql_safe_updates=1'],
            $parameters->options,
        );
    }

    public function testOnlyTheTimeNamesAreSetWithoutSafeUpdates(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: 'en_US', sqlSafeUpdates: false),
        );

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 3, Mysql::ATTR_INIT_COMMAND => "SET lc_time_names='en_US'"],
            $parameters->options,
        );
    }

    public function testTimeZoneIsSetFirst(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: 'de_CH', sqlSafeUpdates: true, timeZone: 'Europe/Zurich'),
        );

        $this->assertSame(
            [
                PDO::ATTR_TIMEOUT => 3,
                Mysql::ATTR_INIT_COMMAND => "SET time_zone='Europe/Zurich', lc_time_names='de_CH', sql_safe_updates=1",
            ],
            $parameters->options,
        );
    }

    public function testOnlyTheTimeZoneIsSet(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: null, sqlSafeUpdates: false, timeZone: 'UTC'),
        );

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 3, Mysql::ATTR_INIT_COMMAND => "SET time_zone='UTC'"],
            $parameters->options,
        );
    }

    public function testNoInitCommandWithoutAnythingToSet(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: null, sqlSafeUpdates: false),
        );

        $this->assertSame([PDO::ATTR_TIMEOUT => 3], $parameters->options);
    }

    public function testConnectTimeoutIsTakenFromTheSettings(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: new DbSettings(
                hostName: 'db.example.com',
                databaseName: 'app',
                userName: 'app_user',
                password: 'not-a-real-password',
                timeZone: null,
                connectTimeoutInSeconds: 1,
            ),
        );

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 1, Mysql::ATTR_INIT_COMMAND => "SET lc_time_names='de_CH', sql_safe_updates=1"],
            $parameters->options,
        );
    }

    public function testConnectTimeoutBelowOneSecondThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: 'not-a-real-password',
            timeZone: null,
            connectTimeoutInSeconds: 0,
        );
    }

    public function testParametersForOtherDriversNeedOnlyADsn(): void
    {
        $parameters = new DbConnectionParameters(dsn: 'sqlite::memory:');

        $this->assertNull($parameters->userName);
        $this->assertNull($parameters->password);
        $this->assertSame([], $parameters->options);
    }
}
