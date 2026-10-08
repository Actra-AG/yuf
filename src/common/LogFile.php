<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use RuntimeException;

final class LogFile
{
    /** @var resource */
    private $stream;

    /**
     * @param string $logDirectory The log directory of the project (`Core::$logDirectory`, with trailing slash)
     *
     * @throws RuntimeException If a directory cannot be created or the log file cannot be opened
     */
    public function __construct(
        string $logDirectory,
        string $group,
        string $logFileName,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $now = $this->clock->now();
        $dayDirectoryPath = $logDirectory . $group;
        foreach (['Y', 'm', 'd'] as $format) {
            LogFile::createDirectoryIfMissing(path: $dayDirectoryPath);
            $dayDirectoryPath .= DIRECTORY_SEPARATOR . $now->format(format: $format);
        }
        LogFile::createDirectoryIfMissing(path: $dayDirectoryPath);
        $filePath = $dayDirectoryPath . DIRECTORY_SEPARATOR . $logFileName . '-' . uniqid(more_entropy: true) . '.log';
        $stream = LogFile::openForAppending(filePath: $filePath);
        if ($stream === false) {
            throw new RuntimeException(message: 'Cannot open log file "' . $filePath . '".');
        }
        $this->stream = $stream;
    }

    public function write(string $line): void
    {
        $now = $this->clock->now();
        // Eight fractional digits, as before: microseconds plus two zeros
        $timestamp = $now->format(format: 'Y-m-d H:i:s') . ',' . $now->format(format: 'u') . '00';
        fwrite(
            stream: $this->stream,
            data: $timestamp . ' - ' . $line . PHP_EOL,
        );
    }

    /**
     * @throws RuntimeException
     */
    private static function createDirectoryIfMissing(string $path): void
    {
        if (is_dir(filename: $path)) {
            return;
        }
        // Another process may create the directory in the meantime: only the final state counts
        set_error_handler(callback: static fn(): bool => true);
        try {
            mkdir(directory: $path);
        } finally {
            restore_error_handler();
        }
        if (!is_dir(filename: $path)) {
            throw new RuntimeException(message: 'Cannot create log directory "' . $path . '".');
        }
    }

    /**
     * @return resource|false
     */
    private static function openForAppending(string $filePath)
    {
        set_error_handler(callback: static fn(): bool => true);
        try {
            return fopen(filename: $filePath, mode: 'a');
        } finally {
            restore_error_handler();
        }
    }
}
