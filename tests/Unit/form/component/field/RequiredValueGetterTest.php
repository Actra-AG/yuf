<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\common\TimeOfDay;
use actra\yuf\form\component\field\DateField;
use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\component\field\FloatField;
use actra\yuf\form\component\field\HiddenIntegerField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\ParsedInputField;
use actra\yuf\form\component\field\TimeField;
use actra\yuf\form\FormFieldValueMissingException;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * The `getRequiredValueAs...()` getters return the value without `null` and throw for an empty field.
 */
final class RequiredValueGetterTest extends TestCase
{
    private static function label(): HtmlText
    {
        return HtmlText::fromHtml(html: 'Label');
    }

    /**
     * @return iterable<string, array{
     *     class-string<ParsedInputField>, string, string, string, callable(ParsedInputField): mixed, mixed
     * }> Field class, nullable getter, valid input, invalid input, required getter call, expected value.
     */
    public static function fieldProvider(): iterable
    {
        yield 'integer' => [
            IntegerField::class, 'getValueAsInt', '42', 'abc',
            static fn(ParsedInputField $field): int => RequiredValueGetterTest::as(IntegerField::class, $field)
                ->getRequiredValueAsInt(),
            42,
        ];
        yield 'hidden integer' => [
            HiddenIntegerField::class, 'getValueAsInt', '7', 'abc',
            static fn(ParsedInputField $field): int => RequiredValueGetterTest::as(HiddenIntegerField::class, $field)
                ->getRequiredValueAsInt(),
            7,
        ];
        yield 'float' => [
            FloatField::class, 'getValueAsFloat', '1.5', 'abc',
            static fn(ParsedInputField $field): float => RequiredValueGetterTest::as(FloatField::class, $field)
                ->getRequiredValueAsFloat(),
            1.5,
        ];
        yield 'decimal' => [
            DecimalField::class, 'getValueAsDecimal', '12.5', 'abc',
            static fn(ParsedInputField $field): string => RequiredValueGetterTest::as(DecimalField::class, $field)
                ->getRequiredValueAsDecimal(),
            '12.50',
        ];
        yield 'date' => [
            DateField::class, 'getValueAsDateTimeImmutable', '2020-02-03', 'abc',
            static fn(ParsedInputField $field): string => RequiredValueGetterTest::as(DateField::class, $field)
                ->getRequiredValueAsDateTimeImmutable()->format(format: 'Y-m-d'),
            '2020-02-03',
        ];
        yield 'time' => [
            TimeField::class, 'getValueAsTimeOfDay', '08:30', 'abc',
            static fn(ParsedInputField $field): string => RequiredValueGetterTest::as(TimeField::class, $field)
                ->getRequiredValueAsTimeOfDay()->toString(),
            '08:30:00',
        ];
    }

    /**
     * @template T of ParsedInputField
     * @param class-string<T> $class
     * @return T
     */
    private static function as(string $class, ParsedInputField $field): ParsedInputField
    {
        return $field instanceof $class
            ? $field
            : throw new LogicException(message: 'Expected ' . $class . ', got ' . $field::class . '.');
    }

    /**
     * @param class-string<ParsedInputField> $class
     */
    private function createField(string $class, bool $required, ?string $initial = null): ParsedInputField
    {
        $requiredError = $required ? HtmlText::fromHtml(html: 'Required') : null;
        $invalid = HtmlText::fromHtml(html: 'Invalid');
        $field = match ($class) {
            IntegerField::class => new IntegerField(
                name: 'field',
                label: RequiredValueGetterTest::label(),
                initialValue: $initial === null ? null : (int) $initial,
                requiredError: $requiredError,
            ),
            HiddenIntegerField::class => new HiddenIntegerField(
                name: 'field',
                value: $initial === null ? null : (int) $initial,
            ),
            FloatField::class => new FloatField(
                name: 'field',
                label: RequiredValueGetterTest::label(),
                initialValue: $initial === null ? null : (float) $initial,
                requiredError: $requiredError,
            ),
            DecimalField::class => new DecimalField(
                name: 'field',
                label: RequiredValueGetterTest::label(),
                scale: 2,
                initialValue: $initial,
                requiredError: $requiredError,
            ),
            DateField::class => new DateField(
                name: 'field',
                label: RequiredValueGetterTest::label(),
                value: $initial === null ? null : new DateTimeImmutable(datetime: $initial),
                invalidError: $invalid,
                requiredError: $requiredError,
            ),
            TimeField::class => new TimeField(
                name: 'field',
                label: RequiredValueGetterTest::label(),
                value: $initial === null ? null : TimeOfDay::fromString(time: $initial . ':00'),
                invalidError: $invalid,
                requiredError: $requiredError,
            ),
            default => throw new LogicException(message: 'Unknown field class ' . $class),
        };
        if ($required && $field instanceof HiddenIntegerField) {
            $field->addRequiredRule(errorMessage: HtmlText::fromHtml(html: 'Required'));
        }

        return $field;
    }

    /**
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testReturnsValueAfterValidation(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected,
    ): void {
        $field = $this->createField(class: $class, required: true);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['field' => $valid])));
        $this->assertSame($expected, $required($field));
    }

    /**
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testEmptyRequiredFieldThrows(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected = null,
    ): void {
        $field = $this->createField(class: $class, required: true);
        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['field' => ''])));

        $this->expectException(FormFieldValueMissingException::class);
        $this->expectExceptionMessageIsOrContains('Field field has no value');

        $required($field);
    }

    /**
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testEmptyOptionalFieldThrowsAndNamesTheNullableGetter(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected = null,
    ): void {
        $field = $this->createField(class: $class, required: false);
        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['field' => ''])));

        $this->expectException(FormFieldValueMissingException::class);
        $this->expectExceptionMessageIsOrContains('is not required');
        $this->expectExceptionMessageIsOrContains($nullableGetter . '()');

        $required($field);
    }

    /**
     * Not validated yet: the initial value is readable, no initial value throws.
     *
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testBeforeValidationTheInitialValueIsReturned(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected,
    ): void {
        $initial = match ($class) {
            DateField::class => '2020-02-03',
            TimeField::class => '08:30',
            DecimalField::class => '12.50',
            FloatField::class => '1.5',
            IntegerField::class => '42',
            default => '7',
        };
        $field = $this->createField(class: $class, required: true, initial: $initial);

        $this->assertSame($expected, $required($field));
    }

    /**
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testBeforeValidationWithoutInitialValueThrows(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected = null,
    ): void {
        $field = $this->createField(class: $class, required: true);

        $this->expectException(FormFieldValueMissingException::class);

        $required($field);
    }

    /**
     * @param class-string<ParsedInputField> $class
     * @param callable(ParsedInputField): mixed $required
     */
    #[DataProvider('fieldProvider')]
    public function testInvalidInputStillThrowsUnexpectedValueException(
        string $class,
        string $nullableGetter,
        string $valid,
        string $invalid,
        callable $required,
        mixed $expected = null,
    ): void {
        $field = $this->createField(class: $class, required: true);
        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['field' => $invalid])));

        $this->expectException(UnexpectedValueException::class);

        $required($field);
    }
}
