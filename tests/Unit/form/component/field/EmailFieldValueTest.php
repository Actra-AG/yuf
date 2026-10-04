<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\EmailField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailFieldValueTest extends TestCase
{
    private function createField(?string $value = null): EmailField
    {
        return new EmailField(
            name: 'email',
            label: HtmlText::encoded(textContent: 'Email'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid'),
            dnsCheck: false
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('a@example.com', $this->createField(value: 'a@example.com')->getRawValue());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validEmailProvider(): iterable
    {
        yield 'plain address' => ['a@example.com', 'a@example.com'];
        yield 'surrounding whitespace is trimmed' => ['  a@example.com ', 'a@example.com'];
        yield 'address is lower-cased' => ['Foo@Example.COM', 'foo@example.com'];
    }

    #[DataProvider('validEmailProvider')]
    public function testValidEmailIsNormalizedByRule(string $input, string $expected): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['email' => $input]);

        $this->assertTrue($isValid);
        $this->assertSame($expected, $field->getRawValue());
    }

    public function testInvalidEmailKeepsInputAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['email' => 'not an address']);

        $this->assertFalse($isValid);
        $this->assertSame('not an address', $field->getRawValue());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame('', $field->getRawValue());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: 'a@example.com');

        $isValid = $field->validate(inputData: ['email' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getRawValue());
    }

    public function testConstructorValueIsNormalizedAsInput(): void
    {
        $this->assertSame('foo@example.com', $this->createField(value: ' Foo@Example.COM ')->getValueAsString());
    }

    public function testInvalidConstructorValueIsKeptTrimmed(): void
    {
        $this->assertSame('nope', $this->createField(value: ' nope ')->getValueAsString());
    }

    public function testSetValueNormalizesAndKeepsInitialValue(): void
    {
        $field = $this->createField(value: 'a@example.com');

        $field->setValue(value: 'B@Example.com');

        $this->assertSame('b@example.com', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSameAddressInOtherCaseIsNoChange(): void
    {
        $field = $this->createField(value: 'a@example.com');

        $field->validate(inputData: ['email' => ' A@Example.com ']);

        $this->assertFalse($field->valueHasChanged());
    }
}