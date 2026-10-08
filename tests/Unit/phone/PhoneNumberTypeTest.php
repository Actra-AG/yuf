<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneMetaDataRepository;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneNumberTypeEnum;
use actra\yuf\phone\PhoneValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validity and type of phone numbers, with the example numbers of the metadata of every region.
 */
final class PhoneNumberTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, PhoneNumberTypeEnum, string}>
     */
    public static function exampleNumberProvider(): iterable
    {
        foreach (PhoneRegionExampleNumbersTest::exampleNumberProvider() as $name => [$region, $type, $example]) {
            $numberType = PhoneNumberTypeEnum::tryFrom(value: $type);
            if ($numberType !== null) {
                yield $name => [$region, $numberType, $example];
            }
        }
    }

    /**
     * The example number as it stands in the metadata, without the parser (which strips a national prefix).
     */
    private function createExampleNumber(string $region, string $example): PhoneNumber
    {
        return new PhoneNumber(
            extension: '',
            countryCode: PhoneExampleNumbers::countryCode(region: $region),
            italianLeadingZero: null,
            numberOfLeadingZeros: 0,
            nationalNumber: $example,
        );
    }

    private function createValidator(): PhoneValidator
    {
        return new PhoneValidator(metaDataRepository: new PhoneMetaDataRepository());
    }

    public function testTheExampleNumbersCoverEveryNumberType(): void
    {
        $coveredTypes = [];
        foreach (PhoneNumberTypeTest::exampleNumberProvider() as $case) {
            $coveredTypes[$case[1]->value] = true;
        }

        foreach (PhoneNumberTypeEnum::cases() as $numberType) {
            if ($numberType !== PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE) {
                $this->assertArrayHasKey($numberType->value, $coveredTypes);
            }
        }
    }

    #[DataProvider('exampleNumberProvider')]
    public function testExampleNumberIsValid(string $region, PhoneNumberTypeEnum $numberType, string $example): void
    {
        $phoneNumber = $this->createExampleNumber(region: $region, example: $example);

        $this->assertTrue($this->createValidator()->isValidNumber(phoneNumber: $phoneNumber));
        $this->assertTrue($phoneNumber->isValid());
    }

    #[DataProvider('exampleNumberProvider')]
    public function testExampleNumberIsOfItsType(string $region, PhoneNumberTypeEnum $numberType, string $example): void
    {
        $phoneNumber = $this->createExampleNumber(region: $region, example: $example);

        $this->assertTrue($this->createValidator()->isValidNumberOfType(
            phoneNumber: $phoneNumber,
            numberType: $numberType,
        ));
        $this->assertTrue($phoneNumber->isValidForType(numberType: $numberType));
    }

    #[DataProvider('exampleNumberProvider')]
    public function testExampleNumberHasItsType(string $region, PhoneNumberTypeEnum $numberType, string $example): void
    {
        $phoneNumber = $this->createExampleNumber(region: $region, example: $example);

        $actualType = $this->createValidator()->getNumberType(phoneNumber: $phoneNumber);

        $this->assertNotNull($actualType);
        $this->assertSame($actualType, $phoneNumber->getType());
        if ($numberType === PhoneNumberTypeEnum::FIXED_LINE || $numberType === PhoneNumberTypeEnum::MOBILE) {
            // Fixed line and mobile numbers of the same pattern cannot be told apart (e.g. in the US).
            $this->assertContains($actualType, [$numberType, PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE]);
        } else {
            $this->assertSame($numberType, $actualType);
        }
    }

    /**
     * @return iterable<string, array{string, string, ?PhoneNumberTypeEnum}>
     */
    public static function knownNumberProvider(): iterable
    {
        yield 'CH fixed line' => ['CH', '044 668 18 00', PhoneNumberTypeEnum::FIXED_LINE];
        yield 'CH mobile' => ['CH', '079 123 45 67', PhoneNumberTypeEnum::MOBILE];
        yield 'CH toll free' => ['CH', '0800 123 456', PhoneNumberTypeEnum::TOLL_FREE];
        yield 'CH premium rate' => ['CH', '0900 123 456', PhoneNumberTypeEnum::PREMIUM_RATE];
        yield 'CH shared cost' => ['CH', '0840 123 456', PhoneNumberTypeEnum::SHARED_COST];
        yield 'CH personal number' => ['CH', '0878 123 456', PhoneNumberTypeEnum::PERSONAL_NUMBER];
        yield 'CH pager' => ['CH', '0740 123 456', PhoneNumberTypeEnum::PAGER];
        yield 'CH UAN' => ['CH', '058 123 45 67', PhoneNumberTypeEnum::UAN];
        yield 'CH voicemail' => ['CH', '0860 123 456 789', PhoneNumberTypeEnum::VOICEMAIL];
        yield 'CH of the right length but no number' => ['CH', '012 345 67 89', null];
        yield 'CH mobile prefix 70 is not assigned' => ['CH', '070 123 45 67', null];
        yield 'DE fixed line' => ['DE', '030 123456', PhoneNumberTypeEnum::FIXED_LINE];
        yield 'DE mobile' => ['DE', '0151 23456789', PhoneNumberTypeEnum::MOBILE];
        yield 'DE toll free' => ['DE', '0800 1234567', PhoneNumberTypeEnum::TOLL_FREE];
        yield 'DE premium rate' => ['DE', '0900 1234567', PhoneNumberTypeEnum::PREMIUM_RATE];
        yield 'DE of a possible length but no number' => ['DE', '0200 123456', null];
        yield 'US fixed line or mobile' => ['US', '650 253 0000', PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE];
        yield 'US toll free' => ['US', '800 234 5678', PhoneNumberTypeEnum::TOLL_FREE];
        yield 'US premium rate' => ['US', '900 234 5678', PhoneNumberTypeEnum::PREMIUM_RATE];
        yield 'US area code 1 does not exist' => ['US', '150 253 0000', null];
        yield 'GB fixed line' => ['GB', '020 7946 0018', PhoneNumberTypeEnum::FIXED_LINE];
        yield 'GB mobile' => ['GB', '07912 345678', PhoneNumberTypeEnum::MOBILE];
        yield 'GB toll free' => ['GB', '0808 157 0192', PhoneNumberTypeEnum::TOLL_FREE];
        yield 'GB premium rate' => ['GB', '09012 345678', PhoneNumberTypeEnum::PREMIUM_RATE];
        yield 'FR fixed line' => ['FR', '01 23 45 67 89', PhoneNumberTypeEnum::FIXED_LINE];
        yield 'FR mobile' => ['FR', '06 12 34 56 78', PhoneNumberTypeEnum::MOBILE];
        yield 'IT fixed line' => ['IT', '06 1234 5678', PhoneNumberTypeEnum::FIXED_LINE];
        yield 'IT mobile' => ['IT', '312 345 6789', PhoneNumberTypeEnum::MOBILE];
    }

    #[DataProvider('knownNumberProvider')]
    public function testKnownNumbers(string $region, string $input, ?PhoneNumberTypeEnum $expected): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $region);

        $this->assertSame($expected, $this->createValidator()->getNumberType(phoneNumber: $phoneNumber));
        $this->assertSame($expected !== null, $this->createValidator()->isValidNumber(phoneNumber: $phoneNumber));
    }

    public function testFixedLineOrMobileIsOfBothTypesButNotOfOthers(): void
    {
        $validator = $this->createValidator();
        $phoneNumber = PhoneNumber::createFromString(input: '650 253 0000', defaultCountryCode: 'US');

        $this->assertTrue($validator->isValidNumberOfType(
            phoneNumber: $phoneNumber,
            numberType: PhoneNumberTypeEnum::FIXED_LINE,
        ));
        $this->assertTrue($validator->isValidNumberOfType(
            phoneNumber: $phoneNumber,
            numberType: PhoneNumberTypeEnum::MOBILE,
        ));
        $this->assertTrue($validator->isValidNumberOfType(
            phoneNumber: $phoneNumber,
            numberType: PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE,
        ));
        $this->assertFalse($validator->isValidNumberOfType(
            phoneNumber: $phoneNumber,
            numberType: PhoneNumberTypeEnum::TOLL_FREE,
        ));
    }

    public function testNumberIsNotOfAnotherType(): void
    {
        $validator = $this->createValidator();
        $mobile = PhoneNumber::createFromString(input: '079 123 45 67', defaultCountryCode: 'CH');

        $this->assertTrue($validator->isValidNumberOfType(
            phoneNumber: $mobile,
            numberType: PhoneNumberTypeEnum::MOBILE,
        ));
        $this->assertFalse($validator->isValidNumberOfType(
            phoneNumber: $mobile,
            numberType: PhoneNumberTypeEnum::FIXED_LINE,
        ));
        $this->assertFalse($validator->isValidNumberOfType(
            phoneNumber: $mobile,
            numberType: PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE,
        ));
    }

    public function testInvalidNumberIsOfNoType(): void
    {
        $validator = $this->createValidator();
        $phoneNumber = PhoneNumber::createFromString(input: '012 345 67 89', defaultCountryCode: 'CH');

        foreach (PhoneNumberTypeEnum::cases() as $numberType) {
            $this->assertFalse($validator->isValidNumberOfType(phoneNumber: $phoneNumber, numberType: $numberType));
        }
        $this->assertFalse($phoneNumber->isValid());
        $this->assertNull($phoneNumber->getType());
    }

    public function testNumberWithUnknownCountryCallingCodeIsInvalid(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 999,
            italianLeadingZero: null,
            numberOfLeadingZeros: 0,
            nationalNumber: '123456789',
        );

        $this->assertFalse($phoneNumber->isValid());
        $this->assertNull($phoneNumber->getType());
    }

    public function testRegionOfASharedCountryCallingCodeIsFoundByItsLeadingDigits(): void
    {
        // +1 242 is the Bahamas, +1 506 Canada: neither is a number of the main region US
        $bahamas = PhoneNumber::createFromString(input: '+1 242 359 1234', defaultCountryCode: null);
        $canada = PhoneNumber::createFromString(input: '+1 506 234 5678', defaultCountryCode: null);

        $this->assertTrue($bahamas->isValid());
        $this->assertTrue($canada->isValid());
        $this->assertSame(PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE, $canada->getType());
    }

    public function testNonGeographicalNumberIsValid(): void
    {
        $example = PhoneExampleNumbers::get(regionOrCallingCode: '800', type: 'tollFree');
        $phoneNumber = PhoneNumber::createFromString(input: '+800 ' . $example, defaultCountryCode: null);

        $this->assertSame(800, $phoneNumber->countryCode);
        $this->assertSame(PhoneNumberTypeEnum::TOLL_FREE, $phoneNumber->getType());
    }
}
