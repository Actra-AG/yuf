<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;

class FileSessionHandler extends AbstractSessionHandler
{
    public function __construct(
        private readonly SessionSettingsModel $sessionSettingsModel,
        Clock $clock = new SystemClock()
    ) {
        parent::__construct(sessionSettingsModel: $sessionSettingsModel, clock: $clock);
    }

    protected function executePreStartActions(): void
    {
        $savePath = $this->sessionSettingsModel->savePath;
        if ($savePath !== '') {
            if (!is_dir(filename: $savePath)) {
                mkdir(
                    directory: $savePath,
                    recursive: true
                );
            }
            session_save_path(path: $savePath);
        }
    }
}