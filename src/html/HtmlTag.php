<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use InvalidArgumentException;
use LogicException;
use Override;

/**
 * An HTML element with attributes and children (tags and texts), rendered as `<name attributes>children</name>`.
 */
final class HtmlTag extends HtmlElement
{
    private const string NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9-]*$/D';

    /** @var list<HtmlTagAttribute> */
    private array $htmlTagAttributes = [];
    /** @var list<HtmlText|HtmlTag> */
    private array $childElements = [];

    /**
     * @param string $name Letters, digits and `-`, starting with a letter (the name is output as it is)
     * @param bool $selfClosing Void elements like `input` or `br`: no children, no closing tag
     * @param list<HtmlTagAttribute> $htmlTagAttributes
     *
     * @throws InvalidArgumentException for an invalid tag name
     */
    public function __construct(string $name, private readonly bool $selfClosing, array $htmlTagAttributes = [])
    {
        if (preg_match(pattern: HtmlTag::NAME_PATTERN, subject: $name) !== 1) {
            throw new InvalidArgumentException(
                message: 'Invalid HTML tag name "' . $name
                    . '": use letters, digits and - only, starting with a letter.',
            );
        }
        parent::__construct(name: $name);
        foreach ($htmlTagAttributes as $htmlTagAttribute) {
            $this->addHtmlTagAttribute(htmlTagAttribute: $htmlTagAttribute);
        }
    }

    public function addHtmlTagAttribute(HtmlTagAttribute $htmlTagAttribute): void
    {
        $this->htmlTagAttributes[] = $htmlTagAttribute;
    }

    /**
     * @throws LogicException if the tag is self-closing
     */
    public function addTag(HtmlTag $htmlTag): void
    {
        $this->addChildElement(childElement: $htmlTag);
    }

    /**
     * @throws LogicException if the tag is self-closing
     */
    public function addText(HtmlText $htmlText): void
    {
        $this->addChildElement(childElement: $htmlText);
    }

    #[Override]
    public function render(): string
    {
        $html = '<' . $this->name;
        foreach ($this->htmlTagAttributes as $htmlTagAttribute) {
            $html .= ' ' . $htmlTagAttribute->render();
        }
        $html .= '>';
        if ($this->selfClosing) {
            return $html;
        }
        foreach ($this->childElements as $childElement) {
            $html .= $childElement->render();
        }

        return $html . '</' . $this->name . '>';
    }

    private function addChildElement(HtmlText|HtmlTag $childElement): void
    {
        if ($this->selfClosing) {
            throw new LogicException(message: 'A self-closing tag cannot have child elements');
        }
        $this->childElements[] = $childElement;
    }
}
