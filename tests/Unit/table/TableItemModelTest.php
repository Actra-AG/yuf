<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\db\DbRowValueException;
use actra\yuf\table\TableItemModel;
use actra\yuf\tests\Double\db\StatusEnum;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class TableItemModelTest extends TestCase
{
    public function testGetRowGivesTypedValues(): void
    {
        $row = TableItemModelTest::model(values: [
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
        $row = TableItemModelTest::model(values: ['ID' => null, 'name' => null])->getRow();

        $this->assertNull($row->getNullableInt(column: 'ID'));
        $this->assertNull($row->getNullableString(column: 'name'));
    }

    public function testNullInNonNullableGetterThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessage('Column "ID" is NULL');
        TableItemModelTest::model(values: ['ID' => null])->getRow()->getInt(column: 'ID');
    }

    public function testMissingColumnThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessage('Column "nope" does not exist');
        TableItemModelTest::model(values: ['ID' => 1])->getRow()->getInt(column: 'nope');
    }

    public function testWrongTypeThrows(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessage('Column "ID" has the type float, but expected int');
        TableItemModelTest::model(values: ['ID' => 1.5])->getRow()->getInt(column: 'ID');
    }

    public function testRawAndRenderedValuesAreUnchanged(): void
    {
        $tableItemModel = TableItemModelTest::model(values: ['ID' => 7, 'text' => "<b>A & B</b>\n\"C\"", 'empty' => null]);

        $this->assertSame(7, $tableItemModel->getRawValue(name: 'ID'));
        $this->assertSame('7', $tableItemModel->renderValue(name: 'ID'));
        $this->assertSame("&lt;b&gt;A &amp; B&lt;/b&gt;\n\"C\"", $tableItemModel->renderValue(name: 'text'));
        $this->assertSame(
            "&lt;b&gt;A &amp; B&lt;/b&gt;<br />\n\"C\"",
            $tableItemModel->renderValue(name: 'text', renderNewLines: true),
        );
        $this->assertSame('', $tableItemModel->renderValue(name: 'empty'));
        $this->assertSame(['ID' => 7, 'text' => "<b>A & B</b>\n\"C\"", 'empty' => null], $tableItemModel->data);
    }

    public function testRenderValueOfNonScalarThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Column "list" holds a array');
        TableItemModelTest::model(values: ['list' => [1]])->renderValue(name: 'list');
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function model(array $values): TableItemModel
    {
        return new TableItemModel(dataObject: (object) $values);
    }
}
