<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

/**
 * Adapted from libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of libphonenumber
 * (https://github.com/google/libphonenumber), see LICENSE and NOTICE in src/phone/. Changed by Actra AG: reduced to
 * parsing, formatting and validation, rewritten to PHP 8.5 and the Actra coding standard (see NOTICE).
 *
 * @author Joshua Gigg and contributors (libphonenumber-for-php)
 * @author The Libphonenumber Authors (libphonenumber)
 */

namespace actra\yuf\phone;

use UnexpectedValueException;

/**
 * Narrows the array of a generated metadata file in `data/` once, at the boundary, into typed value objects.
 *
 * @internal
 */
final readonly class PhoneMetaDataLoader
{
    /**
     * @param string $source name of the metadata (for the error messages)
     */
    public function __construct(private string $source) {}

    /**
     * @param array<array-key, mixed> $data
     * @throws UnexpectedValueException if the data has not the expected structure
     */
    public function load(array $data): PhoneMetaData
    {
        return new PhoneMetaData(
            countryCode: $this->requireInt(data: $data, key: 'countryCode'),
            internationalPrefix: $this->requireString(data: $data, key: 'internationalPrefix'),
            generalDesc: $this->loadDesc(data: $this->requireArray(data: $data, key: 'generalDesc')),
            leadingDigits: $this->findString(data: $data, key: 'leadingDigits'),
            sameMobileAndFixedLinePattern: $this->requireBool(data: $data, key: 'sameMobileAndFixedLinePattern'),
            fixedLine: $this->loadDesc(data: $this->requireArray(data: $data, key: 'fixedLine')),
            mobile: $this->loadDesc(data: $this->requireArray(data: $data, key: 'mobile')),
            tollFree: $this->loadDesc(data: $this->requireArray(data: $data, key: 'tollFree')),
            premiumRate: $this->loadDesc(data: $this->requireArray(data: $data, key: 'premiumRate')),
            sharedCost: $this->loadDesc(data: $this->requireArray(data: $data, key: 'sharedCost')),
            voip: $this->loadDesc(data: $this->requireArray(data: $data, key: 'voip')),
            personalNumber: $this->loadDesc(data: $this->requireArray(data: $data, key: 'personalNumber')),
            pager: $this->loadDesc(data: $this->requireArray(data: $data, key: 'pager')),
            uan: $this->loadDesc(data: $this->requireArray(data: $data, key: 'uan')),
            voicemail: $this->loadDesc(data: $this->requireArray(data: $data, key: 'voicemail')),
            nationalPrefixForParsing: $this->findString(data: $data, key: 'nationalPrefixForParsing'),
            nationalPrefixTransformRule: $this->findString(data: $data, key: 'nationalPrefixTransformRule'),
            preferredExtnPrefix: $this->findString(data: $data, key: 'preferredExtnPrefix'),
            intlNumberFormats: $this->loadFormats(data: $data, key: 'intlNumberFormat'),
            numberFormats: $this->loadFormats(data: $data, key: 'numberFormat'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function loadDesc(array $data): PhoneDesc
    {
        // The pattern is missing or empty for a group without numbers
        $pattern = $this->findString(data: $data, key: 'NationalNumberPattern');

        return new PhoneDesc(
            nationalNumberPattern: $pattern === null || trim(string: $pattern) === '' ? '' : $pattern,
            possibleLength: $this->requireIntList(data: $data, key: 'PossibleLength'),
            possibleLengthLocalOnly: $this->requireIntList(data: $data, key: 'PossibleLengthLocalOnly'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<PhoneFormat>
     */
    private function loadFormats(array $data, string $key): array
    {
        $formats = [];
        foreach ($this->requireArray(data: $data, key: $key) as $formatData) {
            if (!is_array(value: $formatData)) {
                throw $this->createException(key: $key, expected: 'a list of formats');
            }
            $formats[] = new PhoneFormat(
                pattern: $this->requireString(data: $formatData, key: 'pattern'),
                format: $this->requireString(data: $formatData, key: 'format'),
                leadingDigitsPatterns: $this->requireStringList(data: $formatData, key: 'leadingDigitsPatterns'),
                nationalPrefixFormattingRule: $this->findString(
                    data: $formatData,
                    key: 'nationalPrefixFormattingRule',
                ) ?? '',
            );
        }

        return $formats;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function requireInt(array $data, string $key): int
    {
        $value = $this->requireValue(data: $data, key: $key);
        if (!is_int(value: $value)) {
            throw $this->createException(key: $key, expected: 'an integer');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function requireBool(array $data, string $key): bool
    {
        $value = $this->requireValue(data: $data, key: $key);
        if (!is_bool(value: $value)) {
            throw $this->createException(key: $key, expected: 'a boolean');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $this->requireValue(data: $data, key: $key);
        if (!is_string(value: $value)) {
            throw $this->createException(key: $key, expected: 'a string');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function findString(array $data, string $key): ?string
    {
        if (!array_key_exists(key: $key, array: $data) || $data[$key] === null) {
            return null;
        }
        if (!is_string(value: $data[$key])) {
            throw $this->createException(key: $key, expected: 'a string or null');
        }

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function requireArray(array $data, string $key): array
    {
        $value = $this->requireValue(data: $data, key: $key);
        if (!is_array(value: $value)) {
            throw $this->createException(key: $key, expected: 'an array');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<int>
     */
    private function requireIntList(array $data, string $key): array
    {
        $list = [];
        foreach ($this->requireArray(data: $data, key: $key) as $value) {
            if (!is_int(value: $value)) {
                throw $this->createException(key: $key, expected: 'a list of integers');
            }
            $list[] = $value;
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    private function requireStringList(array $data, string $key): array
    {
        $list = [];
        foreach ($this->requireArray(data: $data, key: $key) as $value) {
            if (!is_string(value: $value)) {
                throw $this->createException(key: $key, expected: 'a list of strings');
            }
            $list[] = $value;
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function requireValue(array $data, string $key): mixed
    {
        if (!array_key_exists(key: $key, array: $data)) {
            throw $this->createException(key: $key, expected: 'present');
        }

        return $data[$key];
    }

    private function createException(string $key, string $expected): UnexpectedValueException
    {
        return new UnexpectedValueException(
            message: 'Invalid phone number metadata of ' . $this->source . ': "' . $key . '" must be '
                . $expected . '.',
        );
    }
}
