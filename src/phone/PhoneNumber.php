<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * A parsed phone number: the country calling code, the national number and an optional extension. Create it with
 * `createFromString()`, render it with `PhoneRenderer`.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
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
        $metaDataRepository = new PhoneMetaDataRepository();
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
