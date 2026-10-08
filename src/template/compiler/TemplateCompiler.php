<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\compiler;

use actra\yuf\template\parser\TagNode;
use actra\yuf\template\parser\TemplateNode;
use actra\yuf\template\parser\TextNode;
use actra\yuf\template\runtime\ComparisonOperatorEnum;
use actra\yuf\template\TemplateException;
use LogicException;

/**
 * Turns the node tree of a template into PHP source (design section 6). The compiled code only uses the variable
 * `$runtime` and literals created with `var_export()`, template text is never concatenated into code. `if`, `else` and
 * `for` become PHP blocks, every other tag becomes a call of `$runtime->renderTag()`.
 *
 * A single line break directly after a tag is removed, as the PHP closing tag of the old compiled code did, so the
 * output stays byte-identical. The whitespace between `</tst:if>` and `<tst:else>` belongs to the `if` branch.
 *
 * @internal
 */
final readonly class TemplateCompiler
{
    /** Changed with every change of the compiled code; part of the cache key, so an upgrade never runs old code. */
    public const string FORMAT_VERSION = '1';

    /** Compiled by the compiler itself, own tags cannot use these names. */
    public const array NATIVE_TAGS = ['if', 'else', 'for'];

    /**
     * @param list<TemplateNode> $nodes
     *
     * @throws TemplateException for an `if` or `for` without its attributes, an unknown operator and an `else` that
     *                           does not follow an `if`
     */
    public function compile(array $nodes, string $templateFile): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n// yuf template, format version "
            . TemplateCompiler::FORMAT_VERSION . "\n\n"
            . $this->compileNodes(nodes: $nodes, templateFile: $templateFile, depth: 0, skipLineBreak: false);
    }

    /**
     * @param list<TemplateNode> $nodes
     */
    private function compileNodes(array $nodes, string $templateFile, int $depth, bool $skipLineBreak): string
    {
        $code = '';
        foreach ($this->groupConditionals(nodes: $nodes) as $node) {
            if ($node instanceof TextNode) {
                $code .= $this->compileText(text: $node->text, depth: $depth, skipLineBreak: $skipLineBreak);
                $skipLineBreak = false;

                continue;
            }
            $code .= $this->compileStatement(node: $node, templateFile: $templateFile, depth: $depth);
            $skipLineBreak = true;
        }

        return $code;
    }

    private function compileStatement(TemplateNode $node, string $templateFile, int $depth): string
    {
        if ($node instanceof IfElseNode) {
            return $this->compileIf(node: $node, templateFile: $templateFile, depth: $depth);
        }
        if (!$node instanceof TagNode) {
            throw new LogicException(message: 'Unknown template node ' . $node::class);
        }

        return match ($node->name) {
            'for' => $this->compileFor(tag: $node, templateFile: $templateFile, depth: $depth),
            'else' => throw new TemplateException(
                reason: 'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between)',
                templateFile: $templateFile,
                templateLine: $node->line,
            ),
            default => $this->compileGenericTag(tag: $node, templateFile: $templateFile, depth: $depth),
        };
    }

    /**
     * Joins every `if` with the `else` that follows it with only whitespace in between. The whitespace is rendered
     * with the `if` branch.
     *
     * @param list<TemplateNode> $nodes
     *
     * @return list<IfElseNode|TemplateNode>
     */
    private function groupConditionals(array $nodes): array
    {
        $grouped = [];
        $consumedUntil = 0;
        foreach ($nodes as $index => $node) {
            if ($index < $consumedUntil) {
                continue;
            }
            if (!$node instanceof TagNode || $node->name !== 'if') {
                $grouped[] = $node;

                continue;
            }
            $ifElse = $this->joinElse(if: $node, following: array_slice(array: $nodes, offset: $index + 1));
            $grouped[] = $ifElse;
            $consumedUntil = $ifElse->else === null ? 0 : $index + 2 + count(value: $ifElse->whitespace);
        }

        return $grouped;
    }

    /**
     * @param list<TemplateNode> $following
     */
    private function joinElse(TagNode $if, array $following): IfElseNode
    {
        $whitespace = [];
        foreach ($following as $node) {
            if ($node instanceof TextNode && trim(string: $node->text) === '') {
                $whitespace[] = $node;

                continue;
            }
            if ($node instanceof TagNode && $node->name === 'else') {
                return new IfElseNode(if: $if, whitespace: $whitespace, else: $node);
            }

            break;
        }

        return new IfElseNode(if: $if, whitespace: [], else: null);
    }

    private function compileText(string $text, int $depth, bool $skipLineBreak): string
    {
        if ($skipLineBreak) {
            $text = $this->removeLeadingLineBreak(text: $text);
        }
        if ($text === '') {
            return '';
        }

        return $this->indent(depth: $depth) . 'echo ' . $this->literal(value: $text) . ";\n";
    }

    private function compileIf(IfElseNode $node, string $templateFile, int $depth): string
    {
        $tag = $node->if;
        $operator = $this->readOperator(tag: $tag, templateFile: $templateFile);
        $indent = $this->indent(depth: $depth);
        $code = $indent . 'if ($runtime->compare('
            . $this->literal(
                value: $this->requireAttribute(tag: $tag, name: 'compare', templateFile: $templateFile),
            ) . ', '
            . $this->literal(value: $operator->value) . ', '
            . $this->literal(
                value: $this->requireAttribute(tag: $tag, name: 'against', templateFile: $templateFile),
            ) . ', '
            . $tag->line . ")) {\n"
            . $this->compileNodes(
                nodes: $tag->children,
                templateFile: $templateFile,
                depth: $depth + 1,
                skipLineBreak: true,
            )
            . $this->compileNodes(
                nodes: $node->whitespace,
                templateFile: $templateFile,
                depth: $depth + 1,
                skipLineBreak: false,
            );
        if ($node->else !== null) {
            $code .= $indent . "} else {\n"
                . $this->compileNodes(
                    nodes: $node->else->children,
                    templateFile: $templateFile,
                    depth: $depth + 1,
                    skipLineBreak: true,
                );
        }

        return $code . $indent . "}\n";
    }

    private function compileFor(TagNode $tag, string $templateFile, int $depth): string
    {
        $indent = $this->indent(depth: $depth);
        $variable = $this->requireAttribute(tag: $tag, name: 'var', templateFile: $templateFile);
        $selector = $this->requireAttribute(tag: $tag, name: 'value', templateFile: $templateFile);

        return $indent . 'foreach ($runtime->iterate(' . $this->literal(value: $selector) . ', ' . $tag->line
            . ') as $item) {' . "\n"
            . $indent . '    $runtime->pushScope(' . $this->literal(value: $variable) . ', $item);' . "\n"
            . $this->compileNodes(
                nodes: $tag->children,
                templateFile: $templateFile,
                depth: $depth + 1,
                skipLineBreak: true,
            )
            . $indent . "    \$runtime->popScope();\n"
            . $indent . "}\n";
    }

    private function compileGenericTag(TagNode $tag, string $templateFile, int $depth): string
    {
        $indent = $this->indent(depth: $depth);
        $attributes = [];
        foreach ($tag->attributes as $name => $value) {
            $attributes[] = $this->literal(value: $name) . ' => ' . $this->literal(value: $value);
        }
        $body = 'null';
        if ($tag->hasBody) {
            $body = "static function () use (\$runtime): string {\n"
                . $indent . "    ob_start();\n"
                . $this->compileNodes(
                    nodes: $tag->children,
                    templateFile: $templateFile,
                    depth: $depth + 1,
                    skipLineBreak: true,
                )
                . $indent . "    return (string) ob_get_clean();\n"
                . $indent . '}';
        }

        return $indent . 'echo $runtime->renderTag(' . $this->literal(value: $tag->name) . ', ['
            . implode(separator: ', ', array: $attributes) . '], ' . $tag->line . ', ' . $body . ");\n";
    }

    private function readOperator(TagNode $tag, string $templateFile): ComparisonOperatorEnum
    {
        $attribute = array_key_exists(key: 'operator', array: $tag->attributes) ? $tag->attributes['operator'] : 'eq';
        $operator = ComparisonOperatorEnum::tryFromAttribute(attribute: $attribute);
        if ($operator === null) {
            throw new TemplateException(
                reason: 'Unknown operator "' . $attribute . '", valid are in, eq, ne, gt, ge, lt, le',
                templateFile: $templateFile,
                templateLine: $tag->line,
            );
        }

        return $operator;
    }

    private function requireAttribute(TagNode $tag, string $name, string $templateFile): string
    {
        if (!array_key_exists(key: $name, array: $tag->attributes)) {
            throw new TemplateException(
                reason: 'Missing attribute "' . $name . '" for the tag "' . $tag->name . '"',
                templateFile: $templateFile,
                templateLine: $tag->line,
            );
        }

        return $tag->attributes[$name];
    }

    private function removeLeadingLineBreak(string $text): string
    {
        return match (true) {
            str_starts_with(haystack: $text, needle: "\r\n") => substr(string: $text, offset: 2),
            str_starts_with(haystack: $text, needle: "\n"),
            str_starts_with(haystack: $text, needle: "\r") => substr(string: $text, offset: 1),
            default => $text,
        };
    }

    private function literal(string $value): string
    {
        return var_export(value: $value, return: true);
    }

    private function indent(int $depth): string
    {
        return str_repeat(string: '    ', times: $depth);
    }
}
