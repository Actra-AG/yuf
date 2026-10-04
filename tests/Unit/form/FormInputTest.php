<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormInput;
use actra\yuf\form\InputShapeEnum;
use actra\yuf\form\model\UploadInput;
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

    public function testMapKeepsIntKeysAndOrder(): void
    {
        $input = FormInput::fromArray(data: ['qty' => [123 => '2', 7 => '5', 40 => '1']]);

        $this->assertSame([123 => '2', 7 => '5', 40 => '1'], $input->getMap(name: 'qty'));
        $this->assertSame(['2', '5', '1'], $input->getList(name: 'qty'));
    }

    public function testMapKeepsStringKeysAndOrder(): void
    {
        $input = FormInput::fromArray(data: ['qty' => ['b' => 'x', 'a' => 'y']]);

        $this->assertSame(['b' => 'x', 'a' => 'y'], $input->getMap(name: 'qty'));
        $this->assertSame(['x', 'y'], $input->getList(name: 'qty'));
    }

    public function testMapKeepsMixedKeys(): void
    {
        $input = FormInput::fromArray(data: ['qty' => [5 => 'a', 'k' => 'b', 6 => 'c']]);

        $this->assertSame([5 => 'a', 'k' => 'b', 6 => 'c'], $input->getMap(name: 'qty'));
    }

    public function testMapOfAPlainListHasTheListKeys(): void
    {
        $input = FormInput::fromArray(data: ['field' => ['a', 'b']]);

        $this->assertSame([0 => 'a', 1 => 'b'], $input->getMap(name: 'field'));
    }

    public function testMapOfAnEmptyArrayIsEmpty(): void
    {
        $input = FormInput::fromArray(data: ['field' => []]);

        $this->assertSame([], $input->getMap(name: 'field'));
        $this->assertSame([], $input->getList(name: 'field'));
    }

    public function testNestedArrayWithKeysIsInvalidAndHasNoMap(): void
    {
        $input = FormInput::fromArray(data: ['qty' => [123 => ['2']], 'mixed' => [1 => 'a', 2 => 3]]);

        $this->assertSame(InputShapeEnum::INVALID, $input->getShape(name: 'qty'));
        $this->assertNull($input->getMap(name: 'qty'));
        $this->assertSame(InputShapeEnum::INVALID, $input->getShape(name: 'mixed'));
        $this->assertNull($input->getMap(name: 'mixed'));
    }

    public function testNoMapForTextAndMissingShape(): void
    {
        $input = FormInput::fromArray(data: ['field' => 'a']);

        $this->assertNull($input->getMap(name: 'field'));
        $this->assertNull($input->getMap(name: 'missing'));
    }

    public function testNothingIsReturnedForMissingAndInvalidShape(): void
    {
        $input = FormInput::fromArray(data: ['invalid' => [['x']]]);

        $this->assertNull($input->getText(name: 'missing'));
        $this->assertNull($input->getList(name: 'missing'));
        $this->assertNull($input->getText(name: 'invalid'));
        $this->assertNull($input->getList(name: 'invalid'));
        $this->assertNull($input->getMap(name: 'invalid'));
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

    /**
     * @return array<string, string|int>
     */
    private static function singleFile(): array
    {
        return ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/php1', 'error' => 0, 'size' => 5];
    }

    public function testSingleUploadIsNarrowedToOneUploadInput(): void
    {
        $input = FormInput::fromArray(data: [], files: ['file' => FormInputTest::singleFile()]);

        $this->assertEquals(
            [new UploadInput(name: 'a.txt', tmpName: '/tmp/php1', type: 'text/plain', error: 0, size: 5)],
            $input->getUploads(name: 'file')
        );
        $this->assertFalse($input->hasMalformedUpload(name: 'file'));
    }

    public function testMultipleUploadsAreNarrowedInTheOrderOfTheNames(): void
    {
        $input = FormInput::fromArray(
            data: [],
            files: [
                'file' => [
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'image/png'],
                    'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                    'error' => [0, UPLOAD_ERR_PARTIAL],
                    'size' => [5, 6],
                ],
            ]
        );

        $this->assertEquals(
            [
                new UploadInput(name: 'a.txt', tmpName: '/tmp/php1', type: 'text/plain', error: 0, size: 5),
                new UploadInput(name: 'b.txt', tmpName: '/tmp/php2', type: 'image/png', error: 3, size: 6),
            ],
            $input->getUploads(name: 'file')
        );
    }

    public function testUploadsWithStringKeysAreNarrowed(): void
    {
        $input = FormInput::fromArray(
            data: [],
            files: [
                'file' => [
                    'name' => ['x' => 'a.txt'],
                    'type' => ['x' => 'text/plain'],
                    'tmp_name' => ['x' => '/tmp/php1'],
                    'error' => ['x' => 0],
                    'size' => ['x' => 5],
                ],
            ]
        );

        $this->assertCount(1, $input->getUploads(name: 'file'));
    }

    public function testUploadWithoutEntriesIsValidAndEmpty(): void
    {
        $input = FormInput::fromArray(
            data: [],
            files: ['file' => ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []]]
        );

        $this->assertSame([], $input->getUploads(name: 'file'));
        $this->assertFalse($input->hasMalformedUpload(name: 'file'));
    }

    public function testNameAndTypeOfAnUploadAreTrimmed(): void
    {
        $input = FormInput::fromArray(
            data: [],
            files: ['file' => ['name' => " a.txt\n", 'type' => ' text/plain '] + FormInputTest::singleFile()]
        );

        $upload = $input->getUploads(name: 'file')[0];
        $this->assertSame('a.txt', $upload->name);
        $this->assertSame('text/plain', $upload->type);
    }

    public function testNameThatIsNotAnUploadHasNone(): void
    {
        $input = FormInput::fromArray(data: [], files: ['file' => FormInputTest::singleFile()]);

        $this->assertSame([], $input->getUploads(name: 'other'));
        $this->assertFalse($input->hasMalformedUpload(name: 'other'));
    }

    public function testNumericInputNamesAreReadByTheirStringName(): void
    {
        $input = FormInput::fromArray(data: [], files: [7 => FormInputTest::singleFile()]);

        $this->assertCount(1, $input->getUploads(name: '7'));
    }

    public function testDataWithTheSameNameIsNoUpload(): void
    {
        $input = FormInput::fromArray(data: ['file' => 'posted'], files: ['file' => FormInputTest::singleFile()]);

        $this->assertSame([], $input->getUploads(name: 'file'));
        $this->assertFalse($input->hasMalformedUpload(name: 'file'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function noUploadProvider(): iterable
    {
        yield 'text' => ['x'];
        yield 'int' => [3];
        yield 'null' => [null];
        yield 'list of texts' => [['x', 'y']];
        yield 'array without upload keys' => [['x' => 'y']];
        yield 'nested array' => [[['x']]];
    }

    #[DataProvider('noUploadProvider')]
    public function testValueThatIsNotBuiltLikeAnUploadIsIgnored(mixed $value): void
    {
        $input = FormInput::fromArray(data: [], files: ['file' => $value]);

        $this->assertSame([], $input->getUploads(name: 'file'));
        $this->assertFalse($input->hasMalformedUpload(name: 'file'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function malformedUploadProvider(): iterable
    {
        $multiple = [
            'name' => ['a.txt'],
            'type' => ['text/plain'],
            'tmp_name' => ['/tmp/php1'],
            'error' => [0],
            'size' => [5],
        ];
        yield 'only one key' => [['name' => 'a.txt']];
        yield 'single without size' => [array_diff_key(FormInputTest::singleFile(), ['size' => 1])];
        yield 'single with string error' => [['error' => '0'] + FormInputTest::singleFile()];
        yield 'single with string size' => [['size' => '5'] + FormInputTest::singleFile()];
        yield 'single with list type' => [['type' => ['text/plain']] + FormInputTest::singleFile()];
        yield 'single with null name' => [['name' => null] + FormInputTest::singleFile()];
        yield 'nested names' => [['name' => [['a.txt']]] + $multiple];
        yield 'name list, scalar column' => [['size' => 5] + $multiple];
        yield 'column is missing' => [array_diff_key($multiple, ['tmp_name' => 1])];
        yield 'column has more entries' => [['size' => [5, 6]] + $multiple];
        yield 'column has other keys' => [['size' => [3 => 5]] + $multiple];
        yield 'string error' => [['error' => ['0']] + $multiple];
        yield 'null size' => [['size' => [null]] + $multiple];
        yield 'int name' => [['name' => [5]] + $multiple];
        yield 'object' => [['name' => new stdClass()] + FormInputTest::singleFile()];
    }

    /**
     * @param array<array-key, mixed> $value
     */
    #[DataProvider('malformedUploadProvider')]
    public function testMalformedUploadIsReportedAndHasNoUploads(array $value): void
    {
        $input = FormInput::fromArray(data: [], files: ['file' => $value]);

        $this->assertTrue($input->hasMalformedUpload(name: 'file'));
        $this->assertSame([], $input->getUploads(name: 'file'));
    }

    public function testUploadsAreStillReadAsInputShapes(): void
    {
        $input = FormInput::fromArray(data: [], files: ['file' => FormInputTest::singleFile()]);

        $this->assertSame(InputShapeEnum::INVALID, $input->getShape(name: 'file'));
    }

    /**
     * @param array<array-key, mixed> $get
     * @param array<array-key, mixed> $post
     * @param array<array-key, mixed> $files
     * @param callable(): void $test
     */
    private function withGlobals(array $get, array $post, array $files, callable $test): void
    {
        $savedGet = $_GET;
        $savedPost = $_POST;
        $savedFiles = $_FILES;
        $_GET = $get;
        $_POST = $post;
        $_FILES = $files;
        try {
            $test();
        } finally {
            $_GET = $savedGet;
            $_POST = $savedPost;
            $_FILES = $savedFiles;
        }
    }

    public function testFromGlobalsOfAPostFormReadsThePostedValuesAndTheQueryPart(): void
    {
        $this->withGlobals(
            get: ['contact' => '', 'csrftoken' => 'fallback', 'name' => 'from get'],
            post: ['name' => 'from post', 'tags' => ['a', 'b']],
            files: [],
            test: function (): void {
                $input = FormInput::fromGlobals(methodPost: true);

                $this->assertSame('from post', $input->getText(name: 'name'));
                $this->assertSame(['a', 'b'], $input->getList(name: 'tags'));
                $this->assertTrue($input->hasQueryKey(key: 'contact'));
                $this->assertSame('fallback', $input->getQueryText(key: 'csrftoken'));
            }
        );
    }

    public function testFromGlobalsOfAGetFormReadsTheValuesFromTheQueryString(): void
    {
        $this->withGlobals(
            get: ['contact' => '', 'name' => 'from get'],
            post: ['name' => 'from post'],
            files: [],
            test: function (): void {
                $input = FormInput::fromGlobals(methodPost: false);

                $this->assertSame('from get', $input->getText(name: 'name'));
                $this->assertTrue($input->hasQueryKey(key: 'contact'));
            }
        );
    }

    public function testFromGlobalsNarrowsLikeFromArray(): void
    {
        $this->withGlobals(
            get: [],
            post: ['nested' => [['x']], 'number' => 5],
            files: ['file' => FormInputTest::singleFile()],
            test: function (): void {
                $input = FormInput::fromGlobals(methodPost: true);

                $this->assertSame(InputShapeEnum::INVALID, $input->getShape(name: 'nested'));
                $this->assertSame(InputShapeEnum::INVALID, $input->getShape(name: 'number'));
                $this->assertCount(1, $input->getUploads(name: 'file'));
            }
        );
    }
}