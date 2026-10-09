<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

use LogicException;

/**
 * Renders a `PhoneNumber` in the internal format (`+41.446681800`, for storing), in the E.164 format
 * (`+41446681800`, for interfaces), in the international format (`+41 44 668 18 00`) or in the national format
 * (`044 668 18 00`, for displaying).
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
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
     * Plus sign, country calling code and the national significant number, without separators and without extension
     * (E.164: `+41446681800`).
     */
    public static function renderE164Format(PhoneNumber $phoneNumber): string
    {
        return PhoneConstants::PLUS_SIGN . $phoneNumber->countryCode . $phoneNumber->getNationalSignificantNumber();
    }

    /**
     * Plus sign, country calling code, the national number grouped as it is usual in the country, and the extension.
     * A number with an unknown country calling code is rendered as its national significant number.
     */
    public static function renderInternationalFormat(PhoneNumber $phoneNumber): string
    {
        $phoneMetaData = PhoneRenderer::findMetaData(phoneNumber: $phoneNumber);
        if ($phoneMetaData === null) {
            return $phoneNumber->getNationalSignificantNumber();
        }
        // When the international formats exist, we use them instead of the national formats.
        $availableFormats = $phoneMetaData->intlNumberFormats === []
            ? $phoneMetaData->numberFormats
            : $phoneMetaData->intlNumberFormats;
        $formattedNumber = PhoneRenderer::formatNsn(
            nationalSignificantNumber: $phoneNumber->getNationalSignificantNumber(),
            availableFormats: $availableFormats,
            withNationalPrefix: false,
        );

        return PhoneConstants::PLUS_SIGN . $phoneNumber->countryCode . ' ' . $formattedNumber
            . PhoneRenderer::renderExtension(phoneNumber: $phoneNumber, phoneMetaData: $phoneMetaData);
    }

    /**
     * The national number grouped as it is usual in the country, with the national prefix as the country writes it
     * (`044 668 18 00`, `(650) 253-0000`), and the extension. A number with an unknown country calling code is
     * rendered as its national significant number; a number that matches no format of its country without national
     * prefix.
     */
    public static function renderNationalFormat(PhoneNumber $phoneNumber): string
    {
        $phoneMetaData = PhoneRenderer::findMetaData(phoneNumber: $phoneNumber);
        if ($phoneMetaData === null) {
            return $phoneNumber->getNationalSignificantNumber();
        }

        return PhoneRenderer::formatNsn(
            nationalSignificantNumber: $phoneNumber->getNationalSignificantNumber(),
            availableFormats: $phoneMetaData->numberFormats,
            withNationalPrefix: true,
        ) . PhoneRenderer::renderExtension(phoneNumber: $phoneNumber, phoneMetaData: $phoneMetaData);
    }

    /**
     * The formatting information of regions that share a country calling code is contained by only one region for
     * performance reasons. For example, for NANPA regions it is contained in the metadata for US.
     */
    private static function findMetaData(PhoneNumber $phoneNumber): ?PhoneMetaData
    {
        $countryCallingCode = $phoneNumber->countryCode;
        if (!PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $countryCallingCode)) {
            return null;
        }
        $phoneMetaData = PhoneMetaDataRepository::shared()->getForRegionOrCallingCode(
            countryCallingCode: $countryCallingCode,
            regionCode: PhoneRegionCountryCodeMap::getRegionCodeForCountryCode(
                countryCallingCode: $countryCallingCode,
            ),
        );
        if ($phoneMetaData === null) {
            throw new LogicException(
                message: 'No phone number metadata for country calling code ' . $countryCallingCode,
            );
        }

        return $phoneMetaData;
    }

    /**
     * @param list<PhoneFormat> $availableFormats
     */
    private static function formatNsn(
        string $nationalSignificantNumber,
        array $availableFormats,
        bool $withNationalPrefix,
    ): string {
        $formattingPattern = PhoneRenderer::chooseFormattingPatternForNumber(
            availableFormats: $availableFormats,
            nationalNumber: $nationalSignificantNumber,
        );
        if ($formattingPattern === null) {
            return $nationalSignificantNumber;
        }
        $format = $formattingPattern->format;
        if ($withNationalPrefix && $formattingPattern->nationalPrefixFormattingRule !== '') {
            // The rule replaces the first group of the format (`$1` becomes `0$1`). Its `$1` stands for the replaced
            // group, as in libphonenumber (`$2 15-$3-$4` with the rule `0$1` becomes `0$2 15-$3-$4`).
            $format = preg_replace(
                pattern: PhonePatterns::FIRST_GROUP_PATTERN,
                replacement: $formattingPattern->nationalPrefixFormattingRule,
                subject: $format,
                limit: 1,
            ) ?? $format;
        }

        return new PhoneMatcher(
            pattern: $formattingPattern->pattern,
            subject: $nationalSignificantNumber,
        )->replaceAll(replacement: $format);
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
