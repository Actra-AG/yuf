<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\pagination;

use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlSnippet;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;

/**
 * Provides a pagination for lists, like: << 1 2 ... 5 6 7 8 ... 23 24 25 >>
 *
 * Static because it is a pure function of its arguments: it keeps no state and reads nothing but the snippet.
 */
final class Pagination
{
    /**
     * @param string $listIdentifier Identifies the list in the `page` parameter (`?page=<number>|<identifier>`)
     * @param array<string, string> $additionalLinkParameters Added to every link; names and values are URL encoded
     * @param int $beforeAfter Pages shown before and after the current one
     * @param int $startEnd Pages shown after the first and before the last page
     *
     * @return string Empty if everything fits on one page
     */
    public static function render(
        string $listIdentifier,
        int $totalAmount,
        int $currentPage,
        TemplateEngine $templateEngine,
        int $entriesPerPage = 25,
        int $beforeAfter = 2,
        int $startEnd = 1,
        array $additionalLinkParameters = [],
        string $previousTitle = 'Previous',
        string $nextTitle = 'Next',
        ?string $individualHtmlSnippetPath = null,
    ): string {
        Pagination::assertNotNegative(name: 'beforeAfter', value: $beforeAfter);
        Pagination::assertNotNegative(name: 'startEnd', value: $startEnd);
        if ($entriesPerPage < 1) {
            throw new InvalidArgumentException(
                message: 'Entries per page must be at least 1, ' . $entriesPerPage . ' given.',
            );
        }
        if ($totalAmount <= $entriesPerPage) {
            return '';
        }
        $firstPage = 1;
        $maxPage = intdiv(num1: $totalAmount - 1, num2: $entriesPerPage) + 1;
        $getLinkTarget = static fn(int $pageNumber): string => LinkQuery::create(
            name: 'page',
            value: $pageNumber . '|' . urlencode(string: $listIdentifier),
            additionalParameters: $additionalLinkParameters,
        );
        $pages = new HtmlDataObjectCollection();
        $visiblePages = Pagination::listVisiblePages(
            maxPage: $maxPage,
            currentPage: $currentPage,
            beforeAfter: $beforeAfter,
            startEnd: $startEnd,
        );
        foreach ($visiblePages as $page) {
            $pageObject = new HtmlDataObject();
            $pageObject->addBooleanValue(
                propertyName: 'groupPreviousPages',
                booleanValue: $page === $maxPage - $startEnd
                    && $currentPage < $maxPage - ($beforeAfter + 1) - $startEnd,
            );
            $pageObject->addBooleanValue(propertyName: 'isCurrentPage', booleanValue: $page === $currentPage);
            $pageObject->addHtml(propertyName: 'number', html: (string) $page);
            $pageObject->addHtml(propertyName: 'href', html: $getLinkTarget(pageNumber: $page));
            $pageObject->addBooleanValue(
                propertyName: 'groupNextPages',
                booleanValue: $page === $firstPage + $startEnd
                    && $currentPage > $firstPage + ($beforeAfter + 1) + $startEnd,
            );
            $pages->add(htmlDataObject: $pageObject);
        }
        $replacements = new HtmlReplacementCollection();
        $replacements->addText(identifier: 'previousTitle', text: $previousTitle);
        $replacements->addHtml(
            identifier: 'previousPageHref',
            html: $currentPage <= $firstPage ? '' : $getLinkTarget(pageNumber: min($currentPage - 1, $maxPage)),
        );
        $replacements->addHtmlDataObjectCollection(identifier: 'pages', htmlDataObjectCollection: $pages);
        $replacements->addText(identifier: 'nextTitle', text: $nextTitle);
        $replacements->addHtml(
            identifier: 'nextPageHref',
            html: $currentPage >= $maxPage ? '' : $getLinkTarget(pageNumber: $currentPage + 1),
        );

        return new HtmlSnippet(
            htmlSnippetFilePath: $individualHtmlSnippetPath ?? __DIR__ . DIRECTORY_SEPARATOR . 'pagination.html',
            replacements: $replacements,
        )->render(templateEngine: $templateEngine);
    }

    /**
     * The pages that get a link: the first and the last page and `$startEnd` pages next to them, the current page
     * and `$beforeAfter` pages on each side of it.
     *
     * @return list<int>
     */
    private static function listVisiblePages(int $maxPage, int $currentPage, int $beforeAfter, int $startEnd): array
    {
        $pages = [];
        $ranges = [
            [1, 1 + $startEnd],
            [$maxPage - $startEnd, $maxPage],
            [$currentPage - $beforeAfter, $currentPage + $beforeAfter],
        ];
        foreach ($ranges as [$from, $to]) {
            for ($page = max($from, 1); $page <= min($to, $maxPage); $page++) {
                $pages[$page] = $page;
            }
        }
        ksort(array: $pages);

        return array_values(array: $pages);
    }

    private static function assertNotNegative(string $name, int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                message: 'The argument ' . $name . ' must not be negative, ' . $value . ' given.',
            );
        }
    }
}
