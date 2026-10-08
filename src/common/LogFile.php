<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use Throwable;

class LogFile
{
    /** @var LogFile[] */
    private static array $openLogFiles = [];
    /** @var resource */
    private $stream;

    /**
     * @param string $logDirectory The log directory of the project (`Core::$logDirectory`, with trailing slash)
     */
    public function __construct(
        string $logDirectory,
        string $group,
        string $logFileName,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $groupDirectoryPath = LogFile::createDirectoryIfMissing(path: $logDirectory . $group);
        $dateArr = explode(separator: '-', string: $this->clock->now()->format(format: 'Y-m-d'));
        $yearDirectoryPath = LogFile::createDirectoryIfMissing(
            path: $groupDirectoryPath . DIRECTORY_SEPARATOR . $dateArr[0],
        );
        $monthDirectoryPath = LogFile::createDirectoryIfMissing(
            path: $yearDirectoryPath . DIRECTORY_SEPARATOR . $dateArr[1],
        );
        $dayDirectoryPath = LogFile::createDirectoryIfMissing(
            path: $monthDirectoryPath . DIRECTORY_SEPARATOR . $dateArr[2],
        );
        $this->stream = fopen(
            filename: $dayDirectoryPath . DIRECTORY_SEPARATOR . $logFileName . '-' . uniqid(
                more_entropy: true,
            ) . '.log',
            mode: 'a',
        );
        LogFile::$openLogFiles[$group . '-' . $logFileName] = $this;
    }

    private static function createDirectoryIfMissing($path): string
    {
        if (!is_dir(filename: $path)) {
            try {
                mkdir(directory: $path);
            } catch (Throwable $throwable) {
                if (str_contains(
                    haystack: $throwable->getMessage(),
                    needle: 'mkdir(): Die Datei existiert bereits',
                )) {
                    return $path;
                }
                throw $throwable;
            }
        }

        return $path;
    }

    public static function info(
        string $logDirectory,
        string $logFileName,
        string $message,
    ): void {
        LogFile::log(
            logDirectory: $logDirectory,
            group: 'info',
            logFileName: $logFileName,
            message: $message,
        );
    }

    private static function log(
        string $logDirectory,
        string $group,
        string $logFileName,
        string $message,
    ): void {
        if (array_key_exists(
            key: $group . '-' . $logFileName,
            array: LogFile::$openLogFiles,
        )) {
            $logFile = LogFile::$openLogFiles[$group . '-' . $logFileName];
        } else {
            $logFile = new LogFile(
                logDirectory: $logDirectory,
                group: $group,
                logFileName: $logFileName,
            );
        }
        $logFile->write(line: $message);
    }

    public function write(string $line): void
    {
        if (!is_resource(value: $this->stream)) {
            return;
        }
        $now = $this->clock->now();
        // Eight fractional digits, as before: microseconds plus two zeros
        $timestamp = $now->format(format: 'Y-m-d H:i:s') . ',' . $now->format(format: 'u') . '00';
        fwrite(
            stream: $this->stream,
            data: $timestamp . ' - ' . $line . PHP_EOL,
        );
    }

    public static function debug(
        string $logDirectory,
        string $logFileName,
        string $message,
    ): void {
        LogFile::log(
            logDirectory: $logDirectory,
            group: 'debug',
            logFileName: $logFileName,
            message: $message,
        );
    }

    public static function error(
        string $logDirectory,
        string $logFileName,
        string $message,
    ): void {
        LogFile::log(
            logDirectory: $logDirectory,
            group: 'error',
            logFileName: $logFileName,
            message: $message,
        );
    }

    public function __destruct()
    {
        if (is_resource(value: $this->stream)) {
            fclose(stream: $this->stream);
        }
    }
}
