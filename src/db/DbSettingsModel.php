<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use LogicException;

class DbSettingsModel
{
    private static array $instances = [];
    public readonly string $charset;

    public function __construct(
        public readonly string $identifier,
        public readonly string $hostName,
        public readonly string $databaseName,
        public readonly string $userName,
        public readonly string $password,
        ?string $charset = null,
        public readonly ?string $timeNamesLanguage = 'de_CH',
        public readonly bool $sqlSafeUpdates = true
    ) {
        if (array_key_exists(
            key: $identifier,
            array: DbSettingsModel::$instances
        )) {
            throw new LogicException(message: 'There is already an instance with the identifier ' . $identifier);
        }
        DbSettingsModel::$instances[$identifier] = $this;
        $charset = trim(string: (string)$charset);
        if ($charset === '') {
            $charset = 'utf8mb4';
        }
        if (mb_strtolower(string: $charset) === 'utf-8') {
            throw new LogicException(message: 'Faulty charset setting string "utf-8". Must be "utf8" for PDO driver.');
        }
        $this->charset = $charset;
    }
}