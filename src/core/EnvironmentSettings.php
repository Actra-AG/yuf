<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use BackedEnum;
use DateTimeZone;
use Exception;
use ReflectionEnum;
use UnexpectedValueException;

/**
 * The settings of the environment file (`.env.php`, returns an array), checked once when `Core` starts:
 * `defaultErrorReporting` (int, optional, default `E_ALL`), `defaultTimeZone` (string), `allowedDomains` (list of
 * host names), `logEmailRecipient` (string, may be empty), `debug` (bool), `robots` (string) and
 * `checkTemplateChanges` (bool, optional, default: the value of `debug`) are typed properties.
 * All keys of the file (also the own keys of a project, used as given, e.g. `mailer.hostname`) are read with
 * `getString()`, `getInt()`, `getBool()`, `getStringList()` and `getEnum()` (a backed enum by its value), which throw
 * an `UnexpectedValueException` for a missing key, a wrong type or an unknown enum value.
 */
final readonly class EnvironmentSettings
{
    /**
     * @param list<string> $allowedDomains
     * @param array<array-key, mixed> $values All keys of the environment file
     * @param bool $checkTemplateChanges Whether a changed template is compiled again; `false` in production saves the
     *     file checks of every render, then the template cache must be cleared on every deployment
     */
    public function __construct(
        public int $errorReporting,
        public string $timeZone,
        public array $allowedDomains,
        public string $logEmailRecipient,
        public bool $debug,
        public string $robots,
        private array $values = [],
        public bool $checkTemplateChanges = true,
    ) {}

    /**
     * @param array<array-key, mixed> $values The array of the environment file
     *
     * @throws UnexpectedValueException if a setting is missing or has the wrong type
     */
    public static function fromArray(array $values): EnvironmentSettings
    {
        return new EnvironmentSettings(
            errorReporting: EnvironmentSettings::readOptionalInteger(
                values: $values,
                key: 'defaultErrorReporting',
                default: E_ALL,
            ),
            timeZone: EnvironmentSettings::readTimeZone(values: $values, key: 'defaultTimeZone'),
            allowedDomains: EnvironmentSettings::readStringList(values: $values, key: 'allowedDomains'),
            logEmailRecipient: EnvironmentSettings::readString(values: $values, key: 'logEmailRecipient'),
            debug: EnvironmentSettings::readBoolean(values: $values, key: 'debug'),
            robots: EnvironmentSettings::readString(values: $values, key: 'robots'),
            values: $values,
            // Fast by default: production (no debug) uses the compiled templates without checking the files
            checkTemplateChanges: array_key_exists(key: 'checkTemplateChanges', array: $values)
                ? EnvironmentSettings::readBoolean(values: $values, key: 'checkTemplateChanges')
                : EnvironmentSettings::readBoolean(values: $values, key: 'debug'),
        );
    }

    public function has(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->values);
    }

    /**
     * @throws UnexpectedValueException if the key is missing or the value is no string
     */
    public function getString(string $key): string
    {
        return EnvironmentSettings::readString(values: $this->values, key: $key);
    }

    /**
     * @throws UnexpectedValueException if the key is missing or the value is no integer
     */
    public function getInt(string $key): int
    {
        return EnvironmentSettings::readInteger(values: $this->values, key: $key);
    }

    /**
     * @throws UnexpectedValueException if the key is missing or the value is no boolean
     */
    public function getBool(string $key): bool
    {
        return EnvironmentSettings::readBoolean(values: $this->values, key: $key);
    }

    /**
     * @return list<string>
     *
     * @throws UnexpectedValueException if the key is missing or the value is no list of strings
     */
    public function getStringList(string $key): array
    {
        return EnvironmentSettings::readStringList(values: $this->values, key: $key);
    }

    /**
     * The case of a backed enum by its value, read like `getString()` or `getInt()` by the backing type of the enum
     * (e.g. `'mailer.encryption' => 'starttls'` for `SmtpEncryptionEnum`).
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T
     *
     * @throws UnexpectedValueException if the key is missing, the value has the wrong type or is no value of the enum
     */
    public function getEnum(string $key, string $enumClass): BackedEnum
    {
        $value = new ReflectionEnum(objectOrClass: $enumClass)->getBackingType()?->getName() === 'int'
            ? EnvironmentSettings::readInteger(values: $this->values, key: $key)
            : EnvironmentSettings::readString(values: $this->values, key: $key);
        $case = $enumClass::tryFrom(value: $value);
        if ($case === null) {
            throw new UnexpectedValueException(
                message: 'The environment setting "' . $key . '" must be one of ' . implode(
                    separator: ', ',
                    array: array_map(
                        callback: static fn(BackedEnum $allowedCase): string => (string) $allowedCase->value,
                        array: $enumClass::cases(),
                    ),
                ) . ', not "' . $value . '".',
            );
        }

        return $case;
    }

    private static function createMissingException(string $key, string $expectedType): UnexpectedValueException
    {
        return new UnexpectedValueException(
            message: 'The environment settings miss "' . $key . '" (' . $expectedType . ').',
        );
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function readInteger(array $values, string $key): int
    {
        if (!array_key_exists(key: $key, array: $values)) {
            throw EnvironmentSettings::createMissingException(key: $key, expectedType: 'int');
        }
        $value = $values[$key];
        if (!is_int(value: $value)) {
            throw EnvironmentSettings::createTypeException(
                key: $key,
                expectedType: 'int',
                actualType: get_debug_type(value: $value),
            );
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function readOptionalInteger(array $values, string $key, int $default): int
    {
        if (!array_key_exists(key: $key, array: $values)) {
            return $default;
        }

        return EnvironmentSettings::readInteger(values: $values, key: $key);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function readString(array $values, string $key): string
    {
        if (!array_key_exists(key: $key, array: $values)) {
            throw EnvironmentSettings::createMissingException(key: $key, expectedType: 'string');
        }
        $value = $values[$key];
        if (!is_string(value: $value)) {
            throw EnvironmentSettings::createTypeException(
                key: $key,
                expectedType: 'string',
                actualType: get_debug_type(value: $value),
            );
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function readBoolean(array $values, string $key): bool
    {
        if (!array_key_exists(key: $key, array: $values)) {
            throw EnvironmentSettings::createMissingException(key: $key, expectedType: 'bool');
        }
        $value = $values[$key];
        if (!is_bool(value: $value)) {
            throw EnvironmentSettings::createTypeException(
                key: $key,
                expectedType: 'bool',
                actualType: get_debug_type(value: $value),
            );
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private static function readStringList(array $values, string $key): array
    {
        if (!array_key_exists(key: $key, array: $values)) {
            throw EnvironmentSettings::createMissingException(key: $key, expectedType: 'list of strings');
        }
        $value = $values[$key];
        if (!is_array(value: $value) || !array_is_list(array: $value)) {
            throw EnvironmentSettings::createTypeException(
                key: $key,
                expectedType: 'list of strings',
                actualType: get_debug_type(value: $value),
            );
        }
        $strings = [];
        foreach ($value as $item) {
            if (!is_string(value: $item)) {
                throw EnvironmentSettings::createTypeException(
                    key: $key,
                    expectedType: 'list of strings',
                    actualType: 'list with ' . get_debug_type(value: $item),
                );
            }
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function readTimeZone(array $values, string $key): string
    {
        $timeZone = EnvironmentSettings::readString(values: $values, key: $key);
        try {
            new DateTimeZone(timezone: $timeZone);
        } catch (Exception $exception) {
            throw new UnexpectedValueException(
                message: 'The environment setting "' . $key . '" is not a time zone: "' . $timeZone . '".',
                previous: $exception,
            );
        }

        return $timeZone;
    }

    private static function createTypeException(
        string $key,
        string $expectedType,
        string $actualType,
    ): UnexpectedValueException {
        return new UnexpectedValueException(
            message: 'The environment setting "' . $key . '" must be ' . $expectedType . ', ' . $actualType
                . ' given.',
        );
    }
}
