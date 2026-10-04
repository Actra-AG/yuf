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
    private function createField(?string $value = null): IbanNumberField
    {
        return new IbanNumberField(
            name: 'iban',
            label: HtmlText::encoded(textContent: 'IBAN'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid')
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('CH93', $this->createField(value: 'CH93')->getRawValue());
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
        $this->assertSame($expected, $field->getRawValue());
    }

    public function testInvalidIbanKeepsInputAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => 'xx']);

        $this->assertFalse($isValid);
        $this->assertSame('xx', $field->getRawValue());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame('', $field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getRawValue());
    }
}