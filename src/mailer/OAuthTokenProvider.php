<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Supplies the OAuth 2.0 access token for `SmtpAuthMethodEnum::XOAUTH2`. The implementation caches the token and
 * renews it when it has expired; `SmtpMailer` asks for it once per delivery, after the connection is set up.
 */
interface OAuthTokenProvider
{
    /**
     * @return string a valid access token (it is a credential: never log it or put it into a message)
     *
     * @throws MailerException if no token can be obtained
     */
    public function getAccessToken(): string;
}
