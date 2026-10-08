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
 * Today the engine never escapes: a plain string is output as it is, an HtmlText created with fromHtml() is output as
 * it is, and only HtmlText::fromText() (and addText()) is escaped by the replacement API, not by the engine.
 */
abstract class AbstractTemplateTextTagTestCase extends TemplateCharacterizationTestCase
{
    /**
     * The third value is the result of the new engine where it differs: it escapes plain strings.
     *
     * @return iterable<string, array{array<string, mixed>|HtmlReplacementCollection, string}|array{array<string, mixed>|HtmlReplacementCollection, string, string}>
     */
    public static function valueProvider(): iterable
    {
        yield 'plain string' => [
            ['x' => '<b>a</b> & "q" \'s\''],
            '<b>a</b> & "q" \'s\'',
            '&lt;b&gt;a&lt;/b&gt; &amp; &quot;q&quot; &#039;s&#039;',
        ];
        yield 'empty string' => [['x' => ''], ''];
        yield 'int' => [['x' => 42], '42'];
        yield 'negative int' => [['x' => -3], '-3'];
        yield 'zero' => [['x' => 0], '0'];
        yield 'float' => [['x' => 1.5], '1.5'];
        yield 'true' => [['x' => true], '1'];
        yield 'false' => [['x' => false], ''];
        yield 'null' => [['x' => null], ''];

        $encoded = new HtmlReplacementCollection();
        $encoded->addHtmlText(identifier: 'x', htmlText: HtmlText::fromHtml(html: '<b>a</b>'));
        yield 'HtmlText::fromHtml is output raw' => [$encoded, '<b>a</b>'];

        $unencoded = new HtmlReplacementCollection();
        $unencoded->addHtmlText(identifier: 'x', htmlText: HtmlText::fromText(text: '<b>a</b>'));
        yield 'HtmlText::fromText is escaped' => [$unencoded, '&lt;b&gt;a&lt;/b&gt;'];

        $unencodedQuotes = new HtmlReplacementCollection();
        $unencodedQuotes->addText(identifier: 'x', text: 'a & "b" \'c\'');
        yield 'addText escapes quotes and ampersand' => [$unencodedQuotes, 'a &amp; &quot;b&quot; &#039;c&#039;'];

        $htmlData = new HtmlReplacementCollection();
        $htmlData->addHtml(identifier: 'x', html: '<i>x</i>');
        yield 'addHtml is output raw' => [$htmlData, '<i>x</i>'];

        $nullText = new HtmlReplacementCollection();
        $nullText->addHtml(identifier: 'x', html: null);
        yield 'addHtml with null' => [$nullText, ''];

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
    public function testInlineTagOutputsValue(array|HtmlReplacementCollection $data, string $expected, ?string $expectedByNewEngine = null): void
    {
        if ($this->isNewEngine() && $expectedByNewEngine !== null) {
            $expected = $expectedByNewEngine;
        }

        $this->assertSame('[' . $expected . ']', $this->renderSource(source: "[{tst:text value='x'}]", data: $data));
    }

    /**
     * @param array<string, mixed>|HtmlReplacementCollection $data
     */
    #[DataProvider('valueProvider')]
    public function testElementTagOutputsValue(array|HtmlReplacementCollection $data, string $expected, ?string $expectedByNewEngine = null): void
    {
        if ($this->isNewEngine() && $expectedByNewEngine !== null) {
            $expected = $expectedByNewEngine;
        }

        $this->assertSame('[' . $expected . ']', $this->renderSource(source: '[<tst:text value="x"/>]', data: $data));
    }

    public function testElementTagWithClosingTag(): void
    {
        // Differs: the old engine treats <tst:text> as self-closing, so the closing tag stays as text
        $html = $this->renderSource(source: '<tst:text value="x"></tst:text>', data: ['x' => 'v']);

        $this->assertSame($this->forEngine(old: 'v</tst:text>', new: 'v'), $html);
    }

    public function testInlineTagInAttributeValue(): void
    {
        $html = $this->renderSource(
            source: '<a href="{tst:text value=\'url\'}" title="{tst:text value=\'title\'}">x</a>',
            data: ['url' => '?a=1&b=2', 'title' => 'T'],
        );

        $this->assertSame(
            $this->forEngine(old: '<a href="?a=1&b=2" title="T">x</a>', new: '<a href="?a=1&amp;b=2" title="T">x</a>'),
            $html,
        );
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
        $htmlDataObject->addHtml(propertyName: 'name', html: 'std name');
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'child name');
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

    public function testMissingTopLevelValueThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:text value='x'}");

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'The data with offset "x" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
            newMessage: 'The template data "x" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
            oldCode: 1,
        );

        $this->renderFile(templateFile: $templateFile);
    }

    /**
     * Selector, data, message of the old engine, reason of the new engine.
     *
     * @return iterable<string, array{string, array<string, mixed>, string, string}>
     */
    public static function failingSelectorProvider(): iterable
    {
        $cannotRead = static fn(string $name, string $path): string => 'Cannot read "' . $name . '" of "' . $path
            . '": no key, public property, getter or method without arguments of this name';
        $notAnObject = 'Cannot read "y" of "x": the value is not an array and not an object';

        yield 'missing array key' => [
            'x.y',
            ['x' => ['a' => 1]],
            'Array key "y" does not exist in array "x"',
            'The array "x" has no key "y"',
        ];
        yield 'missing ArrayObject key' => [
            'x.y',
            ['x' => new ArrayObject(array: ['a' => 1])],
            'Array key "y" does not exist in ArrayObject "x"',
            $cannotRead('y', 'x'),
        ];
        yield 'missing property' => [
            'x.y',
            ['x' => (object) ['a' => 1]],
            'Don\'t know how to handle selector part "y"',
            $cannotRead('y', 'x'),
        ];
        yield 'scalar has no parts' => [
            'x.y',
            ['x' => 'text'],
            'The data with offset "x" is not an object nor an array.',
            $notAnObject,
        ];
        yield 'null has no parts' => [
            'x.y',
            ['x' => null],
            'The data with offset "x" is not an object nor an array.',
            $notAnObject,
        ];
        yield 'private property without getter' => [
            'x.secret',
            ['x' => new SelectorTarget()],
            'Could not access protected/private property "secret". Please provide a getter method',
            $cannotRead('secret', 'x'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('failingSelectorProvider')]
    public function testUnresolvableSelectorThrows(
        string $selector,
        array $data,
        string $oldMessage,
        string $newReason,
    ): void {
        $templateFile = $this->writeTemplate(source: '{tst:text value=\'' . $selector . '\'}');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: $oldMessage,
            newMessage: $newReason . ' in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: $data);
    }

    public function testGetterWithoutPropertyOfThatName(): void
    {
        // Differs: the old engine only calls a getter if a property of that name exists
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageIs('Don\'t know how to handle selector part "computed"');
        }

        $html = $this->renderSource(source: "{tst:text value='x.computed'}", data: ['x' => new SelectorTarget()]);

        $this->assertSame('computed', $html);
    }

    public function testHtmlDataObjectCollectionItemsAreStdClassList(): void
    {
        $first = new HtmlDataObject();
        $first->addText(propertyName: 'name', text: '<a>');
        $second = new HtmlDataObject();
        $second->addHtml(propertyName: 'name', html: 'b');
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
