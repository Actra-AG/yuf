<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormInput;
use actra\yuf\form\InputShapeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FormInputTest extends TestCase
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, InputShapeEnum}>
     */
    public static function shapeProvider(): iterable
    {
        yield 'missing key' => [[], InputShapeEnum::MISSING];
        yield 'string' => [['field' => 'a'], InputShapeEnum::TEXT];
        yield 'empty string' => [['field' => ''], InputShapeEnum::TEXT];
        yield 'list of strings' => [['field' => ['a', 'b']], InputShapeEnum::LIST];
        yield 'empty array' => [['field' => []], InputShapeEnum::LIST];
        yield 'array with keys' => [['field' => ['x' => 'a', 'y' => 'b']], InputShapeEnum::LIST];
        yield 'nested array' => [['field' => [['a']]], InputShapeEnum::INVALID];
        yield 'mixed entries' => [['field' => ['a', ['b']]], InputShapeEnum::INVALID];
        yield 'int entry' => [['field' => ['a', 1]], InputShapeEnum::INVALID];
        yield 'int' => [['field' => 1], InputShapeEnum::INVALID];
        yield 'null' => [['field' => null], InputShapeEnum::INVALID];
        yield 'bool' => [['field' => true], InputShapeEnum::INVALID];
        yield 'object' => [['field' => new stdClass()], InputShapeEnum::INVALID];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('shapeProvider')]
    public function testShapeOfAParameter(array $data, InputShapeEnum $expected): void
    {
        $this->assertSame($expected, FormInput::fromArray(data: $data)->getShape(name: 'field'));
    }

    public function testTextIsReturnedForTextShape(): void
    {
        $input = FormInput::fromArray(data: ['field' => ' a ']);

        $this->assertSame(' a ', $input->getText(name: 'field'));
        $this->assertNull($input->getList(name: 'field'));
    }

    public function testListIsReturnedForListShapeWithKeysDropped(): void
    {
        $input = FormInput::fromArray(data: ['field' => [3 => 'b', 1 => 'a']]);

        $this->assertSame(['b', 'a'], $input->getList(name: 'field'));
        $this->assertNull($input->getText(name: 'field'));
    }

    public function testNothingIsReturnedForMissingAndInvalidShape(): void
    {
        $input = FormInput::fromArray(data: ['invalid' => [['x']]]);

        $this->assertNull($input->getText(name: 'missing'));
        $this->assertNull($input->getList(name: 'missing'));
        $this->assertNull($input->getText(name: 'invalid'));
        $this->assertNull($input->getList(name: 'invalid'));
    }

    public function testDataWinsOverFilesWithTheSameName(): void
    {
        $input = FormInput::fromArray(data: ['field' => 'posted'], files: ['field' => ['name' => 'x'], 'other' => 'f']);

        $this->assertSame('posted', $input->getText(name: 'field'));
        $this->assertSame('f', $input->getText(name: 'other'));
    }

    public function testNumericKeysAreReadByTheirStringName(): void
    {
        $input = FormInput::fromArray(data: [5 => 'five']);

        $this->assertSame('five', $input->getText(name: '5'));
    }

    public function testQueryKeysAndTexts(): void
    {
        $input = FormInput::fromArray(data: ['field' => 'a'], query: ['sent' => '', 'token' => 'abc', 'list' => ['x']]);

        $this->assertTrue($input->hasQueryKey(key: 'sent'));
        $this->assertTrue($input->hasQueryKey(key: 'list'));
        $this->assertFalse($input->hasQueryKey(key: 'field'));
        $this->assertSame('abc', $input->getQueryText(key: 'token'));
        $this->assertSame('', $input->getQueryText(key: 'sent'));
        $this->assertNull($input->getQueryText(key: 'list'));
        $this->assertNull($input->getQueryText(key: 'missing'));
    }

    public function testQueryIsNotPartOfTheData(): void
    {
        $input = FormInput::fromArray(data: [], query: ['field' => 'a']);

        $this->assertSame(InputShapeEnum::MISSING, $input->getShape(name: 'field'));
    }
}