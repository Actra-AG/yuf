<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Checks whether a text or a number can be a phone number (`isPossibleNumber()`), whether it is a valid number and of
 * which type. The public entry point for consumers is `PhoneNumber::createFromString()`, which throws for a number
 * that is not possible, and `PhoneNumber::isValid()`, `getType()` and `isValidForType()`.
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

    /**
     * Whether the number is a valid number of its region: it matches the numbers of one of the number types of the
     * metadata, not only the possible lengths. A number of a region that shares its country calling code with others
     * (e.g. +1) is checked against the region it belongs to.
     */
    public function isValidNumber(PhoneNumber $phoneNumber): bool
    {
        return $this->getNumberType(phoneNumber: $phoneNumber) !== null;
    }

    /**
     * The type of a valid number, null for a number that is not valid. `FIXED_LINE_OR_MOBILE` if the number matches
     * the fixed line and the mobile numbers of its region.
     */
    public function getNumberType(PhoneNumber $phoneNumber): ?PhoneNumberTypeEnum
    {
        $regionCode = $this->findRegionCode(phoneNumber: $phoneNumber);
        if ($regionCode === null) {
            return null;
        }
        $phoneMetaData = $this->metaDataRepository->getForRegionOrCallingCode(
            countryCallingCode: $phoneNumber->countryCode,
            regionCode: $regionCode,
        );
        if ($phoneMetaData === null) {
            return null;
        }

        return PhoneValidator::findNumberType(
            nationalSignificantNumber: $phoneNumber->getNationalSignificantNumber(),
            phoneMetaData: $phoneMetaData,
        );
    }

    /**
     * Whether the number is valid and of the given type. A number of type `FIXED_LINE_OR_MOBILE` is of the types
     * `FIXED_LINE`, `MOBILE` and `FIXED_LINE_OR_MOBILE`.
     */
    public function isValidNumberOfType(PhoneNumber $phoneNumber, PhoneNumberTypeEnum $numberType): bool
    {
        $actualType = $this->getNumberType(phoneNumber: $phoneNumber);
        if ($actualType === null) {
            return false;
        }

        return $actualType === $numberType
            || (
                $actualType === PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE
                && ($numberType === PhoneNumberTypeEnum::FIXED_LINE || $numberType === PhoneNumberTypeEnum::MOBILE)
            );
    }

    /**
     * The region of a number: the only region of its country calling code, or for a shared country calling code the
     * first region whose leading digits or number types match the number. Null if there is none.
     */
    private function findRegionCode(PhoneNumber $phoneNumber): ?string
    {
        $regionCodes = PhoneRegionCountryCodeMap::getRegionCodesForCountryCode(
            countryCallingCode: $phoneNumber->countryCode,
        );
        if (count(value: $regionCodes) <= 1) {
            return array_first(array: $regionCodes);
        }
        $nationalSignificantNumber = $phoneNumber->getNationalSignificantNumber();
        foreach ($regionCodes as $regionCode) {
            $phoneMetaData = $this->metaDataRepository->getForRegion(regionCode: $regionCode);
            if ($phoneMetaData === null) {
                continue;
            }
            if ($phoneMetaData->leadingDigits !== null) {
                if (
                    new PhoneMatcher(
                        pattern: $phoneMetaData->leadingDigits,
                        subject: $nationalSignificantNumber,
                    )->lookingAt()
                ) {
                    return $regionCode;
                }
            } elseif (
                PhoneValidator::findNumberType(
                    nationalSignificantNumber: $nationalSignificantNumber,
                    phoneMetaData: $phoneMetaData,
                ) !== null
            ) {
                return $regionCode;
            }
        }

        return null;
    }

    private static function findNumberType(
        string $nationalSignificantNumber,
        PhoneMetaData $phoneMetaData,
    ): ?PhoneNumberTypeEnum {
        if (!PhoneValidator::matchesDesc(number: $nationalSignificantNumber, desc: $phoneMetaData->generalDesc)) {
            return null;
        }
        $orderedDescs = [
            [PhoneNumberTypeEnum::PREMIUM_RATE, $phoneMetaData->premiumRate],
            [PhoneNumberTypeEnum::TOLL_FREE, $phoneMetaData->tollFree],
            [PhoneNumberTypeEnum::SHARED_COST, $phoneMetaData->sharedCost],
            [PhoneNumberTypeEnum::VOIP, $phoneMetaData->voip],
            [PhoneNumberTypeEnum::PERSONAL_NUMBER, $phoneMetaData->personalNumber],
            [PhoneNumberTypeEnum::PAGER, $phoneMetaData->pager],
            [PhoneNumberTypeEnum::UAN, $phoneMetaData->uan],
            [PhoneNumberTypeEnum::VOICEMAIL, $phoneMetaData->voicemail],
        ];
        foreach ($orderedDescs as [$numberType, $desc]) {
            if (PhoneValidator::matchesDesc(number: $nationalSignificantNumber, desc: $desc)) {
                return $numberType;
            }
        }
        $isMobile = PhoneValidator::matchesDesc(number: $nationalSignificantNumber, desc: $phoneMetaData->mobile);
        if (PhoneValidator::matchesDesc(number: $nationalSignificantNumber, desc: $phoneMetaData->fixedLine)) {
            return $phoneMetaData->sameMobileAndFixedLinePattern || $isMobile
                ? PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE
                : PhoneNumberTypeEnum::FIXED_LINE;
        }
        if (!$phoneMetaData->sameMobileAndFixedLinePattern && $isMobile) {
            return PhoneNumberTypeEnum::MOBILE;
        }

        return null;
    }

    private static function matchesDesc(string $number, PhoneDesc $desc): bool
    {
        if (
            $desc->possibleLength !== []
            && !in_array(needle: mb_strlen(string: $number), haystack: $desc->possibleLength, strict: true)
        ) {
            return false;
        }

        return $desc->nationalNumberPattern !== ''
            && new PhoneMatcher(pattern: $desc->nationalNumberPattern, subject: $number)->matchesCompletely();
    }
}
