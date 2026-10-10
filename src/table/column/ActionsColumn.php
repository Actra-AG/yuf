<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use InvalidArgumentException;
use Override;

/**
 * Links per row, e.g. to edit or delete it. `[column]` in a link is replaced by the value of that column of the row:
 * URL encoded in the target of the edit and delete link, HTML encoded in individual links (`linkHtml`). Link targets
 * and individual links are HTML of the application (never user input) and output as they are, a link target must not
 * start with a placeholder (the value could become the scheme of the URL); labels are text and encoded.
 */
final class ActionsColumn extends AbstractTableColumn
{
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    /** @var array<string, string|array{target: string, label: string, cssClass: string}> */
    private array $actionLinks = [];
    private ?string $hideDeleteLinkField = null;
    private ?string $hideDeleteLinkValue = null;
    public string $tdActionGroupClass = 'td-action-group';

    public function __construct(
        string $identifier = 'actions',
        ?HtmlText $label = null,
        string $cellCssClass = 'td-action',
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label ?? HtmlText::fromText(text: ''),
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

    /**
     * @param string $linkTarget Relative or absolute URL, `[column]` is replaced by the URL encoded value; must not
     *     start with a placeholder
     *
     * @throws InvalidArgumentException If the target starts with a placeholder
     */
    public function addEditActionLink(
        string $linkTarget,
        string $label = 'Bearbeiten',
    ): void {
        ActionsColumn::assertTargetDoesNotStartWithPlaceholder(linkTarget: $linkTarget);
        $this->actionLinks[ActionsColumn::EDIT] = ['target' => $linkTarget, 'label' => $label, 'cssClass' => 'edit'];
    }

    /**
     * @param string $linkTarget Relative or absolute URL, `[column]` is replaced by the URL encoded value; must not
     *     start with a placeholder
     * @param ?string $hideField The column to compare to hide the link (as text), together with `$hideValue`
     *
     * @throws InvalidArgumentException If the target starts with a placeholder
     */
    public function addDeleteLink(
        string $linkTarget,
        string $label = 'Löschen',
        ?string $hideField = null,
        ?string $hideValue = null,
    ): void {
        ActionsColumn::assertTargetDoesNotStartWithPlaceholder(linkTarget: $linkTarget);
        $this->actionLinks[ActionsColumn::DELETE] = [
            'target' => $linkTarget,
            'label' => $label,
            'cssClass' => 'delete',
        ];
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
        $htmlReplacements = [];
        $urlReplacements = [];
        foreach ($tableItem->data as $key => $value) {
            $htmlReplacements['[' . $key . ']'] = HtmlEncoder::encode(value: $value);
            $urlReplacements['[' . $key . ']'] = rawurlencode(string: (string) $value);
        }
        $linksHtml = [];
        foreach ($actionLinks as $key => $link) {
            if (is_string(value: $link)) {
                $linksHtml[$key] = strtr(string: $link, from: $htmlReplacements);
                continue;
            }
            $linksHtml[$key] = '<a href="' . strtr(string: $link['target'], from: $urlReplacements) . '" class="'
                . $link['cssClass'] . '">'
                . strtr(string: HtmlEncoder::encodeKeepQuotes(value: $link['label']), from: $htmlReplacements) . '</a>';
        }
        $html = implode(separator: PHP_EOL, array: $linksHtml);
        if (count(value: $linksHtml) === 1) {
            return $html;
        }

        return '<div class="' . $this->tdActionGroupClass . '">' . $html . '</div>';
    }

    private static function assertTargetDoesNotStartWithPlaceholder(string $linkTarget): void
    {
        // Browsers ignore leading control characters and spaces of a URL
        if (preg_match(pattern: '/^[\x00-\x20]*\[/', subject: $linkTarget) === 1) {
            throw new InvalidArgumentException(
                message: 'The link target "' . $linkTarget . '" starts with a placeholder: a value could become the'
                . ' scheme of the URL. Start the target with a fixed path.',
            );
        }
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
        $value = $tableItem->getRawValue(name: $this->hideDeleteLinkField);

        return ($value === null ? null : (string) $value) === $this->hideDeleteLinkValue;
    }
}
