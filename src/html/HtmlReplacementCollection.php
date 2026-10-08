<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use ArrayObject;

class HtmlReplacementCollection
{
    /** @var HtmlReplacement[] */
    private array $replacements = [];

    public function has(string $identifier): bool
    {
        return array_key_exists(
            key: $identifier,
            array: $this->replacements,
        );
    }

    public function get(string $identifier): ?HtmlReplacement
    {
        return $this->has(identifier: $identifier) ? $this->replacements[$identifier] : null;
    }

    public function addHtml(
        string  $identifier,
        ?string $html,
    ): void {
        $this->addHtmlText(
            identifier: $identifier,
            htmlText: $html === null ? null : HtmlText::fromHtml(
                html: $html,
            ),
        );
    }

    public function addHtmlText(
        string    $identifier,
        ?HtmlText $htmlText,
    ): void {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::htmlText(htmlText: $htmlText));
    }

    public function set(
        string           $identifier,
        ?HtmlReplacement $htmlReplacement,
    ): void {
        $this->replacements[$identifier] = $htmlReplacement;
    }

    public function addText(
        string  $identifier,
        ?string $text,
    ): void {
        $this->addHtmlText(
            identifier: $identifier,
            htmlText: $text === null ? null : HtmlText::fromText(
                text: $text,
            ),
        );
    }

    public function addInt(
        string $identifier,
        ?int   $int,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::int(int: $int),
        );
    }

    public function addFloat(
        string $identifier,
        ?float $float,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::float(float: $float),
        );
    }

    public function addBool(
        string $identifier,
        bool   $booleanValue,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::bool(bool: $booleanValue),
        );
    }

    public function addDataObject(
        string          $identifier,
        ?HtmlDataObject $htmlDataObject,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: $htmlDataObject === null ? null : HtmlReplacement::dataObject(htmlDataObject: $htmlDataObject),
        );
    }

    public function addHtmlTextCollection(
        string              $identifier,
        ?HtmlTextCollection $htmlTextCollection,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::textCollection(
                collection: $htmlTextCollection,
            ),
        );
    }

    public function addHtmlDataObjectCollection(
        string                    $identifier,
        ?HtmlDataObjectCollection $htmlDataObjectCollection,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::htmlDataObjectCollection(
                collection: $htmlDataObjectCollection,
            ),
        );
    }

    public function getArrayObject(): ArrayObject
    {
        $items = array_map(
            callback: fn($htmlReplacement) => $htmlReplacement?->getDataForRenderer(),
            array: $this->replacements,
        );

        return new ArrayObject(array: $items);
    }
}
