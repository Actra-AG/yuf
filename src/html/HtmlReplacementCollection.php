<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use ArrayObject;
use Closure;

/**
 * The values of a template by identifier. `addText()` and `addHtml()` say what they do: plain text is escaped, HTML
 * that the application built itself is output as it is; never pass user data to `addHtml()`.
 */
final class HtmlReplacementCollection
{
    /** @var array<string, HtmlReplacement|null> */
    private array $replacements = [];

    public function has(string $identifier): bool
    {
        return array_key_exists(key: $identifier, array: $this->replacements);
    }

    public function get(string $identifier): ?HtmlReplacement
    {
        return $this->replacements[$identifier] ?? null;
    }

    /**
     * @param string|null $html Trusted HTML, output as it is
     */
    public function addHtml(
        string $identifier,
        ?string $html,
    ): void {
        $this->addHtmlText(
            identifier: $identifier,
            htmlText: $html === null ? null : HtmlText::fromHtml(html: $html),
        );
    }

    public function addHtmlText(
        string $identifier,
        ?HtmlText $htmlText,
    ): void {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::fromHtmlText(htmlText: $htmlText));
    }

    public function set(
        string $identifier,
        ?HtmlReplacement $htmlReplacement,
    ): void {
        $this->replacements[$identifier] = $htmlReplacement;
    }

    /**
     * Adds HTML that is only built when a template reads the value (not when the replacements are rendered into the
     * template data without the template using it).
     *
     * @param Closure(): string $html Returns trusted HTML, output as it is
     */
    public function addLazyHtml(string $identifier, Closure $html): void
    {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::fromLazyHtml(html: $html));
    }

    /**
     * @param string|null $text Plain text, escaped when rendered
     */
    public function addText(
        string $identifier,
        ?string $text,
    ): void {
        $this->addHtmlText(
            identifier: $identifier,
            htmlText: $text === null ? null : HtmlText::fromText(text: $text),
        );
    }

    public function addInt(
        string $identifier,
        ?int $int,
    ): void {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::fromInt(int: $int));
    }

    public function addFloat(
        string $identifier,
        ?float $float,
    ): void {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::fromFloat(float: $float));
    }

    public function addBool(
        string $identifier,
        bool $booleanValue,
    ): void {
        $this->set(identifier: $identifier, htmlReplacement: HtmlReplacement::fromBool(bool: $booleanValue));
    }

    public function addDataObject(
        string $identifier,
        ?HtmlDataObject $htmlDataObject,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: $htmlDataObject === null
                ? null
                : HtmlReplacement::fromDataObject(htmlDataObject: $htmlDataObject),
        );
    }

    public function addHtmlTextCollection(
        string $identifier,
        ?HtmlTextCollection $htmlTextCollection,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::fromTextCollection(collection: $htmlTextCollection),
        );
    }

    public function addHtmlDataObjectCollection(
        string $identifier,
        ?HtmlDataObjectCollection $htmlDataObjectCollection,
    ): void {
        $this->set(
            identifier: $identifier,
            htmlReplacement: HtmlReplacement::fromHtmlDataObjectCollection(collection: $htmlDataObjectCollection),
        );
    }

    /**
     * The values as the template engine reads them: texts rendered to HTML, `null` for an identifier without value.
     *
     * Not typed more narrowly because the engine and tests add other values (arrays, objects) to it: `ArrayObject` is
     * invariant.
     *
     * @return ArrayObject<string, mixed>
     */
    public function getArrayObject(): ArrayObject
    {
        /** @var ArrayObject<string, mixed> $arrayObject */
        $arrayObject = new ArrayObject();
        foreach ($this->replacements as $identifier => $htmlReplacement) {
            $arrayObject[$identifier] = $htmlReplacement?->getDataForRenderer();
        }

        return $arrayObject;
    }
}
