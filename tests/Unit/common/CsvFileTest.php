<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\CsvFile;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\ResponseSender;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\core\ResponseSentException;
use Generator;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvFileTest extends TestCase
{
    private const string BOM = "\xEF\xBB\xBF";

    /** @var list<string> */
    private array $createdFiles = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            if (is_file(filename: $path)) {
                unlink(filename: $path);
            }
        }
    }

    public function testWritesBomHeaderAndRows(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['Name', 'City']);
        $csvFile->addRow(data: ['Anna', 'Bern']);
        $csvFile->addRow(data: ['Ben', 'Zürich']);

        $this->assertSame(
            CsvFileTest::BOM . "Name;City\nAnna;Bern\nBen;Zürich\n",
            $this->content(csvFile: $csvFile),
        );
    }

    public function testMoreRowsAreWrittenOneByOneAfterTheAddedRows(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['Name']);
        $csvFile->addRow(data: ['Anna']);
        $moreRows = (static function (): Generator {
            yield ['Ben'];
            yield ['=SUM(A1)'];
        })();

        $path = $csvFile->createTemporaryFile(moreRows: $moreRows);
        $this->createdFiles[] = $path;

        $this->assertSame(CsvFileTest::BOM . "Name\nAnna\nBen\n'=SUM(A1)\n", file_get_contents(filename: $path));
    }

    public function testWithoutByteOrderMark(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['a'], addByteOrderMark: false);
        $csvFile->addRow(data: ['b']);

        $this->assertSame("a\nb\n", $this->content(csvFile: $csvFile));
    }

    public function testWithoutHeaders(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false);
        $csvFile->addRow(data: ['a', 'b']);

        $this->assertSame("a;b\n", $this->content(csvFile: $csvFile));
    }

    public function testWithoutRowsOnlyTheHeaders(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['a', 'b'], addByteOrderMark: false);

        $this->assertSame("a;b\n", $this->content(csvFile: $csvFile));
    }

    public function testEmptyFileHasOnlyTheByteOrderMark(): void
    {
        $this->assertSame(CsvFileTest::BOM, $this->content(csvFile: new CsvFile(fileName: 'export.csv')));
    }

    public function testDelimiterAndEnclosure(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false, delimiter: ',', enclosure: "'");
        $csvFile->addRow(data: ['a b', "it's", 'x,y']);

        $this->assertSame("'a b','it''s','x,y'\n", $this->content(csvFile: $csvFile));
    }

    public function testQuotesCellsWithDelimiterEnclosureAndLineBreaks(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false);
        $csvFile->addRow(data: ['a;b', 'say "hi"', "line\nbreak", 'back\\slash']);

        $this->assertSame(
            "\"a;b\";\"say \"\"hi\"\"\";\"line\nbreak\";back\\slash\n",
            $this->content(csvFile: $csvFile),
        );
    }

    public function testScalarsAndNull(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false);
        $csvFile->addRow(data: [1, 2.5, true, false, null, '', '0']);

        $this->assertSame("1;2.5;1;;;;0\n", $this->content(csvFile: $csvFile));
    }

    /**
     * @return iterable<string, array{bool|float|int|string|null, string}>
     */
    public static function formulaProvider(): iterable
    {
        yield 'equals sign' => ['=SUM(A1:A2)', "'=SUM(A1:A2)"];
        yield 'plus sign' => ['+41 79 000 00 00', "\"'+41 79 000 00 00\""];
        yield 'minus sign' => ['-cmd|calc', "'-cmd|calc"];
        yield 'at sign' => ['@SUM(1)', "'@SUM(1)"];
        yield 'tab' => ["\t=1", "\"'\t=1\""];
        yield 'dash only' => ['-', "'-"];
        yield 'text' => ['Anna', 'Anna'];
        yield 'sign inside of the text' => ['a=b', 'a=b'];
        yield 'negative number as string' => ['-5', '-5'];
        yield 'negative decimal as string' => ['-5.25', '-5.25'];
        yield 'number with plus as string' => ['+5', '+5'];
        yield 'negative integer' => [-5, '-5'];
        yield 'negative float' => [-0.5, '-0.5'];
        yield 'quote is kept' => ["'=1", "'=1"];
    }

    #[DataProvider('formulaProvider')]
    public function testProtectsAgainstFormulas(bool|float|int|string|null $cell, string $expected): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false, delimiter: ';');
        $csvFile->addRow(data: [$cell]);

        $this->assertSame($expected . "\n", $this->content(csvFile: $csvFile));
    }

    public function testProtectsTheHeaders(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['=1+1', 'Name'], addByteOrderMark: false);

        $this->assertSame("'=1+1;Name\n", $this->content(csvFile: $csvFile));
    }

    public function testProtectionCanBeSwitchedOff(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', addByteOrderMark: false, protectAgainstFormulas: false);
        $csvFile->addRow(data: ['=1+1', '@x']);

        $this->assertSame("=1+1;@x\n", $this->content(csvFile: $csvFile));
    }

    public function testTemporaryFileIsReadableByTheOwnerOnly(): void
    {
        $path = $this->create(csvFile: new CsvFile(fileName: 'export.csv'));

        $permissions = fileperms(filename: $path);
        $this->assertIsInt($permissions);
        $this->assertSame('0600', substr(string: sprintf('%o', $permissions), offset: -4));
    }

    public function testTemporaryFilesHaveCsvExtensionAndDifferentPaths(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv');

        $first = $this->create(csvFile: $csvFile);
        $second = $this->create(csvFile: $csvFile);

        $this->assertStringEndsWith('.csv', $first);
        $this->assertSame(sys_get_temp_dir(), dirname(path: $first));
        $this->assertNotSame($first, $second);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCharacterProvider(): iterable
    {
        yield 'empty delimiter' => ['', '"'];
        yield 'long delimiter' => [';;', '"'];
        yield 'empty enclosure' => [';', ''];
        yield 'long enclosure' => [';', '""'];
    }

    #[DataProvider('invalidCharacterProvider')]
    public function testRejectsDelimiterAndEnclosureThatAreNotOneCharacter(string $delimiter, string $enclosure): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsvFile(fileName: 'export.csv', delimiter: $delimiter, enclosure: $enclosure);
    }

    /**
     * @return iterable<string, array{string, list<list<string|null>>}>
     */
    public static function stringProvider(): iterable
    {
        yield 'empty' => ['', []];
        yield 'only whitespace' => ["  \n ", []];
        yield 'one row' => ['a;b;c', [['a', 'b', 'c']]];
        yield 'several rows' => ["a;b\n1;2\n3;4", [['a', 'b'], ['1', '2'], ['3', '4']]];
        yield 'empty lines are left out' => ["a;b\n\n1;2\n  \n", [['a', 'b'], ['1', '2']]];
        yield 'windows line endings' => ["a;b\r\n1;2\r\n", [['a', 'b'], ['1', '2']]];
        yield 'enclosed cells' => ['"a;b";"say ""hi"""', [['a;b', 'say "hi"']]];
        yield 'empty cells' => ['a;;c', [['a', '', 'c']]];
    }

    /**
     * @param list<list<string|null>> $expected
     */
    #[DataProvider('stringProvider')]
    public function testStringToArray(string $string, array $expected): void
    {
        $this->assertSame($expected, CsvFile::stringToArray(string: $string));
    }

    public function testStringToArrayWithOwnCharacters(): void
    {
        $this->assertSame(
            [['a', 'b c'], ['1', '2']],
            CsvFile::stringToArray(
                string: "a,'b c'|1,2",
                delimiter: ',',
                enclosure: "'",
                terminator: '|',
            ),
        );
    }

    public function testStringToArrayKeepsTheBackslashWithoutEscapeCharacter(): void
    {
        $this->assertSame([['a\\', 'b']], CsvFile::stringToArray(string: 'a\\;b', escape: ''));
    }

    private function create(CsvFile $csvFile): string
    {
        $path = $csvFile->createTemporaryFile();
        $this->createdFiles[] = $path;

        return $path;
    }

    private function content(CsvFile $csvFile): string
    {
        $content = file_get_contents(filename: $this->create(csvFile: $csvFile));
        $this->assertIsString($content);

        return $content;
    }

    public function testPushDownloadSendsTheFileAsDownloadThroughTheSender(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['a'], addByteOrderMark: false);
        $csvFile->addRow(data: ['b']);

        $sentResponse = RecordingResponseSender::capture(
            action: static fn(ResponseSender $sender) => $csvFile->pushDownloadAndExit(
                httpRequest: HttpRequestFactory::create(),
                responseSender: $sender,
            ),
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $sentResponse->httpStatusCode);
        $this->assertSame('attachment; filename="export.csv"', $sentResponse->getHeader(key: 'Content-Disposition'));
        $path = $sentResponse->getContentFilePath();
        $this->assertNotNull($path);
        $this->assertSame("a\nb\n", file_get_contents(filename: $path));
        unlink(filename: $path);
    }

    public function testPushDownloadRemovesTheTemporaryFileAfterTheResponse(): void
    {
        $csvFile = new CsvFile(fileName: 'export.csv', headersList: ['a']);
        $sender = new RecordingResponseSender();

        try {
            $csvFile->pushDownloadAndExit(httpRequest: HttpRequestFactory::create(), responseSender: $sender);
        } catch (ResponseSentException) {
            // The double throws instead of ending the process
        }
        $path = $sender->sentResponse?->getContentFilePath();

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertSame(1, $sender->countAfterResponseCallbacks());
        $sender->runAfterResponseCallbacks();
        $this->assertFileDoesNotExist($path);
    }
}
