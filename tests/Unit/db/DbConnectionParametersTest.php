<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbSettings;
use Pdo\Mysql;
use PHPUnit\Framework\TestCase;

/**
 * The MySQL specific part of the connection setup, tested without a server.
 */
final class DbConnectionParametersTest extends TestCase
{
    private function create(?string $timeNamesLanguage, bool $sqlSafeUpdates, ?string $charset = null): DbSettings
    {
        return new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: 'not-a-real-password',
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
            [Mysql::ATTR_INIT_COMMAND => "SET lc_time_names='de_CH', sql_safe_updates=1"],
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
                timeNamesLanguage: null,
            ),
        );

        $this->assertSame([Mysql::ATTR_INIT_COMMAND => 'SET sql_safe_updates=1'], $parameters->options);
    }

    public function testOnlyTheTimeNamesAreSetWithoutSafeUpdates(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: 'en_US', sqlSafeUpdates: false),
        );

        $this->assertSame([Mysql::ATTR_INIT_COMMAND => "SET lc_time_names='en_US'"], $parameters->options);
    }

    public function testNoInitCommandWithoutAnythingToSet(): void
    {
        $parameters = DbConnectionParameters::forMysql(
            dbSettings: $this->create(timeNamesLanguage: null, sqlSafeUpdates: false),
        );

        $this->assertSame([], $parameters->options);
    }

    public function testParametersForOtherDriversNeedOnlyADsn(): void
    {
        $parameters = new DbConnectionParameters(dsn: 'sqlite::memory:');

        $this->assertNull($parameters->userName);
        $this->assertNull($parameters->password);
        $this->assertSame([], $parameters->options);
    }
}
