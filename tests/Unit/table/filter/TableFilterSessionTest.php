<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table\filter;

use actra\yuf\core\RequestMethodEnum;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbQueryData;
use actra\yuf\db\FrameworkDb;
use actra\yuf\html\HtmlText;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\filter\DateFilterField;
use actra\yuf\table\filter\FilterOption;
use actra\yuf\table\filter\OptionsFilterField;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\filter\TextFilterField;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\table\ExposingTableFilter;
use actra\yuf\tests\Double\table\StaticTableRegistries;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): what a `TableFilter`
 * with a text, an options and a date field remembers across requests, when it forgets it, the CSRF requirement for
 * new input and the behaviour without session. `TableFilterCsrfTest` covers the CSRF check with a recording field;
 * this class covers the stored values.
 *
 * A request is simulated by building the filter and the table again with the same identifiers and the `$_SESSION`
 * of the previous request. The static identifier registries have no reset method, so `StaticTableRegistries::reset()`
 * empties them through reflection (removed in step 2 together with the registries).
 *
 * The behaviour tests only use `request()`, `submit()` and the getters of the fields; the keys and value shapes of
 * the storage are pinned in `testStorageLayout…()` only (they may change in step 2).
 */
final class TableFilterSessionTest extends TestCase
{
    private const string FILTER = 'sessionFilter';
    private const string TABLE = 'sessionFilterTable';
    private const string TEXT = 'sessionFilter_name';
    private const string OPTIONS = 'sessionFilter_status';
    private const string DATE = 'sessionFilter_since';

    #[Override]
    protected function setUp(): void
    {
        StaticTableRegistries::reset();
        $_SESSION = ['csrftoken' => 'expected-token'];
    }

