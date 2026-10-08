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
}
