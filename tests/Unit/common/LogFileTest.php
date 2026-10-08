<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\clock\FixedClock;
use actra\yuf\common\LogFile;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LogFileTest extends TestCase
{
    private string $logDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-logfile-' . bin2hex(
            string: random_bytes(length: 8),
        ) . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->logDirectory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory(path: rtrim(string: $this->logDirectory, characters: DIRECTORY_SEPARATOR));
    }

    public function testCreatesTheDirectoryStructureOfTheDate(): void
    {
        new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.123456'),
        );

        $this->assertDirectoryExists($this->logDirectory . 'epp/2026/03/07');
    }

    public function testFileNameHasPrefixAndLogSuffix(): void
    {
        new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.123456'),
        );

        $files = $this->files(directory: $this->logDirectory . 'epp/2026/03/07');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('job-', $files[0]);
        $this->assertStringEndsWith('.log', $files[0]);
    }

    public function testWritesLineWithTimestamp(): void
    {
        $logFile = new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.123456'),
        );

        $logFile->write(line: 'hello');

        $this->assertSame(
            '2026-03-07 08:09:10,12345600 - hello' . PHP_EOL,
            $this->content(logFile: $this->onlyFile($this->logDirectory . 'epp/2026/03/07')),
        );
    }

    public function testSeveralWritesAreAppended(): void
    {
        $logFile = new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.000001'),
        );

        $logFile->write(line: 'first');
        $logFile->write(line: 'second');

        $this->assertSame(
            '2026-03-07 08:09:10,00000100 - first' . PHP_EOL
                . '2026-03-07 08:09:10,00000100 - second' . PHP_EOL,
            $this->content(logFile: $this->onlyFile($this->logDirectory . 'epp/2026/03/07')),
        );
    }

    public function testExistingDirectoriesAreReused(): void
    {
        $clock = $this->clock(dateTime: '2026-03-07 08:09:10.123456');
        new LogFile(logDirectory: $this->logDirectory, group: 'epp', logFileName: 'one', clock: $clock);
        new LogFile(logDirectory: $this->logDirectory, group: 'epp', logFileName: 'two', clock: $clock);

        $files = $this->files(directory: $this->logDirectory . 'epp/2026/03/07');
        $this->assertCount(2, $files);
    }

    public function testDifferentDatesUseDifferentDirectories(): void
    {
        new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.000000'),
        );
        new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-12-31 23:59:59.000000'),
        );

        $this->assertDirectoryExists($this->logDirectory . 'epp/2026/03/07');
        $this->assertDirectoryExists($this->logDirectory . 'epp/2026/12/31');
    }

    public function testThrowsIfTheDirectoryCannotBeCreated(): void
    {
        file_put_contents(filename: $this->logDirectory . 'epp', data: 'not a directory');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains($this->logDirectory . 'epp');

        new LogFile(
            logDirectory: $this->logDirectory,
            group: 'epp',
            logFileName: 'job',
            clock: $this->clock(dateTime: '2026-03-07 08:09:10.123456'),
        );
    }

    private function clock(string $dateTime): FixedClock
    {
        return new FixedClock(now: new DateTimeImmutable(datetime: $dateTime));
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = scandir(directory: $directory);
        $this->assertIsArray($files);

        return array_values(array: array_diff($files, ['.', '..']));
    }

    private function onlyFile(string $directory): string
    {
        $files = $this->files(directory: $directory);
        $this->assertCount(1, $files);

        return $directory . DIRECTORY_SEPARATOR . $files[0];
    }

    private function content(string $logFile): string
    {
        $content = file_get_contents(filename: $logFile);
        $this->assertIsString($content);

        return $content;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir(filename: $path)) {
            return;
        }
        foreach ($this->files(directory: $path) as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir(filename: $entryPath)) {
                $this->removeDirectory(path: $entryPath);
            } else {
                unlink(filename: $entryPath);
            }
        }
        rmdir(directory: $path);
    }
}
