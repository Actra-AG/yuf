<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\ZipCodeValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ZipCodeValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function validateProvider(): iterable
    {
        yield 'CH Zurich' => ['8000', 'CH', true];
        yield 'CH Geneva' => ['1200', 'CH', true];
        yield 'CH lowest' => ['1000', 'CH', true];
        yield 'CH 5xxx up to 57' => ['5799', 'CH', true];
        yield 'CH 5xxx above 57' => ['5800', 'CH', false];
        yield 'CH 7xxx up to 77' => ['7799', 'CH', true];
        yield 'CH 7xxx above 77' => ['7800', 'CH', false];
        yield 'CH 9xxx up to 96' => ['9699', 'CH', true];
        yield 'CH 9xxx above 96' => ['9700', 'CH', false];
        yield 'CH starts with 0' => ['0100', 'CH', false];
        yield 'CH too short' => ['800', 'CH', false];
        yield 'CH too long' => ['80000', 'CH', false];
        yield 'CH letters' => ['80a0', 'CH', false];
        yield 'CH surrounding whitespace is ignored' => [' 8000 ', 'CH', true];
        yield 'CH inner whitespace' => ['80 00', 'CH', false];
        yield 'CH empty' => ['', 'CH', false];
        yield 'DE Berlin' => ['10115', 'DE', true];
        yield 'DE Dresden' => ['01067', 'DE', true];
        yield 'DE 00xxx' => ['00123', 'DE', false];
        yield 'DE 05xxx' => ['05000', 'DE', false];
        yield 'DE 43xxx' => ['43000', 'DE', false];
        yield 'DE too short' => ['1011', 'DE', false];
        yield 'AT Vienna' => ['1010', 'AT', true];
        yield 'AT starts with 0' => ['0123', 'AT', false];
        yield 'AT five digits' => ['10100', 'AT', false];
        yield 'country without format accepts anything' => ['any text', 'FR', true];
        yield 'lower case country code is no known country' => ['x', 'ch', true];
        yield 'empty country code' => ['x', '', true];
        yield 'longest accepted length of a country without format' => ['1234567890123456', 'FR', true];
        yield 'too long, also for a country without format' => ['12345678901234567', 'FR', false];
    }

    #[DataProvider('validateProvider')]
    public function testValidate(string $zipCode, string $countryCode, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, ZipCodeValidator::validate(zipCode: $zipCode, countryCode: $countryCode));
    }
}
