<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\html\HtmlText;
use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\column\ActionsColumn;
use actra\yuf\table\column\BooleanColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\column\FileSizeColumn;
use actra\yuf\table\column\OptionsColumn;
use actra\yuf\table\column\StripHtmlTagsColumn;
use actra\yuf\table\TableItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Characterization of the built-in columns: their output must not change with the typed row access.
 */
final class TableColumnRenderingTest extends TestCase
{
    private const array ROW = [
        'ID' => 42,
        'name' => "<b>Müller & Co</b>\n\"Zürich\"",
        'nothing' => null,
        'created' => '2026-10-05 08:30:00',
        'emptyDate' => '',
        'active' => 1,
        'inactive' => 0,
        'flagText' => 'maybe',
        'size' => 1536,
        'status' => 'blocked',
        'unknownStatus' => 'deleted',
        'html' => '<p>Text & <i>more</i></p>',
        'locked' => 'yes',
    ];

    /**
     * @return array<string, array{AbstractTableColumn, string}>
     */
    public static function columnProvider(): array
    {
        $dateColumnWithEmptyText = new DateColumn(identifier: 'emptyDate', label: 'Date');
        $dateColumnWithEmptyText->setEmptyValueText(htmlText: HtmlText::fromHtml(html: '-'));
        $dateFormatColumn = new DateColumn(identifier: 'created', label: 'Date');
        $dateFormatColumn->format = 'd.m.Y';
        $defaultColumnWithoutNewLines = new DefaultColumn(identifier: 'name', label: 'Name');
        $defaultColumnWithoutNewLines->renderNewLines = false;
        $cssColumn = new DefaultColumn(identifier: 'ID', label: 'ID');
        $cssColumn->addCellCssClass(className: 'number');

        return [
            'default' => [
                new DefaultColumn(identifier: 'name', label: 'Name'),
                "<td>&lt;b&gt;Müller &amp; Co&lt;/b&gt;<br />\n\"Zürich\"</td>",
            ],
            'default without new lines' => [
                $defaultColumnWithoutNewLines,
                "<td>&lt;b&gt;Müller &amp; Co&lt;/b&gt;\n\"Zürich\"</td>",
            ],
            'default int' => [new DefaultColumn(identifier: 'ID', label: 'ID'), '<td>42</td>'],
            'default null' => [new DefaultColumn(identifier: 'nothing', label: 'Nothing'), '<td></td>'],
            'default with css class' => [$cssColumn, '<td class="number">42</td>'],
            'date' => [new DateColumn(identifier: 'created', label: 'Date'), '<td>05.10.2026 08:30:00</td>'],
            'date format' => [$dateFormatColumn, '<td>05.10.2026</td>'],
            'date empty' => [new DateColumn(identifier: 'emptyDate', label: 'Date'), '<td></td>'],
            'date null' => [new DateColumn(identifier: 'nothing', label: 'Date'), '<td></td>'],
            'date empty text' => [$dateColumnWithEmptyText, '<td>-</td>'],
            'boolean true' => [new BooleanColumn(identifier: 'active', label: 'Active'), '<td>Ja</td>'],
            'boolean false' => [new BooleanColumn(identifier: 'inactive', label: 'Active'), '<td>Nein</td>'],
            'boolean null' => [new BooleanColumn(identifier: 'nothing', label: 'Active'), '<td></td>'],
            'boolean other' => [new BooleanColumn(identifier: 'flagText', label: 'Active'), '<td>maybe</td>'],
            'file size' => [new FileSizeColumn(identifier: 'size', label: 'Size'), '<td>1.5 KB</td>'],
            'file size null' => [new FileSizeColumn(identifier: 'nothing', label: 'Size'), '<td></td>'],
            'options known' => [
                new OptionsColumn(
                    identifier: 'status',
                    label: 'Status',
                    options: ['blocked' => 'Gesperrt'],
                    isSortable: false,
                ),
                '<td>Gesperrt</td>',
            ],
            'options unknown' => [
                new OptionsColumn(
                    identifier: 'unknownStatus',
                    label: 'Status',
                    options: ['blocked' => 'Gesperrt'],
                    isSortable: false,
                ),
                '<td>deleted</td>',
            ],
            'strip html tags' => [
                new StripHtmlTagsColumn(identifier: 'html', label: 'Text'),
                '<td>Text &amp; more</td>',
            ],
            'callback raw' => [
                new CallbackColumn(
                    identifier: 'cb',
                    label: 'Callback',
                    callbackFunction: fn(TableItem $tableItem): string => '#' . $tableItem->renderValue(name: 'ID'),
                ),
                '<td>#42</td>',
            ],
            'callback typed' => [
                new CallbackColumn(
                    identifier: 'cb',
                    label: 'Callback',
                    callbackFunction: fn(TableItem $tableItem): string => (string) (
                        $tableItem->getRow()->getInt(column: 'ID') * 2
                    ),
                ),
                '<td>84</td>',
            ],
        ];
    }

