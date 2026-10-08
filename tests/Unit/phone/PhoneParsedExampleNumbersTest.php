<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneNumberTypeEnum;
use actra\yuf\phone\PhoneRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every example number of the metadata, as national number of its region, goes through the parser: it must be valid,
 * of its type, and its renderings must be parsed to the same number again (as in libphonenumber).
 */
final class PhoneParsedExampleNumbersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, PhoneNumberTypeEnum, string}>
     */
    public static function exampleNumberProvider(): iterable
    {
        foreach (PhoneNumberTypeTest::exampleNumberProvider() as $name => $case) {
            yield $name => $case;
        }
    }

    #[DataProvider('exampleNumberProvider')]
    public function testParsedExampleNumberIsValidAndOfItsType(
        string $region,
        PhoneNumberTypeEnum $numberType,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $this->assertSame(PhoneExampleNumbers::countryCode(region: $region), $phoneNumber->countryCode);
        $this->assertTrue($phoneNumber->isValid());
        $this->assertTrue($phoneNumber->isValidForType(numberType: $numberType));
        $actualType = $phoneNumber->getType();
        if ($numberType === PhoneNumberTypeEnum::FIXED_LINE || $numberType === PhoneNumberTypeEnum::MOBILE) {
            // Fixed line and mobile numbers of the same pattern cannot be told apart (e.g. in the US).
            $this->assertContains($actualType, [$numberType, PhoneNumberTypeEnum::FIXED_LINE_OR_MOBILE]);
        } else {
            $this->assertSame($numberType, $actualType);
        }
    }

    #[DataProvider('exampleNumberProvider')]
    public function testNationalRenderingIsParsedToTheSameNumber(
        string $region,
        PhoneNumberTypeEnum $numberType,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: $region,
        );

        $this->assertEquals($phoneNumber, $parsedAgain);
    }

    #[DataProvider('exampleNumberProvider')]
    public function testInternationalRenderingIsParsedToTheSameNumber(
        string $region,
        PhoneNumberTypeEnum $numberType,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: null,
        );

        $this->assertEquals($phoneNumber, $parsedAgain);
    }

    #[DataProvider('exampleNumberProvider')]
    public function testE164RenderingIsParsedToTheSameNumber(
        string $region,
        PhoneNumberTypeEnum $numberType,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderE164Format(phoneNumber: $phoneNumber),
            defaultCountryCode: null,
        );

        $this->assertEquals($phoneNumber, $parsedAgain);
    }
}
