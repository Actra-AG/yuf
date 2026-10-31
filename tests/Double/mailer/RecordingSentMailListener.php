<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\SentMail;
use actra\yuf\mailer\SentMailListener;
use Override;

/**
 * Records every `SentMail` it is told about.
 */
final class RecordingSentMailListener implements SentMailListener
{
    /** @var list<SentMail> */
    public private(set) array $sentMails = [];

    #[Override]
    public function onMailSent(SentMail $sentMail): void
    {
        $this->sentMails[] = $sentMail;
    }
}
