<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\table;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlText;
use actra\yuf\table\filter\AbstractTableFilterField;
use actra\yuf\table\filter\TableFilter;
use LogicException;
use Override;

/**
 * Records whether the filter read the input; it never adds a condition to the query.
 */
final class RecordingTableFilterField extends AbstractTableFilterField
{
    public private(set) bool $inputChecked = false;

    public function __construct(TableFilter $parentFilter)
    {
        parent::__construct(
            parentFilter: $parentFilter,
            filterFieldIdentifier: 'recording',
            label: HtmlText::fromHtml(html: 'Recording'),
            highlightFieldIfSelected: false,
        );
    }

    #[Override]
    public function isSelected(): bool
    {
        return false;
    }

    #[Override]
    protected function renderField(): string
    {
        return '';
    }

    #[Override]
    public function init(): void {}

    #[Override]
    public function reset(): void {}

    #[Override]
    public function checkInput(): void
    {
        $this->inputChecked = true;
    }

    #[Override]
    public function getWhereCondition(): DbQueryData
    {
        throw new LogicException(message: 'Not selected, so no condition is needed.');
    }
}
