<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\DetailDataObject;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\html\HtmlTextCollection;
use PHPUnit\Framework\TestCase;

final class HtmlDataObjectTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $this->assertSame([], get_object_vars(object: new HtmlDataObject()->toTemplateData()));
    }

    public function testTextIsEscapedWhenAdded(): void
    {
        $object = new HtmlDataObject();
        $object->addText(propertyName: 'name', text: '<b>"x" & \'y\'</b>');

        $this->assertSame('&lt;b&gt;&quot;x&quot; &amp; &#039;y&#039;&lt;/b&gt;', $object->toTemplateData()->name);
    }

    public function testNullTextStaysNull(): void
    {
        $object = new HtmlDataObject();
        $object->addText(propertyName: 'name', text: null);

        $this->assertNull($object->toTemplateData()->name);
    }

    public function testHtmlIsStoredAsItIs(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'name', html: '<b>x</b>');
        $object->addHtml(propertyName: 'none', html: null);

        $this->assertSame('<b>x</b>', $object->toTemplateData()->name);
        $this->assertNull($object->toTemplateData()->none);
    }

    public function testBooleanAndNullValue(): void
    {
        $object = new HtmlDataObject();
        $object->addBooleanValue(propertyName: 'yes', booleanValue: true);
        $object->addBooleanValue(propertyName: 'no', booleanValue: false);
        $object->addNullValue(propertyName: 'nothing');

        $this->assertTrue($object->toTemplateData()->yes);
        $this->assertFalse($object->toTemplateData()->no);
        $this->assertNull($object->toTemplateData()->nothing);
    }

    public function testNestedDataObjectIsStoredAsItsData(): void
    {
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'child');
        $object = new HtmlDataObject();
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $object->addDataObject(propertyName: 'none', htmlDataObject: null);

        $this->assertEquals($child->toTemplateData(), $object->toTemplateData()->child);
        $this->assertNull($object->toTemplateData()->none);
    }

    public function testListOfDataObjects(): void
    {
        $first = new HtmlDataObject();
        $first->addHtml(propertyName: 'name', html: 'a');
        $second = new HtmlDataObject();
        $second->addHtml(propertyName: 'name', html: 'b');
        $object = new HtmlDataObject();
        $object->addHtmlDataObjectsArray(propertyName: 'items', htmlDataObjectsArray: [$first, $second]);
        $object->addHtmlDataObjectsArray(propertyName: 'none', htmlDataObjectsArray: null);

        $this->assertEquals([$first->toTemplateData(), $second->toTemplateData()], $object->toTemplateData()->items);
        $this->assertNull($object->toTemplateData()->none);
    }

    public function testLaterChangeOfAChildDoesNotChangeTheParent(): void
    {
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'a');
        $object = new HtmlDataObject();
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $list = new HtmlDataObject();
        $list->addHtmlDataObjectsArray(propertyName: 'items', htmlDataObjectsArray: [$child]);
        $child->addHtml(propertyName: 'name', html: 'b');
        $child->addHtml(propertyName: 'other', html: 'c');

        $this->assertEquals((object) ['name' => 'a'], $object->toTemplateData()->child);
        $this->assertEquals([(object) ['name' => 'a']], $list->toTemplateData()->items);
    }

    public function testOneChildCanBeAddedToSeveralParents(): void
    {
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'a');
        $first = new HtmlDataObject();
        $first->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $second = new HtmlDataObject();
        $second->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $child->addHtml(propertyName: 'name', html: 'b');
        $first->addDataObject(propertyName: 'child', htmlDataObject: $child);

        $this->assertEquals((object) ['name' => 'b'], $first->toTemplateData()->child);
        $this->assertEquals((object) ['name' => 'a'], $second->toTemplateData()->child);
    }

    public function testChangesOfAnAddedGrandchildDoNotLeakThroughTheChild(): void
    {
        $grandchild = new HtmlDataObject();
        $grandchild->addHtml(propertyName: 'name', html: 'a');
        $child = new HtmlDataObject();
        $child->addDataObject(propertyName: 'grandchild', htmlDataObject: $grandchild);
        $object = new HtmlDataObject();
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $grandchild->addHtml(propertyName: 'name', html: 'b');
        $child->addDataObject(propertyName: 'grandchild', htmlDataObject: $grandchild);

        $this->assertEquals((object) ['grandchild' => (object) ['name' => 'a']], $object->toTemplateData()->child);
    }

    public function testTemplateDataIsANewSnapshotEachTime(): void
    {
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'a');
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'title', html: 't');
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $object->addHtmlDataObjectsArray(propertyName: 'items', htmlDataObjectsArray: [$child]);

        $snapshot = $object->toTemplateData();
        $snapshot->title = 'changed';
        $snapshot->child = 'changed';
        $snapshot->items = [];

        $this->assertNotSame($snapshot, $object->toTemplateData());
        $this->assertEquals(
            (object) ['title' => 't', 'child' => (object) ['name' => 'a'], 'items' => [(object) ['name' => 'a']]],
            $object->toTemplateData(),
        );
    }

    public function testPropertiesKeepTheirOrderAndNumericNames(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'b', html: '1');
        $object->addHtml(propertyName: '7', html: '2');
        $object->addHtml(propertyName: 'a', html: '3');

        $this->assertSame(['b' => '1', '7' => '2', 'a' => '3'], get_object_vars(object: $object->toTemplateData()));
    }

    public function testAddingAPropertyAgainReplacesIt(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'name', html: 'a');
        $object->addText(propertyName: 'name', text: 'b');

        $this->assertSame('b', $object->toTemplateData()->name);
    }

    public function testDetailDataObjectEscapesPlainText(): void
    {
        $detail = new DetailDataObject(
            name: HtmlText::fromText(text: '<i>Label</i> & "Co"'),
            value: HtmlText::fromText(text: '<b>&</b>'),
        );

        $this->assertSame('&lt;i&gt;Label&lt;/i&gt; &amp; &quot;Co&quot;', $detail->toTemplateData()->name);
        $this->assertSame('&lt;b&gt;&amp;&lt;/b&gt;', $detail->toTemplateData()->value);
    }

    public function testDetailDataObjectKeepsTrustedHtml(): void
    {
        $detail = new DetailDataObject(
            name: HtmlText::fromHtml(html: '<i>Label</i>'),
            value: HtmlText::fromHtml(html: '<b>&amp;</b>'),
        );

        $this->assertSame('<i>Label</i>', $detail->toTemplateData()->name);
        $this->assertSame('<b>&amp;</b>', $detail->toTemplateData()->value);
    }

    public function testDataObjectCollectionKeepsTheOrder(): void
    {
        $first = new HtmlDataObject();
        $second = new HtmlDataObject();
        $collection = new HtmlDataObjectCollection();
        $collection->add(htmlDataObject: $first);
        $collection->add(htmlDataObject: $second);

        $this->assertSame([$first, $second], $collection->items);
    }

    public function testTextCollection(): void
    {
        $first = HtmlText::fromText(text: 'a');
        $second = HtmlText::fromHtml(html: 'b');
        $collection = new HtmlTextCollection(items: [$first]);
        $collection->add(htmlText: $second);

        $this->assertSame([$first, $second], $collection->items);
    }

    public function testEmptyCollections(): void
    {
        $this->assertSame([], new HtmlTextCollection()->items);
        $this->assertSame([], new HtmlDataObjectCollection()->items);
    }
}
