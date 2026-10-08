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
use actra\yuf\html\HtmlTextCollection;
use actra\yuf\template\runtime\TrustedHtml;
use actra\yuf\template\TemplateData;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TemplateDataTest extends TestCase
{
    private function valueOf(TemplateData $data, string $identifier): mixed
    {
        $this->assertArrayHasKey($identifier, $data->values);

        return $data->values[$identifier];
    }

    public function testPlainValuesAreKeptAsTheyAre(): void
    {
        $object = new stdClass();

        $data = new TemplateData(values: ['a' => 'text', 'b' => 1, 'c' => [1], 'd' => $object, 'e' => null]);

        $this->assertSame(['a' => 'text', 'b' => 1, 'c' => [1], 'd' => $object, 'e' => null], $data->values);
    }

    public function testWithReturnsACopy(): void
    {
        $data = new TemplateData(values: ['a' => 1]);

        $copy = $data->with(identifier: 'b', value: 2);

        $this->assertSame(['a' => 1], $data->values);
        $this->assertSame(['a' => 1, 'b' => 2], $copy->values);
    }

    public function testWithReplacesAnExistingValue(): void
    {
        $data = new TemplateData(values: ['a' => 1]);

        $this->assertSame(['a' => 2], $data->with(identifier: 'a', value: 2)->values);
    }

    public function testTextOfTheReplacementsIsTrustedHtml(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addEncodedText(identifier: 'html', content: '<b>a</b>');
        $replacements->addUnencodedText(identifier: 'text', content: '<b>a</b>');
        $replacements->addEncodedText(identifier: 'null', content: null);

        $data = TemplateData::fromReplacements(replacements: $replacements);

        $this->assertEquals(new TrustedHtml(html: '<b>a</b>'), $this->valueOf($data, 'html'));
        $this->assertEquals(new TrustedHtml(html: '&lt;b&gt;a&lt;/b&gt;'), $this->valueOf($data, 'text'));
        $this->assertNull($this->valueOf($data, 'null'));
    }

    public function testNumbersAndBooleansOfTheReplacementsStayValues(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addInt(identifier: 'int', int: 5);
        $replacements->addFloat(identifier: 'float', float: 1.5);
        $replacements->addBool(identifier: 'bool', booleanValue: true);

        $data = TemplateData::fromReplacements(replacements: $replacements);

        // The replacement classes widen an int to a float, the text output is the same
        $this->assertSame(['int' => 5.0, 'float' => 1.5, 'bool' => true], $data->values);
    }

    public function testItemsOfATextCollectionAreTrustedHtml(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlTextCollection(
            identifier: 'texts',
            htmlTextCollection: new HtmlTextCollection(
                items: [HtmlText::encoded(textContent: '<i>'), HtmlText::unencoded(textContent: '<i>')],
            ),
        );

        $data = TemplateData::fromReplacements(replacements: $replacements);

        $this->assertEquals([new TrustedHtml(html: '<i>'), new TrustedHtml(html: '&lt;i&gt;')], $this->valueOf($data, 'texts'));
    }

    public function testStringsOfADataObjectAreTrustedHtmlAlsoWhenNested(): void
    {
        $child = new HtmlDataObject();
        $child->addTextElement(propertyName: 'name', content: '<c>', isEncodedForRendering: false);
        $object = new HtmlDataObject();
        $object->addTextElement(propertyName: 'name', content: '<b>', isEncodedForRendering: true);
        $object->addBooleanValue(propertyName: 'flag', booleanValue: true);
        $object->addNullValue(propertyName: 'nothing');
        $object->addDataObject(propertyName: 'child', htmlDataObject: $child);
        $replacements = new HtmlReplacementCollection();
        $replacements->addDataObject(identifier: 'object', htmlDataObject: $object);

        $data = TemplateData::fromReplacements(replacements: $replacements);

        $value = $this->valueOf($data, 'object');
        $this->assertInstanceOf(stdClass::class, $value);
        $this->assertEquals(new TrustedHtml(html: '<b>'), $value->name);
        $this->assertTrue($value->flag);
        $this->assertNull($value->nothing);
        $this->assertInstanceOf(stdClass::class, $value->child);
        $this->assertEquals(new TrustedHtml(html: '&lt;c&gt;'), $value->child->name);
    }

    public function testItemsOfADataObjectCollectionAreCopiedWithTrustedHtml(): void
    {
        $first = new HtmlDataObject();
        $first->addTextElement(propertyName: 'name', content: 'a', isEncodedForRendering: true);
        $collection = new HtmlDataObjectCollection();
        $collection->add(htmlDataObject: $first);
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlDataObjectCollection(identifier: 'items', htmlDataObjectCollection: $collection);

        $data = TemplateData::fromReplacements(replacements: $replacements);

        $items = $this->valueOf($data, 'items');
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertArrayHasKey(0, $items);
        $this->assertInstanceOf(stdClass::class, $items[0]);
        $this->assertEquals(new TrustedHtml(html: 'a'), $items[0]->name);
        $this->assertSame('a', $first->data->name, 'The data object of the caller is not changed');
    }

    public function testEmptyReplacements(): void
    {
        $this->assertSame([], TemplateData::fromReplacements(replacements: new HtmlReplacementCollection())->values);
    }
}
