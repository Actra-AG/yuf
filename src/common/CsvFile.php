<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\NativeResponseSender;
use actra\yuf\core\ResponseSender;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builder for one CSV file: collects the rows with `addRow()` (rows are only added, nothing returns or changes them
 * later; the arrays are values, so changing an array afterwards does not change the row) and writes them to a
 * temporary file or sends them as download. Use one instance per file; writing does not change the instance, so it
 * can write the same rows again.
 *
 * Against CSV injection, a text cell that starts with "=", "+", "-", "@", a tab or a carriage return and is no number
 * gets a leading "'", so a spreadsheet application does not run it as formula (OWASP). Numbers and numeric strings
 * stay as they are. Switch it off with `protectAgainstFormulas: false` only for data that is known to be safe.
 */
final class CsvFile
{
    private const string FORMULA_START_CHARACTERS = "=+-@\t\r";

    /** @var list<array<array-key, bool|float|int|string|null>> */
    private array $rows = [];

    /**
     * @param string $fileName Name of the downloaded file
     * @param array<array-key, bool|float|int|string|null> $headersList The first row, none if empty
     * @param bool $addByteOrderMark Starts the file with the UTF-8 byte order mark (spreadsheet applications
     *                               recognize the encoding)
     * @param string $delimiter One character
     * @param string $enclosure One character
     *
     * @throws InvalidArgumentException If the delimiter or the enclosure is not one character
     */
    public function __construct(
        private readonly string $fileName,
        private readonly array $headersList = [],
        private readonly bool $addByteOrderMark = true,
        private readonly string $delimiter = ';',
        private readonly string $enclosure = '"',
        private readonly bool $protectAgainstFormulas = true,
    ) {
        if (strlen(string: $delimiter) !== 1) {
            throw new InvalidArgumentException(
                message: 'The delimiter of a CSV file must be one character, "' . $delimiter . '" given.',
            );
        }
        if (strlen(string: $enclosure) !== 1) {
            throw new InvalidArgumentException(
                message: 'The enclosure of a CSV file must be one character, "' . $enclosure . '" given.',
            );
        }
    }

    /**
     * Parses CSV text, one row per line (a line break inside of a quoted cell is not supported); empty lines are left
     * out.
     *
     * @param non-empty-string $terminator
     *
     * @return list<list<string|null>>
     */
    public static function stringToArray(
        string $string,
        string $delimiter = ';',
        string $enclosure = '"',
        string $escape = '\\',
        string $terminator = "\n",
    ): array {
        $string = trim(string: $string);
        if ($string === '') {
            return [];
        }
        $result = [];
        foreach (explode(separator: $terminator, string: $string) as $row) {
            $row = trim(string: $row);
            if ($row !== '') {
                $result[] = str_getcsv(string: $row, separator: $delimiter, enclosure: $enclosure, escape: $escape);
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, bool|float|int|string|null> $data
     */
    public function addRow(array $data): void
    {
        $this->rows[] = $data;
    }

    /**
     * @return string The path of the new file in the temporary directory (readable by the owner only); the caller
     *                removes it
     *
     * @throws RuntimeException If the file cannot be written
     */
    public function createTemporaryFile(): string
    {
        $temporaryPath = tempnam(directory: sys_get_temp_dir(), prefix: 'yuf-csv-');
        if ($temporaryPath === false) {
            throw new RuntimeException(message: 'Cannot create a temporary file for the CSV file.');
        }
        $path = $temporaryPath . '.csv';
        if (!rename(from: $temporaryPath, to: $path)) {
            unlink(filename: $temporaryPath);
            throw new RuntimeException(message: 'Cannot rename the temporary file "' . $temporaryPath . '".');
        }
        $fileResource = fopen(filename: $path, mode: 'w');
        if ($fileResource === false) {
            unlink(filename: $path);
            throw new RuntimeException(message: 'Cannot open the temporary file "' . $path . '".');
        }
        try {
            $this->writeTo(fileResource: $fileResource, path: $path);
        } catch (RuntimeException $runtimeException) {
            unlink(filename: $path);
            throw $runtimeException;
        } finally {
            fclose(stream: $fileResource);
        }

        return $path;
    }

    /**
     * Sends the file as download and ends the script. The temporary file is removed at the end of the script.
     */
    public function pushDownloadAndExit(
        HttpRequest $httpRequest,
        ResponseSender $responseSender = new NativeResponseSender(),
    ): never {
        $path = $this->createTemporaryFile();
        register_shutdown_function(static function () use ($path): void {
            if (is_file(filename: $path)) {
                unlink(filename: $path);
            }
        });
        HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $path,
            forceDownload: true,
            individualFileName: $this->fileName,
            maxAge: 0,
            httpRequest: $httpRequest,
        )->sendAndExit(responseSender: $responseSender);
    }

    /**
     * @param resource $fileResource
     *
     * @throws RuntimeException
     */
    private function writeTo($fileResource, string $path): void
    {
        if ($this->addByteOrderMark) {
            CsvFile::checkWritten(result: fwrite(stream: $fileResource, data: "\xEF\xBB\xBF"), path: $path);
        }
        if ($this->headersList !== []) {
            $this->writeRow(fileResource: $fileResource, path: $path, row: $this->headersList);
        }
        foreach ($this->rows as $row) {
            $this->writeRow(fileResource: $fileResource, path: $path, row: $row);
        }
    }

    /**
     * @param resource $fileResource
     * @param array<array-key, bool|float|int|string|null> $row
     *
     * @throws RuntimeException
     */
    private function writeRow($fileResource, string $path, array $row): void
    {
        $cells = $this->protectAgainstFormulas
            ? array_map(callback: CsvFile::protectCell(...), array: $row)
            : $row;
        CsvFile::checkWritten(
            result: fputcsv(
                stream: $fileResource,
                fields: $cells,
                separator: $this->delimiter,
                enclosure: $this->enclosure,
                escape: '',
            ),
            path: $path,
        );
    }

    /**
     * @throws RuntimeException
     */
    private static function checkWritten(int|false $result, string $path): void
    {
        if ($result === false) {
            throw new RuntimeException(message: 'Cannot write to the temporary file "' . $path . '".');
        }
    }

    private static function protectCell(bool|float|int|string|null $cell): bool|float|int|string|null
    {
        if (!is_string(value: $cell) || $cell === '' || is_numeric(value: $cell)) {
            return $cell;
        }

        return str_contains(haystack: CsvFile::FORMULA_START_CHARACTERS, needle: $cell[0]) ? '\'' . $cell : $cell;
    }
}
