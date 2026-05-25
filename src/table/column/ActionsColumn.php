<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\TableItemModel;

class ActionsColumn extends AbstractTableColumn
{
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    private array $actionLinks = [];
    private ?string $hideDeleteLinkField = null;
    private ?string $hideDeleteLinkValue = null;
    public string $tdActionGroupClass = 'td-action-group';

    public function __construct(
        string $identifier = 'actions',
        string $label = '',
        string $cellCssClass = 'td-action'
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label
        );
        $this->addCellCssClass(className: $cellCssClass);
    }

    public function addIndividualActionLink(
        string $identifier,
        string $linkHTML
    ): void {
        $this->actionLinks[$identifier] = $linkHTML;
    }

    public function addEditActionLink(
        string $linkTarget,
        string $label = 'Bearbeiten'
    ): void {
        $this->actionLinks[ActionsColumn::EDIT] = '<a href="' . $linkTarget . '" class="edit">' . $label . '</a>';
    }

    public function addDeleteLink(
        string $linkTarget,
        string $label = 'Löschen',
        ?string $hideField = null,
        ?string $hideValue = null
    ): void {
        $this->actionLinks[ActionsColumn::DELETE] = '<a href="' . $linkTarget . '" class="delete">' . $label . '</a>';
        $this->hideDeleteLinkField = $hideField;
        $this->hideDeleteLinkValue = $hideValue;
    }

    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        $actionLinks = $this->actionLinks;
        if (
            array_key_exists(
                key: ActionsColumn::DELETE,
                array: $this->actionLinks
            )
            && $this->hideDeleteLinkField !== null
            && $this->hideDeleteLinkField !== ''
            && $tableItemModel->getRawValue(name: $this->hideDeleteLinkField) === $this->hideDeleteLinkValue
        ) {
            unset($actionLinks[ActionsColumn::DELETE]);
        }
        if ($actionLinks === []) {
            return '';
        }
        $srcArr = [];
        $rplArr = [];
        foreach ($tableItemModel->data as $key => $val) {
            $srcArr[] = '[' . $key . ']';
            $rplArr[] = HtmlEncoder::encode(value: $val);
        }
        foreach ($actionLinks as $key => $val) {
            $actionLinks[$key] = str_replace(
                search: $srcArr,
                replace: $rplArr,
                subject: $val
            );
        }
        $value = $this->renderActionLinks(actionLinks: $actionLinks);
        if (count(value: $actionLinks) === 1) {
            return $value;
        }
        return '<div class="' . $this->tdActionGroupClass . '">' . $value . '</div>';
    }

    protected function renderActionLinks(array $actionLinks): string
    {
        return implode(
            separator: PHP_EOL,
            array: $actionLinks
        );
    }
}