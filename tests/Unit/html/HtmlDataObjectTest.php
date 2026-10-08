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
        $this->assertSame([], get_object_vars(object: new HtmlDataObject()->data));
    }

    public function testTextIsEscapedWhenAdded(): void
    {
        $object = new HtmlDataObject();
        $object->addText(propertyName: 'name', text: '<b>"x" & \'y\'</b>');

        $this->assertSame('&lt;b&gt;&quot;x&quot; &amp; &#039;y&#039;&lt;/b&gt;', $object->data->name);
    }

    public function testNullTextStaysNull(): void
    {
        $object = new HtmlDataObject();
        $object->addText(propertyName: 'name', text: null);

        $this->assertNull($object->data->name);
    }

    public function testHtmlIsStoredAsItIs(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'name', html: '<b>x</b>');
        $object->addHtml(propertyName: 'none', html: null);

        $this->assertSame('<b>x</b>', $object->data->name);
        $this->assertNull($object->data->none);
    }

    public function testBooleanAndNullValue(): void
    {
        $object = new HtmlDataObject();
        $object->addBooleanValue(propertyName: 'yes', booleanValue: true);
        $object->addBooleanValue(propertyName: 'no', booleanValue: false);
        $object->addNullValue(propertyName: 'nothing');

        $this->assertTrue($object->data->yes);
        $this->assertFalse($object->data->no);
        $this->assertNull($object->data->nothing);
    }

    public function testNestedDataObjectIsStoredAsItsData(): void
    {
        $child = new HtmlDataObject();
        $child->addHtml(propertyName: 'name', html: 'child');
        $object = new HtmlDataObject();
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $object->addDataObject(propertyName: 'none', htmlDataObject: null);

        $this->assertSame($child->data, $object->data->child);
        $this->assertNull($object->data->none);
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

        $this->assertSame([$first->data, $second->data], $object->data->items);
        $this->assertNull($object->data->none);
    }

    public function testAddingAPropertyAgainReplacesIt(): void
    {
        $object = new HtmlDataObject();
        $object->addHtml(propertyName: 'name', html: 'a');
        $object->addText(propertyName: 'name', text: 'b');

        $this->assertSame('b', $object->data->name);
    }

    public function testDetailDataObjectWithText(): void
    {
        $detail = new DetailDataObject(name: '<i>Label</i>', value: '<b>&</b>', isHtml: false);

        $this->assertSame('<i>Label</i>', $detail->data->name);
        $this->assertSame('&lt;b&gt;&amp;&lt;/b&gt;', $detail->data->value);
    }

    public function testDetailDataObjectWithHtml(): void
    {
        $detail = new DetailDataObject(name: 'Label', value: '<b>&amp;</b>', isHtml: true);

        $this->assertSame('Label', $detail->data->name);
        $this->assertSame('<b>&amp;</b>', $detail->data->value);
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
