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
use actra\yuf\security\CsrfHiddenFieldRenderer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\TableSessionState;
use actra\yuf\template\TemplateEngine;

/**
 * The identifier of a filter and the identifiers of its fields must be unique per page (they are the keys of the
 * state in the session). Without CSRF token source (no session), the input is accepted without token.
 */
class TableFilter
{
    public private(set) bool $filtersApplied = false;
    /** @var AbstractTableFilterField[] $allFilterFields */
    public private(set) array $allFilterFields = [];
    /** @var AbstractTableFilterField[] $primaryFields */
    private array $primaryFields = [];
    /** @var AbstractTableFilterField[] $secondaryFields */
    private array $secondaryFields = [];
    private readonly TableSessionState $state;

    /**
     * @param Session $session Keeps the values of the filter fields (`ViewContext::$session`)
     * @param ?CsrfTokenSource $csrfTokenSource Protects the filter form (`ViewContext::$formContext`); `null` without
     *                                          session: the input is accepted without token
     */
    public function __construct(
        public readonly string $identifier,
        public readonly HttpRequest $httpRequest,
        public readonly Session $session,
        private readonly ?CsrfTokenSource $csrfTokenSource,
        private readonly bool $showLegend = true,
        private readonly string $resetParameter = 'reset',
        private readonly ?string $individualHtmlSnippetPath = null,
        private readonly string $submitButtonLabel = 'Filter anwenden',
        private readonly string $resetLinkLabel = 'Filter zurücksetzen',
    ) {
        $this->state = new TableSessionState(
            session: $session,
            section: SessionSectionEnum::TABLE_FILTERS,
            group: TableSessionState::GROUP_FILTERS,
        );
    }

    public function validate(DbResultTable $dbResultTable): void
    {
        if ($this->httpRequest->getQueryString(name: $this->resetParameter) !== null) {
            $this->reset(dbResultTable: $dbResultTable);
        }
        if ($this->httpRequest->getQueryString(name: $this->identifier) !== null && $this->isInputAccepted()) {
            $this->reset(dbResultTable: $dbResultTable);
            $this->checkInput();
        }
        $this->filtersApplied = $this->applyFilters(dbResultTable: $dbResultTable);
    }

    /**
     * The filter input is only accepted from the filter form: a POST request with the CSRF token of the user in the
     * posted data (never from the URL; not needed without CSRF token source). Otherwise, the previous filter stays.
     */
    private function isInputAccepted(): bool
    {
        if ($this->httpRequest->getMethod() !== RequestMethodEnum::POST) {
            return false;
        }
        if ($this->csrfTokenSource === null) {
            return true;
        }
        $token = $this->httpRequest->getPostString(name: CsrfTokenSource::FIELD_NAME);

        return $token !== null && $this->csrfTokenSource->isValid(token: $token);
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

    /**
     * @param list<float|int|string|null> $params
     */
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

    public function render(TemplateEngine $templateEngine): string
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addBool(identifier: 'showLegend', booleanValue: $this->showLegend);
        $replacements->addHtml(
            identifier: 'formAction',
            html: '?' . $this->identifier . '&' . DbResultTable::PARAM_FIND,
        );
        $replacements->addHtml(
            identifier: 'csrfField',
            html: CsrfHiddenFieldRenderer::render(csrfTokenSource: $this->csrfTokenSource),
        );
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
        $replacements->addHtml(identifier: 'resetHref', html: '?' . $this->resetParameter);
        $replacements->addHtml(identifier: 'submitButtonLabel', html: $this->submitButtonLabel);
        $replacements->addHtml(identifier: 'resetLinkLabel', html: $this->resetLinkLabel);

        $individualHtmlSnippetPath = $this->individualHtmlSnippetPath;

        return new HtmlSnippet(
            htmlSnippetFilePath: $individualHtmlSnippetPath === null ? __DIR__ . DIRECTORY_SEPARATOR . 'tableFilter.html' : $individualHtmlSnippetPath,
            replacements: $replacements,
        )->render(templateEngine: $templateEngine);
    }

    protected function getFromSession(string $index): ?string
    {
        return $this->state->get(identifier: $this->identifier, index: $index);
    }

    protected function saveToSession(string $index, string $value): void
    {
        $this->state->set(identifier: $this->identifier, index: $index, value: $value);
    }
}
