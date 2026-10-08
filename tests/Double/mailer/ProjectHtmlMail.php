<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\AbstractMail;

/**
 * A mail of a project: extends `AbstractMail` (the extension point) and sets the body.
 */
final class ProjectHtmlMail extends AbstractMail
{
    public function __construct(string $toEmail, string $htmlBody, string $alternativeBody)
    {
        parent::__construct(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From Name',
            toEmail: $toEmail,
            toName: 'To Name',
            subject: 'Subject',
        );
        $this->setHtmlBody(htmlBody: $htmlBody, alternativeBody: $alternativeBody);
    }
}
