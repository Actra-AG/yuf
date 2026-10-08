<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\TableItem;
use Override;

/**
 * Links per row, e.g. to edit or delete it. `[column]` in a link is replaced by the HTML encoded value of that column
 * of the row. Link targets and individual links (`linkHtml`) are HTML of the application (never user input) and output
 * as they are; labels are text and encoded.
 */
final class ActionsColumn extends AbstractTableColumn
{
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    /** @var array<string, string> */
    private array $actionLinks = [];
    private ?string $hideDeleteLinkField = null;
    private ?string $hideDeleteLinkValue = null;
    public string $tdActionGroupClass = 'td-action-group';

    public function __construct(
        string $identifier = 'actions',
        string $label = '',
        string $cellCssClass = 'td-action',
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label,
        );
        $this->addCellCssClass(className: $cellCssClass);
    }

    /**
     * @param string $linkHtml HTML of the whole link, e.g. `<a href="show/[ID]/">Show</a>`
     */
    public function addIndividualActionLink(
        string $identifier,
        string $linkHtml,
    ): void {
        $this->actionLinks[$identifier] = $linkHtml;
    }

    public function addEditActionLink(
        string $linkTarget,
        string $label = 'Bearbeiten',
    ): void {
        $this->actionLinks[ActionsColumn::EDIT] = '<a href="' . $linkTarget . '" class="edit">'
            . HtmlEncoder::encodeKeepQuotes(value: $label) . '</a>';
    }

    /**
     * @param ?string $hideField The column to compare to hide the link (as text), together with `$hideValue`
     */
    public function addDeleteLink(
        string $linkTarget,
        string $label = 'Löschen',
        ?string $hideField = null,
        ?string $hideValue = null,
    ): void {
        $this->actionLinks[ActionsColumn::DELETE] = '<a href="' . $linkTarget . '" class="delete">'
            . HtmlEncoder::encodeKeepQuotes(value: $label) . '</a>';
        $this->hideDeleteLinkField = $hideField;
        $this->hideDeleteLinkValue = $hideValue;
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $actionLinks = $this->actionLinks;
        if ($this->isDeleteLinkHidden(tableItem: $tableItem)) {
            unset($actionLinks[ActionsColumn::DELETE]);
        }
        if ($actionLinks === []) {
            return '';
        }
        // One pass over every link: a value is never searched for placeholders again
        $replacements = [];
        foreach ($tableItem->data as $key => $value) {
            if ($value === null || is_scalar(value: $value)) {
                $replacements['[' . $key . ']'] = HtmlEncoder::encode(value: $value);
            }
        }
        foreach ($actionLinks as $key => $link) {
            $actionLinks[$key] = strtr(string: $link, from: $replacements);
        }
        $html = implode(separator: PHP_EOL, array: $actionLinks);
        if (count(value: $actionLinks) === 1) {
            return $html;
        }

        return '<div class="' . $this->tdActionGroupClass . '">' . $html . '</div>';
    }

    private function isDeleteLinkHidden(TableItem $tableItem): bool
    {
        if (
            !array_key_exists(key: ActionsColumn::DELETE, array: $this->actionLinks)
            || $this->hideDeleteLinkField === null
            || $this->hideDeleteLinkField === ''
        ) {
            return false;
        }
        $value = $tableItem->getScalarValue(name: $this->hideDeleteLinkField);

        return ($value === null ? null : (string) $value) === $this->hideDeleteLinkValue;
    }
}
