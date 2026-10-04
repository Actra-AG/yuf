<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\HiddenField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HiddenFieldValueTest extends TestCase
{
    /**
     * The constructor value keeps its type (int, float, bool); only posted values are always strings.
     *
     * @return iterable<string, array{int|float|string|bool|null}>
     */
    public static function constructorValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['text'];
        yield 'empty string' => [''];
        yield 'int' => [5];
        yield 'zero int' => [0];
        yield 'float' => [1.5];
        yield 'true' => [true];
        yield 'false' => [false];
    }

    #[DataProvider('constructorValueProvider')]
    public function testConstructorValueKeepsItsType(int|float|string|bool|null $value): void
    {
        $field = new HiddenField(name: 'hidden', value: $value);

        $this->assertSame($value, $field->getRawValue());
    }

    public function testStringInputReplacesTypedConstructorValue(): void
    {
        $field = new HiddenField(name: 'hidden', value: 5);

        $isValid = $field->validate(inputData: ['hidden' => '7']);

        $this->assertTrue($isValid);
        $this->assertSame('7', $field->getRawValue());
    }

    public function testStringInputIsStoredUntrimmed(): void
    {
        $field = new HiddenField(name: 'hidden');

        $field->validate(inputData: ['hidden' => ' a ']);

        $this->assertSame(' a ', $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = new HiddenField(name: 'hidden', value: 5);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsTypedConstructorValue(): void
    {
        $field = new HiddenField(name: 'hidden', value: 5);

        $isValid = $field->validate(inputData: ['hidden' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(5, $field->getRawValue());
    }

    public function testFalseIsTreatedAsEmpty(): void
    {
        $field = new HiddenField(name: 'hidden', value: false);

        $this->assertTrue($field->isValueEmpty());
        $this->assertNull($field->getRawValue(returnNullIfEmpty: true));
    }
}