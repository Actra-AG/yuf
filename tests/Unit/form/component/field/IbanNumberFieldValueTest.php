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

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('CH93', $this->createField(value: 'CH93')->getRawValue());
    }

    /**
     * The IBAN is not normalized: spaces, case and surrounding whitespace stay as posted.
     *
     * @return iterable<string, array{string}>
     */
    public static function validIbanProvider(): iterable
    {
        yield 'with spaces' => ['CH93 0076 2011 6238 5295 7'];
        yield 'lower case without spaces' => ['ch9300762011623852957'];
        yield 'surrounding whitespace' => [' CH9300762011623852957 '];
    }

    #[DataProvider('validIbanProvider')]
    public function testValidIbanIsStoredUnchanged(string $input): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => $input]);

        $this->assertTrue($isValid);
        $this->assertSame($input, $field->getRawValue());
    }

    public function testInvalidIbanKeepsInputAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => 'xx']);

        $this->assertFalse($isValid);
        $this->assertSame('xx', $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['iban' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertNull($field->getRawValue());
    }
}