<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\pagination;

use actra\yuf\pagination\Pagination;
use actra\yuf\template\TemplateEngine;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    private TemplateEngine $templateEngine;

    #[Override]
    protected function setUp(): void
    {
        // The template cache checks for its files via is_dir()/file_exists(), which would report stale results
        clearstatcache();
        $this->templateEngine = TemplateEngineFactory::create(
            cacheDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-pagination-test' . DIRECTORY_SEPARATOR,
            templateBaseDirectory: dirname(path: __DIR__, levels: 3) . '/',
        );
    }

    /**
     * @param array<string, string> $additionalLinkParameters
     */
    private function render(
        int $totalAmount,
        int $currentPage,
        array $additionalLinkParameters = [],
        int $entriesPerPage = 25,
        string $previousTitle = 'Previous',
        string $nextTitle = 'Next',
        int $beforeAfter = 2,
        int $startEnd = 1,
    ): string {
        return Pagination::render(
            listIdentifier: 'users',
            totalAmount: $totalAmount,
            currentPage: $currentPage,
            templateEngine: $this->templateEngine,
            entriesPerPage: $entriesPerPage,
            beforeAfter: $beforeAfter,
            startEnd: $startEnd,
            additionalLinkParameters: $additionalLinkParameters,
            previousTitle: $previousTitle,
            nextTitle: $nextTitle,
        );
    }

    /**
     * The items of the list in order: `<` / `>` are the links to the previous / next page (`-` if there is none),
     * `[n]` is the current page, `n` another page, `...` a gap.
     */
    private static function summarize(string $html): string
    {
        preg_match_all(pattern: '#<li([^>]*)>(.*?)</li>#s', subject: $html, matches: $matches, flags: PREG_SET_ORDER);
        $items = [];
        foreach ($matches as $match) {
            $items[] = match (true) {
                str_contains(haystack: $match[1], needle: 'backdisabled'),
                str_contains(haystack: $match[1], needle: 'nextdisabled') => $match[1] === ' class="backdisabled"'
                    ? '<-'
                    : '->',
                str_contains(haystack: $match[2], needle: 'pagination-dots') => '...',
                str_contains(haystack: $match[2], needle: '<strong>') => '[' . strip_tags(string: $match[2]) . ']',
                str_contains(haystack: $match[1], needle: 'back') => '<' . PaginationTest::hrefOf(html: $match[2]),
                str_contains(haystack: $match[1], needle: 'next') => '>' . PaginationTest::hrefOf(html: $match[2]),
                default => strip_tags(string: $match[2]),
            };
        }

        return implode(separator: ' ', array: $items);
    }

    private static function hrefOf(string $html): string
    {
        preg_match(pattern: '#href="([^"]*)"#', subject: $html, matches: $matches);
        if (!array_key_exists(key: 1, array: $matches)) {
            throw new LogicException(message: 'No href in ' . $html);
        }

        return '(' . $matches[1] . ')';
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function pageListProvider(): array
    {
        return [
            'first of four' => [100, 1, '<- [1] 2 3 4 >(?page=2|users)'],
            'second of four' => [100, 2, '<(?page=1|users) 1 [2] 3 4 >(?page=3|users)'],
            'last of four' => [100, 4, '<(?page=3|users) 1 2 3 [4] ->'],
            'first of ten' => [250, 1, '<- [1] 2 3 ... 9 10 >(?page=2|users)'],
            'fifth of ten' => [250, 5, '<(?page=4|users) 1 2 3 4 [5] 6 7 ... 9 10 >(?page=6|users)'],
            'sixth of ten' => [250, 6, '<(?page=5|users) 1 2 ... 4 5 [6] 7 8 9 10 >(?page=7|users)'],
            'last of ten' => [250, 10, '<(?page=9|users) 1 2 ... 8 9 [10] ->'],
            'last of eleven (251 entries)' => [251, 11, '<(?page=10|users) 1 2 ... 9 10 [11] ->'],
        ];
    }

    #[DataProvider('pageListProvider')]
    public function testPageList(int $totalAmount, int $currentPage, string $expectedSummary): void
    {
        $this->assertSame(
            $expectedSummary,
            PaginationTest::summarize(html: $this->render(totalAmount: $totalAmount, currentPage: $currentPage)),
        );
    }

    public function testNoPaginationForOnePage(): void
    {
        $this->assertSame('', $this->render(totalAmount: 0, currentPage: 1));
        $this->assertSame('', $this->render(totalAmount: 25, currentPage: 1));
        $this->assertSame('', $this->render(totalAmount: 7, currentPage: 1, entriesPerPage: 10));
    }

    public function testLastEntryOfAFullPageNeedsNoSecondPage(): void
    {
        $this->assertNotSame('', $this->render(totalAmount: 26, currentPage: 1));
        $this->assertSame(
            '<- [1] 2 3 >(?page=2|users)',
            PaginationTest::summarize(html: $this->render(totalAmount: 21, currentPage: 1, entriesPerPage: 10)),
        );
    }

    public function testPagesAroundTheCurrentPageAndAtTheEndsCanBeWidened(): void
    {
        $this->assertSame(
            '<(?page=5|users) 1 2 3 4 5 [6] 7 8 9 10 11 ... 14 >(?page=7|users)',
            PaginationTest::summarize(
                html: $this->render(
                    totalAmount: 140,
                    currentPage: 6,
                    entriesPerPage: 10,
                    beforeAfter: 5,
                    startEnd: 0,
                ),
            ),
        );
    }

    public function testExactMarkup(): void
    {
        $this->assertSame(
            rtrim(
                string: (string) file_get_contents(
                    filename: dirname(path: __DIR__, levels: 2) . '/Fixture/table/pagination-page-2-of-4.html',
                ),
            ),
            $this->render(totalAmount: 100, currentPage: 2),
        );
    }

    public function testAdditionalLinkParametersAreAppendedToEveryLink(): void
    {
        $html = $this->render(
            totalAmount: 100,
            currentPage: 2,
            additionalLinkParameters: ['lang' => 'de', 'q' => 'a b'],
        );

        $this->assertSame(
            '<(?page=1|users&lang=de&q=a+b) 1 [2] 3 4 >(?page=3|users&lang=de&q=a+b)',
            PaginationTest::summarize(html: $html),
        );
        $this->assertStringContainsString('<li><a href="?page=4|users&lang=de&q=a+b">4</a></li>', $html);
    }

    public function testAdditionalLinkParametersAreUrlEncoded(): void
    {
        $html = $this->render(
            totalAmount: 100,
            currentPage: 1,
            additionalLinkParameters: ['a"b' => 'c&d', 'x' => '"><script>alert(1)</script>', 'page' => '9'],
        );

        $this->assertStringContainsString(
            '<li><a href="?page=2|users&a%22b=c%26d&x=%22%3E%3Cscript%3Ealert%281%29%3C%2Fscript%3E">2</a></li>',
            $html,
        );
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testListIdentifierIsUrlEncoded(): void
    {
        $html = Pagination::render(
            listIdentifier: 'a b"c',
            totalAmount: 100,
            currentPage: 1,
            templateEngine: $this->templateEngine,
        );

        $this->assertStringContainsString('<li><a href="?page=2|a+b%22c">2</a></li>', $html);
    }

    public function testPreviousAndNextOfACurrentPageOutsideOfTheListStayInsideOfTheList(): void
    {
        $this->assertSame(
            '<(?page=10|users) 1 2 ... 9 10 ->',
            PaginationTest::summarize(html: $this->render(totalAmount: 250, currentPage: 20)),
        );
        $this->assertSame(
            '<- 1 2 3 >(?page=1|users)',
            PaginationTest::summarize(html: $this->render(totalAmount: 60, currentPage: 0)),
        );
    }

    public function testEntriesPerPageMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Entries per page must be at least 1, 0 given.');
        $this->render(totalAmount: 100, currentPage: 1, entriesPerPage: 0);
    }

    public function testPagesAroundMustNotBeNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The argument beforeAfter must not be negative, -1 given.');
        $this->render(totalAmount: 100, currentPage: 1, beforeAfter: -1);
    }

    public function testTitlesAreEncoded(): void
    {
        $html = $this->render(totalAmount: 100, currentPage: 2, previousTitle: '<b>Back</b>', nextTitle: 'A&B');

        $this->assertStringContainsString('<title>&lt;b&gt;Back&lt;/b&gt;</title>', $html);
        $this->assertStringContainsString('<title>A&amp;B</title>', $html);
    }
}
