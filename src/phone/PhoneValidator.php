<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Checks whether a text or a number can be a phone number. The public entry point for consumers is
 * `PhoneNumber::createFromString()`, which throws for a number that is not possible.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final readonly class PhoneValidator
{
    public function __construct(private PhoneMetaDataRepository $metaDataRepository) {}

    /**
     * Checks to see if the string of characters could possibly be a phone number at all. At the moment, checks to see
     * that the string begins with at least 2 digits, ignoring any punctuation commonly found in phone numbers. This
     * method does not require the number to be normalized in advance - but does assume that leading non-number
     * symbols have been removed.
     */
    public static function isViablePhoneNumber(string $number): bool
    {
        if (mb_strlen(string: $number) < PhoneConstants::MIN_LENGTH_FOR_NSN) {
            return false;
        }

        return preg_match(pattern: PhonePatterns::VALID_PHONE_NUMBER_PATTERN, subject: $number) === 1;
    }

    public static function isValidRegionCode(?string $regionCode): bool
    {
        return $regionCode !== null
            && in_array(
                needle: $regionCode,
                haystack: PhoneRegionCountryCodeMap::getSupportedRegions(),
                strict: true,
            );
    }

    public static function testNumberLength(string $number, PhoneMetaData $phoneMetaData): PhoneLengthResultEnum
    {
        $possibleLengths = $phoneMetaData->generalDesc->possibleLength;
        if ($possibleLengths === [] || $possibleLengths[0] === -1) {
            return PhoneLengthResultEnum::INVALID_LENGTH;
        }
        $actualLength = mb_strlen(string: $number);
        if (in_array(
            needle: $actualLength,
            haystack: $phoneMetaData->generalDesc->possibleLengthLocalOnly,
            strict: true,
        )) {
            return PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY;
        }
        if ($possibleLengths[0] === $actualLength) {
            return PhoneLengthResultEnum::IS_POSSIBLE;
        }
        if ($possibleLengths[0] > $actualLength) {
            return PhoneLengthResultEnum::TOO_SHORT;
        }
        if (array_last(array: $possibleLengths) < $actualLength) {
            return PhoneLengthResultEnum::TOO_LONG;
        }

        return in_array(
            needle: $actualLength,
            haystack: $possibleLengths,
            strict: true,
        ) ? PhoneLengthResultEnum::IS_POSSIBLE : PhoneLengthResultEnum::INVALID_LENGTH;
    }

    /**
     * Whether the length of the number fits the possible lengths of its country calling code (also the lengths that
     * are possible within an area only). False for an unknown country calling code.
     */
    public function isPossibleNumber(PhoneNumber $phoneNumber): bool
    {
        $phoneMetaData = $this->metaDataRepository->getForRegionOrCallingCode(
            countryCallingCode: $phoneNumber->countryCode,
            regionCode: PhoneRegionCountryCodeMap::getRegionCodeForCountryCode(
                countryCallingCode: $phoneNumber->countryCode,
            ),
        );
        if ($phoneMetaData === null) {
            return false;
        }
        $result = PhoneValidator::testNumberLength(
            number: $phoneNumber->getNationalSignificantNumber(),
            phoneMetaData: $phoneMetaData,
        );

        return $result === PhoneLengthResultEnum::IS_POSSIBLE
            || $result === PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY;
    }
}
