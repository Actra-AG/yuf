<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);
/**
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 */

return [
    'generalDesc' =>
        [
            'NationalNumberPattern' => '(?:[2378]\\d|99)\\d{6}',
            'PossibleLength' =>
                [
                    0 => 8,
                ],
            'PossibleLengthLocalOnly' =>
                [
                    0 => 6,
                ],
        ],
    'fixedLine' =>
        [
            'NationalNumberPattern' => '22\\d{6}',
            'ExampleNumber' => '22221234',
            'PossibleLength' =>
                [
                ],
            'PossibleLengthLocalOnly' =>
                [
                    0 => 6,
                ],
        ],
    'mobile' =>
        [
            'NationalNumberPattern' => '(?:25|3[0134]|7[5-9]|8[08]|99)\\d{6}',
            'ExampleNumber' => '25123456',
            'PossibleLength' =>
                [
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'tollFree' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'premiumRate' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'sharedCost' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'personalNumber' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'voip' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'pager' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'uan' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'voicemail' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'noInternationalDialling' =>
        [
            'PossibleLength' =>
                [
                    0 => -1,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'id' => 'SL',
    'countryCode' => 232,
    'internationalPrefix' => '00',
    'nationalPrefix' => '0',
    'nationalPrefixForParsing' => '0',
    'sameMobileAndFixedLinePattern' => false,
    'numberFormat' =>
        [
            0 =>
                [
                    'pattern' => '(\\d{2})(\\d{6})',
                    'format' => '$1 $2',
                    'leadingDigitsPatterns' =>
                        [
                            0 => '[237-9]',
                        ],
                    'nationalPrefixFormattingRule' => '(0$1)',
                    'domesticCarrierCodeFormattingRule' => '',
                    'nationalPrefixOptionalWhenFormatting' => false,
                ],
        ],
    'intlNumberFormat' =>
        [
        ],
    'mainCountryForCode' => false,
    'leadingZeroPossible' => false,
    'mobileNumberPortableRegion' => false,
];