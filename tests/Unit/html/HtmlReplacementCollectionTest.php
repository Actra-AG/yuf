<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlReplacement;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\html\HtmlTextCollection;
use PHPUnit\Framework\TestCase;

final class HtmlReplacementCollectionTest extends TestCase
{
    public function testUnknownIdentifier(): void
    {
        $replacements = new HtmlReplacementCollection();

        $this->assertFalse($replacements->has(identifier: 'x'));
        $this->assertNull($replacements->get(identifier: 'x'));
        $this->assertSame([], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testHtmlIsOutputAsItIs(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtml(identifier: 'a', html: '<b>&amp;</b>');

        $this->assertTrue($replacements->has(identifier: 'a'));
        $this->assertSame(['a' => '<b>&amp;</b>'], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testTextIsEscaped(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addText(identifier: 'a', text: '<b>&"\'');

        $this->assertSame(['a' => '&lt;b&gt;&amp;&quot;&#039;'], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testNullTextAndHtml(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addText(identifier: 'text', text: null);
        $replacements->addHtml(identifier: 'html', html: null);
        $replacements->addHtmlText(identifier: 'htmlText', htmlText: null);

        $this->assertSame(
            ['text' => null, 'html' => null, 'htmlText' => null],
            $replacements->getArrayObject()->getArrayCopy(),
        );
        $this->assertTrue($replacements->has(identifier: 'text'));
    }

    public function testHtmlText(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlText(identifier: 'a', htmlText: HtmlText::fromText(text: '<'));
        $replacements->addHtmlText(identifier: 'b', htmlText: HtmlText::fromHtml(html: '<'));

        $this->assertSame(['a' => '&lt;', 'b' => '<'], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testScalars(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addInt(identifier: 'int', int: 7);
        $replacements->addInt(identifier: 'noInt', int: null);
        $replacements->addFloat(identifier: 'float', float: 1.5);
        $replacements->addFloat(identifier: 'noFloat', float: null);
        $replacements->addBool(identifier: 'yes', booleanValue: true);
        $replacements->addBool(identifier: 'no', booleanValue: false);

        $this->assertSame(
            ['int' => 7, 'noInt' => null, 'float' => 1.5, 'noFloat' => null, 'yes' => true, 'no' => false],
            $replacements->getArrayObject()->getArrayCopy(),
        );
    }

    public function testDataObject(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'name', html: 'x');
        $replacements = new HtmlReplacementCollection();
        $replacements->addDataObject(identifier: 'object', htmlDataObject: $object);
        $replacements->addDataObject(identifier: 'none', htmlDataObject: null);

        $this->assertTrue($replacements->has(identifier: 'none'));
        $this->assertNull($replacements->get(identifier: 'none'));
        $this->assertSame(['object' => $object->data, 'none' => null], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testTextCollectionIsRendered(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlTextCollection(
            identifier: 'list',
            htmlTextCollection: new HtmlTextCollection(items: [
                HtmlText::fromText(text: '<'),
                HtmlText::fromHtml(html: '<'),
            ]),
        );
        $replacements->addHtmlTextCollection(identifier: 'none', htmlTextCollection: null);

        $this->assertSame(
            ['list' => ['&lt;', '<'], 'none' => null],
            $replacements->getArrayObject()->getArrayCopy(),
        );
    }

    public function testDataObjectCollectionGivesTheDataOfItsItems(): void
    {
        $first = new HtmlDataObject();
        $second = new HtmlDataObject();
        $collection = new HtmlDataObjectCollection();
        $collection->add(htmlDataObject: $first);
        $collection->add(htmlDataObject: $second);
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlDataObjectCollection(identifier: 'items', htmlDataObjectCollection: $collection);
        $replacements->addHtmlDataObjectCollection(identifier: 'none', htmlDataObjectCollection: null);

        $this->assertSame(
            ['items' => [$first->data, $second->data], 'none' => null],
            $replacements->getArrayObject()->getArrayCopy(),
        );
    }

    public function testAddingAgainReplacesAndKeepsTheOrder(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtml(identifier: 'a', html: '1');
        $replacements->addHtml(identifier: 'b', html: '2');
        $replacements->addHtml(identifier: 'a', html: '3');

        $this->assertSame(['a' => '3', 'b' => '2'], $replacements->getArrayObject()->getArrayCopy());
    }

    public function testSetAndGetKeepTheReplacement(): void
    {
        $replacement = HtmlReplacement::fromInt(int: 3);
        $replacements = new HtmlReplacementCollection();
        $replacements->set(identifier: 'a', htmlReplacement: $replacement);
        $replacements->set(identifier: 'b', htmlReplacement: null);

        $this->assertSame($replacement, $replacements->get(identifier: 'a'));
        $this->assertTrue($replacements->has(identifier: 'b'));
        $this->assertNull($replacements->get(identifier: 'b'));
    }

    public function testReplacementFactories(): void
    {
        $object = new HtmlDataObject();

        $this->assertSame('<', HtmlReplacement::fromHtml(html: '<')->getDataForRenderer());
        $this->assertSame('&lt;', HtmlReplacement::fromText(text: '<')->getDataForRenderer());
        $this->assertNull(HtmlReplacement::fromHtml(html: null)->getDataForRenderer());
        $this->assertNull(HtmlReplacement::fromText(text: null)->getDataForRenderer());
        $this->assertTrue(HtmlReplacement::fromBool(bool: true)->getDataForRenderer());
        $this->assertNull(HtmlReplacement::fromBool(bool: null)->getDataForRenderer());
        $this->assertSame(1, HtmlReplacement::fromInt(int: 1)->getDataForRenderer());
        $this->assertSame(1.5, HtmlReplacement::fromFloat(float: 1.5)->getDataForRenderer());
        $this->assertSame($object->data, HtmlReplacement::fromDataObject(htmlDataObject: $object)->getDataForRenderer());
        $this->assertSame(
            'x',
            HtmlReplacement::fromHtmlText(htmlText: HtmlText::fromHtml(html: 'x'))->getDataForRenderer(),
        );
    }
}
