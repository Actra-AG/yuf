<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\renderer\SortableTableHeadRenderer;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\table\SmartTable;
use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use actra\yuf\table\TableMessages;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The HTML of a table that is filled by the application (without database), with the plain head renderer.
 */
final class SmartTableTest extends TestCase
{
    private static function createTable(?TableHeadRenderer $tableHeadRenderer = null): SmartTable
    {
        $table = new SmartTable(
            identifier: 'items',
            tableHeadRenderer: $tableHeadRenderer ?? new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'a', label: 'A & B'));
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'b', label: 'B'));

        return $table;
    }

    private static function column(SmartTable $table, string $identifier): AbstractTableColumn
    {
        if (!array_key_exists(key: $identifier, array: $table->columns)) {
            throw new LogicException(message: 'No column ' . $identifier);
        }

        return $table->columns[$identifier];
    }

    public function testRendersHeadRowsAndTotalAmount(): void
    {
        $table = SmartTableTest::createTable();
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => '<x>', 'b' => 1]));
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y', 'b' => 2]));

        $this->assertSame(
            '<div class="table-meta table-meta-header"><p class="search-result">'
            . 'Es wurden <strong>2</strong> Resultate gefunden.</p></div><div class="table-wrap">'
            . '<table class="table">' . "\n"
            . '<thead><tr>' . "\n"
            . '<th scope="col">A & B</th>' . "\n"
            . '<th scope="col">B</th>' . "\n"
            . '</tr></thead><tbody><tr><td>&lt;x&gt;</td>' . "\n"
            . '<td>1</td></tr>' . "\n"
            . '<tr><td>y</td>' . "\n"
            . '<td>2</td></tr></tbody>' . "\n"
            . '</table></div>',
            $table->render(),
        );
    }

    public function testEmptyTableShowsTheNoDataText(): void
    {
        $this->assertSame(
            '<p class="no-entry">Es wurden keine Einträge gefunden.</p>',
            SmartTableTest::createTable()->render(),
        );
    }

    public function testOneResultHasItsOwnMessage(): void
    {
        $table = SmartTableTest::createTable();
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y', 'b' => 2]));

        $this->assertStringContainsString(
            '<p class="search-result">Es wurde <strong>1</strong> Resultat gefunden.</p>',
            $table->render(),
        );
    }

    public function testTotalAmountUsesTheApostropheAsThousandsSeparator(): void
    {
        $table = SmartTableTest::createTable();
        for ($i = 0; $i < 1234; $i++) {
            $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y', 'b' => $i]));
        }

        $this->assertSame(1234, $table->getTotalAmount());
        $this->assertStringContainsString('<strong>1\'234</strong> Resultate', $table->render());
    }

    public function testTextsAndTemplatesCanBeReplaced(): void
    {
        $table = SmartTableTest::createTable();
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x', 'b' => 1]));
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y', 'b' => 2]));
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'z', 'b' => 3]));
        $table->addCssClass(className: 'wide');
        $table->fullHtml = SmartTable::TOTAL_AMOUNT . SmartTable::TABLE;
        $table->totalAmountHtml = '<b>' . SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER . '</b>';
        $table->tableHtml = SmartTable::TABLE_BODY . '|' . SmartTable::TABLE_HEADER;
        $table->oddRowHtml = '<tr class="odd">' . SmartTable::CELLS . '</tr>';
        $table->evenRowHtml = '<tr class="even">' . SmartTable::CELLS . '</tr>';

        $this->assertSame(
            '<b>Es wurden <strong>3</strong> Resultate gefunden.</b><table class="table wide">' . "\n"
            . '<tr class="odd"><td>x</td>' . "\n" . '<td>1</td></tr>' . "\n"
            . '<tr class="even"><td>y</td>' . "\n" . '<td>2</td></tr>' . "\n"
            . '<tr class="odd"><td>z</td>' . "\n" . '<td>3</td></tr>'
            . '|<tr>' . "\n"
            . '<th scope="col">A & B</th>' . "\n"
            . '<th scope="col">B</th>' . "\n"
            . '</tr>' . "\n"
            . '</table>',
            $table->render(),
        );
    }

    public function testMessagesChangeTheTexts(): void
    {
        $table = new SmartTable(
            identifier: 'items',
            tableHeadRenderer: new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
            messages: new TableMessages(
                noData: 'No <entries> found.',
                oneResult: 'Found [amount] result.',
                numResults: '[amount] results & more',
            ),
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'a', label: 'A'));
        $this->assertSame('<p class="no-entry">No &lt;entries&gt; found.</p>', $table->render());

        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x']));
        $this->assertStringContainsString(
            '<p class="search-result">Found <strong>1</strong> result.</p>',
            $table->render(),
        );

        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y']));
        $this->assertStringContainsString(
            '<p class="search-result"><strong>2</strong> results &amp; more</p>',
            $table->render(),
        );
    }

    public function testEnglishMessages(): void
    {
        $table = new SmartTable(
            identifier: 'items',
            tableHeadRenderer: new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
            messages: TableMessages::english(),
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'a', label: 'A'));
        $this->assertSame('<p class="no-entry">No entries found.</p>', $table->render());

        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x']));
        $this->assertStringContainsString('<strong>1</strong> result found.', $table->render());
    }

    public function testDefaultMessagesAreGerman(): void
    {
        $table = SmartTableTest::createTable();

        $this->assertSame('<p class="no-entry">Es wurden keine Einträge gefunden.</p>', $table->render());

        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x', 'b' => 1]));
        $this->assertStringContainsString('Es wurde <strong>1</strong> Resultat gefunden.', $table->render());
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'y', 'b' => 2]));
        $this->assertStringContainsString('Es wurden <strong>2</strong> Resultate gefunden.', $table->render());
    }

    public function testColumnsKeepTheirOrderAndKnowTheirTable(): void
    {
        $table = SmartTableTest::createTable();

        $this->assertSame(['a', 'b'], array_keys(array: $table->columns));
        $this->assertSame('items', SmartTableTest::column(table: $table, identifier: 'a')->tableIdentifier);
    }

    public function testColumnIdentifierMustBeUnique(): void
    {
        $table = SmartTableTest::createTable();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There is already a column with the same identifier a');
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'a', label: 'Again'));
    }

    public function testHeadOfColumnsWithCssClassesAndWithoutScope(): void
    {
        $table = SmartTableTest::createTable(
            tableHeadRenderer: new class extends TableHeadRenderer {
                #[Override]
                protected bool $addColumnScopeAttribute = false;
            },
        );
        SmartTableTest::column(table: $table, identifier: 'a')->addColumnCssClass(className: 'wide');
        SmartTableTest::column(table: $table, identifier: 'a')->addColumnCssClass(className: 'wide');
        SmartTableTest::column(table: $table, identifier: 'a')->addColumnCssClass(className: 'right');
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x', 'b' => 1]));

        $this->assertStringContainsString(
            '<thead><tr>' . "\n" . '<th class="wide right">A & B</th>' . "\n" . '<th>B</th>' . "\n" . '</tr></thead>',
            $table->render(),
        );
    }

    public function testCellsHaveTheirCssClasses(): void
    {
        $table = SmartTableTest::createTable();
        SmartTableTest::column(table: $table, identifier: 'b')->addCellCssClass(className: 'number');
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x', 'b' => 1]));

        $this->assertStringContainsString('<td>x</td>' . "\n" . '<td class="number">1</td>', $table->render());
    }

    public function testCellValuesAreEscapedAndNotReplacedAsPlaceholders(): void
    {
        $table = SmartTableTest::createTable();
        $table->addDataItem(
            tableItem: new TableItem(
                dataObject: (object) ['a' => '<script>[table]</script>', 'b' => '[tableBody][totalAmount]'],
            ),
        );

        $html = $table->render();

        $this->assertStringContainsString('<td>&lt;script&gt;[table]&lt;/script&gt;</td>', $html);
        $this->assertStringContainsString('<td>[tableBody][totalAmount]</td>', $html);
    }

    public function testSortableHeadRendererNeedsADbResultTable(): void
    {
        $table = SmartTableTest::createTable(tableHeadRenderer: new SortableTableHeadRenderer());

        $this->expectException(LogicException::class);
        $table->render();
    }
}
