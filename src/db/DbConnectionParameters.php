<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use Pdo\Mysql;
use SensitiveParameter;

/**
 * What `FrameworkDb` needs to open a connection. Use `forMysql()` for the settings of a project; the constructor is
 * public so that tests can connect to an in-memory SQLite database (`dsn: 'sqlite::memory:'`).
 *
 * `FrameworkDb` sets the attributes it relies on itself (exceptions on errors, native prepared statements, no
 * stringified values) and overrides the same keys of `$options`.
 */
final readonly class DbConnectionParameters
{
    /**
     * @param array<int, bool|int|string> $options PDO attributes (driver specific ones included)
     */
    public function __construct(
        public string $dsn,
        public ?string $userName = null,
        #[SensitiveParameter]
        public ?string $password = null,
        public array $options = [],
    ) {}

    public static function forMysql(DbSettings $dbSettings): DbConnectionParameters
    {
        $dsn = implode(
            separator: ';',
            array: [
                'host=' . $dbSettings->hostName,
                'dbname=' . $dbSettings->databaseName,
                'charset=' . $dbSettings->charset,
            ],
        );
        $options = [];
        $initCommand = DbConnectionParameters::createMysqlInitCommand(dbSettings: $dbSettings);
        if ($initCommand !== null) {
            $options[Mysql::ATTR_INIT_COMMAND] = $initCommand;
        }

        return new DbConnectionParameters(
            dsn: 'mysql:' . $dsn,
            userName: $dbSettings->userName,
            password: $dbSettings->password,
            options: $options,
        );
    }

    /**
     * The "SET" statement that is run right after connecting; null if there is nothing to set. The values were
     * validated by `DbSettings`.
     */
    private static function createMysqlInitCommand(DbSettings $dbSettings): ?string
    {
        $assignments = [];
        if ($dbSettings->timeNamesLanguage !== null) {
            $assignments[] = "lc_time_names='" . $dbSettings->timeNamesLanguage . "'";
        }
        if ($dbSettings->sqlSafeUpdates) {
            // see: https://dev.mysql.com/doc/refman/8.0/en/mysql-tips.html
            $assignments[] = 'sql_safe_updates=1';
        }
        if ($assignments === []) {
            return null;
        }

        return 'SET ' . implode(separator: ', ', array: $assignments);
    }
}
