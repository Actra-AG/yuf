<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\IbanNumberField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IbanNumberFieldValueTest extends TestCase
{
    private function createField(?string $value = null, ?HtmlText $requiredError = null): IbanNumberField
    {
        return new IbanNumberField(
            name: 'iban',
            label: HtmlText::encoded(textContent: 'IBAN'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid'),
            requiredError: $requiredError
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('CH93', $this->createField(value: 'CH93')->getValueAsString());
    }

    /**
     * Spaces and case stay as posted, surrounding whitespace is trimmed.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function validIbanProvider(): iterable
    {
        yield 'with spaces' => ['CH93 0076 2011 6238 5295 7', 'CH93 0076 2011 6238 5295 7'];
        yield 'lower case without spaces' => ['ch9300762011623852957', 'ch9300762011623852957'];
        yield 'surrounding whitespace' => [' CH9300762011623852957 ', 'CH9300762011623852957'];
    }

    #[DataProvider('validIbanProvider')]
    public function testValidIbanIsStoredTrimmed(string $input, string $expected): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => $input]);

        $this->assertTrue($isValid);
        $this->assertSame($expected, $field->getValueAsString());
    }

    public function testInvalidIbanKeepsInputAndAddsTheError(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => 'xx']);

        $this->assertFalse($isValid);
        $this->assertSame('xx', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Invalid', $field->errorCollection->getFirstError()->render());
    }

    public function testWrongChecksumIsInvalid(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(inputData: ['iban' => 'CH93 0076 2011 6238 5295 8']));
    }

    public function testEmptyIbanIsValidWithoutRequiredError(): void
    {
        $this->assertTrue($this->createField()->validate(inputData: ['iban' => '']));
    }

    public function testEmptyIbanGivesOnlyTheRequiredError(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $isValid = $field->validate(inputData: ['iban' => '']);

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: 'CH9300762011623852957');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: 'CH9300762011623852957');

        $isValid = $field->validate(inputData: ['iban' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('', $field->getValueAsString());
    }

    public function testSetValueIsValidatedWithValidateCurrentValue(): void
    {
        $field = $this->createField();
        $field->setValue(value: 'CH9300762011623852957');
        $valid = $field->validateCurrentValue();
        $field->setValue(value: 'CH9300762011623852958');

        $this->assertTrue($valid);
        $this->assertFalse($field->validateCurrentValue());
    }
}