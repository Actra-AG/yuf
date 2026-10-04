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

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
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

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField(value: 'a@example.com');

        $isValid = $field->validate(inputData: ['email' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('a@example.com', $field->getRawValue());
    }
}