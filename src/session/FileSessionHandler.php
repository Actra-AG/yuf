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
        if (!is_dir(filename: $savePath)) {
            // Another request may create the directory in the meantime: only the final state counts
            set_error_handler(callback: static fn(): bool => true);
            try {
                mkdir(directory: $savePath, permissions: 0o700, recursive: true);
            } finally {
                restore_error_handler();
            }
            if (!is_dir(filename: $savePath)) {
                throw new RuntimeException(message: 'Cannot create the session directory "' . $savePath . '".');
            }
        }
        session_save_path(path: $savePath);
    }

    #[Override]
    protected function sessionExists(string $id): bool
    {
        // The save path may have the form "N;[MODE;]path", the last part is the directory
        $currentSavePath = session_save_path();
        $savePathParts = explode(separator: ';', string: $currentSavePath === false ? '' : $currentSavePath);
        $savePath = array_last(array: $savePathParts);

        return is_file(
            filename: ($savePath === '' ? sys_get_temp_dir() : rtrim(string: $savePath, characters: '/\\'))
            . DIRECTORY_SEPARATOR . 'sess_' . $id,
        );
    }
}
