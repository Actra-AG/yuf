<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

use LogicException;

/**
 * Renders a `PhoneNumber` in the internal format (`+41.446681800`, for storing) or in the international format
 * (`+41 44 668 18 00`, for displaying).
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 */
final class PhoneRenderer
{
    /**
     * Plus sign, country calling code, a dot and the national significant number; without extension.
     */
    public static function renderInternalFormat(PhoneNumber $phoneNumber): string
    {
        return PhoneConstants::PLUS_SIGN . $phoneNumber->countryCode . '.'
            . $phoneNumber->getNationalSignificantNumber();
    }

    /**
     * Plus sign, country calling code, the national number grouped as it is usual in the country, and the extension.
     * A number with an unknown country calling code is rendered as its national significant number.
     */
    public static function renderInternationalFormat(PhoneNumber $phoneNumber): string
    {
        $countryCallingCode = $phoneNumber->countryCode;
        $nationalSignificantNumber = $phoneNumber->getNationalSignificantNumber();
        if (!PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $countryCallingCode)) {
            return $nationalSignificantNumber;
        }

        // The formatting information of regions that share a country calling code is contained by only one region
        // for performance reasons. For example, for NANPA regions it is contained in the metadata for US.
        $regionCode = PhoneRegionCountryCodeMap::getRegionCodeForCountryCode(countryCallingCode: $countryCallingCode);
        $phoneMetaData = new PhoneMetaDataRepository()->getForRegionOrCallingCode(
            countryCallingCode: $countryCallingCode,
            regionCode: $regionCode,
        );
        if ($phoneMetaData === null) {
            throw new LogicException(
                message: 'No phone number metadata for country calling code ' . $countryCallingCode,
            );
        }
        $formattedNumber = PhoneRenderer::formatNsn(
            nationalSignificantNumber: $nationalSignificantNumber,
            phoneMetaData: $phoneMetaData,
        );

        return PhoneConstants::PLUS_SIGN . $countryCallingCode . ' ' . $formattedNumber
            . PhoneRenderer::renderExtension(phoneNumber: $phoneNumber, phoneMetaData: $phoneMetaData);
    }

    private static function formatNsn(string $nationalSignificantNumber, PhoneMetaData $phoneMetaData): string
    {
        // When the international formats exist, we use them instead of the national formats.
        $availableFormats = $phoneMetaData->intlNumberFormats === []
            ? $phoneMetaData->numberFormats
            : $phoneMetaData->intlNumberFormats;
        $formattingPattern = PhoneRenderer::chooseFormattingPatternForNumber(
            availableFormats: $availableFormats,
            nationalNumber: $nationalSignificantNumber,
        );
        if ($formattingPattern === null) {
            return $nationalSignificantNumber;
        }

        return new PhoneMatcher(
            pattern: $formattingPattern->pattern,
            subject: $nationalSignificantNumber,
        )->replaceAll(replacement: $formattingPattern->format);
    }

    /**
     * @param list<PhoneFormat> $availableFormats
     */
    private static function chooseFormattingPatternForNumber(
        array $availableFormats,
        string $nationalNumber,
    ): ?PhoneFormat {
        foreach ($availableFormats as $numFormat) {
            // We always use the last leading digits pattern, as it is the most detailed.
            $leadingDigitsPattern = array_last(array: $numFormat->leadingDigitsPatterns);
            if (
                $leadingDigitsPattern !== null
                && !new PhoneMatcher(pattern: $leadingDigitsPattern, subject: $nationalNumber)->lookingAt()
            ) {
                continue;
            }
            if (new PhoneMatcher(pattern: $numFormat->pattern, subject: $nationalNumber)->matches()) {
                return $numFormat;
            }
        }

        return null;
    }

    private static function renderExtension(PhoneNumber $phoneNumber, PhoneMetaData $phoneMetaData): string
    {
        if ($phoneNumber->extension === '') {
            return '';
        }

        return ($phoneMetaData->preferredExtnPrefix ?? PhoneConstants::DEFAULT_EXTN_PREFIX) . $phoneNumber->extension;
    }
}
