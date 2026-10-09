<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

use UnexpectedValueException;

/**
 * Loads the phone number metadata from the files in `data/`. A repository remembers what it has loaded for its own
 * lifetime. `shared()` is the one repository of the process: the metadata never changes, so it is loaded once per
 * region and request instead of for every parse, validation and formatting. The files are plain PHP arrays, so
 * OPcache holds them.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final class PhoneMetaDataRepository
{
    private static ?PhoneMetaDataRepository $shared = null;
    /** @var array<string, PhoneMetaData> */
    private array $regionMetaData = [];
    /** @var array<int, PhoneMetaData> */
    private array $nonGeographicalMetaData = [];

    public static function shared(): PhoneMetaDataRepository
    {
        return PhoneMetaDataRepository::$shared ??= new PhoneMetaDataRepository();
    }

    public function getForRegionOrCallingCode(int $countryCallingCode, string $regionCode): ?PhoneMetaData
    {
        if ($regionCode !== PhoneRegionCountryCodeMap::NON_GEOGRAPHICAL_REGION) {
            return $this->getForRegion(regionCode: $regionCode);
        }
        if (!PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $countryCallingCode)) {
            return null;
        }
        if (!array_key_exists(key: $countryCallingCode, array: $this->nonGeographicalMetaData)) {
            $this->nonGeographicalMetaData[$countryCallingCode] = $this->load(name: (string) $countryCallingCode);
        }

        return $this->nonGeographicalMetaData[$countryCallingCode];
    }

    public function getForRegion(?string $regionCode): ?PhoneMetaData
    {
        if ($regionCode === null || !PhoneValidator::isValidRegionCode(regionCode: $regionCode)) {
            return null;
        }
        if (!array_key_exists(key: $regionCode, array: $this->regionMetaData)) {
            $this->regionMetaData[$regionCode] = $this->load(name: $regionCode);
        }

        return $this->regionMetaData[$regionCode];
    }

    /**
     * @param string $name a supported region code or a country calling code that exists
     */
    private function load(string $name): PhoneMetaData
    {
        $data = include __DIR__ . '/data/PhoneNumberMetadata_' . $name . '.php';
        if (!is_array(value: $data)) {
            throw new UnexpectedValueException(message: 'Phone number metadata of ' . $name . ' is not an array.');
        }

        return new PhoneMetaDataLoader(source: $name)->load(data: $data);
    }
}