    #[Override]
    protected function tearDown(): void
    {
        StaticTableRegistries::reset();
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    /**
     * One request of the user to a page with a table and its filter.
     *
     * @param array<string, string> $query
     * @param array<string, string> $post
     *
     * @return array{filter: TableFilter, table: DbResultTable, text: TextFilterField, options: OptionsFilterField, date: DateFilterField}
     */
    private function request(
        RequestMethodEnum $method = RequestMethodEnum::GET,
        array $query = [],
        array $post = [],
        string $optionsDefault = '',
    ): array {
        StaticTableRegistries::reset();
        $httpRequest = HttpRequestFactory::create(method: $method, queryParameters: $query, postParameters: $post);
        $filter = new TableFilter(identifier: TableFilterSessionTest::FILTER, httpRequest: $httpRequest);
        $text = new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );
        $options = new OptionsFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'status',
            label: HtmlText::fromHtml(html: 'Status'),
            filterOptions: [
                new FilterOption(identifier: 'all', label: 'All', whereCondition: new DbQueryData(query: '1=1', params: [])),
                new FilterOption(
                    identifier: 'active',
                    label: 'Active',
                    whereCondition: new DbQueryData(query: 'users.status=?', params: ['active']),
                ),
                new FilterOption(
                    identifier: 'blocked',
                    label: 'Blocked',
                    whereCondition: new DbQueryData(query: 'users.status=?', params: ['blocked']),
                ),
            ],
            defaultValue: $optionsDefault,
        );
        $date = new DateFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'since',
            label: HtmlText::fromHtml(html: 'Since'),
            dataTableColumnReference: 'users.created',
            dateMustBeSameOrLater: true,
        );
        $filter->addPrimaryField(abstractTableFilterField: $text);
        $filter->addPrimaryField(abstractTableFilterField: $options);
        $filter->addSecondaryField(abstractTableFilterField: $date);
        $table = new DbResultTable(
            identifier: TableFilterSessionTest::TABLE,
            db: TableFilterSessionTest::createStub(FrameworkDb::class),
            dbQuery: TableFilterSessionTest::createStub(DbQuery::class),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-table-filter-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: $httpRequest,
            tableFilter: $filter,
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'id', label: 'Id', isSortable: true));
        $table->fillBySelectQuery();

        return ['filter' => $filter, 'table' => $table, 'text' => $text, 'options' => $options, 'date' => $date];
    }

    /**
     * The user sends the filter form (POST with the CSRF token of the session).
     *
     * @param array<string, string> $values field identifier => value
     *
     * @return array{filter: TableFilter, table: DbResultTable, text: TextFilterField, options: OptionsFilterField, date: DateFilterField}
     */
    private function submit(array $values, string $token = 'expected-token', string $optionsDefault = ''): array
    {
        return $this->request(
            method: RequestMethodEnum::POST,
            query: [TableFilterSessionTest::FILTER => '', 'find' => ''],
            post: ['csrftoken' => $token] + $values,
            optionsDefault: $optionsDefault,
        );
    }

    public function testStorageLayoutIsOneArrayPerFilterFieldBelowTheKeyColumnFilter(): void
    {
        $this->submit(
            values: [
                TableFilterSessionTest::TEXT => 'ann',
                TableFilterSessionTest::OPTIONS => 'active',
                TableFilterSessionTest::DATE => '2026-03-01',
            ],
        );

        $this->assertSame(
            [
                'csrftoken' => 'expected-token',
                'columnFilter' => [
                    'sessionFilter_name' => ['sessionFilter_name' => 'ann'],
                    'sessionFilter_status' => ['sessionFilter_status' => 'active'],
                    'sessionFilter_since' => ['sessionFilter_since' => '2026-03-01 00:00:00'],
                ],
                'table' => [
                    'sessionFilterTable' => [
                        'pagination_page' => '1',
                        'sort_column' => 'id',
                        'sort_direction' => 'ASC',
                    ],
                ],
            ],
            $_SESSION,
        );
    }

    public function testStorageLayoutOfTheFirstRequestHasEmptyArraysWrittenOnRead(): void
    {
        $this->request();

        $this->assertSame(
            [
                'csrftoken' => 'expected-token',
                'columnFilter' => [
                    'sessionFilter_name' => [],
                    'sessionFilter_status' => [],
                    'sessionFilter_since' => [],
                ],
                'table' => [
                    'sessionFilterTable' => [
                        'sort_column' => 'id',
                        'sort_direction' => 'ASC',
                        'pagination_page' => '1',
                    ],
                ],
            ],
            $_SESSION,
        );
    }

    /**
     * The protected accessors of `TableFilter` for own filters use the key `tableFilter`; yuf's own filter fields use
     * `columnFilter` instead, so nothing of yuf writes `tableFilter`.
     */
    public function testStorageLayoutOfTheProtectedAccessorsOfTableFilter(): void
    {
        $filter = new ExposingTableFilter(identifier: 'own', httpRequest: HttpRequestFactory::create());

        $filter->write(index: 'key', value: 'value');

        $this->assertSame('value', $filter->read(index: 'key'));
        $this->assertNull($filter->read(index: 'unknown'));
        $this->assertSame(['own' => ['key' => 'value']], $_SESSION['tableFilter'] ?? null);
    }

    public function testFilterStartsWithoutValuesAndIsNotApplied(): void
    {
        $result = $this->request();

        $this->assertSame('', $result['text']->getValue());
        $this->assertSame('', $result['options']->selectedValue);
        $this->assertFalse($result['text']->isSelected());
        $this->assertFalse($result['options']->isSelected());
        $this->assertFalse($result['date']->isSelected());
        $this->assertFalse($result['filter']->filtersApplied);
    }

    public function testSubmittedValuesAreRememberedForTheNextRequests(): void
    {
        $submitted = $this->submit(
            values: [
                TableFilterSessionTest::TEXT => 'ann',
                TableFilterSessionTest::OPTIONS => 'active',
                TableFilterSessionTest::DATE => '2026-03-01',
            ],
        );

        $this->assertSame('ann', $submitted['text']->getValue());
        $this->assertSame('active', $submitted['options']->selectedValue);
        $this->assertTrue($submitted['date']->isSelected());
        $this->assertTrue($submitted['filter']->filtersApplied);
        for ($nextRequest = 1; $nextRequest <= 2; $nextRequest++) {
            $next = $this->request();

            $this->assertSame('ann', $next['text']->getValue());
            $this->assertSame('active', $next['options']->selectedValue);
            $this->assertTrue($next['date']->isSelected());
            $this->assertSame(['2026-03-01 00:00:00'], $next['date']->getWhereCondition()->params);
            $this->assertTrue($next['filter']->filtersApplied);
        }
    }

    public function testRememberedValuesAreRenderedInTheFields(): void
    {
        $this->submit(
            values: [
                TableFilterSessionTest::TEXT => 'a"nn',
                TableFilterSessionTest::OPTIONS => 'blocked',
                TableFilterSessionTest::DATE => '2026-03-01 10:20:30',
            ],
        );

        $html = $this->renderFilter(filter: $this->request()['filter']);

        $this->assertStringContainsString('value="a&quot;nn"', $html);
        $this->assertStringContainsString('<option value="blocked" selected>', $html);
        $this->assertStringContainsString('value="01.03.2026 10:20:30"', $html);
    }

    public function testSubmittingTheFilterReplacesAllValuesIncludingThoseNotSent(): void
    {
        $this->submit(
            values: [TableFilterSessionTest::TEXT => 'ann', TableFilterSessionTest::OPTIONS => 'active'],
        );

        $result = $this->submit(values: [TableFilterSessionTest::TEXT => 'bob']);

        $this->assertSame('bob', $result['text']->getValue());
        $this->assertSame('', $result['options']->selectedValue);
        $this->assertSame('bob', $this->request()['text']->getValue());
        $this->assertSame('', $this->request()['options']->selectedValue);
    }

    public function testUnknownOptionResetsTheOptionsFieldToItsDefault(): void
    {
        $this->submit(values: [TableFilterSessionTest::OPTIONS => 'active'], optionsDefault: 'all');

        $result = $this->submit(values: [TableFilterSessionTest::OPTIONS => 'unknown'], optionsDefault: 'all');

        $this->assertSame('all', $result['options']->selectedValue);
        $this->assertSame('all', $this->request(optionsDefault: 'all')['options']->selectedValue);
    }

    public function testResetParameterForgetsAllValuesAndGoesBackToTheFirstPage(): void
    {
        $this->submit(
            values: [
                TableFilterSessionTest::TEXT => 'ann',
                TableFilterSessionTest::OPTIONS => 'active',
                TableFilterSessionTest::DATE => '2026-03-01',
            ],
        );
        $this->request()['table']->setCurrentPaginationPage(page: 4);

        $reset = $this->request(query: ['reset' => '']);

        $this->assertSame('', $reset['text']->getValue());
        $this->assertFalse($reset['options']->isSelected());
        $this->assertFalse($reset['filter']->filtersApplied);
        for ($nextRequest = 1; $nextRequest <= 2; $nextRequest++) {
            $next = $this->request();

            $this->assertSame('', $next['text']->getValue());
            $this->assertFalse($next['options']->isSelected());
            $this->assertFalse($next['date']->isSelected());
            $this->assertSame(1, $next['table']->getCurrentPaginationPage());
        }
    }

    public function testResetParameterRestoresTheDefaultOfTheOptionsField(): void
    {
        $this->submit(values: [TableFilterSessionTest::OPTIONS => 'blocked'], optionsDefault: 'active');

        $reset = $this->request(query: ['reset' => ''], optionsDefault: 'active');

        $this->assertSame('active', $reset['options']->selectedValue);
        $this->assertTrue($reset['filter']->filtersApplied);
    }

    public function testResetParameterNeedsNoCsrfToken(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);
        unset($_SESSION['csrftoken']);

        $reset = $this->request(query: ['reset' => '']);

        $this->assertSame('', $reset['text']->getValue());
    }

    public function testSubmittingTheFilterGoesBackToTheFirstPage(): void
    {
        $this->request()['table']->setCurrentPaginationPage(page: 4);

        $submitted = $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);

        $this->assertSame(1, $submitted['table']->getCurrentPaginationPage());
    }

    public function testEmptyInputClearsTheDateAndAnInvalidDateIsForgotten(): void
    {
        $this->submit(values: [TableFilterSessionTest::DATE => '2026-03-01']);

        $invalid = $this->submit(values: [TableFilterSessionTest::DATE => 'not a date']);
        $this->submit(values: [TableFilterSessionTest::DATE => '2026-03-01']);
        $empty = $this->submit(values: [TableFilterSessionTest::DATE => '']);

        $this->assertFalse($invalid['date']->isSelected());
        $this->assertFalse($empty['date']->isSelected());
        $this->assertFalse($this->request()['date']->isSelected());
    }

    public function testDateWithoutTimeGetsTheStartOfTheDayForALaterThanFieldAndIsRememberedAsSuch(): void
    {
        $this->submit(values: [TableFilterSessionTest::DATE => '2026-03-01']);

        $this->assertSame(
            ['users.created>=?', ['2026-03-01 00:00:00']],
            [
                $this->request()['date']->getWhereCondition()->query,
                $this->request()['date']->getWhereCondition()->params,
            ],
        );
    }

    public function testFilterInputWithoutTheCsrfTokenOfTheSessionKeepsTheRememberedValues(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann', TableFilterSessionTest::OPTIONS => 'active']);

        $results = [
            $this->submit(values: [TableFilterSessionTest::TEXT => 'bob'], token: 'wrong'),
            $this->submit(values: [TableFilterSessionTest::TEXT => 'bob'], token: ''),
            $this->request(
                method: RequestMethodEnum::POST,
                query: [TableFilterSessionTest::FILTER => ''],
                post: [TableFilterSessionTest::TEXT => 'bob'],
            ),
            $this->request(
                query: [
                    TableFilterSessionTest::FILTER => '',
                    'csrftoken' => 'expected-token',
                    TableFilterSessionTest::TEXT => 'bob',
                ],
            ),
        ];

        foreach ($results as $result) {
            $this->assertSame('ann', $result['text']->getValue());
            $this->assertSame('active', $result['options']->selectedValue);
        }
    }

    public function testFilterInputWithoutTheCsrfTokenKeepsThePageUnlessTheFormActionSaysFind(): void
    {
        $this->request()['table']->setCurrentPaginationPage(page: 4);
        $withoutToken = $this->request(
            method: RequestMethodEnum::POST,
            query: [TableFilterSessionTest::FILTER => ''],
            post: [TableFilterSessionTest::TEXT => 'bob'],
        );
        $pageAfterRequestWithoutToken = $withoutToken['table']->getCurrentPaginationPage();

        // The form action of the filter contains the parameter "find", which goes back to the first page
        $withWrongToken = $this->submit(values: [TableFilterSessionTest::TEXT => 'bob'], token: 'wrong');

        $this->assertSame(4, $pageAfterRequestWithoutToken);
        $this->assertSame(1, $withWrongToken['table']->getCurrentPaginationPage());
    }

    public function testFilterInputIsAcceptedWithTheTokenOfTheSessionOnly(): void
    {
        $_SESSION['csrftoken'] = 'another-token';

        $this->assertSame('', $this->submit(values: [TableFilterSessionTest::TEXT => 'ann'])['text']->getValue());
        $this->assertSame(
            'ann',
            $this->submit(values: [TableFilterSessionTest::TEXT => 'ann'], token: 'another-token')['text']->getValue(),
        );
    }

    public function testValuesOfAnotherSessionAreNotVisible(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);
        $_SESSION = ['csrftoken' => 'expected-token'];

        $this->assertSame('', $this->request()['text']->getValue());
    }

    public function testValuesAreGoneAfterTheUserDataWasCleared(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);

        AbstractSessionHandler::clearUserData();

        $this->assertSame('', $this->request()['text']->getValue());
    }

    public function testFilterRendersTheCsrfFieldOfTheSession(): void
    {
        $html = $this->renderFilter(filter: $this->request()['filter']);

        $this->assertStringContainsString('<input type="hidden" name="csrftoken" value="expected-token">', $html);
    }

    /**
     * Without session the placeholder `csrfField` is empty as long as nothing wrote to `$_SESSION`; a filter
     * without fields reads nothing.
     */
    public function testWithoutSessionAFilterWithoutFieldsRendersNoCsrfField(): void
    {
        unset($_SESSION);
        $filter = new TableFilter(identifier: TableFilterSessionTest::FILTER, httpRequest: HttpRequestFactory::create());

        $html = $this->renderFilter(filter: $filter);

        $this->assertStringNotContainsString('csrftoken', $html);
    }

    /**
     * Not a feature but today's behaviour: the filter fields (and the table) write into `$_SESSION` when they are
     * built, which makes `AbstractSessionHandler::enabled()` true. Without session the filter renders a token field
     * whose token can never be accepted (findings of docs/session/plan.md, step 1).
     */
    public function testWithoutSessionAFilterWithFieldsRendersACsrfFieldThatIsNeverAccepted(): void
    {
        unset($_SESSION);
        $filter = $this->request()['filter'];

        $html = $this->renderFilter(filter: $filter);

        $this->assertMatchesRegularExpression('~<input type="hidden" name="csrftoken" value="[A-Za-z0-9+/=]{44}">~', $html);
        $this->assertTrue(AbstractSessionHandler::enabled());
    }

    public function testWithoutSessionFilterInputIsNeverAccepted(): void
    {
        unset($_SESSION);

        $result = $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);

        $this->assertSame('', $result['text']->getValue());
        $this->assertFalse($result['filter']->filtersApplied);
    }

    public function testWithoutSessionTheFilterStartsEmpty(): void
    {
        unset($_SESSION);

        $result = $this->request();

        $this->assertSame('', $result['text']->getValue());
        $this->assertFalse($result['filter']->filtersApplied);
    }

    /**
     * Removed in step 2 together with the static registry of `TableFilter`.
     */
    public function testSecondFilterWithTheSameIdentifierThrows(): void
    {
        new TableFilter(identifier: 'duplicateFilter', httpRequest: HttpRequestFactory::create());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There is already a filter with the same identifier duplicateFilter');

        new TableFilter(identifier: 'duplicateFilter', httpRequest: HttpRequestFactory::create());
    }

    /**
     * Removed in step 2 together with the static registry of `AbstractTableFilterField`.
     */
    public function testSecondFilterFieldWithTheSameIdentifierThrows(): void
    {
        $filter = new TableFilter(identifier: 'duplicateField', httpRequest: HttpRequestFactory::create());
        $createField = static fn(): TextFilterField => new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );
        $createField();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There is already a column filter with the same identifier duplicateField_name');

        $createField();
    }

    private function renderFilter(TableFilter $filter): string
    {
        return $filter->render(
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-table-filter-render-test/',
                templateBaseDirectory: dirname(path: __DIR__, levels: 4) . '/',
            ),
        );
    }
}
