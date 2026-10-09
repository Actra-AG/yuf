<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Settings of a MySQL connection. The values come from the configuration of the project, not from a request: they are
 * validated because they end up in the DSN and in the init command of the connection.
 */
final readonly class DbSettings
{
    private const string CHARSET_PATTERN = '/^[A-Za-z0-9_]+$/D';
    private const string TIME_NAMES_LANGUAGE_PATTERN = '/^[a-z]{2,3}_[A-Z]{2}(@[a-z]+)?$/D';
    private const string DEFAULT_CHARSET = 'utf8mb4';

    public string $charset;

    /**
     * @param ?string $charset null or empty for `utf8mb4`
     * @param ?string $timeNamesLanguage MySQL locale of the day and month names (`lc_time_names`), e.g. `de_CH`;
     *                                   null to keep the default of the server
     * @param bool $sqlSafeUpdates Refuses UPDATE and DELETE statements without a key in the WHERE clause:
     *                             https://dev.mysql.com/doc/refman/8.0/en/mysql-tips.html
     * @param int $connectTimeoutInSeconds Gives up connecting after this time (`PDO::ATTR_TIMEOUT`), so an unreachable
     *                                     database does not block every PHP worker for the default 60 seconds
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $hostName,
        public string $databaseName,
        public string $userName,
        #[SensitiveParameter]
        public string $password,
        ?string $charset = null,
        public ?string $timeNamesLanguage = 'de_CH',
        public bool $sqlSafeUpdates = true,
        public int $connectTimeoutInSeconds = 3,
    ) {
        if ($connectTimeoutInSeconds < 1) {
            throw new InvalidArgumentException(
                message: 'The connect timeout must be at least 1 second, got ' . $connectTimeoutInSeconds . '.',
            );
        }
        DbSettings::assertDsnValue(name: 'hostName', value: $hostName);
        DbSettings::assertDsnValue(name: 'databaseName', value: $databaseName);
        $this->charset = DbSettings::normalizeCharset(charset: $charset);
        if (
            $timeNamesLanguage !== null
            && preg_match(pattern: DbSettings::TIME_NAMES_LANGUAGE_PATTERN, subject: $timeNamesLanguage) !== 1
        ) {
            throw new InvalidArgumentException(
                message: 'The time names language must be a MySQL locale like "de_CH", got "'
                . $timeNamesLanguage . '".',
            );
        }
    }

    private static function normalizeCharset(?string $charset): string
    {
        $charset = trim(string: (string) $charset);
        if ($charset === '') {
            return DbSettings::DEFAULT_CHARSET;
        }
        if (mb_strtolower(string: $charset) === 'utf-8') {
            throw new InvalidArgumentException(
                message: 'Faulty charset setting string "utf-8". Must be "utf8" for PDO driver.',
            );
        }
        if (preg_match(pattern: DbSettings::CHARSET_PATTERN, subject: $charset) !== 1) {
            throw new InvalidArgumentException(
                message: 'The charset may only contain letters, digits and underscores, got "' . $charset . '".',
            );
        }

        return $charset;
    }

    /**
     * Host name and database name are part of the DSN, where ";" starts the next setting.
     */
    private static function assertDsnValue(string $name, string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException(message: 'The ' . $name . ' must not be empty.');
        }
        if (preg_match(pattern: '/[;\x00-\x1F\x7F]/', subject: $value) === 1) {
            throw new InvalidArgumentException(
                message: 'The ' . $name . ' must not contain ";" or control characters.',
            );
        }
    }
}
