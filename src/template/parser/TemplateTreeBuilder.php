<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\parser;

use actra\yuf\template\TemplateException;

/**
 * Builds the node tree for `TemplateParser` and checks that every closing tag matches its opening tag.
 *
 * @internal
 */
final class TemplateTreeBuilder
{
    /** @var list<OpenTagFrame> */
    private array $openTags = [];
    /** @var list<TemplateNode> */
    private array $rootNodes = [];

    public function __construct(private readonly string $templateFile, private readonly string $namespacePrefix) {}

    public function addText(string $text): void
    {
        if ($text === '') {
            return;
        }
        $this->addNode(node: new TextNode(text: $text));
    }

    /**
     * @param array<string, string> $attributes
     */
    public function addTag(string $name, array $attributes, int $line): void
    {
        $this->addNode(node: new TagNode(name: $name, attributes: $attributes, children: [], line: $line, hasBody: false));
    }

    /**
     * @param array<string, string> $attributes
     */
    public function open(string $name, array $attributes, int $line): void
    {
        $this->openTags[] = new OpenTagFrame(name: $name, attributes: $attributes, line: $line);
    }

    public function close(string $name, int $line): void
    {
        $frame = array_pop(array: $this->openTags);
        if ($frame === null) {
            throw new TemplateException(
                reason: 'Unexpected closing tag </' . $this->namespacePrefix . ':' . $name . '> without an opening tag',
                templateFile: $this->templateFile,
                templateLine: $line,
            );
        }
        if ($frame->name !== $name) {
            throw new TemplateException(
                reason: 'The closing tag </' . $this->namespacePrefix . ':' . $name . '> does not match the opening tag <'
                    . $this->namespacePrefix . ':' . $frame->name . '> of line ' . $frame->line,
                templateFile: $this->templateFile,
                templateLine: $line,
            );
        }
        $this->addNode(
            node: new TagNode(
                name: $frame->name,
                attributes: $frame->attributes,
                children: $frame->children,
                line: $frame->line,
                hasBody: true,
            ),
        );
    }

    /**
     * @return list<TemplateNode>
     */
    public function finish(): array
    {
        $frame = array_pop(array: $this->openTags);
        if ($frame !== null) {
            throw new TemplateException(
                reason: 'The tag <' . $this->namespacePrefix . ':' . $frame->name . '> is not closed',
                templateFile: $this->templateFile,
                templateLine: $frame->line,
            );
        }

        return $this->rootNodes;
    }

    private function addNode(TemplateNode $node): void
    {
        $frame = end(array: $this->openTags);
        if ($frame === false) {
            $this->rootNodes[] = $node;

            return;
        }
        $frame->children[] = $node;
    }
}
