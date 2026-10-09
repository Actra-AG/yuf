<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\EnvironmentSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class EnvironmentSettingsTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createValues(array $overrides = []): array
    {
        return array_merge(
            [
                'defaultErrorReporting' => E_ALL,
                'defaultTimeZone' => 'Europe/Zurich',
                'allowedDomains' => ['example.com', 'www.example.com'],
                'logEmailRecipient' => 'error@example.com',
                'debug' => true,
                'robots' => 'noindex,nofollow',
            ],
            $overrides,
        );
    }

    public function testSettingsAreReadFromTheArray(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues());

        $this->assertSame(E_ALL, $settings->errorReporting);
        $this->assertSame('Europe/Zurich', $settings->timeZone);
        $this->assertSame(['example.com', 'www.example.com'], $settings->allowedDomains);
        $this->assertSame('error@example.com', $settings->logEmailRecipient);
        $this->assertTrue($settings->debug);
        $this->assertSame('noindex,nofollow', $settings->robots);
    }

    public function testOwnKeysAreReadWithTypedGetters(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues([
            'mailer.hostname' => 'localhost',
            'mailer.port' => 1025,
            'mailer.tls' => false,
            'mail.recipients' => ['a@example.com', 'b@example.com'],
        ]));

        $this->assertSame('localhost', $settings->getString(key: 'mailer.hostname'));
        $this->assertSame(1025, $settings->getInt(key: 'mailer.port'));
        $this->assertFalse($settings->getBool(key: 'mailer.tls'));
        $this->assertSame(['a@example.com', 'b@example.com'], $settings->getStringList(key: 'mail.recipients'));
        $this->assertSame('Europe/Zurich', $settings->getString(key: 'defaultTimeZone'));
    }

    public function testHasTellsIfAKeyExists(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues(['mailer.password' => '']));

        $this->assertTrue($settings->has(key: 'mailer.password'));
        $this->assertFalse($settings->has(key: 'mailer.username'));
    }

    public function testMissingOwnKeyThrows(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues());

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The environment settings miss "db.hostname" (string).');
        $settings->getString(key: 'db.hostname');
    }

    public function testOwnKeyWithWrongTypeThrows(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues(['mailer.port' => '1025']));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The environment setting "mailer.port" must be int, string given.');
        $settings->getInt(key: 'mailer.port');
    }

    public function testOwnBoolWithWrongTypeThrows(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues(['flag' => 1]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The environment setting "flag" must be bool, int given.');
        $settings->getBool(key: 'flag');
    }

    public function testOwnListWithWrongTypeThrows(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues(['list' => 'a']));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The environment setting "list" must be list of strings, string given.');
        $settings->getStringList(key: 'list');
    }

    public function testEmptyMailRecipientAndNoDomainsAreValid(): void
    {
        $settings = EnvironmentSettings::fromArray(
            values: $this->createValues(['logEmailRecipient' => '', 'allowedDomains' => []]),
        );

        $this->assertSame('', $settings->logEmailRecipient);
        $this->assertSame([], $settings->allowedDomains);
    }

    public function testErrorReportingDefaultsToAll(): void
    {
        $values = $this->createValues();
        unset($values['defaultErrorReporting']);

        $this->assertSame(E_ALL, EnvironmentSettings::fromArray(values: $values)->errorReporting);
    }

    public function testErrorReportingIsReadFromTheArray(): void
    {
        $settings = EnvironmentSettings::fromArray(
            values: $this->createValues(['defaultErrorReporting' => E_ALL & ~E_DEPRECATED]),
        );

        $this->assertSame(E_ALL & ~E_DEPRECATED, $settings->errorReporting);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingKeyProvider(): iterable
    {
        yield 'time zone' => ['defaultTimeZone', 'string'];
        yield 'domains' => ['allowedDomains', 'list of strings'];
        yield 'mail recipient' => ['logEmailRecipient', 'string'];
        yield 'debug' => ['debug', 'bool'];
        yield 'robots' => ['robots', 'string'];
    }

    #[DataProvider('missingKeyProvider')]
    public function testMissingSettingThrows(string $key, string $type): void
    {
        $values = $this->createValues();
        unset($values[$key]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The environment settings miss "' . $key . '" (' . $type . ').');
        EnvironmentSettings::fromArray(values: $values);
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'error reporting string' => [
            'defaultErrorReporting',
            'E_ALL',
            'The environment setting "defaultErrorReporting" must be int, string given.',
        ];
        yield 'debug as string' => [
            'debug',
            '1',
            'The environment setting "debug" must be bool, string given.',
        ];
        yield 'robots null' => [
            'robots',
            null,
            'The environment setting "robots" must be string, null given.',
        ];
        yield 'domains string' => [
            'allowedDomains',
            'example.com',
            'The environment setting "allowedDomains" must be list of strings, string given.',
        ];
        yield 'domains with integer' => [
            'allowedDomains',
            ['example.com', 5],
            'The environment setting "allowedDomains" must be list of strings, list with int given.',
        ];
        yield 'domains with keys' => [
            'allowedDomains',
            ['main' => 'example.com'],
            'The environment setting "allowedDomains" must be list of strings, array given.',
        ];
        yield 'unknown time zone' => [
            'defaultTimeZone',
            'Mars/Olympus',
            'The environment setting "defaultTimeZone" is not a time zone: "Mars/Olympus".',
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testInvalidSettingThrows(string $key, mixed $value, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs($message);
        EnvironmentSettings::fromArray(values: $this->createValues([$key => $value]));
    }

    public function testTemplateChangesAreCheckedByDefaultOnlyWithDebug(): void
    {
        $withDebug = EnvironmentSettings::fromArray(values: $this->createValues(['debug' => true]));
        $withoutDebug = EnvironmentSettings::fromArray(values: $this->createValues(['debug' => false]));

        $this->assertTrue($withDebug->checkTemplateChanges);
        $this->assertFalse($withoutDebug->checkTemplateChanges);
    }

    public function testCheckingTemplateChangesCanBeTurnedOnWithoutDebug(): void
    {
        $settings = EnvironmentSettings::fromArray(
            values: $this->createValues(['debug' => false, 'checkTemplateChanges' => true]),
        );

        $this->assertTrue($settings->checkTemplateChanges);
    }

    public function testCheckingTemplateChangesCanBeTurnedOff(): void
    {
        $settings = EnvironmentSettings::fromArray(values: $this->createValues(['checkTemplateChanges' => false]));

        $this->assertFalse($settings->checkTemplateChanges);
    }

    public function testCheckTemplateChangesWithWrongTypeThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);

        EnvironmentSettings::fromArray(values: $this->createValues(['checkTemplateChanges' => 'no']));
    }
}
