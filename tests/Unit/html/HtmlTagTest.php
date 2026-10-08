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
        $attribute = HtmlTagAttribute::fromText(name: 'title', text: 'a "b" <c> & d');

        $this->assertSame('title="a &quot;b&quot; &lt;c&gt; &amp; d"', $attribute->render());
    }

    public function testAttributeValueIsOutputAsItIsWhenEncoded(): void
    {
        $attribute = HtmlTagAttribute::fromHtml(name: 'title', html: 'a &amp; b');

        $this->assertSame('title="a &amp; b"', $attribute->render());
    }

    public function testAttributeWithoutValueIsABooleanAttribute(): void
    {
        $this->assertSame(
            'disabled',
            HtmlTagAttribute::fromName(name: 'disabled')->render(),
        );
    }

    public function testIntegerAttributeValue(): void
    {
        $this->assertSame(
            'maxlength="30"',
            HtmlTagAttribute::fromText(name: 'maxlength', text: 30)->render(),
        );
    }

    public function testEmptyAttributeValueIsRenderedEmpty(): void
    {
        $this->assertSame(
            'value=""',
            HtmlTagAttribute::fromText(name: 'value', text: '')->render(),
        );
    }

    public function testNameAndValueOfAnAttributeAreReadable(): void
    {
        $attribute = HtmlTagAttribute::fromText(name: 'class', text: 'a');

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
            HtmlTagAttribute::fromText(name: 'type', text: 'text'),
            HtmlTagAttribute::fromName(name: 'required'),
        ]);

        $this->assertSame('<input type="text" required>', $tag->render());
    }

    public function testAttributesCanBeAddedLater(): void
    {
        $tag = new HtmlTag(name: 'a', selfClosing: false);
        $tag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'href', text: '/x?a=1&b=2'),
        );

        $this->assertSame('<a href="/x?a=1&amp;b=2"></a>', $tag->render());
    }

    public function testNestedTagsAndTexts(): void
    {
        $list = new HtmlTag(name: 'ul', selfClosing: false);
        $item = new HtmlTag(name: 'li', selfClosing: false, htmlTagAttributes: [
            HtmlTagAttribute::fromText(name: 'class', text: 'first'),
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
            HtmlTagAttribute::fromText(name: $name, text: 'v')->render(),
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
        HtmlTagAttribute::fromText(name: $name, text: 'v');
    }

    public function testAttributeWithoutValueHasNoValueProperty(): void
    {
        $this->assertNull(HtmlTagAttribute::fromName(name: 'disabled')->value);
    }

    #[DataProvider('invalidAttributeNameProvider')]
    public function testInvalidAttributeNameIsRejectedByEveryNamedConstructor(string $name): void
    {
        foreach ([
            static fn(): HtmlTagAttribute => HtmlTagAttribute::fromName(name: $name),
            static fn(): HtmlTagAttribute => HtmlTagAttribute::fromHtml(name: $name, html: 'v'),
        ] as $create) {
            try {
                $create();
                HtmlTagTest::fail('An InvalidArgumentException was expected for "' . $name . '".');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('Invalid HTML attribute name', $exception->getMessage());
            }
        }
    }

    public function testEncodedValueWithDoubleQuoteIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('passed as HTML but contains a double quote');
        HtmlTagAttribute::fromHtml(name: 'title', html: 'a" onclick="x');
    }

    public function testDoubleQuoteInAPlainValueIsEscaped(): void
    {
        $this->assertSame(
            'title="a&quot; onclick=&quot;x"',
            HtmlTagAttribute::fromText(name: 'title', text: 'a" onclick="x')->render(),
        );
    }

    public function testEncodedValueWithSingleQuoteAndEntitiesIsAllowed(): void
    {
        $this->assertSame(
            "title=\"it's &quot;x&quot;\"",
            HtmlTagAttribute::fromHtml(name: 'title', html: "it's &quot;x&quot;")->render(),
        );
    }

    public function testAttributeValueWithInvalidUtf8IsSubstituted(): void
    {
        $this->assertSame(
            "title=\"a\u{FFFD}b\"",
            HtmlTagAttribute::fromText(name: 'title', text: "a\xFFb")->render(),
        );
    }

    public function testTextWithInvalidUtf8IsSubstituted(): void
    {
        $this->assertSame("a\u{FFFD}b", HtmlText::fromText(text: "a\xFFb")->render());
    }
}
