<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\template\SelectorTarget;
use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use ArrayObject;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1).
 *
 * Today the engine never escapes: a plain string is output as it is, an HtmlText created with encoded() is output as
 * it is, and only HtmlText::unencoded() (and addUnencodedText()) is escaped by the replacement API, not by the engine.
 */
final class TemplateTextTagTest extends TemplateCharacterizationTestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>|HtmlReplacementCollection, string}>
     */
    public static function valueProvider(): iterable
    {
        yield 'plain string is not escaped' => [['x' => '<b>a</b> & "q" \'s\''], '<b>a</b> & "q" \'s\''];
        yield 'empty string' => [['x' => ''], ''];
        yield 'int' => [['x' => 42], '42'];
        yield 'negative int' => [['x' => -3], '-3'];
        yield 'zero' => [['x' => 0], '0'];
        yield 'float' => [['x' => 1.5], '1.5'];
        yield 'true' => [['x' => true], '1'];
        yield 'false' => [['x' => false], ''];
        yield 'null' => [['x' => null], ''];

        $encoded = new HtmlReplacementCollection();
        $encoded->addHtmlText(identifier: 'x', htmlText: HtmlText::encoded(textContent: '<b>a</b>'));
        yield 'HtmlText::encoded is output raw' => [$encoded, '<b>a</b>'];

        $unencoded = new HtmlReplacementCollection();
        $unencoded->addHtmlText(identifier: 'x', htmlText: HtmlText::unencoded(textContent: '<b>a</b>'));
        yield 'HtmlText::unencoded is escaped' => [$unencoded, '&lt;b&gt;a&lt;/b&gt;'];

        $unencodedQuotes = new HtmlReplacementCollection();
        $unencodedQuotes->addUnencodedText(identifier: 'x', content: 'a & "b" \'c\'');
        yield 'unencoded text escapes quotes and ampersand' => [$unencodedQuotes, 'a &amp; &quot;b&quot; &#039;c&#039;'];

        $encodedText = new HtmlReplacementCollection();
        $encodedText->addEncodedText(identifier: 'x', content: '<i>x</i>');
        yield 'addEncodedText is output raw' => [$encodedText, '<i>x</i>'];

        $nullText = new HtmlReplacementCollection();
        $nullText->addEncodedText(identifier: 'x', content: null);
        yield 'addEncodedText with null' => [$nullText, ''];

        $int = new HtmlReplacementCollection();
        $int->addInt(identifier: 'x', int: 7);
        yield 'addInt' => [$int, '7'];

        $float = new HtmlReplacementCollection();
        $float->addFloat(identifier: 'x', float: 2.25);
        yield 'addFloat' => [$float, '2.25'];

        $bool = new HtmlReplacementCollection();
        $bool->addBool(identifier: 'x', booleanValue: true);
        yield 'addBool true' => [$bool, '1'];

        $boolFalse = new HtmlReplacementCollection();
        $boolFalse->addBool(identifier: 'x', booleanValue: false);
        yield 'addBool false' => [$boolFalse, ''];
    }

    /**
     * @param array<string, mixed>|HtmlReplacementCollection $data
     */
    #[DataProvider('valueProvider')]
    public function testInlineTagOutputsValue(array|HtmlReplacementCollection $data, string $expected): void
    {
        $this->assertSame('[' . $expected . ']', $this->renderSource(source: "[{tst:text value='x'}]", data: $data));
    }

    /**
     * @param array<string, mixed>|HtmlReplacementCollection $data
     */
    #[DataProvider('valueProvider')]
    public function testElementTagOutputsValue(array|HtmlReplacementCollection $data, string $expected): void
    {
        $this->assertSame('[' . $expected . ']', $this->renderSource(source: '[<tst:text value="x"/>]', data: $data));
    }

    public function testElementTagIsSelfClosingSoAClosingTagStaysAsText(): void
    {
        $html = $this->renderSource(source: '<tst:text value="x"></tst:text>', data: ['x' => 'v']);

        $this->assertSame('v</tst:text>', $html);
    }

    public function testInlineTagInAttributeValue(): void
    {
        $html = $this->renderSource(
            source: '<a href="{tst:text value=\'url\'}" title="{tst:text value=\'title\'}">x</a>',
            data: ['url' => '?a=1&b=2', 'title' => 'T'],
        );

        $this->assertSame('<a href="?a=1&b=2" title="T">x</a>', $html);
    }

    public function testSeveralInlineTagsInOneLine(): void
    {
        $html = $this->renderSource(source: "{tst:text value='a'}-{tst:text value='b'}", data: ['a' => 1, 'b' => 2]);

        $this->assertSame('1-2', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function selectorProvider(): iterable
    {
        yield 'array key' => ['arr.key', 'array value'];
        yield 'nested array' => ['arr.nested.deep', 'deep value'];
        yield 'ArrayObject key' => ['ao.key', 'ao value'];
        yield 'stdClass property' => ['std.name', 'std name'];
        yield 'nested stdClass' => ['std.child.name', 'child name'];
        yield 'array inside stdClass' => ['std.list.first', 'first'];
        yield 'public property' => ['target.label', 'public label'];
        yield 'getter get' => ['target.title', 'getter title'];
        yield 'getter is' => ['target.enabled', '1'];
        yield 'getter has' => ['target.comments', ''];
        yield 'public method without brackets is called' => ['target.describe', 'described no getter'];
    }

    #[DataProvider('selectorProvider')]
    public function testSelector(string $selector, string $expected): void
    {
        $htmlDataObject = new HtmlDataObject();
        $htmlDataObject->addTextElement(propertyName: 'name', content: 'std name', isEncodedForRendering: true);
        $child = new HtmlDataObject();
        $child->addTextElement(propertyName: 'name', content: 'child name', isEncodedForRendering: true);
        $htmlDataObject->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $htmlDataObject->data->list = ['first' => 'first'];
        $replacements = new HtmlReplacementCollection();
        $replacements->addDataObject(identifier: 'std', htmlDataObject: $htmlDataObject);
        $data = $replacements->getArrayObject();
        $data['arr'] = ['key' => 'array value', 'nested' => ['deep' => 'deep value']];
        $data['ao'] = new ArrayObject(array: ['key' => 'ao value']);
        $data['target'] = new SelectorTarget();

        $html = $this->renderSource(source: '{tst:text value=\'' . $selector . '\'}', data: $data);

        $this->assertSame($expected, $html);
    }

    public function testMissingTopLevelValueThrowsWithCodeOne(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:text value='x'}");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(1);
        $this->expectExceptionMessageIs(
            'The data with offset "x" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function failingSelectorProvider(): iterable
    {
        yield 'missing array key' => ['x.y', ['x' => ['a' => 1]], 'Array key "y" does not exist in array "x"'];
        yield 'missing ArrayObject key' => [
            'x.y',
            ['x' => new ArrayObject(array: ['a' => 1])],
            'Array key "y" does not exist in ArrayObject "x"',
        ];
        yield 'missing property' => ['x.y', ['x' => (object) ['a' => 1]], 'Don\'t know how to handle selector part "y"'];
        yield 'scalar has no parts' => ['x.y', ['x' => 'text'], 'The data with offset "x" is not an object nor an array.'];
        yield 'null has no parts' => ['x.y', ['x' => null], 'The data with offset "x" is not an object nor an array.'];
        yield 'private property without getter' => [
            'x.secret',
            ['x' => new SelectorTarget()],
            'Could not access protected/private property "secret". Please provide a getter method',
        ];
        // Differs from docs/template-engine/design.md: a getter only works if a property of that name exists
        yield 'getter without property' => [
            'x.computed',
            ['x' => new SelectorTarget()],
            'Don\'t know how to handle selector part "computed"',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('failingSelectorProvider')]
    public function testUnresolvableSelectorThrows(string $selector, array $data, string $expectedMessage): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessageIs($expectedMessage);

        $this->renderSource(source: '{tst:text value=\'' . $selector . '\'}', data: $data);
    }

    public function testHtmlDataObjectCollectionItemsAreStdClassList(): void
    {
        $first = new HtmlDataObject();
        $first->addTextElement(propertyName: 'name', content: '<a>', isEncodedForRendering: false);
        $second = new HtmlDataObject();
        $second->addTextElement(propertyName: 'name', content: 'b', isEncodedForRendering: true);
        $collection = new HtmlDataObjectCollection();
        $collection->add(htmlDataObject: $first);
        $collection->add(htmlDataObject: $second);
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlDataObjectCollection(identifier: 'items', htmlDataObjectCollection: $collection);

        $html = $this->renderSource(
            source: '<tst:for value="items" var="item">[{tst:text value=\'item.name\'}]</tst:for>',
            data: $replacements,
        );

        $this->assertSame('[&lt;a&gt;][b]', $html);
    }
}
