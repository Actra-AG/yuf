<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SimpleXmlExtended;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use stdClass;

final class SimpleXmlExtendedTest extends TestCase
{
    private const string XML_DECLARATION = "<?xml version=\"1.0\"?>\n";

    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function xmlProvider(): iterable
    {
        yield 'children' => ['<a><b>1</b><c>x</c></a>', ['b' => '1', 'c' => 'x']];
        yield 'repeated child' => ['<a><b>1</b><b>2</b></a>', ['b' => ['1', '2']]];
        yield 'attributes' => ['<a x="1"><b>t</b></a>', ['@attributes' => ['x' => '1'], 'b' => 't']];
        yield 'cdata' => ['<a><b><![CDATA[x<y]]></b></a>', ['b' => 'x<y']];
        yield 'empty root stays an array' => ['<a/>', []];
        yield 'empty child is an empty string' => ['<a><b>1</b><c/></a>', ['b' => '1', 'c' => '']];
        yield 'first empty child is an empty string' => ['<a><c/><b>1</b></a>', ['c' => '', 'b' => '1']];
        yield 'empty grandchild is an empty string' => ['<a><b><c/></b></a>', ['b' => ['c' => '']]];
        yield 'nested' => ['<a><b><c>1</c><d/></b><e/></a>', ['b' => ['c' => '1', 'd' => ''], 'e' => '']];
        yield 'multibyte' => ['<a><b>Jörg</b></a>', ['b' => 'Jörg']];
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('xmlProvider')]
    public function testConvertXmlToArray(string $xml, array $expected): void
    {
        $this->assertSame($expected, SimpleXmlExtended::convertXmlToArray(xml: $xml));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidXmlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'not closed' => ['<a>'];
        yield 'no xml' => ['text'];
        yield 'mismatched tags' => ['<a><b></a></b>'];
    }

    #[DataProvider('invalidXmlProvider')]
    public function testConvertXmlToArrayReturnsFalseForInvalidXml(string $xml): void
    {
        $this->assertFalse(SimpleXmlExtended::convertXmlToArray(xml: $xml));
    }

    public function testConvertXmlToArrayDoesNotLoadExternalEntities(): void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'yuf-xxe-');
        $this->assertIsString($path);
        file_put_contents(filename: $path, data: 'secret');
        $xml = '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x SYSTEM "file://' . $path . '">]><a><b>&x;</b></a>';

        $result = SimpleXmlExtended::convertXmlToArray(xml: $xml);

        unlink(filename: $path);
        $this->assertNotSame(['b' => 'secret'], $result);
    }

    public function testConvertXmlToArrayKeepsTheErrorHandlingOfLibxml(): void
    {
        $previous = libxml_use_internal_errors(false);

        SimpleXmlExtended::convertXmlToArray(xml: '<a>');

        $this->assertFalse(libxml_use_internal_errors($previous));
    }

    public function testAddArray(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');

        $xml->addArray(array: [
            'a' => 1,
            'b' => ['c' => 'x<y', 'd' => null],
            'e' => [1, 2],
            'f' => true,
            'g' => false,
            'h' => 1.5,
        ]);

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION
            . '<root><a><![CDATA[1]]></a><b><c><![CDATA[x<y]]></c><d><![CDATA[]]></d></b>'
            . '<e><item0><![CDATA[1]]></item0><item1><![CDATA[2]]></item1></e>'
            . '<f><![CDATA[1]]></f><g><![CDATA[]]></g><h><![CDATA[1.5]]></h></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testAddArrayConvertsObjects(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');
        $inner = new stdClass();
        $inner->p = 'q';
        $object = new stdClass();
        $object->o = $inner;

        $xml->addArray(array: $object);

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION . '<root><o><p><![CDATA[q]]></p></o></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testAddArrayWithoutNullValues(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');

        $xml->addArray(array: ['a' => 1, 'b' => null, 'c' => ['d' => null, 'e' => 2]], includeNull: false);

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION
            . '<root><a><![CDATA[1]]></a><c><e><![CDATA[2]]></e></c></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testAddArrayAddsToAGivenElement(): void
    {
        $xml = new SimpleXmlExtended(data: '<root><target/></root>');
        $target = $xml->target;
        $this->assertInstanceOf(SimpleXmlExtended::class, $target);

        $xml->addArray(array: ['a' => 'x'], xml: $target);

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION . '<root><target><a><![CDATA[x]]></a></target></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testAddArrayRejectsAValueThatIsNoText(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');
        $resource = fopen(filename: 'php://memory', mode: 'r');
        $this->assertIsResource($resource);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"a"/');

        try {
            $xml->addArray(array: ['a' => $resource]);
        } finally {
            fclose(stream: $resource);
        }
    }

    public function testAddChildWithValueIsACdataSection(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');

        $child = $xml->addChild(qualifiedName: 'k', value: 'v&w');

        $this->assertInstanceOf(SimpleXmlExtended::class, $child);
        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION . '<root><k><![CDATA[v&w]]></k></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testAddChildWithoutValueIsAnEmptyElement(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');

        $xml->addChild(qualifiedName: 'n');

        $this->assertSame(SimpleXmlExtendedTest::XML_DECLARATION . '<root><n/></root>' . "\n", $xml->asXML());
    }

    public function testAddCdata(): void
    {
        $xml = new SimpleXmlExtended(data: '<root><n/></root>');
        $element = $xml->n;
        $this->assertInstanceOf(SimpleXmlExtended::class, $element);

        $element->addCdata(cdataText: 5);
        $element->addCdata(cdataText: null);
        $element->addCdata(cdataText: 'a]]>b');

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION . '<root><n><![CDATA[5]]><![CDATA[]]><![CDATA[a]]]]><![CDATA[>b]]>'
            . '</n></root>' . "\n",
            $xml->asXML(),
        );
    }

    public function testRemove(): void
    {
        $xml = new SimpleXmlExtended(data: '<root><a>1</a><b>2</b></root>');
        $element = $xml->a;
        $this->assertInstanceOf(SimpleXmlExtended::class, $element);

        $xml->remove(node: $element);

        $this->assertSame(SimpleXmlExtendedTest::XML_DECLARATION . '<root><b>2</b></root>' . "\n", $xml->asXML());
    }

    public function testAddXml(): void
    {
        $xml = new SimpleXmlExtended(data: '<root/>');

        $xml->addXml(xmlToAppend: new SimpleXMLElement(data: '<i><j>1</j></i>'));

        $this->assertSame(
            SimpleXmlExtendedTest::XML_DECLARATION . '<root><i><j>1</j></i></root>' . "\n",
            $xml->asXML(),
        );
    }
}
