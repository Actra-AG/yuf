<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\TableHelper;
use actra\yuf\table\TableItem;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\db\SqliteDatabase;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use PHPUnit\Framework\TestCase;

final class TableHelperTest extends TestCase
{
    public function testCreateTableRendersWithTheGivenHeadRenderer(): void
    {
        $table = TableHelper::createTable(identifier: 'items', tableHeadRenderer: new TableHeadRenderer());
        $table->addColumn(abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'a', label: 'A'));
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x']));

        $this->assertSame('items', $table->identifier);
        $this->assertStringContainsString('<thead><tr>' . "\n" . '<th scope="col">A</th>', $table->render());
    }

    public function testCreateTableWithoutHeadRendererUsesThePlainHead(): void
    {
        $table = TableHelper::createTable(identifier: 'items');
        $table->addColumn(abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'a', label: 'A'));
        $table->addDataItem(tableItem: new TableItem(dataObject: (object) ['a' => 'x']));

        $this->assertStringContainsString('<thead><tr>' . "\n" . '<th scope="col">A</th>', $table->render());
    }

    public function testColumnFactories(): void
    {
        $default = TableHelper::createDefaultColumn(
            identifier: 'a',
            label: 'A',
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $date = TableHelper::createDateColumn(identifier: 'b', label: 'B', isSortable: true);
        $actions = TableHelper::createActionsColumn(identifier: 'actions', label: 'Actions');
        $options = TableHelper::createOptionsColumn(
            identifier: 'c',
            label: 'C',
            options: ['x' => 'Xx'],
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $callback = TableHelper::createCallbackColumn(
            identifier: 'd',
            label: 'D',
            callbackFunction: static fn(TableItem $tableItem): string => 'cb',
        );

        $this->assertSame(
            ['a', 'A', true, false],
            [$default->identifier, $default->label, $default->isSortable, $default->sortAscendingByDefault],
        );
        $this->assertSame(['b', true, true], [$date->identifier, $date->isSortable, $date->sortAscendingByDefault]);
        $this->assertSame(['actions', false], [$actions->identifier, $actions->isSortable]);
        $this->assertSame(['action'], $actions->cellCssClasses);
        $this->assertSame([true, false], [$options->isSortable, $options->sortAscendingByDefault]);
        $this->assertSame('<td>cb</td>', $callback->renderCell(tableItem: new TableItem(dataObject: (object) [])));
    }

    public function testCreateDbResultTableBindsTheParameters(): void
    {
        $table = TableHelper::createDbResultTable(
            identifier: 'users',
            db: SqliteDatabase::create(),
            selectQuery: 'SELECT id, name FROM users WHERE id > ?',
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-table-helper-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: HttpRequestFactory::create(),
            session: new Session(storage: new ArraySessionStorage()),
            params: [1],
        );
        $table->addColumn(abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'name', label: 'Name'));

        $this->assertSame(2, $table->getTotalAmount());
        $this->assertSame(
            ['Ben', 'Cleo'],
            array_map(
                callback: static fn(TableItem $tableItem): string => $tableItem->getRow()->getString(column: 'name'),
                array: $table->tableItemCollection->list(),
            ),
        );
    }
}
