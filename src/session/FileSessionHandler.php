<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use Override;

class FileSessionHandler extends AbstractSessionHandler
{
    public function __construct(
        private readonly SessionSettings $sessionSettings,
        Clock $clock = new SystemClock(),
    ) {
        parent::__construct(sessionSettings: $sessionSettings, clock: $clock);
    }

    #[Override]
    protected function executePreStartActions(): void
    {
        $savePath = $this->sessionSettings->savePath;
        if ($savePath !== '') {
            if (!is_dir(filename: $savePath)) {
                mkdir(
                    directory: $savePath,
                    recursive: true,
                );
            }
            session_save_path(path: $savePath);
        }
    }
}
