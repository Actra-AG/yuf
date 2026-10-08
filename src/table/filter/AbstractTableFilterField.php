<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlText;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\TableSessionState;

/**
 * Extension point: a project extends this class for its own field types. The identifier of the field is
 * `<filter identifier>_<field identifier>` and must be unique per page.
 */
abstract class AbstractTableFilterField
{
    public readonly string $identifier;
    protected readonly HttpRequest $httpRequest;
    private readonly TableSessionState $state;

    protected function __construct(
        TableFilter $parentFilter,
        string $filterFieldIdentifier,
        private readonly HtmlText $label,
        protected readonly bool $highlightFieldIfSelected,
    ) {
        $this->identifier = $parentFilter->identifier . '_' . $filterFieldIdentifier;
        $this->httpRequest = $parentFilter->httpRequest;
        $this->state = new TableSessionState(
            session: $parentFilter->session,
            section: SessionSectionEnum::TABLE_FILTERS,
            group: TableSessionState::GROUP_FIELDS,
        );
    }

    public function render(): HtmlDataObject
    {
        $field = new HtmlDataObject();
        $field->addText(propertyName: 'identifier', text: $this->identifier);
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
        return $this->state->get(identifier: $this->identifier, index: $index);
    }

    protected function saveToSession(string $index, string $value): void
    {
        // A cleared value is the same as no value: nothing to store
        if ($value === '' && $this->getFromSession(index: $index) === null) {
            return;
        }
        $this->state->set(identifier: $this->identifier, index: $index, value: $value);
    }
}
