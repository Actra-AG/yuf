<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Extension point: is told about every mail that a mailer has sent (e.g. to log it in a table). Attach it with
 * `AbstractMailer::addSentMailListener()`.
 *
 * It is called after the mailer has handed over the message without an exception, never for a failed delivery. The
 * mail is sent at that point: an exception of the listener propagates out of `AbstractMail::send()`, but the mail
 * counts as sent (it cannot be sent again). A listener handles its own expected errors instead of throwing, so that a
 * caller does not send the mail a second time.
 */
interface SentMailListener
{
    public function onMailSent(SentMail $sentMail): void;
}
