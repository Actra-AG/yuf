<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\common\SearchHelper;
use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use Override;
use RuntimeException;

/**
 * A text input: the value is searched with the search syntax of `SearchHelper` in the column (an SQL expression of the
 * application, never user input).
 */
final class TextFilterField extends AbstractTableFilterField
{
    private string $value = '';

    public function __construct(
        TableFilter $parentFilter,
        string $filterFieldIdentifier,
        HtmlText $label,
        private readonly string $dataTableColumnReference,
        bool $highlightFieldIfSelected = false,
    ) {
        parent::__construct(
            parentFilter: $parentFilter,
            filterFieldIdentifier: $filterFieldIdentifier,
            label: $label,
            highlightFieldIfSelected: $highlightFieldIfSelected,
        );
    }

    #[Override]
    public function init(): void
    {
        $this->value = (string) $this->getFromSession(index: $this->identifier);
    }

    #[Override]
    public function reset(): void
    {
        $this->setValue(value: '');
    }

    #[Override]
    public function checkInput(): void
    {
        $this->setValue(value: (string) $this->httpRequest->getPostString(name: $this->identifier));
    }

    #[Override]
    public function getWhereCondition(): DbQueryData
    {
        $column = preg_replace(pattern: '!\s+!', replacement: ' ', subject: $this->dataTableColumnReference);
        if ($column === null) {
            throw new RuntimeException(
                message: 'The column of the filter field ' . $this->identifier . ' cannot be normalized: '
                . preg_last_error_msg(),
            );
        }

        return SearchHelper::createSqlFilters(filterArr: [$column => $this->value]);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    private function setValue(string $value): void
    {
        $this->value = $value;
        $this->saveToSession(index: $this->identifier, value: $value);
    }

    #[Override]
    protected function renderField(): string
    {
        $classes = ['text'];
        if (
            $this->highlightFieldIfSelected
            && $this->isSelected()
        ) {
            $classes[] = 'highlight';
        }

        $identifier = HtmlEncoder::encode(value: $this->identifier);

        return '<input type="text" class="' . implode(separator: ' ', array: $classes) . '" name="' . $identifier
            . '" id="filter-' . $identifier . '" value="' . HtmlEncoder::encode(value: $this->value) . '">';
    }

    #[Override]
    public function isSelected(): bool
    {
        return ($this->value !== '');
    }
}
