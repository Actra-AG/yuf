<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlTagTest extends TestCase
{
    public function testTextFromTextIsEscaped(): void
    {
        $this->assertSame('&lt;b&gt; &amp; &quot;', HtmlText::fromText(text: '<b> & "')->render());
    }

    public function testTextFromHtmlIsOutputAsItIs(): void
    {
        $this->assertSame('<b>x</b> &amp;', HtmlText::fromHtml(html: '<b>x</b> &amp;')->render());
    }

    public function testAttributeValueIsEscapedWhenNotEncoded(): void
    {
        $attribute = new HtmlTagAttribute(name: 'title', value: 'a "b" <c> & d', valueIsEncodedForRendering: false);

        $this->assertSame('title="a &quot;b&quot; &lt;c&gt; &amp; d"', $attribute->render());
    }

    public function testAttributeValueIsOutputAsItIsWhenEncoded(): void
    {
        $attribute = new HtmlTagAttribute(name: 'title', value: 'a &amp; b', valueIsEncodedForRendering: true);

        $this->assertSame('title="a &amp; b"', $attribute->render());
    }

    public function testAttributeWithoutValueIsABooleanAttribute(): void
    {
        $this->assertSame(
            'disabled',
            new HtmlTagAttribute(name: 'disabled', value: null, valueIsEncodedForRendering: true)->render(),
        );
    }

    public function testIntegerAttributeValue(): void
    {
        $this->assertSame(
            'maxlength="30"',
            new HtmlTagAttribute(name: 'maxlength', value: 30, valueIsEncodedForRendering: false)->render(),
        );
    }

    public function testEmptyAttributeValueIsRenderedEmpty(): void
    {
        $this->assertSame(
            'value=""',
            new HtmlTagAttribute(name: 'value', value: '', valueIsEncodedForRendering: false)->render(),
        );
    }

    public function testNameAndValueOfAnAttributeAreReadable(): void
    {
        $attribute = new HtmlTagAttribute(name: 'class', value: 'a', valueIsEncodedForRendering: true);

        $this->assertSame('class', $attribute->name);
        $this->assertSame('a', $attribute->value);
    }

    public function testEmptyTag(): void
    {
        $this->assertSame('<div></div>', new HtmlTag(name: 'div', selfClosing: false)->render());
    }

    public function testSelfClosingTagHasNoClosingTag(): void
    {
        $tag = new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: [
            new HtmlTagAttribute(name: 'type', value: 'text', valueIsEncodedForRendering: true),
            new HtmlTagAttribute(name: 'required', value: null, valueIsEncodedForRendering: true),
        ]);

        $this->assertSame('<input type="text" required>', $tag->render());
    }

    public function testAttributesCanBeAddedLater(): void
    {
        $tag = new HtmlTag(name: 'a', selfClosing: false);
        $tag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(name: 'href', value: '/x?a=1&b=2', valueIsEncodedForRendering: false),
        );

        $this->assertSame('<a href="/x?a=1&amp;b=2"></a>', $tag->render());
    }

    public function testNestedTagsAndTexts(): void
    {
        $list = new HtmlTag(name: 'ul', selfClosing: false);
        $item = new HtmlTag(name: 'li', selfClosing: false, htmlTagAttributes: [
            new HtmlTagAttribute(name: 'class', value: 'first', valueIsEncodedForRendering: true),
        ]);
        $item->addText(htmlText: HtmlText::fromText(text: 'a < b'));
        $item->addText(htmlText: HtmlText::fromHtml(html: '<em>!</em>'));
        $list->addTag(htmlTag: $item);
        $list->addTag(htmlTag: new HtmlTag(name: 'li', selfClosing: false));

        $this->assertSame('<ul><li class="first">a &lt; b<em>!</em></li><li></li></ul>', $list->render());
    }

    public function testSelfClosingTagCannotHaveChildren(): void
    {
        $tag = new HtmlTag(name: 'br', selfClosing: true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A self-closing tag cannot have child elements');
        $tag->addText(htmlText: HtmlText::fromText(text: 'x'));
    }

    public function testSelfClosingTagCannotHaveTagChildren(): void
    {
        $tag = new HtmlTag(name: 'br', selfClosing: true);

        $this->expectException(LogicException::class);
        $tag->addTag(htmlTag: new HtmlTag(name: 'span', selfClosing: false));
    }

    public function testRenderingTwiceGivesTheSameResult(): void
    {
        $tag = new HtmlTag(name: 'p', selfClosing: false);
        $tag->addText(htmlText: HtmlText::fromText(text: 'x'));

        $this->assertSame($tag->render(), $tag->render());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validTagNameProvider(): iterable
    {
        yield 'lower case' => ['div'];
        yield 'upper case' => ['DIV'];
        yield 'with digit' => ['h1'];
        yield 'custom element' => ['my-element'];
    }

    #[DataProvider('validTagNameProvider')]
    public function testValidTagName(string $name): void
    {
        $this->assertSame('<' . $name . '></' . $name . '>', new HtmlTag(name: $name, selfClosing: false)->render());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTagNameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['div class="x"'];
        yield 'closing bracket' => ['div><script>'];
        yield 'slash' => ['/div'];
        yield 'starts with digit' => ['1div'];
        yield 'starts with dash' => ['-div'];
        yield 'quote' => ['a"b'];
        yield 'trailing line break' => ["div\n"];
    }

    #[DataProvider('invalidTagNameProvider')]
    public function testInvalidTagNameIsRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid HTML tag name');
        new HtmlTag(name: $name, selfClosing: false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAttributeNameProvider(): iterable
    {
        yield 'plain' => ['class'];
        yield 'data' => ['data-confirm-message'];
        yield 'aria' => ['aria-describedby'];
        yield 'namespace' => ['xml:lang'];
        yield 'camel case' => ['viewBox'];
        yield 'underscore' => ['_x'];
    }

    #[DataProvider('validAttributeNameProvider')]
    public function testValidAttributeName(string $name): void
    {
        $this->assertSame(
            $name . '="v"',
            new HtmlTagAttribute(name: $name, value: 'v', valueIsEncodedForRendering: false)->render(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAttributeNameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['a b'];
        yield 'equals sign' => ['a=b'];
        yield 'quote' => ['a"b'];
        yield 'injected attribute' => ['a" onclick="x'];
        yield 'angle bracket' => ['a>'];
        yield 'starts with digit' => ['1a'];
        yield 'starts with dash' => ['-a'];
        yield 'trailing line break' => ["a\n"];
    }

    #[DataProvider('invalidAttributeNameProvider')]
    public function testInvalidAttributeNameIsRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid HTML attribute name');
        new HtmlTagAttribute(name: $name, value: 'v', valueIsEncodedForRendering: false);
    }

    public function testEncodedValueWithDoubleQuoteIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('marked as encoded but contains a double quote');
        new HtmlTagAttribute(name: 'title', value: 'a" onclick="x', valueIsEncodedForRendering: true);
    }

    public function testDoubleQuoteInAPlainValueIsEscaped(): void
    {
        $this->assertSame(
            'title="a&quot; onclick=&quot;x"',
            new HtmlTagAttribute(name: 'title', value: 'a" onclick="x', valueIsEncodedForRendering: false)->render(),
        );
    }

    public function testEncodedValueWithSingleQuoteAndEntitiesIsAllowed(): void
    {
        $this->assertSame(
            "title=\"it's &quot;x&quot;\"",
            new HtmlTagAttribute(name: 'title', value: "it's &quot;x&quot;", valueIsEncodedForRendering: true)->render(),
        );
    }

    public function testAttributeValueWithInvalidUtf8IsSubstituted(): void
    {
        $this->assertSame(
            "title=\"a\u{FFFD}b\"",
            new HtmlTagAttribute(name: 'title', value: "a\xFFb", valueIsEncodedForRendering: false)->render(),
        );
    }

    public function testTextWithInvalidUtf8IsSubstituted(): void
    {
        $this->assertSame("a\u{FFFD}b", HtmlText::fromText(text: "a\xFFb")->render());
    }
}
