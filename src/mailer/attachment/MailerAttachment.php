<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer\attachment;

use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;

/**
 * A file that is sent with a message, as attachment or as inline image (referenced in the HTML as `cid:<fileName>`).
 */
interface MailerAttachment
{
    /**
     * The name the recipient sees: no directories, no control characters.
     */
    public string $fileName { get; }

    /**
     * `type/subtype`, e.g. `application/pdf`.
     */
    public string $type { get; }

    public MailerEncodingEnum $encoding { get; }

    public bool $dispositionInline { get; }

    /**
     * @throws MailerException if the content cannot be read
     */
    public function getContent(): string;
}
