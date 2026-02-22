<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

class FileSessionHandler extends AbstractSessionHandler
{
    public function __construct(private readonly SessionSettingsModel $sessionSettingsModel)
    {
        parent::__construct(sessionSettingsModel: $sessionSettingsModel);
    }

    protected function executePreStartActions(): void
    {
        if ($this->sessionSettingsModel->savePath !== '') {
            session_save_path(path: $this->sessionSettingsModel->savePath);
        }
    }
}