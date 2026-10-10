<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
use Override;
use RuntimeException;

/**
 * Keeps the sessions in files below the save path of `SessionSettings`, else the default save path of the project (the
 * directory is created with mode 0700, without a path PHP's own setting applies).
 */
final class FileSessionHandler extends AbstractSessionHandler
{
    public function __construct(
        HttpRequest $httpRequest,
        private readonly SessionSettings $sessionSettings,
        private readonly string $defaultSavePath,
        Clock $clock = new SystemClock(),
    ) {
        parent::__construct(httpRequest: $httpRequest, sessionSettings: $sessionSettings, clock: $clock);
    }

    /**
     * @throws RuntimeException if the save path cannot be created
     */
    #[Override]
    protected function executePreStartActions(): void
    {
        $savePath = $this->sessionSettings->savePath ?? $this->defaultSavePath;
        if ($savePath === '') {
            return;
        }
        $directory = FileSessionHandler::splitSavePath(savePath: $savePath)['directory'];
        if ($directory !== '' && !is_dir(filename: $directory)) {
            // Another request may create the directory in the meantime: only the final state counts
            set_error_handler(callback: static fn(): bool => true);
            try {
                mkdir(directory: $directory, permissions: 0o700, recursive: true);
            } finally {
                restore_error_handler();
            }
            if (!is_dir(filename: $directory)) {
                throw new RuntimeException(message: 'Cannot create the session directory "' . $directory . '".');
            }
        }
        // The whole value, PHP reads the level prefix itself
        session_save_path(path: $savePath);
    }

    #[Override]
    protected function sessionExists(string $id): bool
    {
        $currentSavePath = session_save_path();
        $savePath = FileSessionHandler::splitSavePath(savePath: $currentSavePath === false ? '' : $currentSavePath);
        $directory = $savePath['directory'] === '' ? sys_get_temp_dir() : rtrim(
            string: $savePath['directory'],
            characters: '/\\',
        );
        // PHP stores the session below one subdirectory per character of the ID, as many as the level says
        foreach (str_split(string: substr(string: $id, offset: 0, length: $savePath['levels'])) as $character) {
            $directory .= DIRECTORY_SEPARATOR . $character;
        }

        return is_file(filename: $directory . DIRECTORY_SEPARATOR . 'sess_' . $id);
    }

    /**
     * Splits the save path of PHP, "[N;[MODE;]]directory" (N levels of subdirectories, the mode of the files), into the
     * number of levels and the directory.
     *
     * @return array{levels: int, directory: string}
     */
    private static function splitSavePath(string $savePath): array
    {
        $parts = explode(separator: ';', string: $savePath);
        $directory = array_pop(array: $parts);
        $levels = 0;
        if (count(value: $parts) > 0 && preg_match(pattern: '/^\d{1,2}$/D', subject: $parts[0]) === 1) {
            $levels = (int) $parts[0];
        }

        return ['levels' => $levels, 'directory' => $directory];
    }
}
