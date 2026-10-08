<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchHelper;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): what a
 * `SearchHelper` remembers across requests and when it forgets it. `SearchHelperRequestTest` covers where the input
 * comes from and the basics; this class adds the edge cases and the storage layout.
 *
 * A request is simulated by creating a helper with the `$_SESSION` of the previous request. The behaviour tests only
 * use `helper()` and the return values; the key `searchHelper` and the value shapes are pinned in
 * `testStorageLayout…()` only (they may change in step 2).
 */
final class SearchHelperSessionTest extends TestCase
{
    private const array RANGE = ['minDate' => '2026-01-01', 'maxDate' => '2026-12-31'];

    #[Override]
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    private function helper(array $query = [], array $post = [], string $instanceName = 'users'): SearchHelper
    {
        return SearchHelper::create(
            instanceName: $instanceName,
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
            valueSource: InputSourceEnum::POST,
        );
    }

    public function testStorageLayoutIsOneArrayPerInstanceBelowTheKeySearchHelper(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status');
        $this->helper(post: ['level' => 'b'])->checkFilter(array: ['a' => 'A', 'b' => 'B'], fieldName: 'level');
        $this->helper(query: ['find' => ''], post: ['groups' => ['1'], 'groupsID' => '7'])->checkMultiFilter(
            array: ['1' => 'One', '2' => 'Two'],
            fieldName: 'groups',
        );
        $this->helper(post: ['from' => '2026-03-01', 'to' => '2026-04-30'])->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );

