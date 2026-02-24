<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;

readonly class FilterOption
{
    public function __construct(
        public string $identifier,
        public string $label,
        public DbQueryData $whereCondition
    ) {
    }

    public function render(string $selectedValue): string
    {
        $attributes = [
            'option',
            'value="' . $this->identifier . '"',
        ];
        if ($this->identifier === $selectedValue) {
            $attributes[] = 'selected';
        }

        return '<' . implode(separator: ' ', array: $attributes) . '>' . $this->label . '</option>';
    }
}