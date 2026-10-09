<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * A parsed phone number: the country calling code, the national number and an optional extension. Create it with
 * `createFromString()`, render it with `PhoneRenderer`.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 */
final readonly class PhoneNumber
{
    /**
     * @param ?bool $italianLeadingZero true if the national number had leading zeros that belong to it (only
     *      country calling code 39), null if it is not known
     */
    public function __construct(
        public string $extension,
        public int $countryCode,
        public ?bool $italianLeadingZero,
        public int $numberOfLeadingZeros,
        public string $nationalNumber,
    ) {}

    /**
     * Parses a phone number in national or international notation (`+41 44 668 18 00`, `0041 44 668 18 00`,
     * `044 668 18 00`, `tel:` URIs, with an extension) and checks that its length is possible for its country.
     *
     * @param ?string $defaultCountryCode the region (ISO 3166-1 alpha-2, upper case, e.g. `CH`) of a number without
     *      country calling code
     * @throws PhoneParseException if the text is no possible phone number
     */
    public static function createFromString(string $input, ?string $defaultCountryCode): PhoneNumber
    {
        $metaDataRepository = PhoneMetaDataRepository::shared();
        $phoneNumber = new PhoneParser(metaDataRepository: $metaDataRepository)->parse(
            numberToParse: $input,
            defaultCountryCode: $defaultCountryCode,
        );
        if (!new PhoneValidator(metaDataRepository: $metaDataRepository)->isPossibleNumber(phoneNumber: $phoneNumber)) {
            throw new PhoneParseException(
                message: 'The supplied phone number is not possible.',
                error: PhoneParseErrorEnum::NOT_POSSIBLE,
            );
        }

        return $phoneNumber;
    }

    /**
     * Whether the number is a valid number of its region (not only of a possible length).
     */
    public function isValid(): bool
    {
        return new PhoneValidator(metaDataRepository: PhoneMetaDataRepository::shared())->isValidNumber(
            phoneNumber: $this,
        );
    }

    /**
     * The type of the number, null if it is not valid. `FIXED_LINE_OR_MOBILE` if the number matches the fixed line and
     * the mobile numbers of its region.
     */
    public function getType(): ?PhoneNumberTypeEnum
    {
        return new PhoneValidator(metaDataRepository: PhoneMetaDataRepository::shared())->getNumberType(
            phoneNumber: $this,
        );
    }

    /**
     * Whether the number is valid and of the given type (a `FIXED_LINE_OR_MOBILE` number is of both types).
     */
    public function isValidForType(PhoneNumberTypeEnum $numberType): bool
    {
        return new PhoneValidator(metaDataRepository: PhoneMetaDataRepository::shared())->isValidNumberOfType(
            phoneNumber: $this,
            numberType: $numberType,
        );
    }

    /**
     * The national number with its leading zeros (the Italian ones), without national prefix.
     */
    public function getNationalSignificantNumber(): string
    {
        if ($this->italianLeadingZero === true && $this->numberOfLeadingZeros > 0) {
            return str_repeat(string: '0', times: $this->numberOfLeadingZeros) . $this->nationalNumber;
        }

        return $this->nationalNumber;
    }
}
