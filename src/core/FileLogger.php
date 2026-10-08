<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Throwable;

/**
 * Writes every issue into a ticket file of the log directory (`ticket_<hash>.txt`, one file per distinct issue) and
 * mails a new issue (or one that was not seen for 24 hours) to the log recipient. The entry describes the request
 * without secrets (see `RequestLogFormatter`).
 */
final class FileLogger implements Logger
{
    private const string DOUBLE_NEW_LINE = PHP_EOL . PHP_EOL;
    private const int DEFAULT_MAX_LOG_SIZE = 10_000_000;
    private const int SECONDS_UNTIL_ISSUE_IS_NEW_AGAIN = 86400;

    private bool $lastIssueIsNew = false;
    private readonly string $logDirectory;
    private readonly RequestLogFormatter $requestLogFormatter;

    /**
     * @param string $logEmailRecipient Mail address of the new issues, empty for no mails
     * @param int $maxLogSize Size in bytes at which a ticket file is moved to `<file>.<number>`, 0 for no limit
     *
     * @throws InvalidArgumentException if the log directory does not exist
     */
    public function __construct(
        private readonly string $logEmailRecipient,
        string $logDirectory,
        private readonly HttpRequest $httpRequest,
        private readonly Clock $clock = new SystemClock(),
        private readonly int $maxLogSize = FileLogger::DEFAULT_MAX_LOG_SIZE,
    ) {
        if (!is_dir(filename: $logDirectory)) {
            throw new InvalidArgumentException(message: 'Log directory does not exist: ' . $logDirectory);
        }
        $this->logDirectory = rtrim(string: $logDirectory, characters: DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->requestLogFormatter = new RequestLogFormatter();
    }

    #[Override]
    public function logException(Throwable $throwable): void
    {
        $previousException = $throwable->getPrevious();
        $realException = $previousException ?? $throwable;
        $message = $realException::class . ': (' . $realException->getCode() . ') "' . $realException->getMessage()
            . '"' . PHP_EOL;
        $message .= 'thrown in file: ' . $realException->getFile() . ' (Line: ' . $realException->getLine() . ')'
            . FileLogger::DOUBLE_NEW_LINE;
        $hashableContent = $message;
        $message .= $realException->getTraceAsString();

        // Don't use dynamic data ($traceLineArray['args']) from backtrace for hash-able content
        foreach ($realException->getTrace() as $traceLineArray) {
            $hashableContent
                .= (array_key_exists(key: 'file', array: $traceLineArray) ? $traceLineArray['file'] : '')
                . (array_key_exists(key: 'line', array: $traceLineArray) ? $traceLineArray['line'] : '')
                . (array_key_exists(key: 'class', array: $traceLineArray) ? $traceLineArray['class'] : '')
                . (array_key_exists(key: 'type', array: $traceLineArray) ? $traceLineArray['type'] : '')
                . $traceLineArray['function']
                . PHP_EOL;
        }
        $hash = hash(algo: 'sha256', data: $hashableContent);
        $this->deliverMessage(hash: $hash, message: $message);
    }

    #[Override]
    public function logMessage(string $message): void
    {
        $hash = hash(algo: 'sha256', data: $message);
        $this->deliverMessage(hash: $hash, message: $message);
    }

    /**
     * Whether the last logged issue was new (first time or not seen for 24 hours): it was mailed.
     */
    public function lastIssueIsNew(): bool
    {
        return $this->lastIssueIsNew;
    }

    private function deliverMessage(string $hash, string $message): void
    {
        $this->lastIssueIsNew = false;
        $ticketFile = 'ticket_' . $hash . '.txt';
        $ticketFullPath = $this->logDirectory . $ticketFile;
        $isNewIssue = $this->isNewIssue(ticketFullPath: $ticketFullPath);
        $this->writeMessage(message: $message, filenameFullPath: $ticketFullPath);
        if ($isNewIssue) {
            $this->lastIssueIsNew = true;
            $this->mailMessage(fullMessage: 'Ticketfile: ' . $ticketFile . FileLogger::DOUBLE_NEW_LINE . $message);
        }
    }

    private function isNewIssue(string $ticketFullPath): bool
    {
        // File will only be written, if the desired content is unique / not already present (determined by the hash):
        if (!file_exists(filename: $ticketFullPath)) {
            return true;
        }
        $modified = filemtime(filename: $ticketFullPath);
        if ($modified === false) {
            return true;
        }

        return ($modified + FileLogger::SECONDS_UNTIL_ISSUE_IS_NEW_AGAIN) < $this->clock->now()->getTimestamp();
    }

    private function writeMessage(string $message, string $filenameFullPath): void
    {
        $message .= FileLogger::DOUBLE_NEW_LINE . $this->requestLogFormatter->format(httpRequest: $this->httpRequest);

        $this->rotateIfTooLarge(filenameFullPath: $filenameFullPath);
        $now = $this->clock->now();
        // Eight fractional digits, as before: microseconds plus two zeros
        $timestamp = $now->format(format: 'Y-m-d H:i:s') . ',' . $now->format(format: 'u') . '00';
        error_log(
            message: $timestamp . PHP_EOL . $message . PHP_EOL . str_repeat(string: '=', times: 70) . PHP_EOL,
            message_type: 3,
            destination: $filenameFullPath,
        );
    }

    /**
     * Moves a ticket file that reached the maximum size to the next free `<file>.<number>` and starts an empty one.
     *
     * @throws RuntimeException if the file cannot be moved
     */
    private function rotateIfTooLarge(string $filenameFullPath): void
    {
        if ($this->maxLogSize <= 0 || !file_exists(filename: $filenameFullPath)) {
            return;
        }
        $fileSize = filesize(filename: $filenameFullPath);
        if ($fileSize === false || $fileSize < $this->maxLogSize) {
            return;
        }
        $rotatedFilePath = $filenameFullPath . '.' . ($this->findHighestRotationNumber(
            filenameFullPath: $filenameFullPath,
        ) + 1);
        // rename() does not work proper
        if (!copy(from: $filenameFullPath, to: $rotatedFilePath)) {
            throw new RuntimeException(message: 'Cannot copy the log file to ' . $rotatedFilePath);
        }
        if (file_put_contents(filename: $filenameFullPath, data: '') === false) {
            throw new RuntimeException(message: 'Cannot empty the log file ' . $filenameFullPath);
        }
    }

    private function findHighestRotationNumber(string $filenameFullPath): int
    {
        $directory = dirname(path: $filenameFullPath);
        $entries = scandir(directory: $directory);
        if ($entries === false) {
            throw new RuntimeException(message: 'Cannot read the log directory ' . $directory);
        }
        $pattern = '/^' . preg_quote(str: basename(path: $filenameFullPath), delimiter: '/') . '\.(\d+)$/';
        $highestNumber = 0;
        foreach ($entries as $entry) {
            if (
                preg_match(pattern: $pattern, subject: $entry, matches: $matches) === 1
                && array_key_exists(key: 1, array: $matches)
            ) {
                $highestNumber = max($highestNumber, (int) $matches[1]);
            }
        }

        return $highestNumber;
    }

    private function mailMessage(string $fullMessage): void
    {
        if ($this->logEmailRecipient === '') {
            return;
        }
        error_log(
            message: $fullMessage,
            message_type: 1,
            destination: $this->logEmailRecipient,
            additional_headers: implode(separator: PHP_EOL, array: [
                'From: error@' . $this->getMailDomain(),
                'Date: ' . $this->clock->now()->format(format: 'r'),
                'Content-Type: text/plain; charset=UTF-8',
            ]),
        );
    }

    private function getMailDomain(): string
    {
        $serverName = $this->httpRequest->getServerName();

        return $serverName === '' ? $this->httpRequest->getHost() : $serverName;
    }
}
