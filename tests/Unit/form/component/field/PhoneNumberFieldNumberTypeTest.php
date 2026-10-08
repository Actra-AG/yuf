<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use actra\yuf\phone\PhoneNumberTypeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberFieldNumberTypeTest extends TestCase
{
    /**
     * @param list<PhoneNumberTypeEnum> $allowedNumberTypes
     */
    private function createField(
        array $allowedNumberTypes = [],
        ?HtmlText $numberTypeErrorMessage = null,
        string $countryCode = 'CH',
    ): PhoneNumberField {
        return new PhoneNumberField(
            name: 'phone',
            label: HtmlText::fromHtml(html: 'Phone'),
            value: null,
            invalidErrorMessage: HtmlText::fromHtml(html: 'Invalid'),
            countryCode: $countryCode,
            allowedNumberTypes: $allowedNumberTypes,
            numberTypeErrorMessage: $numberTypeErrorMessage,
        );
    }

    /**
     * @return list<string>
     */
    private function listErrors(PhoneNumberField $field): array
    {
        return array_map(
            callback: fn(HtmlText $error): string => $error->render(),
            array: $field->errorCollection->listErrors(),
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function withoutAllowListProvider(): iterable
    {
        yield 'fixed line' => ['044 123 45 67', true];
        yield 'mobile' => ['079 123 45 67', true];
        yield 'premium rate' => ['0900 123 456', true];
        yield 'possible length but no assigned number (as before)' => ['012 345 67 89', true];
        yield 'too short' => ['12', false];
    }

    #[DataProvider('withoutAllowListProvider')]
    public function testWithoutAllowListEveryPossibleNumberIsAccepted(string $input, bool $expected): void
    {
        $field = $this->createField();

        $this->assertSame($expected, $field->validate(input: FormInput::fromArray(data: ['phone' => $input])));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function mobileOnlyProvider(): iterable
    {
        yield 'mobile' => ['079 123 45 67', true];
        yield 'mobile in international notation' => ['+41 79 123 45 67', true];
        yield 'fixed line' => ['044 123 45 67', false];
        yield 'premium rate' => ['0900 123 456', false];
        yield 'possible length but no assigned number' => ['012 345 67 89', false];
    }

    #[DataProvider('mobileOnlyProvider')]
    public function testAllowListOfMobileNumbers(string $input, bool $expected): void
    {
        $field = $this->createField(allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE]);

        $this->assertSame($expected, $field->validate(input: FormInput::fromArray(data: ['phone' => $input])));
    }

    public function testNumberOfAnotherTypeAddsTheNumberTypeErrorMessage(): void
    {
        $field = $this->createField(
            allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE],
            numberTypeErrorMessage: HtmlText::fromHtml(html: 'Mobile only'),
        );

        $field->validate(input: FormInput::fromArray(data: ['phone' => '044 123 45 67']));

        $this->assertSame(['Mobile only'], $this->listErrors(field: $field));
        $this->assertSame('+41.441234567', $field->getValueAsString());
    }

    public function testNumberTypeErrorMessageDefaultsToTheInvalidErrorMessage(): void
    {
        $field = $this->createField(allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE]);

        $field->validate(input: FormInput::fromArray(data: ['phone' => '044 123 45 67']));

        $this->assertSame(['Invalid'], $this->listErrors(field: $field));
    }

    public function testImpossibleNumberGivesTheInvalidErrorMessageOnly(): void
    {
        $field = $this->createField(
            allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE],
            numberTypeErrorMessage: HtmlText::fromHtml(html: 'Mobile only'),
        );

        $field->validate(input: FormInput::fromArray(data: ['phone' => '12']));

        $this->assertSame(['Invalid'], $this->listErrors(field: $field));
    }

    public function testSeveralTypesAreAllowed(): void
    {
        $field = $this->createField(
            allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE, PhoneNumberTypeEnum::FIXED_LINE],
        );

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['phone' => '044 123 45 67'])));
        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['phone' => '079 123 45 67'])));
        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['phone' => '0800 123 456'])));
    }

    public function testFixedLineOrMobileNumberFitsEveryOfTheTwoTypes(): void
    {
        $fixedLine = $this->createField(allowedNumberTypes: [PhoneNumberTypeEnum::FIXED_LINE], countryCode: 'US');
        $tollFree = $this->createField(allowedNumberTypes: [PhoneNumberTypeEnum::TOLL_FREE], countryCode: 'US');

        $this->assertTrue($fixedLine->validate(input: FormInput::fromArray(data: ['phone' => '650 253 0000'])));
        $this->assertFalse($tollFree->validate(input: FormInput::fromArray(data: ['phone' => '650 253 0000'])));
    }

    public function testEmptyValueIsNotCheckedAgainstTheTypes(): void
    {
        $field = $this->createField(allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE]);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['phone' => ''])));
    }
}
