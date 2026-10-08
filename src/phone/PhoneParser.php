<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Parses a text to a `PhoneNumber`. It does not check whether the length of the number is possible for its region
 * (see `PhoneValidator`); `PhoneNumber::createFromString()` does both.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final readonly class PhoneParser
{
    public function __construct(private PhoneMetaDataRepository $metaDataRepository) {}

    /**
     * @throws PhoneParseException
     */
    public function parse(string $numberToParse, ?string $defaultCountryCode): PhoneNumber
    {
        $numberToParse = trim(string: $numberToParse);
        if ($numberToParse === '') {
            throw new PhoneParseException(message: 'The string is empty.', error: PhoneParseErrorEnum::EMPTY_STRING);
        }
        if (str_starts_with(haystack: $numberToParse, needle: PhoneConstants::DOUBLE_ZERO)) {
            $numberToParse = PhoneConstants::PLUS_SIGN . substr(string: $numberToParse, offset: 2);
        }
        if (str_starts_with(haystack: $numberToParse, needle: PhoneConstants::PLUS_SIGN)) {
            $defaultCountryCode = null;
        }
        if (mb_strlen(string: $numberToParse) > PhoneConstants::MAX_INPUT_STRING_LENGTH) {
            throw new PhoneParseException(
                message: 'The string supplied was too long to parse.',
                error: PhoneParseErrorEnum::TOO_LONG,
            );
        }
        $nationalNumber = $this->buildNationalNumberForParsing(numberToParse: $numberToParse);
        $this->assertViableNumberAndRegion(nationalNumber: $nationalNumber, defaultRegion: $defaultCountryCode);

        // Attempt to parse the extension first, since it does not require region-specific data
        ['number' => $nationalNumber, 'extension' => $extension] = $this->maybeStripExtension(number: $nationalNumber);

        $regionMetaData = $this->metaDataRepository->getForRegion(regionCode: $defaultCountryCode);
        $extraction = $this->extractCountryCodeOrThrow(
            nationalNumber: $nationalNumber,
            regionMetaData: $regionMetaData,
        );
        $countryCode = $extraction['countryCode'];
        $normalizedNationalNumber = $extraction['nationalNumber'];
        if ($countryCode !== 0) {
            $regionMetaData = $this->findMetaDataOfExtractedCountryCode(
                countryCode: $countryCode,
                defaultRegion: $defaultCountryCode,
                defaultRegionMetaData: $regionMetaData,
            );
        } else {
            // No country calling code in the number: the national number is the normalized number, the country
            // calling code is the one of the default region.
            $normalizedNationalNumber .= PhoneNumberNormalizer::normalize(number: $nationalNumber);
            if ($defaultCountryCode !== null && $regionMetaData !== null) {
                $countryCode = $regionMetaData->countryCode;
            }
        }
        $normalizedNationalNumber = $this->stripNationalPrefixIfLengthStaysPossible(
            normalizedNationalNumber: $normalizedNationalNumber,
            regionMetaData: $regionMetaData,
        );

        return $this->createPhoneNumber(
            extension: $extension,
            countryCode: $countryCode,
            normalizedNationalNumber: $normalizedNationalNumber,
        );
    }

    /**
     * @throws PhoneParseException
     */
    private function assertViableNumberAndRegion(string $nationalNumber, ?string $defaultRegion): void
    {
        if (!PhoneValidator::isViablePhoneNumber(number: $nationalNumber)) {
            throw new PhoneParseException(
                message: 'The string supplied did not seem to be a phone number.',
                error: PhoneParseErrorEnum::NOT_A_NUMBER,
            );
        }
        // The default region has to be valid, or the number has to start with a plus sign
        if (!$this->checkRegionForParsing(numberToParse: $nationalNumber, defaultRegion: $defaultRegion)) {
            throw new PhoneParseException(
                message: 'Missing or invalid default region.',
                error: PhoneParseErrorEnum::INVALID_COUNTRY_CODE,
            );
        }
    }

    /**
     * Extracts the country calling code. If it is invalid, the number is tried once more without its plus sign to
     * choose the message.
     *
     * @return array{countryCode: int, nationalNumber: string} country calling code 0: not in the number
     * @throws PhoneParseException
     */
    private function extractCountryCodeOrThrow(string $nationalNumber, ?PhoneMetaData $regionMetaData): array
    {
        try {
            return $this->maybeExtractCountryCode(fullNumber: $nationalNumber, defaultRegionMetaData: $regionMetaData);
        } catch (PhoneParseException $phoneParseException) {
            if ($phoneParseException->error !== PhoneParseErrorEnum::INVALID_COUNTRY_CODE) {
                throw $phoneParseException;
            }
            $matcher = new PhoneMatcher(pattern: PhonePatterns::PLUS_CHARS_PATTERN, subject: $nationalNumber);
            if ($matcher->lookingAt()) {
                $retry = $this->maybeExtractCountryCode(
                    fullNumber: mb_substr(string: $nationalNumber, start: (int) $matcher->end()),
                    defaultRegionMetaData: $regionMetaData,
                );
                if ($retry['countryCode'] === 0) {
                    throw new PhoneParseException(
                        message: 'Could not interpret numbers after plus-sign.',
                        error: PhoneParseErrorEnum::INVALID_COUNTRY_CODE,
                    );
                }
            }
            throw $phoneParseException;
        }
    }

    private function findMetaDataOfExtractedCountryCode(
        int $countryCode,
        ?string $defaultRegion,
        ?PhoneMetaData $defaultRegionMetaData,
    ): ?PhoneMetaData {
        $phoneNumberRegion = PhoneRegionCountryCodeMap::getRegionCodeForCountryCode(countryCallingCode: $countryCode);
        if ($phoneNumberRegion === $defaultRegion) {
            return $defaultRegionMetaData;
        }

        return $this->metaDataRepository->getForRegionOrCallingCode(
            countryCallingCode: $countryCode,
            regionCode: $phoneNumberRegion,
        );
    }

    /**
     * The national prefix and carrier code are removed only if the number stays long enough to be possible for the
     * region. Otherwise the original could be a valid short number.
     *
     * @throws PhoneParseException
     */
    private function stripNationalPrefixIfLengthStaysPossible(
        string $normalizedNationalNumber,
        ?PhoneMetaData $regionMetaData,
    ): string {
        if (mb_strlen(string: $normalizedNationalNumber) < PhoneConstants::MIN_LENGTH_FOR_NSN) {
            throw new PhoneParseException(
                message: 'The string supplied is too short to be a phone number.',
                error: PhoneParseErrorEnum::TOO_SHORT_NSN,
            );
        }
        if ($regionMetaData !== null) {
            $potentialNationalNumber = PhoneParser::stripNationalPrefix(
                number: $normalizedNationalNumber,
                phoneMetaData: $regionMetaData,
            );
            $validationResult = PhoneValidator::testNumberLength(
                number: $potentialNationalNumber,
                phoneMetaData: $regionMetaData,
            );
            if (!in_array(needle: $validationResult, haystack: [
                PhoneLengthResultEnum::TOO_SHORT,
                PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY,
                PhoneLengthResultEnum::INVALID_LENGTH,
            ], strict: true)) {
                $normalizedNationalNumber = $potentialNationalNumber;
            }
        }

        return $normalizedNationalNumber;
    }

    /**
     * @throws PhoneParseException
     */
    private function createPhoneNumber(
        string $extension,
        int $countryCode,
        string $normalizedNationalNumber,
    ): PhoneNumber {
        $lengthOfNationalNumber = mb_strlen(string: $normalizedNationalNumber);
        if ($lengthOfNationalNumber < PhoneConstants::MIN_LENGTH_FOR_NSN) {
            throw new PhoneParseException(
                message: 'The string supplied is too short to be a phone number.',
                error: PhoneParseErrorEnum::TOO_SHORT_NSN,
            );
        }
        if ($lengthOfNationalNumber > PhoneConstants::MAX_LENGTH_FOR_NSN) {
            throw new PhoneParseException(
                message: 'The string supplied is too long to be a phone number.',
                error: PhoneParseErrorEnum::TOO_LONG,
            );
        }
        $leadingZeros = $this->detectLeadingZeros(
            countryCode: $countryCode,
            normalizedNationalNumber: $normalizedNationalNumber,
        );
        $nationalNumber = ltrim(string: $normalizedNationalNumber, characters: '0');

        return new PhoneNumber(
            extension: $extension,
            countryCode: $countryCode,
            italianLeadingZero: $leadingZeros['italianLeadingZero'],
            numberOfLeadingZeros: $leadingZeros['numberOfLeadingZeros'],
            nationalNumber: $nationalNumber === '' ? '0' : $nationalNumber,
        );
    }

    /**
     * Leading zeros of the national number that were not stripped as national prefix belong to the number in every
     * country, as in libphonenumber (the example number of Gabon `01441234`, the Italian numbers). A number without
     * leading zero has no Italian leading zero (`null`, "not known", for Italy as before, `false` elsewhere). Note
     * that if the national number is all zeros, the last zero is not counted as a leading zero.
     *
     * @return array{italianLeadingZero: ?bool, numberOfLeadingZeros: int}
     */
    private function detectLeadingZeros(int $countryCode, string $normalizedNationalNumber): array
    {
        $numberOfLeadingZeros = 1;
        $length = strlen(string: $normalizedNationalNumber);
        if ($length <= 1 || !str_starts_with(haystack: $normalizedNationalNumber, needle: '0')) {
            $isItalian = in_array(
                needle: $countryCode,
                haystack: PhoneConstants::ITALIAN_LEADING_ZERO_COUNTRY_CODES,
                strict: true,
            );

            return ['italianLeadingZero' => $isItalian ? null : false, 'numberOfLeadingZeros' => $numberOfLeadingZeros];
        }
        while (
            $numberOfLeadingZeros < ($length - 1)
            && substr(string: $normalizedNationalNumber, offset: $numberOfLeadingZeros, length: 1) === '0'
        ) {
            $numberOfLeadingZeros++;
        }

        return ['italianLeadingZero' => true, 'numberOfLeadingZeros' => $numberOfLeadingZeros];
    }

    private function buildNationalNumberForParsing(string $numberToParse): string
    {
        $indexOfPhoneContext = strpos(haystack: $numberToParse, needle: PhoneConstants::RFC3966_PHONE_CONTEXT);
        if ($indexOfPhoneContext === false) {
            // Extract a possible number from the string passed in (this strips leading characters that could not be
            // the start of a phone number.)
            $nationalNumber = $this->extractPossibleNumber(number: $numberToParse);
        } else {
            $nationalNumber = $this->buildNationalNumberFromRfc3966(
                numberToParse: $numberToParse,
                indexOfPhoneContext: $indexOfPhoneContext,
            );
        }

        // Delete the isdn-sub address and everything after it if it is present. Note extension won't appear at the
        // same time with isdn-sub address according to paragraph 5.3 of the RFC3966 spec. If both phone context and
        // isdn-subaddress are absent but other parameters are present, the parameters are left in the number. This is
        // because we are concerned about deleting content from a potential number string when there is no strong
        // evidence that the number is actually written in RFC3966.
        $indexOfIsdn = strpos(haystack: $nationalNumber, needle: PhoneConstants::RFC3966_ISDN_SUBADDRESS);
        if ($indexOfIsdn !== false && $indexOfIsdn > 0) {
            $nationalNumber = substr(string: $nationalNumber, offset: 0, length: $indexOfIsdn);
        }

        return $nationalNumber;
    }

    private function buildNationalNumberFromRfc3966(string $numberToParse, int $indexOfPhoneContext): string
    {
        $nationalNumber = '';
        $phoneContextStart = $indexOfPhoneContext + strlen(string: PhoneConstants::RFC3966_PHONE_CONTEXT);
        // If the phone context contains a phone number prefix, we need to capture it, whereas domains will be ignored.
        if (
            $phoneContextStart < (strlen(string: $numberToParse) - 1)
            && substr(string: $numberToParse, offset: $phoneContextStart, length: 1) === PhoneConstants::PLUS_SIGN
        ) {
            // Additional parameters might follow the phone context. If so, we will remove them here because the
            // parameters after phone context are not important for parsing the phone number.
            $phoneContextEnd = strpos(haystack: $numberToParse, needle: ';', offset: $phoneContextStart);
            if ($phoneContextEnd !== false && $phoneContextEnd > 0) {
                $nationalNumber .= substr(
                    string: $numberToParse,
                    offset: $phoneContextStart,
                    length: $phoneContextEnd - $phoneContextStart,
                );
            } else {
                $nationalNumber .= substr(string: $numberToParse, offset: $phoneContextStart);
            }
        }

        // Now append everything between the "tel:" prefix and the phone-context. This should include the national
        // number, an optional extension or isdn-subaddress component. Note we also handle the case when "tel:" is
        // missing, as we have seen in some of the phone number inputs. In that case, we append everything from the
        // beginning.
        $indexOfRfc3966Prefix = strpos(haystack: $numberToParse, needle: PhoneConstants::RFC3966_PREFIX);
        $indexOfNationalNumber = $indexOfRfc3966Prefix === false
            ? 0
            : $indexOfRfc3966Prefix + strlen(string: PhoneConstants::RFC3966_PREFIX);

        return $nationalNumber . substr(
            string: $numberToParse,
            offset: $indexOfNationalNumber,
            length: $indexOfPhoneContext - $indexOfNationalNumber,
        );
    }

    private function extractPossibleNumber(string $number): string
    {
        $matches = [];
        $result = preg_match(
            pattern: '/' . PhonePatterns::VALID_START_CHAR_PATTERN . '/ui',
            subject: $number,
            matches: $matches,
            flags: PREG_OFFSET_CAPTURE,
        );
        if ($result !== 1) {
            return '';
        }
        $number = substr(string: $number, offset: $matches[0][1]);

        // Remove trailing non-alpha non-numerical characters. The matcher gives a character offset.
        $trailingCharsMatcher = new PhoneMatcher(pattern: PhonePatterns::UNWANTED_END_CHAR_PATTERN, subject: $number);
        $trailingCharsStart = $trailingCharsMatcher->find() ? $trailingCharsMatcher->start() : null;
        if ($trailingCharsStart !== null && $trailingCharsStart > 0) {
            $number = mb_substr(string: $number, start: 0, length: $trailingCharsStart);
        }

        // Check for extra numbers at the end.
        $result = preg_match(
            pattern: '%' . PhonePatterns::SECOND_NUMBER_START_PATTERN . '%',
            subject: $number,
            matches: $matches,
            flags: PREG_OFFSET_CAPTURE,
        );
        if ($result === 1) {
            $number = substr(string: $number, offset: 0, length: $matches[0][1]);
        }

        return $number;
    }

    private function checkRegionForParsing(string $numberToParse, ?string $defaultRegion): bool
    {
        if (PhoneValidator::isValidRegionCode(regionCode: $defaultRegion)) {
            return true;
        }
        if ($numberToParse === '') {
            return false;
        }

        return preg_match(pattern: '/^' . PhonePatterns::PLUS_CHARS_PATTERN . '/ui', subject: $numberToParse) === 1;
    }

    /**
     * If we find a potential extension, and the number preceding it is a viable number, we assume it is an extension.
     *
     * @return array{number: string, extension: string} the number without the extension
     */
    private function maybeStripExtension(string $number): array
    {
        $matches = [];
        $result = preg_match(
            pattern: PhonePatterns::EXTN_PATTERN,
            subject: $number,
            matches: $matches,
            flags: PREG_OFFSET_CAPTURE,
        );
        $numberWithoutExtension = $result === 1 ? substr(string: $number, offset: 0, length: $matches[0][1]) : '';
        if (!PhoneValidator::isViablePhoneNumber(number: $numberWithoutExtension)) {
            return ['number' => $number, 'extension' => ''];
        }
        // The extension is captured into one of several groups: the first one that captured some digits
        foreach (array_slice(array: $matches, offset: 1) as $group) {
            if ($group[0] !== '') {
                return ['number' => $numberWithoutExtension, 'extension' => $group[0]];
            }
        }

        return ['number' => $number, 'extension' => ''];
    }

    /**
     * @return array{countryCode: int, nationalNumber: string} country calling code 0: the number has none; the
     *      national number is the part after the country calling code
     * @throws PhoneParseException
     */
    private function maybeExtractCountryCode(string $fullNumber, ?PhoneMetaData $defaultRegionMetaData): array
    {
        if ($fullNumber === '') {
            return ['countryCode' => 0, 'nationalNumber' => ''];
        }
        // Without default region, the international prefix is something that will never match
        $stripped = $this->maybeStripInternationalPrefixAndNormalize(
            number: $fullNumber,
            possibleIddPrefix: $defaultRegionMetaData === null
                ? 'NonMatch'
                : $defaultRegionMetaData->internationalPrefix,
        );
        $fullNumber = $stripped['number'];
        if ($stripped['source'] !== PhoneCountryCodeSourceEnum::FROM_DEFAULT_COUNTRY) {
            return $this->extractCountryCodeAfterPrefix(fullNumber: $fullNumber);
        }
        if ($defaultRegionMetaData === null) {
            return ['countryCode' => 0, 'nationalNumber' => ''];
        }

        return $this->extractCountryCodeOfDefaultRegion(
            fullNumber: $fullNumber,
            defaultRegionMetaData: $defaultRegionMetaData,
        );
    }

    /**
     * @return array{countryCode: int, nationalNumber: string}
     * @throws PhoneParseException
     */
    private function extractCountryCodeAfterPrefix(string $fullNumber): array
    {
        if (mb_strlen(string: $fullNumber) <= PhoneConstants::MIN_LENGTH_FOR_NSN) {
            throw new PhoneParseException(
                message: 'Phone number had an IDD, but after this was not long enough to be a viable phone number.',
                error: PhoneParseErrorEnum::TOO_SHORT_AFTER_IDD,
            );
        }
        $extraction = $this->extractCountryCode(fullNumber: $fullNumber);
        if ($extraction['countryCode'] === 0) {
            throw new PhoneParseException(
                message: 'Country calling code supplied was not recognised.',
                error: PhoneParseErrorEnum::INVALID_COUNTRY_CODE,
            );
        }

        return $extraction;
    }

    /**
     * Checks to see if the number starts with the country calling code of the default region. If so, we remove the
     * country calling code, and do some checks on the validity of the number before and after.
     *
     * @return array{countryCode: int, nationalNumber: string}
     */
    private function extractCountryCodeOfDefaultRegion(string $fullNumber, PhoneMetaData $defaultRegionMetaData): array
    {
        $defaultCountryCode = $defaultRegionMetaData->countryCode;
        $defaultCountryCodeString = (string) $defaultCountryCode;
        if (str_starts_with(haystack: $fullNumber, needle: $defaultCountryCodeString)) {
            $potentialNationalNumber = PhoneParser::stripNationalPrefix(
                number: substr(string: $fullNumber, offset: strlen(string: $defaultCountryCodeString)),
                phoneMetaData: $defaultRegionMetaData,
            );
            $generalDesc = $defaultRegionMetaData->generalDesc;
            // If the number was not valid before but is valid now, or if it was too long before, we consider the
            // number with the country calling code stripped to be a better result and keep that instead.
            if (
                (
                    !PhoneParser::matchNationalNumber(number: $fullNumber, numberDesc: $generalDesc)
                    && PhoneParser::matchNationalNumber(number: $potentialNationalNumber, numberDesc: $generalDesc)
                )
                || PhoneValidator::testNumberLength(
                    number: $fullNumber,
                    phoneMetaData: $defaultRegionMetaData,
                ) === PhoneLengthResultEnum::TOO_LONG
            ) {
                return ['countryCode' => $defaultCountryCode, 'nationalNumber' => $potentialNationalNumber];
            }
        }

        return ['countryCode' => 0, 'nationalNumber' => ''];
    }

    /**
     * @return array{source: PhoneCountryCodeSourceEnum, number: string} the normalized number without the
     *      international prefix
     */
    private function maybeStripInternationalPrefixAndNormalize(string $number, string $possibleIddPrefix): array
    {
        $matches = [];
        $result = preg_match(
            pattern: '/^' . PhonePatterns::PLUS_CHARS_PATTERN . '/' . PhoneConstants::REGEX_FLAGS,
            subject: $number,
            matches: $matches,
            flags: PREG_OFFSET_CAPTURE,
        );
        if ($result === 1) {
            $withoutPlus = mb_substr(string: $number, start: $matches[0][1] + mb_strlen(string: $matches[0][0]));

            return [
                'source' => PhoneCountryCodeSourceEnum::FROM_NUMBER_WITH_PLUS_SIGN,
                'number' => PhoneNumberNormalizer::normalize(number: $withoutPlus),
            ];
        }
        // Attempt to parse the first digits as an international prefix.
        $number = PhoneNumberNormalizer::normalize(number: $number);
        $withoutIdd = $this->stripInternationalDialingPrefix(iddPattern: $possibleIddPrefix, number: $number);
        if ($withoutIdd === null) {
            return ['source' => PhoneCountryCodeSourceEnum::FROM_DEFAULT_COUNTRY, 'number' => $number];
        }

        return ['source' => PhoneCountryCodeSourceEnum::FROM_NUMBER_WITH_IDD, 'number' => $withoutIdd];
    }

    /**
     * @return ?string the number without the prefix, or null if the number does not start with the prefix (or the
     *      digit after the prefix is a zero, which no country calling code starts with)
     */
    private function stripInternationalDialingPrefix(string $iddPattern, string $number): ?string
    {
        $matcher = new PhoneMatcher(pattern: $iddPattern, subject: $number);
        if (!$matcher->lookingAt()) {
            return null;
        }
        $matchEnd = (int) $matcher->end();
        $digitMatcher = new PhoneMatcher(
            pattern: PhonePatterns::CAPTURING_DIGIT_PATTERN,
            subject: substr(string: $number, offset: $matchEnd),
        );
        if ($digitMatcher->find()) {
            $normalizedGroup = PhoneNumberNormalizer::normalizeDigits(number: (string) $digitMatcher->group(group: 1));
            if ($normalizedGroup === '0') {
                return null;
            }
        }

        return substr(string: $number, offset: $matchEnd);
    }

    /**
     * @return array{countryCode: int, nationalNumber: string} country calling code 0: none found
     */
    private function extractCountryCode(string $fullNumber): array
    {
        $numberLength = mb_strlen(string: $fullNumber);
        // Country codes do not begin with a '0'.
        if ($numberLength === 0 || $fullNumber[0] === '0') {
            return ['countryCode' => 0, 'nationalNumber' => ''];
        }
        for ($i = 1; $i <= PhoneConstants::MAX_LENGTH_COUNTRY_CODE && $i <= $numberLength; $i++) {
            $potentialCountryCode = (int) substr(string: $fullNumber, offset: 0, length: $i);
            if (PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $potentialCountryCode)) {
                return [
                    'countryCode' => $potentialCountryCode,
                    'nationalNumber' => substr(string: $fullNumber, offset: $i),
                ];
            }
        }

        return ['countryCode' => 0, 'nationalNumber' => ''];
    }

    /**
     * Strips the national prefix (and the carrier code) of the region, applying the transform rule of the region if it
     * has one. The number stays as it is if it would not be viable afterwards.
     */
    public static function stripNationalPrefix(string $number, PhoneMetaData $phoneMetaData): string
    {
        $possibleNationalPrefix = $phoneMetaData->nationalPrefixForParsing;
        if ($number === '' || $possibleNationalPrefix === null || $possibleNationalPrefix === '') {
            return $number;
        }
        // Attempt to parse the first digits as a national prefix.
        $prefixMatcher = new PhoneMatcher(pattern: $possibleNationalPrefix, subject: $number);
        if (!$prefixMatcher->lookingAt()) {
            return $number;
        }
        $generalDesc = $phoneMetaData->generalDesc;
        $isViableOriginalNumber = PhoneParser::matchNationalNumber(number: $number, numberDesc: $generalDesc);
        // Nothing captured by the capturing groups of the prefix means that no transformation is necessary, and we
        // just remove the national prefix.
        $numOfGroups = (int) $prefixMatcher->groupCount();
        $transformRule = $phoneMetaData->nationalPrefixTransformRule;
        if (
            $transformRule === null
            || $transformRule === ''
            || $prefixMatcher->group(group: $numOfGroups - 1) === null
        ) {
            $withoutPrefix = substr(string: $number, offset: (int) $prefixMatcher->end());
            // If the original number was viable, and the resultant number is not, we keep the original.
            if (
                $isViableOriginalNumber
                && !PhoneParser::matchNationalNumber(number: $withoutPrefix, numberDesc: $generalDesc)
            ) {
                return $number;
            }

            return $withoutPrefix;
        }
        // Check that the resultant number is still viable by making the transformation first.
        $transformedNumber = $prefixMatcher->replaceFirst(replacement: $transformRule);
        if (
            $isViableOriginalNumber
            && !PhoneParser::matchNationalNumber(number: $transformedNumber, numberDesc: $generalDesc)
        ) {
            return $number;
        }

        return $transformedNumber;
    }

    private static function matchNationalNumber(string $number, PhoneDesc $numberDesc): bool
    {
        if ($numberDesc->nationalNumberPattern === '') {
            return false;
        }

        return new PhoneMatcher(pattern: $numberDesc->nationalNumberPattern, subject: $number)->matches();
    }
}
