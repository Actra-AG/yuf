<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Options with integer keys (e.g. database ids): the integer getters of the single and the multi options field.
 */
final class OptionsFieldIntKeysTest extends TestCase
{
    private function createOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addIntItem(key: 1, htmlText: HtmlText::fromHtml(html: 'One'));
        $formOptions->addIntItem(key: 20, htmlText: HtmlText::fromHtml(html: 'Twenty'));
        $formOptions->addIntItem(key: -5, htmlText: HtmlText::fromHtml(html: 'Minus five'));
        $formOptions->addItem(key: 'text', htmlText: HtmlText::fromHtml(html: 'Text'));

        return $formOptions;
    }

    private function createSingle(?string $initialValue = null): SelectOptionsField
    {
        return new SelectOptionsField(
            name: 'single',
            label: HtmlText::fromHtml(html: 'Single'),
            formOptions: $this->createOptions(),
            initialValue: $initialValue,
        );
    }

    /**
     * @param list<string> $initialValues
     */
    private function createMulti(array $initialValues = []): MultiSelectOptionsField
    {
        return new MultiSelectOptionsField(
            name: 'multi',
            label: HtmlText::fromHtml(html: 'Multi'),
            formOptions: $this->createOptions(),
            initialValues: $initialValues,
        );
    }

    public function testSingleValueAsIntIsNullWithoutSelection(): void
    {
        $this->assertNull($this->createSingle()->getValueAsInt());
    }

    public function testSingleValueAsIntOfAPostedKey(): void
    {
        $field = $this->createSingle();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['single' => '20']));

        $this->assertTrue($isValid);
        $this->assertSame(20, $field->getValueAsInt());
    }

    public function testSingleValueAsIntOfANegativeKeyAndOfAnInitialValue(): void
    {
        $this->assertSame(-5, $this->createSingle(initialValue: '-5')->getValueAsInt());
    }

    public function testSingleValueAsIntIsNullAfterAnUnknownKeyWasRejected(): void
    {
        $field = $this->createSingle(initialValue: '1');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['single' => '999']));

        $this->assertFalse($isValid);
        $this->assertNull($field->getValueAsInt());
    }

    public function testSingleValueAsIntThrowsForATextKey(): void
    {
        $field = $this->createSingle(initialValue: 'text');

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsInt();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIntegerKeys(): iterable
    {
        yield 'plus sign' => ['+1'];
        yield 'with spaces' => [' 1'];
        yield 'decimal' => ['1.5'];
        yield 'trailing newline' => ["1\n"];
        yield 'overflow' => ['9223372036854775808'];
    }

    #[DataProvider('notIntegerKeys')]
    public function testSingleValueAsIntThrowsForKeysThatAreNotStrictIntegers(string $key): void
    {
        $field = $this->createSingle(initialValue: $key);

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsInt();
    }

    public function testMultiIntValuesAreInTheStoredOrder(): void
    {
        $field = $this->createMulti();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['multi' => ['20', '1', '-5']]));

        $this->assertTrue($isValid);
        $this->assertSame([20, 1, -5], $field->getIntValues());
    }

    public function testMultiIntValuesAreEmptyWithoutSelection(): void
    {
        $this->assertSame([], $this->createMulti()->getIntValues());
    }

    public function testMultiAddedAndRemovedIntValues(): void
    {
        $field = $this->createMulti(initialValues: ['1', '20']);

        $field->validate(input: FormInput::fromArray(data: ['multi' => ['20', '-5']]));

        $this->assertSame([-5], $field->getAddedIntValues());
        $this->assertSame([1], $field->getRemovedIntValues());
    }

    public function testMultiIntValuesThrowForATextKey(): void
    {
        $field = $this->createMulti(initialValues: ['1', 'text']);

        $this->expectException(UnexpectedValueException::class);

        $field->getIntValues();
    }

    public function testMultiAddedIntValuesThrowForATextKey(): void
    {
        $field = $this->createMulti();
        $field->setValues(values: ['text']);

        $this->expectException(UnexpectedValueException::class);

        $field->getAddedIntValues();
    }

    public function testStringGettersStillReturnStringsForIntegerKeys(): void
    {
        $field = $this->createMulti(initialValues: ['1']);

        $this->assertSame(['1'], $field->getValues());
    }
}
