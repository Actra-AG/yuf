<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * A drop-down of options. The selected option is only taken from the posted value if it is one of the options; the
 * empty value (`''`) means that no option is selected and the filter is not applied.
 */
final class OptionsFilterField extends AbstractTableFilterField
{
    public private(set) string $selectedValue = '';
    /** @var array<string, FilterOption> */
    private readonly array $filterOptions;

    /**
     * @param list<FilterOption> $filterOptions
     */
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
            if (array_key_exists(key: $filterOption->identifier, array: $finalOptions)) {
                throw new InvalidArgumentException(
                    message: 'The filter field ' . $this->identifier . ' has the option "'
                    . $filterOption->identifier . '" twice.',
                );
            }
            $finalOptions[$filterOption->identifier] = $filterOption;
        }
        if ($defaultValue !== '' && !array_key_exists(key: $defaultValue, array: $finalOptions)) {
            throw new InvalidArgumentException(
                message: 'The default value "' . $defaultValue . '" of the filter field ' . $this->identifier
                . ' is none of its options: ' . implode(separator: ', ', array: array_keys(array: $finalOptions)) . '.',
            );
        }
        $this->filterOptions = $finalOptions;
    }

    #[Override]
    public function init(): void
    {
        $storedValue = (string) $this->getFromSession(index: $this->identifier);
        // An option that does not exist anymore (the options changed since the session was started) selects nothing
        $this->selectedValue = array_key_exists(key: $storedValue, array: $this->filterOptions) ? $storedValue : '';
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
        if (!array_key_exists(key: $this->selectedValue, array: $this->filterOptions)) {
            throw new LogicException(
                message: 'The filter field ' . $this->identifier . ' has no selected option, so it has no condition: '
                . 'ask isSelected() first.',
            );
        }

        return $this->filterOptions[$this->selectedValue]->whereCondition;
    }

    #[Override]
    protected function renderField(): string
    {
        $filterName = HtmlEncoder::encode(value: $this->identifier);
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
