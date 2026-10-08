<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneRegionCountryCodeMap;
use UnexpectedValueException;

/**
 * Reads the example numbers (`ExampleNumber`) of the generated phone number metadata, so the tests do not depend on
 * invented numbers.
 */
final class PhoneExampleNumbers
{
    private const string DATA_DIRECTORY = __DIR__ . '/../../../src/phone/data/';

    /**
     * @return list<string>
     */
    public static function regions(): array
    {
        return PhoneRegionCountryCodeMap::getSupportedRegions();
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFile(string $name): array
    {
        $data = include PhoneExampleNumbers::DATA_DIRECTORY . 'PhoneNumberMetadata_' . $name . '.php';
        if (!is_array(value: $data)) {
            throw new UnexpectedValueException(message: 'Invalid phone number metadata of ' . $name);
        }
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    public static function countryCode(string $region): int
    {
        $data = PhoneExampleNumbers::loadFile(name: $region);
        $countryCode = array_key_exists(key: 'countryCode', array: $data) ? $data['countryCode'] : null;
        if (!is_int(value: $countryCode)) {
            throw new UnexpectedValueException(message: 'Invalid country code of ' . $region);
        }

        return $countryCode;
    }

    /**
     * The example number of one number type (e.g. `fixedLine`, `mobile`) of a region or of a country calling code
     * without region (e.g. `800`), or null.
     */
    public static function find(string $regionOrCallingCode, string $type): ?string
    {
        $data = PhoneExampleNumbers::loadFile(name: $regionOrCallingCode);
        $desc = array_key_exists(key: $type, array: $data) ? $data[$type] : null;
        if (!is_array(value: $desc) || !array_key_exists(key: 'ExampleNumber', array: $desc)) {
            return null;
        }
        $example = $desc['ExampleNumber'];
        if (!is_string(value: $example)) {
            throw new UnexpectedValueException(message: 'Invalid example number of ' . $regionOrCallingCode);
        }

        return $example;
    }

    public static function get(string $regionOrCallingCode, string $type): string
    {
        $example = PhoneExampleNumbers::find(regionOrCallingCode: $regionOrCallingCode, type: $type);
        if ($example === null) {
            throw new UnexpectedValueException(
                message: 'No example number of type ' . $type . ' for ' . $regionOrCallingCode,
            );
        }

        return $example;
    }

    /**
     * Every example number of every number type of a region.
     *
     * @return array<string, string> number type => example number
     */
    public static function listForRegion(string $region): array
    {
        $examples = [];
        foreach (array_keys(array: PhoneExampleNumbers::loadFile(name: $region)) as $type) {
            $example = PhoneExampleNumbers::find(regionOrCallingCode: $region, type: $type);
            if ($example !== null) {
                $examples[$type] = $example;
            }
        }

        return $examples;
    }
}
