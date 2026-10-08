<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\table;

use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\template\TemplateEngine;

final class FixedPageDbResultTable extends DbResultTable
{
    public function __construct(
        string $identifier,
        FrameworkDb $db,
        DbQuery $dbQuery,
        TemplateEngine $templateEngine,
        HttpRequest $httpRequest,
        private readonly int $totalAmount,
        private readonly int $currentPage,
    ) {
        parent::__construct(
            identifier: $identifier,
            db: $db,
            dbQuery: $dbQuery,
            templateEngine: $templateEngine,
            httpRequest: $httpRequest,
        );
    }

    #[\Override]
    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    #[\Override]
    public function getCurrentPaginationPage(): int
    {
        return $this->currentPage;
    }
}
