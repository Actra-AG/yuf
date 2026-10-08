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
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\filter\DateFilterField;
use actra\yuf\table\filter\FilterOption;
use actra\yuf\table\filter\OptionsFilterField;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\filter\TextFilterField;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\table\ExposingTableFilter;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * What a `TableFilter` with a text, an options and a date field remembers across requests (in
 * `yuf.tableFilters.fields`), when it forgets it, the CSRF requirement for new input and the behaviour without CSRF
 * token source (no session). `TableFilterCsrfTest` covers the CSRF check with a recording field; this class covers
 * the stored values.
 *
 * A request is simulated by building the filter and the table again with the same identifiers and the session of
 * the previous request. The behaviour tests only use `request()`, `submit()` and the getters of the fields; the keys
 * and value shapes of the storage are pinned in `testStorageLayout…()` only.
 */
final class TableFilterSessionTest extends TestCase
{
    private const string FILTER = 'sessionFilter';
    private const string TABLE = 'sessionFilterTable';
    private const string TEXT = 'sessionFilter_name';
    private const string OPTIONS = 'sessionFilter_status';
    private const string DATE = 'sessionFilter_since';

    private ArraySessionStorage $storage;
    private Session $session;
    private SessionCsrfTokenSource $tokenSource;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage(data: ['yuf' => ['csrf' => ['token' => 'expected-token']]]);
        $this->session = new Session(storage: $this->storage);
        $this->tokenSource = new SessionCsrfTokenSource(session: $this->session);
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
        bool $withCsrfTokenSource = true,
    ): array {
        $httpRequest = HttpRequestFactory::create(method: $method, queryParameters: $query, postParameters: $post);
        $filter = new TableFilter(
            identifier: TableFilterSessionTest::FILTER,
            httpRequest: $httpRequest,
            session: $this->session,
            csrfTokenSource: $withCsrfTokenSource ? $this->tokenSource : null,
        );
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
                new FilterOption(
                    identifier: 'all',
                    label: 'All',
                    whereCondition: new DbQueryData(query: '1=1', params: []),
                ),
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
            session: $this->session,
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
    private function submit(
        array $values,
        string $token = 'expected-token',
        string $optionsDefault = '',
        bool $withCsrfTokenSource = true,
    ): array {
        return $this->request(
            method: RequestMethodEnum::POST,
            query: [TableFilterSessionTest::FILTER => '', 'find' => ''],
            post: ['csrftoken' => $token] + $values,
            optionsDefault: $optionsDefault,
            withCsrfTokenSource: $withCsrfTokenSource,
        );
    }

    public function testStorageLayoutIsOneArrayPerFilterFieldInTheFieldsGroupOfTheTableFiltersSection(): void
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
                'yuf' => [
                    'csrf' => ['token' => 'expected-token'],
                    'tableFilters' => [
                        'fields' => [
                            'sessionFilter_name' => ['sessionFilter_name' => 'ann'],
                            'sessionFilter_status' => ['sessionFilter_status' => 'active'],
                            'sessionFilter_since' => ['sessionFilter_since' => '2026-03-01 00:00:00'],
                        ],
                    ],
                ],
            ],
            $this->storage->all(),
        );
    }

    /**
     * Fix of v4.30.0: before, a filter and its fields wrote empty arrays (and the table its defaults) on every first
     * request.
     */
    public function testFirstRequestWritesNothingIntoTheSession(): void
    {
        $this->request();

        $this->assertSame(['yuf' => ['csrf' => ['token' => 'expected-token']]], $this->storage->all());
    }

    /**
     * The protected accessors of `TableFilter` for own filters use the group `filters`; the filter fields of yuf
     * use the group `fields`, so they cannot collide.
     */
    public function testStorageLayoutOfTheProtectedAccessorsOfTableFilter(): void
    {
        $filter = new ExposingTableFilter(
            identifier: 'own',
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
            csrfTokenSource: null,
        );

        $filter->write(index: 'key', value: 'value');

        $this->assertSame('value', $filter->read(index: 'key'));
        $this->assertNull($filter->read(index: 'unknown'));
        $this->assertSame(
            ['filters' => ['own' => ['key' => 'value']]],
            $this->session->getSection(section: SessionSectionEnum::TABLE_FILTERS),
        );
    }

    public function testOwnFilterStateAndFieldStateDoNotCollide(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);
        $filter = new ExposingTableFilter(
            identifier: TableFilterSessionTest::TEXT,
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
            csrfTokenSource: null,
        );

        $filter->write(index: TableFilterSessionTest::TEXT, value: 'own');

        $this->assertSame('own', $filter->read(index: TableFilterSessionTest::TEXT));
        $this->assertSame('ann', $this->request()['text']->getValue());
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
        $this->session->setSection(section: SessionSectionEnum::CSRF, data: []);

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
        $this->session->setSection(section: SessionSectionEnum::CSRF, data: ['token' => 'another-token']);

        $this->assertSame('', $this->submit(values: [TableFilterSessionTest::TEXT => 'ann'])['text']->getValue());
        $this->assertSame(
            'ann',
            $this->submit(values: [TableFilterSessionTest::TEXT => 'ann'], token: 'another-token')['text']->getValue(),
        );
    }

    public function testValuesOfAnotherSessionAreNotVisible(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);
        $this->storage->replaceAll(data: ['yuf' => ['csrf' => ['token' => 'expected-token']]]);

        $this->assertSame('', $this->request()['text']->getValue());
    }

    public function testValuesAreGoneAfterTheUserDataWasCleared(): void
    {
        $this->submit(values: [TableFilterSessionTest::TEXT => 'ann']);

        $this->session->clearUserData();

        $this->assertSame('', $this->request()['text']->getValue());
    }

    public function testFilterRendersTheCsrfFieldOfTheSession(): void
    {
        $html = $this->renderFilter(filter: $this->request()['filter']);

        $this->assertStringContainsString('<input type="hidden" name="csrftoken" value="expected-token">', $html);
    }

    /**
     * Decision of v4.30.0: without CSRF token source (no session) the filter renders no CSRF field.
     */
    public function testWithoutTokenSourceTheFilterRendersNoCsrfField(): void
    {
        $filter = $this->request(withCsrfTokenSource: false)['filter'];

        $html = $this->renderFilter(filter: $filter);

        $this->assertStringNotContainsString('csrftoken', $html);
    }

    /**
     * Decision of v4.30.0: without CSRF token source the posted filter input is accepted without token (CSRF needs
     * a session cookie). Before, it was never accepted.
     */
    public function testWithoutTokenSourceFilterInputIsAcceptedWithoutToken(): void
    {
        $result = $this->request(
            method: RequestMethodEnum::POST,
            query: [TableFilterSessionTest::FILTER => '', 'find' => ''],
            post: [TableFilterSessionTest::TEXT => 'ann'],
            withCsrfTokenSource: false,
        );

        $this->assertSame('ann', $result['text']->getValue());
        $this->assertTrue($result['filter']->filtersApplied);
    }

    public function testWithoutTokenSourceFilterInputIsStillOnlyAcceptedFromAPostRequest(): void
    {
        $result = $this->request(
            query: [TableFilterSessionTest::FILTER => '', TableFilterSessionTest::TEXT => 'ann'],
            withCsrfTokenSource: false,
        );

        $this->assertSame('', $result['text']->getValue());
        $this->assertFalse($result['filter']->filtersApplied);
    }

    public function testTheSameFilterIdentifierMayBeUsedAgain(): void
    {
        $first = new TableFilter(
            identifier: 'duplicateFilter',
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
            csrfTokenSource: null,
        );
        $second = new TableFilter(
            identifier: 'duplicateFilter',
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
            csrfTokenSource: null,
        );
        $createField = static fn(TableFilter $filter): TextFilterField => new TextFilterField(
            parentFilter: $filter,
            filterFieldIdentifier: 'name',
            label: HtmlText::fromHtml(html: 'Name'),
            dataTableColumnReference: 'users.name',
        );

        $this->assertNotSame($first, $second);
        $this->assertSame($createField($first)->identifier, $createField($first)->identifier);
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
