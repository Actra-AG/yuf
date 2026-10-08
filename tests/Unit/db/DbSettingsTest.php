<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbSettingsTest extends TestCase
{
    private function create(
        string $hostName = 'db.example.com',
        string $databaseName = 'app',
        ?string $charset = null,
        ?string $timeNamesLanguage = 'de_CH',
        bool $sqlSafeUpdates = true,
    ): DbSettings {
        return new DbSettings(
            hostName: $hostName,
            databaseName: $databaseName,
            userName: 'app_user',
            password: 'not-a-real-password',
            charset: $charset,
            timeNamesLanguage: $timeNamesLanguage,
            sqlSafeUpdates: $sqlSafeUpdates,
        );
    }

    public function testDefaults(): void
    {
        $settings = new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: 'not-a-real-password',
        );

        $this->assertSame('utf8mb4', $settings->charset);
        $this->assertSame('de_CH', $settings->timeNamesLanguage);
        $this->assertTrue($settings->sqlSafeUpdates);
    }

    public function testValuesAreKept(): void
    {
        $settings = $this->create(charset: 'utf8', timeNamesLanguage: null, sqlSafeUpdates: false);

        $this->assertSame('db.example.com', $settings->hostName);
        $this->assertSame('app', $settings->databaseName);
        $this->assertSame('app_user', $settings->userName);
        $this->assertSame('not-a-real-password', $settings->password);
        $this->assertSame('utf8', $settings->charset);
        $this->assertNull($settings->timeNamesLanguage);
        $this->assertFalse($settings->sqlSafeUpdates);
    }

    public function testSeveralSettingsWithTheSameValuesCanExist(): void
    {
        $this->assertNotSame($this->create(), $this->create());
    }

    #[DataProvider('charsetProvider')]
    public function testCharsetIsNormalized(?string $charset, string $expected): void
    {
        $this->assertSame($expected, $this->create(charset: $charset)->charset);
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function charsetProvider(): array
    {
        return [
            'null' => [null, 'utf8mb4'],
            'empty' => ['', 'utf8mb4'],
            'blank' => ['  ', 'utf8mb4'],
            'trimmed' => [' latin1 ', 'latin1'],
            'utf8' => ['utf8', 'utf8'],
            'with underscore and digit' => ['utf8mb4_0900', 'utf8mb4_0900'],
        ];
    }

    #[DataProvider('invalidCharsetProvider')]
    public function testInvalidCharsetThrows(string $charset, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->create(charset: $charset);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidCharsetProvider(): array
    {
        return [
            'utf-8' => ['utf-8', 'Faulty charset setting string "utf-8". Must be "utf8" for PDO driver.'],
            'UTF-8' => ['UTF-8', 'Faulty charset setting string'],
            'dsn injection' => ['utf8;dbname=other', 'may only contain letters, digits and underscores'],
            'space' => ['utf8 mb4', 'may only contain letters, digits and underscores'],
            'inner line break' => ["utf8\nmb4", 'may only contain letters, digits and underscores'],
        ];
    }

    #[DataProvider('timeNamesLanguageProvider')]
    public function testValidTimeNamesLanguageIsAccepted(string $language): void
    {
        $this->assertSame($language, $this->create(timeNamesLanguage: $language)->timeNamesLanguage);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function timeNamesLanguageProvider(): array
    {
        return [
            'de_CH' => ['de_CH'],
            'en_US' => ['en_US'],
            'three letters' => ['fil_PH'],
            'with script' => ['sr_RS@latin'],
        ];
    }

    #[DataProvider('invalidTimeNamesLanguageProvider')]
    public function testInvalidTimeNamesLanguageThrows(string $language): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The time names language must be a MySQL locale like "de_CH"');

        $this->create(timeNamesLanguage: $language);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTimeNamesLanguageProvider(): array
    {
        return [
            'empty' => [''],
            'lowercase country' => ['de_ch'],
            'dash' => ['de-CH'],
            'sql' => ["de_CH'; DROP TABLE users; --"],
            'trailing line break' => ["de_CH\n"],
        ];
    }

    #[DataProvider('invalidDsnValueProvider')]
    public function testInvalidHostNameThrows(string $hostName, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->create(hostName: $hostName);
    }

    #[DataProvider('invalidDsnValueProvider')]
    public function testInvalidDatabaseNameThrows(string $databaseName, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->create(databaseName: $databaseName);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDsnValueProvider(): array
    {
        return [
            'empty' => ['', 'must not be empty'],
            'semicolon' => ['example.com;dbname=other', 'must not contain ";" or control characters'],
            'line break' => ["example.com\n", 'must not contain ";" or control characters'],
            'null byte' => ["example\0.com", 'must not contain ";" or control characters'],
        ];
    }

    public function testMessageNamesTheInvalidSettingButNotThePassword(): void
    {
        try {
            $this->create(hostName: '');
            DbSettingsTest::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('hostName', $exception->getMessage());
            $this->assertStringNotContainsString('not-a-real-password', $exception->getMessage());
        }
    }
}