    #[DataProvider('columnProvider')]
    public function testColumnRendersAsBefore(AbstractTableColumn $abstractTableColumn, string $expectedHtml): void
    {
        $this->assertSame(
            $expectedHtml,
            $abstractTableColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnReplacesPlaceholdersEncoded(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addEditActionLink(linkTarget: 'edit/[ID]/?n=[name]');
        $actionsColumn->addDeleteLink(linkTarget: 'delete/[ID]/');

        $this->assertSame(
            '<td class="td-action"><div class="td-action-group">'
            . '<a href="edit/42/?n=&lt;b&gt;Müller &amp; Co&lt;/b&gt;' . "\n"
            . '&quot;Zürich&quot;" class="edit">Bearbeiten</a>'
            . "\n" . '<a href="delete/42/" class="delete">Löschen</a></div></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnHidesDeleteLinkByRawValue(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addDeleteLink(linkTarget: 'delete/[ID]/', hideField: 'locked', hideValue: 'yes');

        $this->assertSame(
            '<td class="td-action"></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnWithOneLinkHasNoGroup(): void
    {
        $actionsColumn = new ActionsColumn(identifier: 'actions', label: 'Actions', cellCssClass: 'actions');
        $actionsColumn->addEditActionLink(linkTarget: 'edit/[ID]/', label: 'Edit');

        $this->assertSame(
            '<td class="actions"><a href="edit/42/" class="edit">Edit</a></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnWithoutLinksIsEmpty(): void
    {
        $this->assertSame(
            '<td class="td-action"></td>',
            new ActionsColumn()->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnIndividualLinksAndGroupClass(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->tdActionGroupClass = 'group';
        $actionsColumn->addIndividualActionLink(identifier: 'show', linkHtml: '<a href="show/[ID]/">[status]</a>');
        $actionsColumn->addDeleteLink(linkTarget: 'delete/[ID]/', hideField: 'locked', hideValue: 'no');

        $this->assertSame(
            '<td class="td-action"><div class="group"><a href="show/42/">blocked</a>' . "\n"
            . '<a href="delete/42/" class="delete">Löschen</a></div></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnPlaceholdersOfNullAndNumbers(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addIndividualActionLink(identifier: 'x', linkHtml: '<a href="[nothing]|[ID]|[size]">x</a>');

        $this->assertSame(
            '<td class="td-action"><a href="|42|1536">x</a></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testSortableColumnGetsItsCssClass(): void
    {
        $defaultColumn = new DefaultColumn(identifier: 'ID', label: 'ID', isSortable: true);
        $defaultColumn->addColumnCssClass(className: 'number');
        $defaultColumn->addColumnCssClass(className: 'sort');

        $this->assertSame(['sort', 'number'], $defaultColumn->columnCssClasses);
        $this->assertSame([], $defaultColumn->cellCssClasses);
        $this->assertSame('ID', $defaultColumn->identifier);
        $this->assertTrue($defaultColumn->sortAscendingByDefault);
    }

    public function testActionsColumnLabelsAreEncodedButLinkHtmlIsNot(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addEditActionLink(linkTarget: 'edit/[ID]/', label: '<b>Edit</b> & "more"');
        $actionsColumn->addIndividualActionLink(identifier: 'x', linkHtml: '<a href="x"><b>X</b></a>');

        $this->assertSame(
            '<td class="td-action"><div class="td-action-group">'
            . '<a href="edit/42/" class="edit">&lt;b&gt;Edit&lt;/b&gt; &amp; "more"</a>' . "\n"
            . '<a href="x"><b>X</b></a></div></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnPlaceholdersInValuesAreNotReplacedAgain(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addIndividualActionLink(identifier: 'x', linkHtml: '<a href="[name]|[secret]">x</a>');

        $this->assertSame(
            '<td class="td-action"><a href="[secret]|s3cret">x</a></td>',
            $actionsColumn->renderCell(
                tableItem: new TableItem(dataObject: (object) ['name' => '[secret]', 'secret' => 's3cret']),
            ),
        );
    }

    public function testActionsColumnDoesNotReplacePlaceholdersOfNonScalarValues(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addIndividualActionLink(identifier: 'x', linkHtml: '<a href="[list]|[ID]">x</a>');

        $this->assertSame(
            '<td class="td-action"><a href="[list]|7">x</a></td>',
            $actionsColumn->renderCell(tableItem: new TableItem(dataObject: (object) ['list' => [1], 'ID' => 7])),
        );
    }

    public function testActionsColumnHidesDeleteLinkOfANumberLikeItsTextValue(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addDeleteLink(linkTarget: 'delete/[ID]/', hideField: 'ID', hideValue: '42');

        $this->assertSame(
            '<td class="td-action"></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testActionsColumnHidesDeleteLinkOfANullValue(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addDeleteLink(linkTarget: 'delete/', hideField: 'nothing');

        $this->assertSame(
            '<td class="td-action"></td>',
            $actionsColumn->renderCell(tableItem: TableColumnRenderingTest::model()),
        );
    }

    public function testOptionsColumnEncodesLabelsUnlessTheyAreHtml(): void
    {
        $options = [
            'a' => '<b>A</b> & "x"',
            'b' => HtmlText::fromHtml(html: '<b>B</b>'),
            7 => 'Seven',
        ];

        $this->assertSame(
            '<td>&lt;b&gt;A&lt;/b&gt; &amp; "x"</td>',
            new OptionsColumn(identifier: 'k', label: 'K', options: $options, isSortable: false)->renderCell(
                tableItem: TableColumnRenderingTest::item(values: ['k' => 'a']),
            ),
        );
        $this->assertSame(
            '<td><b>B</b></td>',
            new OptionsColumn(identifier: 'k', label: 'K', options: $options, isSortable: false)->renderCell(
                tableItem: TableColumnRenderingTest::item(values: ['k' => 'b']),
            ),
        );
        $this->assertSame(
            '<td>Seven</td>',
            new OptionsColumn(identifier: 'k', label: 'K', options: $options, isSortable: false)->renderCell(
                tableItem: TableColumnRenderingTest::item(values: ['k' => '7']),
            ),
            'a numeric string finds the integer key',
        );
    }

    public function testOptionsColumnRendersOtherTypesAsText(): void
    {
        $column = new OptionsColumn(identifier: 'k', label: 'K', options: ['1' => 'One'], isSortable: false);

        $this->assertSame('<td>1.5</td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['k' => 1.5])));
        $this->assertSame('<td></td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['k' => null])));
        $this->assertSame('<td>One</td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['k' => 1])));
    }

    public function testFileSizeColumnTakesNumericStrings(): void
    {
        $column = new FileSizeColumn(identifier: 'size', label: 'Size');

        $this->assertSame('<td>1.5 KB</td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['size' => '1536'])));
        $this->assertSame('<td>1.5 KB</td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['size' => 1536.0])));
    }

    public function testFileSizeColumnRejectsOtherValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "size" holds a string, which is no size in bytes.');
        new FileSizeColumn(identifier: 'size', label: 'Size')->renderCell(
            tableItem: TableColumnRenderingTest::item(values: ['size' => 'big']),
        );
    }

    public function testStripHtmlTagsColumnOfNullAndNumbers(): void
    {
        $column = new StripHtmlTagsColumn(identifier: 'v', label: 'V');

        $this->assertSame('<td></td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['v' => null])));
        $this->assertSame('<td>42</td>', $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['v' => 42])));
    }

    public function testDateColumnNamesTheColumnOfAnInvalidDate(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "created" does not hold a date');
        new DateColumn(identifier: 'created', label: 'Date')->renderCell(
            tableItem: TableColumnRenderingTest::item(values: ['created' => 'yesterday-ish']),
        );
    }

    public function testNonScalarValueOfAColumnThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "list" holds a array, which cannot be rendered.');
        new DefaultColumn(identifier: 'list', label: 'List')->renderCell(
            tableItem: new TableItem(dataObject: (object) ['list' => [1]]),
        );
    }

    public function testCallbackColumnOutputIsNotEncoded(): void
    {
        $column = new CallbackColumn(
            identifier: 'cb',
            label: 'Callback',
            callbackFunction: static fn(TableItem $tableItem): string => '<b>'
                . $tableItem->renderValue(name: 'name') . '</b>',
        );

        $this->assertSame(
            '<td><b>&lt;i&gt;</b></td>',
            $column->renderCell(tableItem: TableColumnRenderingTest::item(values: ['name' => '<i>'])),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function item(array $values): TableItem
    {
        return new TableItem(dataObject: (object) $values);
    }

    private static function model(): TableItem
    {
        return new TableItem(dataObject: (object) TableColumnRenderingTest::ROW);
    }
}
