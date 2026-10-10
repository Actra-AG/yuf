<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\db\DbRowValueException;
use actra\yuf\table\TableItem;
use actra\yuf\tests\Double\db\StatusEnum;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

final class TableItemTest extends TestCase
{
    public function testGetRowGivesTypedValues(): void
    {
        $row = TableItemTest::tableItem(values: [
            'ID' => 42,
            'name' => 'Muster',
            'price' => '12.50',
            'ratio' => 0.5,
            'active' => 1,
            'created' => '2026-10-05 08:30:00',
            'status' => 'blocked',
        ])->getRow();

        $this->assertSame(42, $row->getInt(column: 'ID'));
        $this->assertSame('Muster', $row->getString(column: 'name'));
        $this->assertSame('12.50', $row->getDecimal(column: 'price'));
        $this->assertSame(0.5, $row->getFloat(column: 'ratio'));
        $this->assertTrue($row->getBool(column: 'active'));
        $this->assertEquals(
            new DateTimeImmutable(datetime: '2026-10-05 08:30:00'),
            $row->getDateTimeImmutable(column: 'created'),
        );
        $this->assertSame(StatusEnum::Blocked, $row->getEnum(column: 'status', enumClass: StatusEnum::class));
    }

    public function testNullableGettersReturnNull(): void
    {
        $row = TableItemTest::tableItem(values: ['ID' => null, 'name' => null])->getRow();

        $this->assertNull($row->getNullableInt(column: 'ID'));
        $this->assertNull($row->getNullableString(column: 'name'));
    }

    public function testNullInNonNullableGetterThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "ID" is NULL');
        TableItemTest::tableItem(values: ['ID' => null])->getRow()->getInt(column: 'ID');
    }

    public function testMissingColumnThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "nope" does not exist');
        TableItemTest::tableItem(values: ['ID' => 1])->getRow()->getInt(column: 'nope');
    }

    public function testWrongTypeThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "ID" has the type float, but expected int');
        TableItemTest::tableItem(values: ['ID' => 1.5])->getRow()->getInt(column: 'ID');
    }

    public function testRawAndRenderedValuesAreUnchanged(): void
    {
        $tableItem = TableItemTest::tableItem(values: ['ID' => 7, 'text' => "<b>A & B</b>\n\"C\"", 'empty' => null]);

        $this->assertSame(7, $tableItem->getRawValue(name: 'ID'));
        $this->assertSame('7', $tableItem->renderValue(name: 'ID'));
        $this->assertSame("&lt;b&gt;A &amp; B&lt;/b&gt;\n\"C\"", $tableItem->renderValue(name: 'text'));
        $this->assertSame(
            "&lt;b&gt;A &amp; B&lt;/b&gt;<br />\n\"C\"",
            $tableItem->renderValue(name: 'text', renderNewLines: true),
        );
        $this->assertSame('', $tableItem->renderValue(name: 'empty'));
        $this->assertSame(['ID' => 7, 'text' => "<b>A & B</b>\n\"C\"", 'empty' => null], $tableItem->data);
    }

    public function testNonScalarValueIsRejectedWhenTheRowIsCreated(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "list" holds a array, but a table row holds scalars');
        TableItemTest::tableItem(values: ['ID' => 1, 'list' => [1]]);
    }

    public function testObjectValueIsRejectedWhenTheRowIsCreated(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "created" holds a DateTimeImmutable');
        TableItemTest::tableItem(values: ['created' => new DateTimeImmutable(datetime: '2026-10-05')]);
    }

    public function testNumericColumnNamesCanBeRead(): void
    {
        $dataObject = new stdClass();
        $dataObject->{'0'} = 'a';
        $dataObject->{'1'} = 'b';

        $tableItem = new TableItem(dataObject: $dataObject);

        $this->assertSame('a', $tableItem->getRawValue(name: '0'));
        $this->assertSame('b', $tableItem->renderValue(name: '1'));
    }

    public function testMissingColumnOfRawValueNamesTheColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The row has no column "nope", it has: ID, name.');
        TableItemTest::tableItem(values: ['ID' => 1, 'name' => 'x'])->getRawValue(name: 'nope');
    }

    public function testRawValueIsTheScalarOfTheColumn(): void
    {
        $tableItem = TableItemTest::tableItem(values: ['i' => 1, 's' => 'a', 'n' => null, 'f' => 1.5, 'b' => false]);

        $this->assertSame(1, $tableItem->getRawValue(name: 'i'));
        $this->assertSame('a', $tableItem->getRawValue(name: 's'));
        $this->assertNull($tableItem->getRawValue(name: 'n'));
        $this->assertSame(1.5, $tableItem->getRawValue(name: 'f'));
        $this->assertFalse($tableItem->getRawValue(name: 'b'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function tableItem(array $values): TableItem
    {
        return new TableItem(dataObject: (object) $values);
    }
}
