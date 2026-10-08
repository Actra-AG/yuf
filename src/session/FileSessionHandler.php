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

class FileSessionHandler extends AbstractSessionHandler
{
    public function __construct(
        HttpRequest $httpRequest,
        private readonly SessionSettings $sessionSettings,
        private readonly string $defaultSavePath,
        Clock $clock = new SystemClock(),
    ) {
        parent::__construct(httpRequest: $httpRequest, sessionSettings: $sessionSettings, clock: $clock);
    }

    #[Override]
    protected function executePreStartActions(): void
    {
        $savePath = $this->sessionSettings->savePath ?? $this->defaultSavePath;
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
