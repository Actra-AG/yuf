<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\api\CurlClient;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpStatusCodeEnum;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * Sends through the Microsoft Graph API (`POST /users/{mailbox}/sendMail` with the MIME message that yuf builds, so
 * HTML, attachments and headers work as with `SmtpMailer`). For Microsoft 365, which ends basic authentication for
 * SMTP.
 *
 * Needs an app registration with the application permission `Mail.Send` (admin consent; restrict it to the mailboxes
 * the app needs with an application access policy in Exchange Online) and an `OAuthTokenProvider` that returns a token
 * for the Graph scope, e.g. `MicrosoftClientCredentialsTokenProvider`. The address of the `From` header of the mail
 * must be the mailbox or an address the mailbox may send as; the envelope sender (`$senderEmail` of the mail) does
 * not exist in Graph and is not used. The recipients come from the `To`, `Cc` and `Bcc` headers; Exchange removes
 * the `Bcc` header from the delivered message.
 *
 * The whole request (the message as Base64) is limited to 4 MB by Graph: a larger message is not sent. Larger
 * attachments need upload sessions, which this mailer does not support.
 *
 * Graph answers `202 Accepted` before the delivery: a later bounce is not reported here.
 */
final class GraphMailer extends AbstractMailer
{
    public const string DEFAULT_BASE_URL = 'https://graph.microsoft.com/v1.0';
    // The documented limit of the request ("less than 4 MB"), the Base64 text of the message counts
    private const int MAX_REQUEST_SIZE_IN_BYTES = 4194304;
    private const int CONNECT_TIMEOUT_IN_SECONDS = 3;
    private const int REQUEST_TIMEOUT_IN_SECONDS = 60;
    private const int MAX_RESPONSE_SIZE_IN_BYTES = 1048576;

    /**
     * @param string $senderMailbox ID or user principal name of the mailbox that sends (`noreply@example.com`)
     *
     * @throws InvalidArgumentException If the mailbox is empty
     */
    public function __construct(
        string $serverAddress,
        private readonly string $senderMailbox,
        private readonly OAuthTokenProvider $oAuthTokenProvider,
        private readonly string $graphBaseUrl = GraphMailer::DEFAULT_BASE_URL,
        private readonly CurlClient $curlClient = new CurlClient(),
        Clock $clock = new SystemClock(),
        MimeIdGenerator $mimeIdGenerator = new RandomMimeIdGenerator(),
        ServerNameResolver $serverNameResolver = new ReverseDnsServerNameResolver(),
    ) {
        if ($senderMailbox === '') {
            throw new InvalidArgumentException(message: 'The sender mailbox must not be empty.');
        }
        parent::__construct(
            serverAddress: $serverAddress,
            clock: $clock,
            mimeIdGenerator: $mimeIdGenerator,
            serverNameResolver: $serverNameResolver,
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return true;
    }

    /**
     * Graph reads the blind copy recipients of a MIME message from its `Bcc` header (there is no envelope).
     */
    #[Override]
    public function headerHasBcc(): bool
    {
        return true;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return MailerConstants::MAX_LINE_LENGTH;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $payload = base64_encode(
            string: $mailMimeHeader->getMimeHeader() . MailerConstants::CRLF . MailerConstants::CRLF
                . $mailMimeBody->getMimeBody(),
        );
        if (strlen(string: $payload) > GraphMailer::MAX_REQUEST_SIZE_IN_BYTES) {
            throw new MailerException(
                message: 'The message is too large for the Microsoft Graph API: the request with the Base64 encoded'
                    . ' message is ' . strlen(string: $payload) . ' bytes, the limit is '
                    . GraphMailer::MAX_REQUEST_SIZE_IN_BYTES . ' bytes (4 MB). Larger attachments are not supported'
                    . ' by this mailer.',
            );
        }
        $accessToken = $this->oAuthTokenProvider->getAccessToken();
        $request = CurlPostRequest::createWithPlainTextBody(
            requestTargetUrl: rtrim(string: $this->graphBaseUrl, characters: '/') . '/users/'
                . rawurlencode(string: $this->senderMailbox) . '/sendMail',
            plainText: $payload,
        );
        try {
            $request->useTokenAuthentication(token: $accessToken);
        } catch (InvalidArgumentException|LogicException) {
            throw new MailerException(
                message: 'The access token cannot be sent: it must be visible ASCII, and the Graph URL must use'
                    . ' HTTPS.',
            );
        }
        $request->setTimeoutInSeconds(
            connectTimeOut: GraphMailer::CONNECT_TIMEOUT_IN_SECONDS,
            requestTimeOut: GraphMailer::REQUEST_TIMEOUT_IN_SECONDS,
        );
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: GraphMailer::MAX_RESPONSE_SIZE_IN_BYTES);
        $response = $this->curlClient->send(request: $request);
        if ($response->rawResponseBody === false) {
            throw new MailerException(
                message: 'The request to the Microsoft Graph API failed: ' . $response->errorMessage,
            );
        }
        if ($response->hasErrors() || $response->responseHttpCode !== HttpStatusCodeEnum::HTTP_ACCEPTED) {
            $details = MailerHttpErrorReader::describeGraphError(response: $response);
            throw new MailerException(
                message: 'The Microsoft Graph API did not accept the message: HTTP status '
                    . $response->curlInfo['http_code'] . ($details === '' ? '.' : ': ' . $details),
            );
        }
    }
}
