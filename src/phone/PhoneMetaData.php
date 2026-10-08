<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * The phone number metadata of one region or of one non-geographical country calling code, narrowed from the
 * generated files in `data/` by `PhoneMetaDataLoader`.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final readonly class PhoneMetaData
{
    /**
     * @param ?string $leadingDigits pattern that tells the numbers of this region from the ones of the other regions
     *      with the same country calling code
     * @param list<PhoneFormat> $intlNumberFormats formats for the international notation, if they differ
     * @param list<PhoneFormat> $numberFormats
     */
    public function __construct(
        public int $countryCode,
        public string $internationalPrefix,
        public PhoneDesc $generalDesc,
        public ?string $leadingDigits,
        public bool $sameMobileAndFixedLinePattern,
        public PhoneDesc $fixedLine,
        public PhoneDesc $mobile,
        public PhoneDesc $tollFree,
        public PhoneDesc $premiumRate,
        public PhoneDesc $sharedCost,
        public PhoneDesc $voip,
        public PhoneDesc $personalNumber,
        public PhoneDesc $pager,
        public PhoneDesc $uan,
        public PhoneDesc $voicemail,
        public ?string $nationalPrefixForParsing,
        public ?string $nationalPrefixTransformRule,
        public ?string $preferredExtnPrefix,
        public array $intlNumberFormats,
        public array $numberFormats,
    ) {}

    public function hasPreferredExtnPrefix(): bool
    {
        return $this->preferredExtnPrefix !== null;
    }
}
