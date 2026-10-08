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
                new OptionsColumn(identifier: 'status', label: 'Status', options: ['blocked' => 'Gesperrt'], isOrderAble: false),
                '<td>Gesperrt</td>',
            ],
            'options unknown' => [
                new OptionsColumn(identifier: 'unknownStatus', label: 'Status', options: ['blocked' => 'Gesperrt'], isOrderAble: false),
                '<td>deleted</td>',
            ],
            'strip html tags' => [new StripHtmlTagsColumn(identifier: 'html', label: 'Text'), '<td>Text &amp; more</td>'],
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
                    callbackFunction: fn(TableItem $tableItem): string => (string) ($tableItem->getRow()->getInt(column: 'ID') * 2),
                ),
                '<td>84</td>',
            ],
        ];
    }

    #[DataProvider('columnProvider')]
    public function testColumnRendersAsBefore(AbstractTableColumn $abstractTableColumn, string $expectedHtml): void
    {
        $this->assertSame($expectedHtml, $abstractTableColumn->renderCell(tableItem: TableColumnRenderingTest::model()));
    }

    public function testActionsColumnReplacesPlaceholdersEncoded(): void
    {
        $actionsColumn = new ActionsColumn();
        $actionsColumn->addEditActionLink(linkTarget: 'edit/[ID]/?n=[name]');
        $actionsColumn->addDeleteLink(linkTarget: 'delete/[ID]/');

        $this->assertSame(
            '<td class="td-action"><div class="td-action-group">'
            . '<a href="edit/42/?n=&lt;b&gt;Müller &amp; Co&lt;/b&gt;' . "\n" . '&quot;Zürich&quot;" class="edit">Bearbeiten</a>'
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

    private static function model(): TableItem
    {
        return new TableItem(dataObject: (object) TableColumnRenderingTest::ROW);
    }
}
