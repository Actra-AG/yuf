<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlSnippet;
use actra\yuf\security\CsrfToken;
use actra\yuf\table\table\DbResultTable;
use LogicException;

class TableFilter
{
    private const string SESSION_DATA_TYPE = 'tableFilter';
    /** @var TableFilter[] */
    private static array $instances = [];

    public private(set) bool $filtersApplied = false;
    /** @var AbstractTableFilterField[] $allFilterFields */
    public private(set) array $allFilterFields = [];
    /** @var AbstractTableFilterField[] $primaryFields */
    private array $primaryFields = [];
    /** @var AbstractTableFilterField[] $secondaryFields */
    private array $secondaryFields = [];

    public function __construct(
        public readonly string $identifier,
        private readonly bool $showLegend = true,
        private readonly string $resetParameter = 'reset',
        private readonly ?string $individualHtmlSnippetPath = null,
        private readonly string $submitButtonLabel = 'Filter anwenden',
        private readonly string $resetLinkLabel = 'Filter zurücksetzen',
    ) {
        if (array_key_exists(key: $identifier, array: TableFilter::$instances)) {
            throw new LogicException(message: 'There is already a filter with the same identifier ' . $identifier);
        }
        TableFilter::$instances[$identifier] = $this;
    }

    public function validate(DbResultTable $dbResultTable): void
    {
        if (HttpRequest::getInputString(keyName: $this->resetParameter) !== null) {
            $this->reset(dbResultTable: $dbResultTable);
        }
        if (HttpRequest::getInputString(keyName: $this->identifier) !== null && $this->hasValidCsrfToken()) {
            $this->reset(dbResultTable: $dbResultTable);
            $this->checkInput();
        }
        $this->filtersApplied = $this->applyFilters(dbResultTable: $dbResultTable);
    }

    /**
     * The filter input is only accepted from the filter form: a POST request with the CSRF token of the user in the
     * posted data (never from the URL). Otherwise, the previous filter stays.
     */
    private function hasValidCsrfToken(): bool
    {
        if (HttpRequest::getRequestMethod() !== RequestMethodEnum::POST) {
            return false;
        }
        $fieldName = CsrfToken::getFieldName();
        if (!array_key_exists(key: $fieldName, array: $_POST) || !is_string(value: $_POST[$fieldName])) {
            return false;
        }

        return CsrfToken::validateToken(token: $_POST[$fieldName]);
    }

    protected function reset(DbResultTable $dbResultTable): void
    {
        foreach ($this->allFilterFields as $abstractTableFilterField) {
            $abstractTableFilterField->reset();
        }
        $dbResultTable->setCurrentPaginationPage(page: 1);
    }

    protected function checkInput(): void
    {
        foreach ($this->allFilterFields as $abstractTableFilterField) {
            $abstractTableFilterField->checkInput();
        }
    }

    protected function applyFilters(DbResultTable $dbResultTable): bool
    {
        $whereConditions = [];
        $parameters = [];
        foreach ($this->allFilterFields as $abstractTableFilterField) {
            if (!$abstractTableFilterField->isSelected()) {
                continue;
            }
            $dbQueryData = $abstractTableFilterField->getWhereCondition();
            $whereConditions[] = $dbQueryData->query;
            foreach ($dbQueryData->params as $sqlParameter) {
                $parameters[] = $sqlParameter;
            }
        }
        if (count(value: $whereConditions) === 0) {
            return false;
        }

        $this->addWhereConditionsToSelectQuery(
            dbResultTable: $dbResultTable,
            whereConds: $whereConditions,
            params: $parameters,
        );

        return true;
    }

    private function addWhereConditionsToSelectQuery(
        DbResultTable $dbResultTable,
        array $whereConds,
        array $params,
    ): void {
        foreach ($whereConds as $key => $val) {
            $whereConds[$key] = '(' . $val . ')';
        }

        $dbResultTable->dbQuery->addWherePart(
            wherePart: implode(separator: ' AND ', array: $whereConds),
            parameters: $params,
        );
    }

    public function addPrimaryField(AbstractTableFilterField $abstractTableFilterField): void
    {
        $abstractTableFilterField->init();
        $this->primaryFields[] = $abstractTableFilterField;
        $this->allFilterFields[$abstractTableFilterField->identifier] = $abstractTableFilterField;
    }

    public function addSecondaryField(AbstractTableFilterField $abstractTableFilterField): void
    {
        $abstractTableFilterField->init();
        $this->secondaryFields[] = $abstractTableFilterField;
        $this->allFilterFields[$abstractTableFilterField->identifier] = $abstractTableFilterField;
    }

    public function render(): string
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addBool(identifier: 'showLegend', booleanValue: $this->showLegend);
        $replacements->addEncodedText(
            identifier: 'formAction',
            content: '?' . $this->identifier . '&' . DbResultTable::PARAM_FIND,
        );
        $replacements->addEncodedText(identifier: 'csrfField', content: CsrfToken::renderAsHiddenPostField());
        $primaryFields = new HtmlDataObjectCollection();
        foreach ($this->primaryFields as $abstractTableFilterField) {
            $primaryFields->add(htmlDataObject: $abstractTableFilterField->render());
        }
        $replacements->addHtmlDataObjectCollection(
            identifier: 'primaryFields',
            htmlDataObjectCollection: $primaryFields,
        );
        if (count(value: $this->secondaryFields) > 0) {
            $secondaryFields = new HtmlDataObjectCollection();
            $isSecondaryFilterTriggered = false;
            foreach ($this->secondaryFields as $abstractTableFilterField) {
                $secondaryFields->add(htmlDataObject: $abstractTableFilterField->render());
                if ($abstractTableFilterField->isSelected()) {
                    $isSecondaryFilterTriggered = true;
                }
            }
            $replacements->addBool(identifier: 'hasSecondaryFilters', booleanValue: true);
            $replacements->addBool(identifier: 'isSecondaryFilterTriggered', booleanValue: $isSecondaryFilterTriggered);
            $replacements->addHtmlDataObjectCollection(
                identifier: 'secondaryFields',
                htmlDataObjectCollection: $secondaryFields,
            );
        } else {
            $replacements->addBool(identifier: 'hasSecondaryFilters', booleanValue: false);
        }
        $replacements->addEncodedText(identifier: 'resetHref', content: '?' . $this->resetParameter);
        $replacements->addEncodedText(identifier: 'submitButtonLabel', content: $this->submitButtonLabel);
        $replacements->addEncodedText(identifier: 'resetLinkLabel', content: $this->resetLinkLabel);

        $individualHtmlSnippetPath = $this->individualHtmlSnippetPath;

        return new HtmlSnippet(
            htmlSnippetFilePath: $individualHtmlSnippetPath === null ? __DIR__ . DIRECTORY_SEPARATOR . 'tableFilter.html' : $individualHtmlSnippetPath,
            replacements: $replacements,
        )->render();
    }

    protected function getFromSession(string $index): ?string
    {
        return DbResultTable::getFromSession(
            dataType: TableFilter::SESSION_DATA_TYPE,
            identifier: $this->identifier,
            index: $index,
        );
    }

    protected function saveToSession(string $index, string $value): void
    {
        DbResultTable::saveToSession(
            dataType: TableFilter::SESSION_DATA_TYPE,
            identifier: $this->identifier,
            index: $index,
            value: $value,
        );
    }
}
