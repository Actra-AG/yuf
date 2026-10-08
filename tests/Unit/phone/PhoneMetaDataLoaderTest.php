<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneMetaDataLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class PhoneMetaDataLoaderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function createValidData(): array
    {
        return [
            'countryCode' => 41,
            'internationalPrefix' => '00',
            'generalDesc' => [
                'NationalNumberPattern' => '[2-9]\d{8}',
                'PossibleLength' => [9],
                'PossibleLengthLocalOnly' => [7],
            ],
            'sameMobileAndFixedLinePattern' => false,
            'fixedLine' => [
                'NationalNumberPattern' => '[2-6]\d{8}',
                'PossibleLength' => [9],
                'PossibleLengthLocalOnly' => [],
            ],
            'mobile' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'tollFree' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'premiumRate' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'sharedCost' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'voip' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'personalNumber' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'pager' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'uan' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'voicemail' => ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []],
            'nationalPrefixForParsing' => '0',
            'numberFormat' => [
                [
                    'pattern' => '(\d{2})(\d{7})',
                    'format' => '$1 $2',
                    'leadingDigitsPatterns' => ['[2-9]'],
                    'nationalPrefixFormattingRule' => '0$1',
                ],
            ],
            'intlNumberFormat' => [],
        ];
    }

    public function testLoadNarrowsTheArrayToTypedValues(): void
    {
        $phoneMetaData = new PhoneMetaDataLoader(source: 'XX')->load(data: PhoneMetaDataLoaderTest::createValidData());

        $this->assertSame(41, $phoneMetaData->countryCode);
        $this->assertSame('00', $phoneMetaData->internationalPrefix);
        $this->assertSame('[2-9]\d{8}', $phoneMetaData->generalDesc->nationalNumberPattern);
        $this->assertSame([9], $phoneMetaData->generalDesc->possibleLength);
        $this->assertSame([7], $phoneMetaData->generalDesc->possibleLengthLocalOnly);
        $this->assertSame('0', $phoneMetaData->nationalPrefixForParsing);
        $this->assertNull($phoneMetaData->nationalPrefixTransformRule);
        $this->assertNull($phoneMetaData->preferredExtnPrefix);
        $this->assertCount(1, $phoneMetaData->numberFormats);
        $this->assertSame(['[2-9]'], $phoneMetaData->numberFormats[0]->leadingDigitsPatterns);
        $this->assertSame([], $phoneMetaData->intlNumberFormats);
        $this->assertSame('0$1', $phoneMetaData->numberFormats[0]->nationalPrefixFormattingRule);
        $this->assertNull($phoneMetaData->leadingDigits);
        $this->assertFalse($phoneMetaData->sameMobileAndFixedLinePattern);
        $this->assertSame('[2-6]\d{8}', $phoneMetaData->fixedLine->nationalNumberPattern);
        $this->assertSame('', $phoneMetaData->mobile->nationalNumberPattern);
    }

    public function testMissingNationalPrefixFormattingRuleIsEmpty(): void
    {
        $data = PhoneMetaDataLoaderTest::createValidData();
        $data['numberFormat'] = [['pattern' => 'x', 'format' => '$1', 'leadingDigitsPatterns' => []]];

        $phoneMetaData = new PhoneMetaDataLoader(source: 'XX')->load(data: $data);

        $format = array_first(array: $phoneMetaData->numberFormats);
        $this->assertNotNull($format);
        $this->assertSame('', $format->nationalPrefixFormattingRule);
    }

    public function testLoadReindexesLists(): void
    {
        $data = PhoneMetaDataLoaderTest::createValidData();
        $data['generalDesc'] = [
            'NationalNumberPattern' => '\d+',
            'PossibleLength' => [3 => 9, 7 => 12],
            'PossibleLengthLocalOnly' => [],
        ];

        $phoneMetaData = new PhoneMetaDataLoader(source: 'XX')->load(data: $data);

        $this->assertSame([9, 12], $phoneMetaData->generalDesc->possibleLength);
    }

    public function testMissingOrBlankNationalNumberPatternIsEmpty(): void
    {
        $data = PhoneMetaDataLoaderTest::createValidData();
        $data['generalDesc'] = [
            'NationalNumberPattern' => '  ',
            'PossibleLength' => [-1],
            'PossibleLengthLocalOnly' => [],
        ];
        $this->assertSame(
            '',
            new PhoneMetaDataLoader(source: 'XX')->load(data: $data)->generalDesc->nationalNumberPattern,
        );

        $data['generalDesc'] = ['PossibleLength' => [-1], 'PossibleLengthLocalOnly' => []];
        $this->assertSame(
            '',
            new PhoneMetaDataLoader(source: 'XX')->load(data: $data)->generalDesc->nationalNumberPattern,
        );
    }

    public function testOptionalValuesAreRead(): void
    {
        $data = PhoneMetaDataLoaderTest::createValidData();
        $data['nationalPrefixTransformRule'] = '$2';
        $data['preferredExtnPrefix'] = ' x';
        $data['leadingDigits'] = '1[2-9]';

        $phoneMetaData = new PhoneMetaDataLoader(source: 'XX')->load(data: $data);

        $this->assertSame('$2', $phoneMetaData->nationalPrefixTransformRule);
        $this->assertSame(' x', $phoneMetaData->preferredExtnPrefix);
        $this->assertSame('1[2-9]', $phoneMetaData->leadingDigits);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidDataProvider(): iterable
    {
        $valid = PhoneMetaDataLoaderTest::createValidData();

        yield 'missing country code' => [
            array_diff_key($valid, ['countryCode' => true]),
            'Invalid phone number metadata of XX: "countryCode" must be present.',
        ];
        yield 'country code is a string' => [
            ['countryCode' => '41'] + $valid,
            'Invalid phone number metadata of XX: "countryCode" must be an integer.',
        ];
        yield 'international prefix is an integer' => [
            ['internationalPrefix' => 0] + $valid,
            'Invalid phone number metadata of XX: "internationalPrefix" must be a string.',
        ];
        yield 'general description is no array' => [
            ['generalDesc' => 'x'] + $valid,
            'Invalid phone number metadata of XX: "generalDesc" must be an array.',
        ];
        yield 'optional value is an array' => [
            ['preferredExtnPrefix' => []] + $valid,
            'Invalid phone number metadata of XX: "preferredExtnPrefix" must be a string or null.',
        ];
        yield 'same pattern flag is a string' => [
            ['sameMobileAndFixedLinePattern' => 'false'] + $valid,
            'Invalid phone number metadata of XX: "sameMobileAndFixedLinePattern" must be a boolean.',
        ];
        yield 'missing number type' => [
            array_diff_key($valid, ['mobile' => true]),
            'Invalid phone number metadata of XX: "mobile" must be present.',
        ];
        yield 'possible lengths are strings' => [
            ['generalDesc' => ['PossibleLength' => ['9'], 'PossibleLengthLocalOnly' => []]] + $valid,
            'Invalid phone number metadata of XX: "PossibleLength" must be a list of integers.',
        ];
        yield 'formats are no arrays' => [
            ['numberFormat' => ['x']] + $valid,
            'Invalid phone number metadata of XX: "numberFormat" must be a list of formats.',
        ];
        yield 'format without pattern' => [
            ['numberFormat' => [['format' => '$1', 'leadingDigitsPatterns' => []]]] + $valid,
            'Invalid phone number metadata of XX: "pattern" must be present.',
        ];
        yield 'leading digits patterns are integers' => [
            ['numberFormat' => [['pattern' => 'x', 'format' => '$1', 'leadingDigitsPatterns' => [1]]]] + $valid,
            'Invalid phone number metadata of XX: "leadingDigitsPatterns" must be a list of strings.',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidDataProvider')]
    public function testLoadThrowsForInvalidData(array $data, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs($message);

        new PhoneMetaDataLoader(source: 'XX')->load(data: $data);
    }
}
