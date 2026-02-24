<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\renderer;

use actra\yuf\pagination\Pagination;
use actra\yuf\table\table\DbResultTable;

readonly class TablePaginationRenderer
{
    public function __construct(public ?string $individualHtmlSnippetPath = null)
    {
    }

    public function render(
        DbResultTable $dbResultTable,
        int $entriesPerPage = 25,
        int $beforeAfter = 2,
        int $startEnd = 1
    ): string {
        return Pagination::render(
            listIdentifier: $dbResultTable->identifier,
            totalAmount: $dbResultTable->getTotalAmount(),
            currentPage: $dbResultTable->getCurrentPaginationPage(),
            entriesPerPage: $entriesPerPage,
            beforeAfter: $beforeAfter,
            startEnd: $startEnd,
            additionalLinkParameters: $dbResultTable->additionalLinkParameters,
            individualHtmlSnippetPath: $this->individualHtmlSnippetPath
        );
    }
}