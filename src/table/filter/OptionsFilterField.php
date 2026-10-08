<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlText;
use LogicException;
use Override;

class OptionsFilterField extends AbstractTableFilterField
{
    public private(set) string $selectedValue = '';
    /** @var FilterOption[] */
    private readonly array $filterOptions;

    public function __construct(
        TableFilter $parentFilter,
        string $filterFieldIdentifier,
        HtmlText $label,
        array $filterOptions,
        private readonly string $defaultValue = '',
        private readonly bool $chosenEnhancedDropDown = false,
        bool $highlightFieldIfSelected = false,
    ) {
        parent::__construct(
            parentFilter: $parentFilter,
            filterFieldIdentifier: $filterFieldIdentifier,
            label: $label,
            highlightFieldIfSelected: $highlightFieldIfSelected,
        );
        $finalOptions = [];
        foreach ($filterOptions as $filterOption) {
            if (!($filterOption instanceof FilterOption)) {
                throw new LogicException(message: 'Option must be an instance of FilterOption');
            }
            $finalOptions[$filterOption->identifier] = $filterOption;
        }
        $this->filterOptions = $finalOptions;
    }

    #[Override]
    public function init(): void
    {
        $this->selectedValue = (string) $this->getFromSession(index: $this->identifier);
    }

    #[Override]
    public function reset(): void
    {
        $this->setSelectedValue(selectedValue: $this->defaultValue);
    }

    private function setSelectedValue(string $selectedValue): void
    {
        $this->selectedValue = $selectedValue;
        $this->saveToSession(index: $this->identifier, value: $selectedValue);
    }

    #[Override]
    public function checkInput(): void
    {
        $inputValue = (string) $this->httpRequest->getPostString(name: $this->identifier);
        if (array_key_exists(key: $inputValue, array: $this->filterOptions)) {
            $this->setSelectedValue(selectedValue: $inputValue);
        }
    }

    #[Override]
    public function getWhereCondition(): DbQueryData
    {
        return $this->filterOptions[$this->selectedValue]->whereCondition;
    }

    #[Override]
    protected function renderField(): string
    {
        $filterName = $this->identifier;
        $htmlArr = [];
        $classes = [];
        if (
            $this->highlightFieldIfSelected
            && $this->isSelected()
        ) {
            $classes[] = 'highlight';
        }
        if ($this->chosenEnhancedDropDown) {
            $classes[] = 'chosen';
        }
        $htmlArr[] = '<select name="' . $filterName . '" id="filter-' . $filterName . '" class="' . implode(
            separator: ' ',
            array: $classes,
        ) . '">';
        foreach ($this->filterOptions as $filterOption) {
            $htmlArr[] = $filterOption->render(selectedValue: $this->selectedValue);
        }
        $htmlArr[] = '</select>';

        return implode(separator: PHP_EOL, array: $htmlArr);
    }

    #[Override]
    public function isSelected(): bool
    {
        return ($this->selectedValue !== '');
    }
}
