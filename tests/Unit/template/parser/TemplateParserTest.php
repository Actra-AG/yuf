<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\parser;

use actra\yuf\template\parser\TagNode;
use actra\yuf\template\parser\TemplateNode;
use actra\yuf\template\parser\TemplateParser;
use actra\yuf\template\parser\TextNode;
use actra\yuf\template\TemplateException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateParserTest extends TestCase
{
    /**
     * @return list<TemplateNode>
     */
    private function parse(string $source, string $namespacePrefix = 'tst'): array
    {
        return new TemplateParser(namespacePrefix: $namespacePrefix)->parse(source: $source, templateFile: 'page.html');
    }

    /**
     * @param list<TemplateNode> $nodes
     */
    private function tagAt(array $nodes, int $index): TagNode
    {
        $this->assertArrayHasKey($index, $nodes);
        $node = $nodes[$index];
        $this->assertInstanceOf(TagNode::class, $node);

        return $node;
    }

    public function testTextWithoutTagsIsOneTextNode(): void
    {
        $this->assertEquals([new TextNode(text: "a <b>&amp;</b>\n")], $this->parse(source: "a <b>&amp;</b>\n"));
    }

    public function testEmptySourceHasNoNodes(): void
    {
        $this->assertSame([], $this->parse(source: ''));
    }

    public function testInlineTag(): void
    {
        $nodes = $this->parse(source: "a{tst:lang key='greeting' vars='v'}b");

        $this->assertEquals(
            [
                new TextNode(text: 'a'),
                new TagNode(
                    name: 'lang',
                    attributes: ['key' => 'greeting', 'vars' => 'v'],
                    children: [],
                    line: 1,
                    hasBody: false,
                ),
                new TextNode(text: 'b'),
            ],
            $nodes,
        );
    }

    public function testInlineTagWithoutAttributes(): void
    {
        $this->assertEquals(
            [new TagNode(name: 'foo', attributes: [], children: [], line: 1, hasBody: false)],
            $this->parse(source: '{tst:foo}'),
        );
    }

    public function testSelfClosingElementTag(): void
    {
        $this->assertEquals(
            [new TagNode(name: 'text', attributes: ['value' => 'a.b'], children: [], line: 1, hasBody: false)],
            $this->parse(source: '<tst:text value="a.b"/>'),
        );
    }

    public function testElementTagWithChildren(): void
    {
        $nodes = $this->parse(source: '<tst:for value="l" var="i">[{tst:text value=\'i\'}]</tst:for>');

        $this->assertEquals(
            [
                new TagNode(
                    name: 'for',
                    attributes: ['value' => 'l', 'var' => 'i'],
                    children: [
                        new TextNode(text: '['),
                        new TagNode(name: 'text', attributes: ['value' => 'i'], children: [], line: 1, hasBody: false),
                        new TextNode(text: ']'),
                    ],
                    line: 1,
                    hasBody: true,
                ),
            ],
            $nodes,
        );
    }

    public function testEmptyElementTagWithClosingTagHasABody(): void
    {
        $this->assertEquals(
            [new TagNode(name: 'text', attributes: ['value' => 'x'], children: [], line: 1, hasBody: true)],
            $this->parse(source: '<tst:text value="x"></tst:text>'),
        );
    }

    public function testNestedTags(): void
    {
        $nodes = $this->parse(
            source: '<tst:for value="a" var="x"><tst:for value="x" var="y"><tst:if compare="y" against="1">Y'
                . '</tst:if></tst:for></tst:for>',
        );

        $outer = $this->tagAt(nodes: $nodes, index: 0);
        $inner = $this->tagAt(nodes: $outer->children, index: 0);
        $if = $this->tagAt(nodes: $inner->children, index: 0);
        $this->assertSame('if', $if->name);
        $this->assertEquals([new TextNode(text: 'Y')], $if->children);
    }

    public function testAttributesAreInAnyOrderAndMayBeEmpty(): void
    {
        $nodes = $this->parse(source: '<tst:if against="" compare="p" operator="EQ"/>');

        $this->assertEquals(
            [
                new TagNode(
                    name: 'if',
                    attributes: ['against' => '', 'compare' => 'p', 'operator' => 'EQ'],
                    children: [],
                    line: 1,
                    hasBody: false,
                ),
            ],
            $nodes,
        );
    }

    public function testValuesOfElementAttributesAreTrimmed(): void
    {
        $nodes = $this->parse(source: '<tst:text value=" a.b "/>');

        $this->assertEquals(
            [new TagNode(name: 'text', attributes: ['value' => 'a.b'], children: [], line: 1, hasBody: false)],
            $nodes,
        );
    }

    public function testAttributeValuesMayContainAngleBracketsAndBraces(): void
    {
        $nodes = $this->parse(source: '<tst:if compare="a" against="x > {y}"/>');

        $this->assertSame(
            ['compare' => 'a', 'against' => 'x > {y}'],
            $this->tagAt(nodes: $nodes, index: 0)->attributes,
        );
    }

    public function testLineNumbers(): void
    {
        $nodes = $this->parse(
            source: "line 1\n{tst:text value='a'}\n\n<tst:if compare=\"b\" against=\"c\">\n  {tst:text value='d'}\n"
                . '</tst:if>',
        );

        $this->assertSame(2, $this->tagAt(nodes: $nodes, index: 1)->line);
        $if = $this->tagAt(nodes: $nodes, index: 3);
        $this->assertSame(4, $if->line);
        $this->assertSame(5, $this->tagAt(nodes: $if->children, index: 1)->line);
    }

    public function testLineNumberCountsLineBreaksInAttributesAndComments(): void
    {
        $nodes = $this->parse(source: "<!--\n-->\n<tst:text\n value=\"a\"\n/>{tst:text value='b'}");

        $this->assertSame(3, $this->tagAt(nodes: $nodes, index: 2)->line);
        $this->assertSame(5, $this->tagAt(nodes: $nodes, index: 3)->line);
    }

    public function testTagsInHtmlCommentsStayText(): void
    {
        $nodes = $this->parse(source: "a<!-- {tst:text value='x'} <tst:text value=\"y\"/> -->b");

        $this->assertEquals(
            [
                new TextNode(text: 'a'),
                new TextNode(text: "<!-- {tst:text value='x'} <tst:text value=\"y\"/> -->"),
                new TextNode(text: 'b'),
            ],
            $nodes,
        );
    }

    public function testElementTagWithSingleQuotedAttributesStaysText(): void
    {
        $this->assertEquals(
            [new TextNode(text: "<tst:text value='x'/>")],
            $this->parse(source: "<tst:text value='x'/>"),
        );
    }

    public function testTagsOfOtherNamespacesStayText(): void
    {
        $source = '<other:text value="x"/>{other:text value=\'x\'}';

        $this->assertEquals([new TextNode(text: $source)], $this->parse(source: $source));
    }

    public function testNamespacePrefixIsAnArgument(): void
    {
        $nodes = $this->parse(source: "{x:text value='a'}{tst:text value='b'}", namespacePrefix: 'x');

        $this->assertEquals(
            [
                new TagNode(name: 'text', attributes: ['value' => 'a'], children: [], line: 1, hasBody: false),
                new TextNode(text: "{tst:text value='b'}"),
            ],
            $nodes,
        );
    }

    public function testHtmlAroundTheTagsIsKeptAsText(): void
    {
        $nodes = $this->parse(source: '<a href="{tst:text value=\'u\'}" class="x">go</a>');

        $this->assertEquals(
            [
                new TextNode(text: '<a href="'),
                new TagNode(name: 'text', attributes: ['value' => 'u'], children: [], line: 1, hasBody: false),
                new TextNode(text: '" class="x">go</a>'),
            ],
            $nodes,
        );
    }

    public function testMismatchedClosingTagThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The closing tag </tst:for> does not match the opening tag <tst:if> of line 2 in page.html on line 3',
        );

        $this->parse(source: "a\n<tst:if compare=\"v\" against=\"a\">\nY</tst:for>");
    }

    public function testClosingTagWithoutOpeningTagThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Unexpected closing tag </tst:if> without an opening tag in page.html on line 2',
        );

        $this->parse(source: "a\n</tst:if>");
    }

    public function testUnclosedTagThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('The tag <tst:for> is not closed in page.html on line 2');

        $this->parse(source: "a\n<tst:for value=\"l\" var=\"i\">x");
    }

    public function testExceptionHasFileAndLine(): void
    {
        try {
            $this->parse(source: "\n\n</tst:if>");
            TemplateParserTest::fail('A TemplateException is expected');
        } catch (TemplateException $exception) {
            $this->assertSame('page.html', $exception->templateFile);
            $this->assertSame(3, $exception->templateLine);
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function phpCodeProvider(): iterable
    {
        yield '<?php' => ['a<?php echo 1; ?>', 1];
        yield '<?=' => ["a\nb<?= \$x ?>", 2];
        yield 'short open tag' => ["a\n\n<? echo 1; ?>", 3];
        yield 'xml declaration' => ['<?xml version="1.0"?><a/>', 1];
        yield 'in an HTML comment' => ['<!-- <?php -->', 1];
    }

    #[DataProvider('phpCodeProvider')]
    public function testPhpCodeThrows(string $source, int $expectedLine): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'PHP code is not allowed in a template, prepare the values in the view instead in page.html on line '
                . $expectedLine,
        );

        $this->parse(source: $source);
    }
}