        $this->assertSame(
            [
                'searchHelper' => [
                    'users' => [
                        'status' => 'active',
                        'level' => 'b',
                        'groups' => [1, '7'],
                        'from' => '01.03.2026',
                        'to' => '30.04.2026',
                    ],
                ],
            ],
            $_SESSION,
        );
    }

    public function testStorageLayoutOfAFieldWithoutInputIsTheDefaultWrittenOnRead(): void
    {
        $this->helper()->checkString(fieldName: 'status', default: 'all');
        $this->helper()->checkMultiFilter(array: [], fieldName: 'groups', default: [3]);

        $this->assertSame(['searchHelper' => ['users' => ['status' => 'all', 'groups' => [3]]]], $_SESSION);
    }

    public function testStorageLayoutOfTheDateRangeWithoutInputIsTheWholeRangeInTheDisplayFormat(): void
    {
        $this->helper()->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );

        $this->assertSame(['searchHelper' => ['users' => ['from' => '01.01.2026', 'to' => '31.12.2026']]], $_SESSION);
    }

    public function testValueIsRememberedForTheNextRequests(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status', default: 'all');

        $this->assertSame('active', $this->helper()->checkString(fieldName: 'status', default: 'all'));
        $this->assertSame('active', $this->helper()->checkString(fieldName: 'status', default: 'all'));
    }

    public function testNewInputReplacesTheRememberedValue(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status');

        $this->assertSame('blocked', $this->helper(post: ['status' => 'blocked'])->checkString(fieldName: 'status'));
        $this->assertSame('blocked', $this->helper()->checkString(fieldName: 'status'));
    }

    public function testEmptyInputReplacesTheRememberedValueByTheEmptyString(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status', default: 'all');

        $this->assertSame('', $this->helper(post: ['status' => ''])->checkString(fieldName: 'status', default: 'all'));
        $this->assertSame('', $this->helper()->checkString(fieldName: 'status', default: 'all'));
    }

    public function testDefaultIsOnlyUsedWhenNothingIsRemembered(): void
    {
        $this->helper()->checkString(fieldName: 'status', default: 'all');

        $this->assertSame('all', $this->helper()->checkString(fieldName: 'status', default: 'other'));
    }

    public function testValuesAreKeptPerInstanceAndField(): void
    {
        $this->helper(post: ['status' => 'active', 'term' => 'ann'])->checkString(fieldName: 'status');
        $this->helper(post: ['status' => 'active', 'term' => 'ann'])->checkString(fieldName: 'term');

        $this->assertSame('', $this->helper(instanceName: 'orders')->checkString(fieldName: 'status'));
        $this->assertSame('ann', $this->helper()->checkString(fieldName: 'term'));
        $this->assertSame('active', $this->helper()->checkString(fieldName: 'status'));
    }

    public function testFindWithInputRemembersTheInputInsteadOfTheDefault(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status', default: 'all');

        $value = $this->helper(query: ['find' => ''], post: ['status' => 'blocked'])->checkString(
            fieldName: 'status',
            default: 'all',
        );

        $this->assertSame('blocked', $value);
        $this->assertSame('blocked', $this->helper()->checkString(fieldName: 'status', default: 'all'));
    }

    public function testResetOnlyForgetsTheFieldsThatAreChecked(): void
    {
        $this->helper(post: ['status' => 'active', 'term' => 'ann'])->checkString(fieldName: 'status');
        $this->helper(post: ['status' => 'active', 'term' => 'ann'])->checkString(fieldName: 'term');

        $this->helper(query: ['reset' => ''])->checkString(fieldName: 'status');

        $this->assertSame('', $this->helper()->checkString(fieldName: 'status'));
        $this->assertSame('ann', $this->helper()->checkString(fieldName: 'term'));
    }

    public function testFilterKeepsTheRememberedKeyForAnUnknownKeyAndStartsWithTheDefault(): void
    {
        $options = ['a' => 'A', 'b' => 'B'];

        $first = $this->helper(post: ['level' => 'x'])->checkFilter(array: $options, fieldName: 'level', default: 'a');
        $this->helper(post: ['level' => 'b'])->checkFilter(array: $options, fieldName: 'level', default: 'a');
        $next = $this->helper(post: ['level' => 'x'])->checkFilter(array: $options, fieldName: 'level', default: 'a');

        $this->assertSame('a', $first);
        $this->assertSame('b', $next);
    }

    public function testFilterIsResetByFindAndResetToTheDefault(): void
    {
        $options = ['a' => 'A', 'b' => 'B'];
        $this->helper(post: ['level' => 'b'])->checkFilter(array: $options, fieldName: 'level', default: 'a');

        $value = $this->helper(query: ['reset' => ''])->checkFilter(array: $options, fieldName: 'level', default: 'a');

        $this->assertSame('a', $value);
        $this->assertSame('a', $this->helper()->checkFilter(array: $options, fieldName: 'level', default: 'a'));
    }

    public function testMultiFilterIsRememberedAndResetToTheDefaultByFindAndReset(): void
    {
        $options = ['1' => 'One', '2' => 'Two'];
        $this->helper(query: ['find' => ''], post: ['groups' => ['2']])->checkMultiFilter(
            array: $options,
            fieldName: 'groups',
        );

        $remembered = $this->helper()->checkMultiFilter(array: $options, fieldName: 'groups', default: [5]);
        $reset = $this->helper(query: ['reset' => ''])->checkMultiFilter(
            array: $options,
            fieldName: 'groups',
            default: [5],
        );

        $this->assertSame([2], $remembered);
        $this->assertSame([5], $reset);
        $this->assertSame([5], $this->helper()->checkMultiFilter(array: $options, fieldName: 'groups'));
    }

    public function testDateRangeIsRememberedAndFindAndResetGoBackToTheWholeRange(): void
    {
        $this->helper(post: ['from' => '2026-03-01', 'to' => '2026-04-30'])->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );

        $remembered = $this->helper()->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );
        $reset = $this->helper(query: ['reset' => ''])->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
            defaultFrom: '2026-02-01',
        );

        $this->assertSame('2026-03-01', $remembered['dateFrom']->format(format: 'Y-m-d'));
        $this->assertSame('2026-04-30', $remembered['dateTo']->format(format: 'Y-m-d'));
        $this->assertSame('2026-02-01', $reset['dateFrom']->format(format: 'Y-m-d'));
        $this->assertSame('2026-12-31', $reset['dateTo']->format(format: 'Y-m-d'));
    }

    public function testDateRangeWithInvalidOrReversedInputIsCorrected(): void
    {
        $reversed = $this->helper(post: ['from' => '2026-06-01', 'to' => '2026-03-01'])->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );
        $invalid = $this->helper(post: ['from' => 'nonsense', 'to' => ''])->checkDateRangeFilter(
            dateRange: SearchHelperSessionTest::RANGE,
            fromField: 'from',
            toField: 'to',
        );

        $this->assertSame('2026-06-01', $reversed['dateTo']->format(format: 'Y-m-d'));
        $this->assertSame('2026-01-01', $invalid['dateFrom']->format(format: 'Y-m-d'));
        $this->assertSame('2026-12-31', $invalid['dateTo']->format(format: 'Y-m-d'));
        $this->assertSame(
            '2026-01-01',
            $this->helper()->checkDateRangeFilter(
                dateRange: SearchHelperSessionTest::RANGE,
                fromField: 'from',
                toField: 'to',
            )['dateFrom']->format(format: 'Y-m-d'),
        );
    }

    public function testValuesAreGoneAfterTheUserDataWasCleared(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status');

        AbstractSessionHandler::clearUserData();

        $this->assertSame('', $this->helper()->checkString(fieldName: 'status'));
    }

    public function testValuesOfAnotherSessionAreNotVisible(): void
    {
        $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status');
        $_SESSION = [];

        $this->assertSame('', $this->helper()->checkString(fieldName: 'status'));
    }

    public function testWithoutSessionTheValuesAreOnlyKnownWithinTheRequest(): void
    {
        unset($_SESSION);

        $value = $this->helper(post: ['status' => 'active'])->checkString(fieldName: 'status', default: 'all');
        unset($_SESSION); // the next request starts without the data of this one
        $next = $this->helper()->checkString(fieldName: 'status', default: 'all');

        $this->assertSame('active', $value);
        $this->assertSame('all', $next);
    }
}
