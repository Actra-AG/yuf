<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table\filter;

use actra\yuf\core\RequestMethodEnum;
use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\filter\DateFilterField;
use actra\yuf\table\filter\FilterOption;
use actra\yuf\table\filter\OptionsFilterField;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\filter\TextFilterField;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the three field types of a table filter render and which condition they give the query. The values of the
 * fields over several requests are covered by `TableFilterSessionTest`.
 */
final class TableFilterFieldsTest extends TestCase
{
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * @param array<string, string> $post
     */
    private function createFilter(array $post = []): TableFilter
    {
        return new TableFilter(
            identifier: 'f',
            httpRequest: HttpRequestFactory::create(method: RequestMethodEnum::POST, postParameters: $post),
            session: $this->session,
            csrfTokenSource: new InMemoryCsrfTokenSource(),
        );
    }

    /**
     * @param list<FilterOption> $filterOptions
     */
    private static function optionsField(
        TableFilter $filter,
        array $filterOptions,
        string $defaultValue = '',
        bool $chosenEnhancedDropDown = false,
        bool $highlightFieldIfSelected = false,
    ): OptionsFilterField {
        return new OptionsFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'status',
            label: HtmlText::fromHtml(html: 'Status'),
            filterOptions: $filterOptions,
            defaultValue: $defaultValue,
            chosenEnhancedDropDown: $chosenEnhancedDropDown,
            highlightFieldIfSelected: $highlightFieldIfSelected,
        );
    }

    private static function option(string $identifier, string $label = 'Label'): FilterOption
    {
        return new FilterOption(
            identifier: $identifier,
            label: $label,
            whereCondition: new DbQueryData(query: 'status=?', params: [$identifier]),
        );
    }

    public function testFieldIdentifierIsPrefixedByTheFilter(): void
    {
        $filter = $this->createFilter();

        $field = new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );

        $this->assertSame('f_name', $field->identifier);
    }

    public function testTextFieldRendersEncodedValueAndHighlight(): void
    {
        $filter = $this->createFilter(post: ['f_name' => 'a"<b>']);
        $field = new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: '<b>Name</b>'),
            dataTableColumnReference: 'users.name',
            highlightFieldIfSelected: true,
        );

        $this->assertFalse($field->isSelected());
        $rendered = $field->render();
        $this->assertSame(
            '<input type="text" class="text" name="f_name" id="filter-f_name" value="">',
            $rendered->toTemplateData()->html,
        );

        $field->checkInput();

        $this->assertTrue($field->isSelected());
        $this->assertSame('a"<b>', $field->getValue());
        $this->assertSame(
            '<input type="text" class="text highlight" name="f_name" id="filter-f_name" value="a&quot;&lt;b&gt;">',
            $field->render()->toTemplateData()->html,
        );
    }

    public function testTextFieldConditionOfTheColumnAndValue(): void
    {
        $filter = $this->createFilter(post: ['f_name' => 'ann']);
        $field = new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: "users.name\n   ,  users.email",
        );
        $field->checkInput();

        $condition = $field->getWhereCondition();

        $this->assertSame('(users.name , users.email LIKE ? ESCAPE \'!\')', $condition->query);
        $this->assertSame(['%ann%'], $condition->params);
    }

    public function testOptionsFieldRendersTheOptions(): void
    {
        $filter = $this->createFilter(post: ['f_status' => 'blocked']);
        $field = TableFilterFieldsTest::optionsField(
            filter: $filter,
            filterOptions: [
                TableFilterFieldsTest::option(identifier: 'active', label: 'Active'),
                TableFilterFieldsTest::option(identifier: 'blocked', label: 'Blocked'),
            ],
            chosenEnhancedDropDown: true,
            highlightFieldIfSelected: true,
        );
        $this->assertFalse($field->isSelected());
        $this->assertSame(
            '<select name="f_status" id="filter-f_status" class="chosen">' . "\n"
            . '<option value="active">Active</option>' . "\n"
            . '<option value="blocked">Blocked</option>' . "\n"
            . '</select>',
            $field->render()->toTemplateData()->html,
        );

        $field->checkInput();

        $this->assertTrue($field->isSelected());
        $this->assertSame('blocked', $field->selectedValue);
        $this->assertSame(
            '<select name="f_status" id="filter-f_status" class="highlight chosen">' . "\n"
            . '<option value="active">Active</option>' . "\n"
            . '<option value="blocked" selected>Blocked</option>' . "\n"
            . '</select>',
            $field->render()->toTemplateData()->html,
        );
        $condition = $field->getWhereCondition();
        $this->assertSame('status=?', $condition->query);
        $this->assertSame(['blocked'], $condition->params);
    }

    public function testOptionsFieldIgnoresAnUnknownValueAndResetsToTheDefault(): void
    {
        $filter = $this->createFilter(post: ['f_status' => 'unknown']);
        $field = TableFilterFieldsTest::optionsField(
            filter: $filter,
            filterOptions: [TableFilterFieldsTest::option(identifier: 'active')],
            defaultValue: 'active',
        );

        $field->checkInput();
        $this->assertFalse($field->isSelected());

        $field->reset();
        $this->assertSame('active', $field->selectedValue);
        $this->assertTrue($field->isSelected());
    }

    public function testOptionsFieldRejectsDuplicateOptions(): void
    {
        $filter = $this->createFilter();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The filter field f_status has the option "active" twice.');
        TableFilterFieldsTest::optionsField(
            filter: $filter,
            filterOptions: [
                TableFilterFieldsTest::option(identifier: 'active'),
                TableFilterFieldsTest::option(identifier: 'active'),
            ],
        );
    }

    public function testOptionsFieldRejectsADefaultThatIsNoOption(): void
    {
        $filter = $this->createFilter();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The default value "gone" of the filter field f_status is none of its options: active.',
        );
        TableFilterFieldsTest::optionsField(
            filter: $filter,
            filterOptions: [TableFilterFieldsTest::option(identifier: 'active')],
            defaultValue: 'gone',
        );
    }

    public function testOptionsFieldForgetsAStoredOptionThatDoesNotExistAnymore(): void
    {
        $this->session->setSection(
            section: SessionSectionEnum::TABLE_FILTERS,
            data: ['fields' => ['f_status' => ['f_status' => 'removed']]],
        );
        $filter = $this->createFilter();
        $field = TableFilterFieldsTest::optionsField(
            filter: $filter,
            filterOptions: [TableFilterFieldsTest::option(identifier: 'active')],
        );

        $field->init();

        $this->assertFalse($field->isSelected());
        $this->assertSame('', $field->selectedValue);
    }

    public function testFilterOptionLabelIsEncodedUnlessItIsHtml(): void
    {
        $text = new FilterOption(
            identifier: 'a"b',
            label: 'Old <b> & "new"',
            whereCondition: new DbQueryData(query: '1=1', params: []),
        );
        $html = new FilterOption(
            identifier: 'c',
            label: HtmlText::fromHtml(html: '<b>Bold</b>'),
            whereCondition: new DbQueryData(query: '1=1', params: []),
        );

        $this->assertSame(
            '<option value="a&quot;b">Old &lt;b&gt; &amp; &quot;new&quot;</option>',
            $text->render(selectedValue: ''),
        );
        $this->assertSame('<option value="c" selected><b>Bold</b></option>', $html->render(selectedValue: 'c'));
    }

    public function testFilterOptionRendersSelectedOption(): void
    {
        $option = TableFilterFieldsTest::option(identifier: 'a', label: 'A');

        $this->assertSame('<option value="a">A</option>', $option->render(selectedValue: ''));
        $this->assertSame('<option value="a" selected>A</option>', $option->render(selectedValue: 'a'));
    }

    public function testDateFieldRendersTheValueInTheRenderFormat(): void
    {
        $filter = $this->createFilter(post: ['f_since' => '2026-03-01']);
        $field = new DateFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'since',
            label: HtmlText::fromHtml(html: 'Since'),
            dataTableColumnReference: 'users.created',
            dateMustBeSameOrLater: true,
            renderFormat: 'd.m.Y',
            highlightFieldIfSelected: true,
        );
        $this->assertSame(
            '<input type="text" class="text" name="f_since" id="filter-f_since" value="">',
            $field->render()->toTemplateData()->html,
        );

        $field->checkInput();

        $this->assertTrue($field->isSelected());
        $this->assertSame(
            '<input type="text" class="text highlight" name="f_since" id="filter-f_since" value="01.03.2026">',
            $field->render()->toTemplateData()->html,
        );
    }

    public function testDateFieldConditionDependsOnTheDirection(): void
    {
        $cases = [[true, '>=?', '2026-03-01 00:00:00'], [false, '<=?', '2026-03-01 23:59:59']];
        foreach ($cases as [$later, $operator, $param]) {
            $filter = $this->createFilter(post: ['f_since' => '2026-03-01']);
            $field = new DateFilterField(
                parentFilter: $filter,
                filterFieldIdentifier: 'since',
                label: HtmlText::fromHtml(html: 'Since'),
                dataTableColumnReference: 'users.created',
                dateMustBeSameOrLater: $later,
            );
            $field->checkInput();

            $condition = $field->getWhereCondition();

            $this->assertSame('users.created' . $operator, $condition->query);
            $this->assertSame([$param], $condition->params);
        }
    }

    public function testDateFieldKeepsTheTimeOfTheInputAndRejectsInvalidDates(): void
    {
        $cases = [['2026-03-01 10:20', true], ['not a date', false], ['2026-02-31', false], ['', false]];
        foreach ($cases as [$input, $valid]) {
            $filter = $this->createFilter(post: ['f_since' => $input]);
            $field = new DateFilterField(
                parentFilter: $filter,
                filterFieldIdentifier: 'since',
                label: HtmlText::fromHtml(html: 'Since'),
                dataTableColumnReference: 'users.created',
                dateMustBeSameOrLater: true,
            );

            $field->checkInput();

            $this->assertSame($valid, $field->isSelected(), $input);
        }
    }

    public function testFieldWithoutHighlightIsMarkedWhenSelected(): void
    {
        $filter = $this->createFilter(post: ['f_name' => 'x']);
        $field = new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );
        $field->checkInput();

        $rendered = $field->render();

        $this->assertSame('f_name', $rendered->toTemplateData()->identifier);
        $this->assertTrue($rendered->toTemplateData()->highlight);
        $this->assertSame('Name', $rendered->toTemplateData()->label);
    }

    /**
     * @return iterable<string, array{string, bool, ?string}> Input, field for dates from, stored value (null: invalid)
     */
    public static function dateInputProvider(): iterable
    {
        yield 'ISO date from' => ['2026-03-01', true, '2026-03-01 00:00:00'];
        yield 'ISO date to' => ['2026-03-01', false, '2026-03-01 23:59:59'];
        yield 'ISO date with minutes' => ['2026-03-01 10:20', true, '2026-03-01 10:20:00'];
        yield 'ISO date with seconds' => ['2026-03-01 10:20:30', false, '2026-03-01 10:20:30'];
        yield 'Swiss date from' => ['01.03.2026', true, '2026-03-01 00:00:00'];
        yield 'Swiss date to' => ['01.03.2026', false, '2026-03-01 23:59:59'];
        yield 'Swiss date with minutes' => ['01.03.2026 10:20', true, '2026-03-01 10:20:00'];
        yield 'Swiss date with seconds' => ['01.03.2026 10:20:30', true, '2026-03-01 10:20:30'];
        yield 'Swiss date without leading zeros' => ['1.3.2026', true, '2026-03-01 00:00:00'];
        yield 'Swiss date without leading zeros with minutes' => ['1.3.2026 9:05', true, null];
        yield 'Swiss date without leading zeros with time' => ['1.3.2026 09:05', false, '2026-03-01 09:05:00'];
        yield 'leap day' => ['2028-02-29', true, '2028-02-29 00:00:00'];
        yield 'tomorrow' => ['tomorrow', true, null];
        yield 'next monday' => ['next monday', true, null];
        yield 'relative' => ['+1 day', true, null];
        yield 'now' => ['now', true, null];
        yield 'today' => ['today', false, null];
        yield 'date with relative part' => ['2026-03-01 +1 day', true, null];
        yield 'day that does not exist' => ['2026-02-30', true, null];
        yield 'day that does not exist, Swiss' => ['30.02.2026', true, null];
        yield 'leap day of a common year' => ['2026-02-29', true, null];
        yield 'month 13' => ['2026-13-01', true, null];
        yield 'hour 25' => ['2026-03-01 25:00', true, null];
        yield 'minute 60' => ['2026-03-01 10:60', true, null];
        yield 'ISO date without leading zeros' => ['2026-3-1', true, '2026-03-01 00:00:00'];
        yield 'two digit year' => ['26-03-01', true, null];
        yield 'slashes' => ['2026/03/01', true, null];
        yield 'US notation' => ['03/01/2026', true, null];
        yield 'text after the date' => ['2026-03-01 abc', true, null];
        yield 'text before the date' => ['abc 2026-03-01', true, null];
        yield 'surrounding whitespace is trimmed by the request' => [" 2026-03-01\n", true, '2026-03-01 00:00:00'];
        yield 'space inside' => ['2026-03-01  10:20', true, null];
        yield 'timestamp' => ['@86400', true, null];
        yield 'year only' => ['2026', true, null];
        yield 'time zone' => ['2026-03-01 10:20:30 +0100', true, null];
        yield 'ISO with T' => ['2026-03-01T10:20:30', true, null];
    }

    #[DataProvider('dateInputProvider')]
    public function testDateFieldAcceptsTheDateFormatsOnly(string $input, bool $later, ?string $stored): void
    {
        $filter = $this->createFilter(post: ['f_since' => $input]);
        $field = new DateFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'since',
            label: HtmlText::fromHtml(html: 'Since'),
            dataTableColumnReference: 'users.created',
            dateMustBeSameOrLater: $later,
        );

        $field->checkInput();

        $this->assertSame($stored !== null, $field->isSelected());
        if ($stored !== null) {
            $this->assertSame([$stored], $field->getWhereCondition()->params);
        }
    }

    public function testDateFieldAcceptsItsRenderFormat(): void
    {
        $filter = $this->createFilter(post: ['f_since' => '1 March 2026']);
        $field = new DateFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'since',
            label: HtmlText::fromHtml(html: 'Since'),
            dataTableColumnReference: 'users.created',
            dateMustBeSameOrLater: true,
            renderFormat: 'j F Y',
        );

        $field->checkInput();

        $this->assertTrue($field->isSelected());
        $this->assertSame(['2026-03-01 00:00:00'], $field->getWhereCondition()->params);
    }

    public function testDateFieldForgetsTheEarlierValueForInvalidInput(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
        $valid = $this->createFilter(post: ['f_since' => '2026-03-01']);
        $this->dateField(filter: $valid)->checkInput();

        $filter = $this->createFilter(post: ['f_since' => 'tomorrow']);
        $field = $this->dateField(filter: $filter);
        $field->checkInput();

        $this->assertFalse($field->isSelected());
        $this->assertFalse($this->dateField(filter: $this->createFilter())->isSelected());
    }

    public function testDateFieldIgnoresAnUnreadableValueOfTheSession(): void
    {
        $this->session = new Session(
            storage: new ArraySessionStorage(
                data: ['yuf' => ['tableFilters' => ['fields' => ['f_since' => ['f_since' => 'tomorrow']]]]],
            ),
        );

        $this->assertFalse($this->dateField(filter: $this->createFilter())->isSelected());
    }

    private function dateField(TableFilter $filter): DateFilterField
    {
        $field = new DateFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'since',
            label: HtmlText::fromHtml(html: 'Since'),
            dataTableColumnReference: 'users.created',
            dateMustBeSameOrLater: true,
        );
        $filter->addPrimaryField(abstractTableFilterField: $field);

        return $field;
    }

    private static function textField(TableFilter $filter, string $identifier): TextFilterField
    {
        return new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: $identifier,
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );
    }

    public function testFilterRejectsASecondPrimaryFieldWithTheSameIdentifier(): void
    {
        $filter = $this->createFilter();
        $filter->addPrimaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The filter f already has a field with the identifier f_name.');
        $filter->addPrimaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
    }

    public function testFilterRejectsASecondSecondaryFieldWithTheSameIdentifier(): void
    {
        $filter = $this->createFilter();
        $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));

        $this->expectException(InvalidArgumentException::class);
        $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
    }

    public function testFilterRejectsASecondaryFieldWithTheIdentifierOfAPrimaryField(): void
    {
        $filter = $this->createFilter();
        $filter->addPrimaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));

        $this->expectException(InvalidArgumentException::class);
        $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
    }

    public function testFilterRejectsAPrimaryFieldWithTheIdentifierOfASecondaryField(): void
    {
        $filter = $this->createFilter();
        $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));

        $this->expectException(InvalidArgumentException::class);
        $filter->addPrimaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
    }

    public function testFilterKeepsTheFirstFieldWhenASecondOneIsRejected(): void
    {
        $filter = $this->createFilter();
        $first = TableFilterFieldsTest::textField($filter, 'name');
        $filter->addPrimaryField(abstractTableFilterField: $first);

        try {
            $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(['f_name' => $first], $filter->allFilterFields);
    }

    public function testFilterAcceptsFieldsWithDifferentIdentifiers(): void
    {
        $filter = $this->createFilter();

        $filter->addPrimaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'name'));
        $filter->addSecondaryField(abstractTableFilterField: TableFilterFieldsTest::textField($filter, 'city'));

        $this->assertSame(['f_name', 'f_city'], array_keys(array: $filter->allFilterFields));
    }
}
