<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\DateField;
use actra\yuf\form\component\field\TimeField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Characterization of DateField and TimeField (both extend DateTimeFieldCore). Values are strings, never objects.
 */
final class DateTimeFieldValueTest extends TestCase
{
    private function createDateField(?string $value = null): DateField
    {
        return new DateField(
            name: 'date',
            label: HtmlText::encoded(textContent: 'Date'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid')
        );
    }

    private function createTimeField(?string $value = null): TimeField
    {
        return new TimeField(
            name: 'time',
            label: HtmlText::encoded(textContent: 'Time'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid')
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validDateProvider(): iterable
    {
        yield 'ISO date' => ['2020-02-03', '2020-02-03'];
        yield 'ISO date without leading zeros' => ['2020-2-3', '2020-02-03'];
        yield 'Swiss date format is converted to ISO' => ['3.2.2020', '2020-02-03'];
    }

    #[DataProvider('validDateProvider')]
    public function testValidDateIsNormalizedToIsoString(string $input, string $expected): void
    {
        $field = $this->createDateField();

        $isValid = $field->validate(inputData: ['date' => $input]);

        $this->assertTrue($isValid);
        $this->assertSame($expected, $field->getRawValue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDateProvider(): iterable
    {
        yield 'impossible date' => ['2020-02-30'];
        yield 'leading whitespace' => [' 2020-02-03'];
        yield 'text' => ['tomorrow'];
    }

    #[DataProvider('invalidDateProvider')]
    public function testInvalidDateKeepsInputAsString(string $input): void
    {
        $field = $this->createDateField();

        $isValid = $field->validate(inputData: ['date' => $input]);

        $this->assertFalse($isValid);
        $this->assertSame($input, $field->getRawValue());
    }

    public function testDateValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createDateField()->getRawValue());
    }

    public function testDateValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('2020-01-02', $this->createDateField(value: '2020-01-02')->getRawValue());
    }

    public function testDateValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createDateField(value: '2020-01-02');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testDateArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createDateField(value: '2020-01-02');

        $isValid = $field->validate(inputData: ['date' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('2020-01-02', $field->getRawValue());
    }

    public function testGetValueAsDateTimeImmutableReturnsNullForEmptyString(): void
    {
        $this->assertNull($this->createDateField(value: '')->getValueAsDateTimeImmutable());
    }

    public function testGetValueAsDateTimeImmutableParsesValue(): void
    {
        $dateTime = $this->createDateField(value: '2020-01-02')->getValueAsDateTimeImmutable();

        $this->assertSame('2020-01-02', $dateTime?->format(format: 'Y-m-d'));
    }

    /**
     * KNOWN BUG (fixed in Task 3): a null value is not handled, only an empty string.
     */
    public function testGetValueAsDateTimeImmutableThrowsTypeErrorForNullBecauseOfKnownBug(): void
    {
        $field = $this->createDateField();

        $this->expectException(TypeError::class);

        $field->getValueAsDateTimeImmutable();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validTimeProvider(): iterable
    {
        yield 'hours and minutes get seconds added' => ['08:05', '08:05:00'];
        yield 'with seconds' => ['08:05:07', '08:05:07'];
    }

    #[DataProvider('validTimeProvider')]
    public function testValidTimeIsNormalizedToStringWithSeconds(string $input, string $expected): void
    {
        $field = $this->createTimeField();

        $isValid = $field->validate(inputData: ['time' => $input]);

        $this->assertTrue($isValid);
        $this->assertSame($expected, $field->getRawValue());
    }

    public function testInvalidTimeKeepsInputAsString(): void
    {
        $field = $this->createTimeField();

        $isValid = $field->validate(inputData: ['time' => '25:00']);

        $this->assertFalse($isValid);
        $this->assertSame('25:00', $field->getRawValue());
    }

    public function testTimeValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createTimeField()->getRawValue());
    }

    public function testTimeValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createTimeField(value: '08:00');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    /**
     * The rejected array leaves the previous value in place, and the rules still run on it (here: seconds are added).
     */
    public function testTimeArrayInputIsRejectedAndKeepsPreviousValueNormalizedByRule(): void
    {
        $field = $this->createTimeField(value: '08:00');

        $isValid = $field->validate(inputData: ['time' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('08:00:00', $field->getRawValue());
    }
}