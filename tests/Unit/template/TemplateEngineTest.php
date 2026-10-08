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
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\tag\TextTag;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\NewEngineTestCase;
use actra\yuf\tests\Double\template\ShoutTag;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use actra\yuf\tests\Double\template\ThrowingTag;
use ArrayObject;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The new template engine as a whole. The characterization tests (AbstractTemplate*TestCase) pin the output that must
 * stay the same, the tests of the single classes pin the details; these tests pin what the new engine adds.
 */
final class TemplateEngineTest extends NewEngineTestCase
{
    public function testForVariableNeverOverwritesOrRemovesAnOuterValue(): void
    {
        $source = "{tst:text value='i'}<tst:for value=\"l\" var=\"i\">[{tst:text value='i'}]</tst:for>{tst:text value='i'}";

        $html = $this->render(source: $source, data: ['l' => [1, 2], 'i' => 'outer']);

        $this->assertSame('outer[1][2]outer', $html);
    }

    public function testNestedLoopsWithTheSameVariableName(): void
    {
        $source = '<tst:for value="l" var="i">(<tst:for value="i" var="i">{tst:text value=\'i\'}</tst:for>)</tst:for>';

        $html = $this->render(source: $source, data: ['l' => [[1, 2], [3]]]);

        $this->assertSame('(12)(3)', $html);
    }

