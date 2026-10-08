<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlText;
use actra\yuf\table\table\DbResultTable;
use LogicException;

abstract class AbstractTableFilterField
{
    private const string SESSION_DATA_TYPE = 'columnFilter';

    /** @var AbstractTableFilterField[] */
    private static array $instances = [];
    public readonly string $identifier;

    protected function __construct(
        TableFilter $parentFilter,
        string $filterFieldIdentifier,
        private readonly HtmlText $label,
        protected readonly bool $highlightFieldIfSelected,
    ) {
        $uniqueIdentifier = $parentFilter->identifier . '_' . $filterFieldIdentifier;
        if (array_key_exists(key: $uniqueIdentifier, array: AbstractTableFilterField::$instances)) {
            throw new LogicException(
                message: 'There is already a column filter with the same identifier ' . $uniqueIdentifier,
            );
        }
        $this->identifier = $uniqueIdentifier;
        AbstractTableFilterField::$instances[$uniqueIdentifier] = $this;
    }

    public function render(): HtmlDataObject
    {
        $field = new HtmlDataObject();
        $field->addHtml(propertyName: 'identifier', html: $this->identifier);
        $field->addBooleanValue(
            propertyName: 'highlight',
            booleanValue: $this->isSelected() && !$this->highlightFieldIfSelected,
        );
        $field->addHtml(propertyName: 'label', html: $this->label->render());
        $field->addHtml(propertyName: 'html', html: $this->renderField());

        return $field;
    }

    abstract public function isSelected(): bool;

    abstract protected function renderField(): string;

    abstract public function init(): void;

    abstract public function reset(): void;

    abstract public function checkInput(): void;

    abstract public function getWhereCondition(): DbQueryData;

    protected function getFromSession(string $index): ?string
    {
        return DbResultTable::getFromSession(
            dataType: AbstractTableFilterField::SESSION_DATA_TYPE,
            identifier: $this->identifier,
            index: $index,
        );
    }

    protected function saveToSession(string $index, string $value): void
    {
        DbResultTable::saveToSession(
            dataType: AbstractTableFilterField::SESSION_DATA_TYPE,
            identifier: $this->identifier,
            index: $index,
            value: $value,
        );
    }
}
