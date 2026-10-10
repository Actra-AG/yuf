<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchState;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The search state of the user is kept in the session; the values come from the declared source of the request, the
 * parameters `reset` and `find` from the query string. The helper never writes into the superglobals of the request.
 */
final class SearchStateRequestTest extends TestCase
{
    /** @var array<mixed> */
    private array $savedGet = [];

    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    #[Override]
    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    private function createHelper(
        array $query = [],
        array $post = [],
        InputSourceEnum $source = InputSourceEnum::POST,
    ): SearchState {
        return SearchState::create(
            instanceName: 'users',
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
            valueSource: $source,
            session: $this->session,
        );
    }

    /**
     * @param array<int|string, string> $items
     */
    private static function options(array $items): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($items as $key => $html) {
            $formOptions->addItem(key: (string) $key, htmlText: HtmlText::fromHtml(html: $html));
        }

        return $formOptions;
    }

    public function testStringTakesTheDefaultWithoutInput(): void
    {
        $this->assertSame('all', $this->createHelper()->checkString(fieldName: 'status', default: 'all'));
    }

    public function testStringIsReadFromThePostedDataAndKept(): void
    {
        $helper = $this->createHelper(post: ['status' => ' active ']);

        $this->assertSame('active', $helper->checkString(fieldName: 'status'));

        $this->assertSame('active', $this->createHelper()->checkString(fieldName: 'status'));
    }

    public function testStringIsReadFromTheQueryStringForAGetSearch(): void
    {
        $helper = $this->createHelper(
            query: ['status' => 'blocked'],
            post: ['status' => 'ignored'],
            source: InputSourceEnum::QUERY,
        );

        $this->assertSame('blocked', $helper->checkString(fieldName: 'status'));
    }

    public function testSearchTermUsesTheFieldSearchterm(): void
    {
        $this->assertSame('ann', $this->createHelper(post: ['searchterm' => 'ann'])->checkSearchTerm());
    }

    public function testFindAndResetClearTheKeptValue(): void
    {
        $this->createHelper(post: ['status' => 'active'])->checkString(fieldName: 'status');

        $found = $this->createHelper(query: ['find' => ''])->checkString(fieldName: 'status', default: 'all');
        $this->createHelper(post: ['status' => 'active'])->checkString(fieldName: 'status');
        $reset = $this->createHelper(query: ['reset' => ''])->checkString(fieldName: 'status', default: 'all');

        $this->assertSame('all', $found);
        $this->assertSame('all', $reset);
    }

    public function testFindInThePostedDataIsNoFind(): void
    {
        $this->createHelper(post: ['status' => 'active'])->checkString(fieldName: 'status');

        $value = $this->createHelper(post: ['find' => '', 'reset' => ''])->checkString(fieldName: 'status');

        $this->assertSame('active', $value);
    }

    public function testFilterOnlyAcceptsKnownKeys(): void
    {
        $options = SearchStateRequestTest::options(items: ['a' => 'A', 'b' => 'B']);

        $known = $this->createHelper(post: ['f' => 'b'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');
        $unknown = $this->createHelper(post: ['f' => 'x'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertSame('b', $known);
        $this->assertSame('b', $unknown); // the unknown key is ignored, the remembered one stays
    }

    public function testMultiFilterCollectsTheCheckedKeysOfASearchRequest(): void
    {
        $options = SearchStateRequestTest::options(items: ['1' => 'One', '2' => 'Two', '3' => 'Three']);
        $helper = $this->createHelper(query: ['find' => ''], post: ['groups' => ['1', '3', '9']]);

        $groups = $helper->checkMultiOptionsFilter(formOptions: $options, fieldName: 'groups');

        $this->assertSame(['1', '3'], $groups);
    }

    public function testMultiFilterIgnoresTheInputOfARequestWithoutFindOrReset(): void
    {
        $options = SearchStateRequestTest::options(items: ['1' => 'One']);

        $helper = $this->createHelper(post: ['groups' => ['1']]);

        $groups = $helper->checkMultiOptionsFilter(formOptions: $options, fieldName: 'groups', default: ['5']);

        $this->assertSame([], $groups);
    }

    public function testDateRangeIsClampedToTheRangeAndKept(): void
    {
        $range = ['minDate' => '2026-01-01', 'maxDate' => '2026-12-31'];
        $helper = $this->createHelper(post: ['from' => '2025-06-01', 'to' => '2026-03-15']);

        $result = $helper->checkDateRangeFilter(dateRange: $range, fromField: 'from', toField: 'to');

        $this->assertSame('2026-01-01', $result['dateFrom']->format(format: 'Y-m-d'));
        $this->assertSame('2026-03-15', $result['dateTo']->format(format: 'Y-m-d'));
        $kept = $this->createHelper()->checkDateRangeFilter(dateRange: $range, fromField: 'from', toField: 'to');
        $this->assertSame('2026-03-15', $kept['dateTo']->format(format: 'Y-m-d'));
    }

    public function testDateRangeStartsWithTheWholeRange(): void
    {
        $range = ['minDate' => '2026-01-01', 'maxDate' => '2026-12-31'];

        $result = $this->createHelper()->checkDateRangeFilter(dateRange: $range, fromField: 'from', toField: 'to');

        $this->assertSame('2026-01-01', $result['dateFrom']->format(format: 'Y-m-d'));
        $this->assertSame('2026-12-31', $result['dateTo']->format(format: 'Y-m-d'));
    }

    public function testTheSuperglobalsAreNotWritten(): void
    {
        $_GET = ['untouched' => '1'];

        $helper = $this->createHelper(query: ['find' => '', 'status' => 'x'], source: InputSourceEnum::QUERY);

        $helper->checkString(fieldName: 'status');

        $this->assertSame(['untouched' => '1'], $_GET);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function dateProvider(): array
    {
        return [
            'german date' => ['07.03.2026', '2026-03-07'],
            'iso date' => ['2026-03-07', '2026-03-07'],
            'empty' => ['', null],
            'text' => ['not a date', null],
            'impossible date' => ['2026-02-30', null],
        ];
    }

    #[DataProvider('dateProvider')]
    public function testCheckDate(string $date, ?string $expected): void
    {
        $result = SearchState::checkDate(date: $date);

        $this->assertSame($expected, $result?->format(format: 'Y-m-d'));
    }
}