    public function testLoopVariableIsGoneAfterTheLoop(): void
    {
        $templateFile = $this->writeTemplate(source: "<tst:for value=\"l\" var=\"i\"></tst:for>{tst:text value='i'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "i" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['l' => [1]]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function iterableProvider(): iterable
    {
        yield 'list' => [['a', 'b'], 'ab'];
        yield 'keys of an array are ignored' => [['x' => 'a', 'y' => 'b'], 'ab'];
        yield 'null' => [null, ''];
        yield 'empty array' => [[], ''];
        yield 'ArrayObject' => [new ArrayObject(array: ['a', 'b']), 'ab'];
        yield 'public properties of an object' => [(object) ['x' => 'a', 'y' => 'b'], 'ab'];
        yield 'generator' => [(static fn(): Generator => yield from ['a', 'b'])(), 'ab'];
    }

    #[DataProvider('iterableProvider')]
    public function testForIteratesOver(mixed $value, string $expected): void
    {
        $html = $this->render(source: '<tst:for value="l" var="i">{tst:text value=\'i\'}</tst:for>', data: ['l' => $value]);

        $this->assertSame($expected, $html);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function notIterableProvider(): iterable
    {
        yield 'string' => ['abc', 'string'];
        yield 'int' => [5, 'int'];
        yield 'bool' => [true, 'bool'];
    }

    #[DataProvider('notIterableProvider')]
    public function testForOverAScalarThrows(mixed $value, string $type): void
    {
        $templateFile = $this->writeTemplate(source: "a\n<tst:for value=\"l\" var=\"i\">x</tst:for>");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The value "l" of type ' . $type . ' cannot be used in a for tag, it must be an array or an object in ' . $templateFile . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile, data: ['l' => $value]);
    }

    public function testForOverAMissingValueThrowsWithTheLineOfTheTag(): void
    {
        $templateFile = $this->writeTemplate(source: "a\nb\n<tst:for value=\"l\" var=\"i\">x</tst:for>");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "l" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 3',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testItemsOfTheHtmlClassesAreNotEscapedAgain(): void
    {
        $item = new HtmlDataObject();
        $item->addTextElement(propertyName: 'name', content: 'Tom & Jerry <3', isEncodedForRendering: false);
        $item->addTextElement(propertyName: 'link', content: '<a href="/">x</a>', isEncodedForRendering: true);
        $collection = new HtmlDataObjectCollection();
        $collection->add(htmlDataObject: $item);
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlDataObjectCollection(identifier: 'items', htmlDataObjectCollection: $collection);

        $html = $this->render(
            source: '<tst:for value="items" var="item">{tst:text value=\'item.name\'}|{tst:text value=\'item.link\'}</tst:for>',
            data: $replacements,
        );

        $this->assertSame('Tom &amp; Jerry &lt;3|<a href="/">x</a>', $html);
    }

    public function testStringsOfAStdClassInThePlainDataAreEscaped(): void
    {
        $item = new HtmlDataObject();
        $item->addTextElement(propertyName: 'name', content: '<b>', isEncodedForRendering: true);

        $html = $this->render(source: "{tst:text value='o.name'}", data: ['o' => $item->data]);

        $this->assertSame('&lt;b&gt;', $html);
    }

    public function testComparisonErrorHasTheLineOfTheIfTag(): void
    {
        $templateFile = $this->writeTemplate(source: "a\n<tst:if compare=\"v\" operator=\"gt\" against=\"1\">x</tst:if>");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The operators gt, ge, lt and le need a numeric value, got string in ' . $templateFile . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'abc']);
    }

    public function testIfComparesValuesOfTheHtmlClassesAsText(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addEncodedText(identifier: 'empty', content: '');
        $replacements->addEncodedText(identifier: 'text', content: 'abc');
        $source = '<tst:if compare="empty" against="">E</tst:if><tst:if compare="text" operator="in" against="x abc">I</tst:if><tst:if compare="text" against="true">T</tst:if>';

        $this->assertSame('EIT', $this->render(source: $source, data: $replacements));
    }

    public function testSelectorsOfObjectsWorkInIfAndFor(): void
    {
        $object = (object) ['items' => ['a', 'b'], 'enabled' => true];

        $html = $this->render(
            source: '<tst:if compare="o.enabled" against="true"><tst:for value="o.items" var="i">{tst:text value=\'i\'}</tst:for></tst:if>',
            data: ['o' => $object],
        );

        $this->assertSame('ab', $html);
    }

    public function testExceptionClosesTheOutputBuffersOfTheTemplate(): void
    {
        $this->useOwnTags(ownTags: [new ThrowingTag()]);
        $level = ob_get_level();

        try {
            $this->render(source: 'before<tst:throwing/>after');
            TemplateEngineTest::fail('The tag throws');
        } catch (RuntimeException $exception) {
            $this->assertSame('The tag failed', $exception->getMessage());
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testExceptionInASubTemplateClosesTheOutputBuffers(): void
    {
        $subTemplate = $this->writeTemplate(source: "x{tst:text value='missing'}");
        $level = ob_get_level();

        try {
            $this->render(source: 'a<tst:loadSubTpl tplfile="' . $subTemplate . '"/>b');
            TemplateEngineTest::fail('The value is missing');
        } catch (TemplateException $exception) {
            $this->assertSame($subTemplate, $exception->templateFile);
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testExceptionInAnElementTagBodyClosesTheOutputBuffers(): void
    {
        $this->useOwnTags(ownTags: [new ShoutTag()]);
        $level = ob_get_level();

        try {
            $this->render(source: "<tst:shout>a{tst:text value='missing'}</tst:shout>");
            TemplateEngineTest::fail('The value is missing');
        } catch (TemplateException) {
            $this->assertSame($level, ob_get_level());
        }
    }

    public function testRenderingTwiceWithOtherDataGivesIndependentResults(): void
    {
        $templateFile = $this->writeTemplate(source: "<tst:for value=\"l\" var=\"i\">{tst:text value='i'}</tst:for>{tst:text value='o'}");

        $first = $this->renderFile(templateFile: $templateFile, data: ['l' => [1, 2], 'o' => 'A']);
        $second = $this->renderFile(templateFile: $templateFile, data: ['l' => [3], 'o' => 'B']);

        $this->assertSame('12A', $first);
        $this->assertSame('3B', $second);
    }





    public function testNamespacePrefixIsAConstructorArgument(): void
    {
        $workDirectory = new TemplateWorkDirectory();

        try {
            $engine = new TemplateEngine(
                cache: new DirectoryTemplateCache(
                    cacheDirectory: $workDirectory->cacheDirectory,
                    templateBaseDirectory: $workDirectory->templateDirectory,
                ),
                tags: new TemplateTagCollection(new TextTag()),
                namespacePrefix: 'yuf',
            );

            $html = $engine->render(
                templateFile: $workDirectory->writeTemplate(source: "{yuf:text value='x'}<yuf:text value=\"x\"/>{tst:text value='x'}"),
                data: new TemplateData(values: ['x' => 'v']),
            );

            $this->assertSame("vv{tst:text value='x'}", $html);
        } finally {
            $workDirectory->cleanUp();
        }
    }

    public function testCompiledTemplateIsReusedAndRecompiledWhenTheTemplateChanges(): void
    {
        $workDirectory = new TemplateWorkDirectory();

        try {
            $cache = new DirectoryTemplateCache(
                cacheDirectory: $workDirectory->cacheDirectory,
                templateBaseDirectory: $workDirectory->templateDirectory,
            );
            $engine = new TemplateEngine(cache: $cache, tags: new TemplateTagCollection(new TextTag()));
            $templateFile = $workDirectory->writeTemplate(source: 'first');
            touch(filename: $templateFile, mtime: time() - 100);
            clearstatcache();

            $this->assertSame('first', $engine->render(templateFile: $templateFile, data: new TemplateData()));
            $this->assertFileExists($cache->getCompiledFile(templateFile: $templateFile));
            // The compiled file runs in a static closure and only uses the runtime
            $this->assertStringNotContainsString('$this', (string) file_get_contents(filename: $cache->getCompiledFile(templateFile: $templateFile)));

            // A changed template with an old modification time is not compiled again: the cache is used
            file_put_contents(filename: $templateFile, data: 'second');
            touch(filename: $templateFile, mtime: time() - 100);
            clearstatcache();
            $this->assertSame('first', $engine->render(templateFile: $templateFile, data: new TemplateData()));

            // A newer template is compiled again, also by another engine (the next request)
            touch(filename: $templateFile, mtime: time() + 100);
            clearstatcache();
            $otherEngine = new TemplateEngine(cache: $cache, tags: new TemplateTagCollection(new TextTag()));
            $this->assertSame('second', $otherEngine->render(templateFile: $templateFile, data: new TemplateData()));
        } finally {
            $workDirectory->cleanUp();
        }
    }

    public function testSyntaxErrorIsNotCached(): void
    {
        $workDirectory = new TemplateWorkDirectory();

        try {
            $cache = new DirectoryTemplateCache(
                cacheDirectory: $workDirectory->cacheDirectory,
                templateBaseDirectory: $workDirectory->templateDirectory,
            );
            $engine = new TemplateEngine(cache: $cache, tags: new TemplateTagCollection());
            $templateFile = $workDirectory->writeTemplate(source: '<tst:if compare="a" against="b">');

            try {
                $engine->render(templateFile: $templateFile, data: new TemplateData());
                TemplateEngineTest::fail('The tag is not closed');
            } catch (TemplateException $exception) {
                $this->assertSame('The tag <tst:if> is not closed in ' . $templateFile . ' on line 1', $exception->getMessage());
            }

            $this->assertFileDoesNotExist($cache->getCompiledFile(templateFile: $templateFile));
        } finally {
            $workDirectory->cleanUp();
        }
    }

    public function testMissingTemplateFileWithoutLocation(): void
    {
        try {
            $this->renderFile(templateFile: '/nonexistent/page.html');
            TemplateEngineTest::fail('The template does not exist');
        } catch (TemplateException $exception) {
            $this->assertNull($exception->templateFile);
            $this->assertSame('Template file not found: /nonexistent/page.html', $exception->getMessage());
        }
    }

    public function testTagWithABodyOfAnOwnTag(): void
    {
        $this->useOwnTags(ownTags: [new ShoutTag()]);

        $html = $this->render(
            source: "<tst:for value=\"l\" var=\"i\"><tst:shout>a {tst:text value='i'}\n</tst:shout>|</tst:for>",
            data: ['l' => ['<x>', 'y']],
        );

        $this->assertSame('A &LT;X&GT;|A Y|', $html);
    }
}
