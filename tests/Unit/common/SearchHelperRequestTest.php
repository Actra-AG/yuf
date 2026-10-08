<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchHelper;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The search state of the user is kept in the session; the values come from the declared source of the request, the
 * parameters `reset` and `find` from the query string. The helper never writes into the superglobals of the request.
 */
final class SearchHelperRequestTest extends TestCase
{
    /** @var array<mixed> */
    private array $savedGet = [];

    #[Override]
    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION); // Sessions are disabled in the CLI, the request handler checks that
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
    ): SearchHelper {
        return SearchHelper::create(
            instanceName: 'users',
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
            valueSource: $source,
        );
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
        $options = ['a' => 'A', 'b' => 'B'];

        $this->assertSame('b', $this->createHelper(post: ['f' => 'b'])->checkFilter(array: $options, fieldName: 'f'));
        $this->assertSame('b', $this->createHelper(post: ['f' => 'x'])->checkFilter(array: $options, fieldName: 'f'));
    }

    public function testMultiFilterCollectsTheCheckedKeysOfASearchRequest(): void
    {
        $options = ['1' => 'One', '2' => 'Two', '3' => 'Three'];
        $helper = $this->createHelper(query: ['find' => ''], post: ['groups' => ['1', '3', '9'], 'groupsID' => '7']);

        $groups = $helper->checkMultiFilter(array: $options, fieldName: 'groups');

        $this->assertSame([1, 3, '7'], $groups);
    }

    public function testMultiFilterIgnoresTheInputOfARequestWithoutFindOrReset(): void
    {
        $options = ['1' => 'One'];

        $helper = $this->createHelper(post: ['groups' => ['1']]);

        $groups = $helper->checkMultiFilter(array: $options, fieldName: 'groups', default: [5]);

        $this->assertSame([5], $groups);
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
}
