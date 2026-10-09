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
            'NationalNumberPattern' => '222\\d{6}|(?:0\\d|80)\\d{7}',
            'PossibleLength' =>
                [
                    0 => 9,
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'fixedLine' =>
        [
            'NationalNumberPattern' => '222[1-589]\\d{5}',
            'ExampleNumber' => '222123456',
            'PossibleLength' =>
                [
                ],
            'PossibleLengthLocalOnly' =>
                [
                ],
        ],
    'mobile' =>
        [
            'NationalNumberPattern' => '0[14-6]\\d{7}',
            'ExampleNumber' => '061234567',
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
            'NationalNumberPattern' => '80(?:0\\d\\d|11[0-4])\\d{4}',
            'ExampleNumber' => '800123456',
            'PossibleLength' =>
                [
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
    'id' => 'CG',
    'countryCode' => 242,
    'internationalPrefix' => '00',
    'sameMobileAndFixedLinePattern' => false,
    'numberFormat' =>
        [
            0 =>
                [
                    'pattern' => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
                    'format' => '$1 $2 $3 $4',
                    'leadingDigitsPatterns' =>
                        [
                            0 => '801',
                        ],
                    'nationalPrefixFormattingRule' => '',
                    'domesticCarrierCodeFormattingRule' => '',
                    'nationalPrefixOptionalWhenFormatting' => false,
                ],
            1 =>
                [
                    'pattern' => '(\\d)(\\d{4})(\\d{4})',
                    'format' => '$1 $2 $3',
                    'leadingDigitsPatterns' =>
                        [
                            0 => '8',
                        ],
                    'nationalPrefixFormattingRule' => '',
                    'domesticCarrierCodeFormattingRule' => '',
                    'nationalPrefixOptionalWhenFormatting' => false,
                ],
            2 =>
                [
                    'pattern' => '(\\d{2})(\\d{3})(\\d{4})',
                    'format' => '$1 $2 $3',
                    'leadingDigitsPatterns' =>
                        [
                            0 => '[02]',
                        ],
                    'nationalPrefixFormattingRule' => '',
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