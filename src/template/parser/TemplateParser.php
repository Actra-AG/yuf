<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\parser;

use actra\yuf\template\TemplateException;

/**
 * Turns the source of a template into a tree of `TextNode` and `TagNode` (design section 2). It does not know the tags:
 * every `{tst:name …}` and `<tst:name …>` is a `TagNode`. Everything else, including HTML comments, is text.
 *
 * @internal
 */
final readonly class TemplateParser
{
    private string $tokenPattern;

    public function __construct(private string $namespacePrefix = 'tst')
    {
        $prefix = preg_quote(str: $this->namespacePrefix, delimiter: '~');
        // 1, 2: inline tag name and attributes; 3: slash of a closing tag; 4, 5: element tag name and attributes;
        // 6: slash of a self-closing tag. HTML comments are matched first, so tags in comments stay text.
        $this->tokenPattern = '~<!--.*?-->'
            . '|\{' . $prefix . ':(\w+)((?:\s+\w+=\'[^\']*\')*)\s*\}'
            . '|<(/)?' . $prefix . ':(\w+)((?:\s+[\w-]+="[^"]*")*)\s*(/)?>~s';
    }

    /**
     * @return list<TemplateNode>
     *
     * @throws TemplateException for PHP code, a closing tag that does not match and a tag that is not closed
     */
    public function parse(string $source, string $templateFile): array
    {
        $this->rejectPhpCode(source: $source, templateFile: $templateFile);
        $builder = new TemplateTreeBuilder(templateFile: $templateFile, namespacePrefix: $this->namespacePrefix);
        preg_match_all(
            pattern: $this->tokenPattern,
            subject: $source,
            matches: $tokens,
            flags: PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        );
        $position = 0;
        $line = 1;
        /** @var list<array<int, array{0: string|null, 1: int}>> $tokens */
        foreach ($tokens as $groups) {
            $tokenText = $this->groupText(groups: $groups, index: 0) ?? '';
            $offset = $this->groupOffset(groups: $groups);
            $text = substr(string: $source, offset: $position, length: $offset - $position);
            $builder->addText(text: $text);
            $line += substr_count(haystack: $text, needle: "\n");
            $this->addToken(builder: $builder, groups: $groups, line: $line);
            $line += substr_count(haystack: $tokenText, needle: "\n");
            $position = $offset + strlen(string: $tokenText);
        }
        $builder->addText(text: substr(string: $source, offset: $position));

        return $builder->finish();
    }

    private function rejectPhpCode(string $source, string $templateFile): void
    {
        $offset = strpos(haystack: $source, needle: '<?');
        if ($offset === false) {
            return;
        }

        throw new TemplateException(
            reason: 'PHP code is not allowed in a template, prepare the values in the view instead',
            templateFile: $templateFile,
            templateLine: 1 + substr_count(haystack: $source, needle: "\n", offset: 0, length: $offset),
        );
    }

    /**
     * @param array<int, array{0: string|null, 1: int}> $groups
     */
    private function addToken(TemplateTreeBuilder $builder, array $groups, int $line): void
    {
        $elementName = $this->groupText(groups: $groups, index: 4);
        $inlineName = $this->groupText(groups: $groups, index: 1);
        if ($elementName !== null) {
            $this->addElementTag(builder: $builder, groups: $groups, name: $elementName, line: $line);

            return;
        }
        if ($inlineName !== null) {
            $builder->addTag(
                name: $inlineName,
                attributes: $this->parseAttributes(attributes: $this->groupText(groups: $groups, index: 2) ?? '', quote: "'"),
                line: $line,
            );

            return;
        }
        $builder->addText(text: $this->groupText(groups: $groups, index: 0) ?? '');
    }

    /**
     * @param array<int, array{0: string|null, 1: int}> $groups
     */
    private function addElementTag(TemplateTreeBuilder $builder, array $groups, string $name, int $line): void
    {
        if ($this->groupText(groups: $groups, index: 3) !== null) {
            $builder->close(name: $name, line: $line);

            return;
        }
        $attributes = $this->parseAttributes(attributes: $this->groupText(groups: $groups, index: 5) ?? '', quote: '"');
        if ($this->groupText(groups: $groups, index: 6) !== null) {
            $builder->addTag(name: $name, attributes: $attributes, line: $line);

            return;
        }
        $builder->open(name: $name, attributes: $attributes, line: $line);
    }

    /**
     * @param array<int, array{0: string|null, 1: int}> $groups
     */
    private function groupText(array $groups, int $index): ?string
    {
        return array_key_exists(key: $index, array: $groups) ? $groups[$index][0] : null;
    }

    /**
     * @param array<int, array{0: string|null, 1: int}> $groups
     */
    private function groupOffset(array $groups): int
    {
        return array_key_exists(key: 0, array: $groups) ? $groups[0][1] : 0;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attributes, string $quote): array
    {
        preg_match_all(
            pattern: '~([\w-]+)=' . $quote . '([^' . $quote . ']*)' . $quote . '~',
            subject: $attributes,
            matches: $matches,
            flags: PREG_PATTERN_ORDER,
        );
        /** @var array{list<string>, list<string>, list<string>} $matches */
        $values = $quote === '"' ? array_map(callback: trim(...), array: $matches[2]) : $matches[2];
        $parsed = array_combine(keys: $matches[1], values: $values);

        return $parsed;
    }
}
