# Sending mail

Passwords, client secrets and tokens are never written to `$mailer->log` (`AUTH ... (hidden)`) or exception messages.

## SMTP

`SmtpMailer` sends through an SMTP server. `encryption:` (`SmtpEncryptionEnum`) sets how the connection is encrypted:

- `STARTTLS` (default, port 587): the connection is encrypted with STARTTLS before the credentials are sent. A server
  without STARTTLS, data that the server sent before the handshake, or a failed handshake aborts the delivery.
- `IMPLICIT_TLS` (port 465): the connection is encrypted from the first byte (SMTPS), no STARTTLS.
- `NONE` (port 25): no encryption. With a user name this is only accepted for a server on the same host (`localhost`,
  `127.0.0.0/8`, `::1`): for any other host the constructor throws an `InvalidArgumentException`. Without a user
  name no credentials are sent.

`port:` defaults to the port of the encryption. The certificate chain and the host name are always verified, TLS 1.2
or newer is required, and there is no fallback to plain text. With a user name the mailer
authenticates with the method that the server announces in its `AUTH` line:

- `PLAIN`, otherwise `LOGIN` (user name and `smtpPassword:`); a server that announces no method gets `LOGIN`.
- `XOAUTH2` (Microsoft 365, Gmail) with an `OAuthTokenProvider`: its `getAccessToken()` returns the OAuth 2.0 access
  token, `smtpUserName:` is the mailbox, `smtpPassword:` is not used (pass `''`). There is no `CRAM-MD5`.
- `authMethod:` (`SmtpAuthMethodEnum::LOGIN`, `PLAIN`, `XOAUTH2`) fixes the method; the delivery aborts with a
  `MailerException` if the server does not announce it.

```php
$mailer = new SmtpMailer(
    serverAddress: '192.0.2.1',
    hostName: 'smtp.office365.com',
    smtpUserName: 'noreply@example.com',
    smtpPassword: '',
    serverNameCache: $core->fileCache,
    oAuthTokenProvider: $tokenProvider, // your implementation of OAuthTokenProvider
);
```

### Configuration in .env.php

Read the settings of the mailer from the environment file ([setup.md](setup.md#environment-settings)), the encryption
with `getEnum()`: `'implicit_tls'` for port 465, `'none'` only for a server on the same host. Without `mailer.port`
the default port of the encryption applies.

```php
// .env.php
'mailer.hostname' => 'smtp.example.com',
'mailer.port' => 587, // optional
'mailer.username' => 'mailer@example.com',
'mailer.password' => '…',
'mailer.encryption' => 'starttls', // 'implicit_tls', 'none'
```

```php
$environmentSettings = $core->environmentSettings;
$mailer = new SmtpMailer(
    serverAddress: $core->httpRequest->getServerAddress(),
    hostName: $environmentSettings->getString(key: 'mailer.hostname'),
    smtpUserName: $environmentSettings->getString(key: 'mailer.username'),
    smtpPassword: $environmentSettings->getString(key: 'mailer.password'),
    serverNameCache: $core->fileCache,
    encryption: $environmentSettings->getEnum(key: 'mailer.encryption', enumClass: SmtpEncryptionEnum::class),
    port: $environmentSettings->has(key: 'mailer.port') ? $environmentSettings->getInt(key: 'mailer.port') : null,
);
```

## Addresses

`MailerAddress` (all recipients, sender, `From`, `Reply-To`) and `ValidatedEmailAddress` (the form's email field)
reject control characters (below 0x20 and 0x7F, e.g. NUL, tab, vertical tab, line breaks) and quoted local parts
(`"a b"@example.com`). `MailerAddress` trims spaces, tabs and line breaks around the address; the domain is converted
to punycode. `ValidatedEmailAddress` also removes spaces, tabs, line breaks and zero-width spaces inside a pasted
address, but rejects every other control character (`EmailAddressErrorEnum::CONTROL_CHARACTERS`).

## Microsoft 365 (Graph API)

Microsoft ends basic authentication for SMTP. `GraphMailer` sends the MIME message of yuf through
`POST /users/{mailbox}/sendMail` (HTML, attachments and headers work as with `SmtpMailer`).
`MicrosoftClientCredentialsTokenProvider` gets the access token with the OAuth 2.0 client credentials flow and keeps
it until one minute before it expires: use one instance for all mails.

Register an app in Microsoft Entra ID with a client secret and the **application** permission `Mail.Send` for Microsoft
Graph (admin consent). This allows the app to send as every mailbox of the tenant: restrict it with an application
access policy in Exchange Online. The `From` address must be the mailbox of the mailer or an address it may send as.

```php
$tokenProvider = new MicrosoftClientCredentialsTokenProvider(
    tenantId: $core->environmentSettings->getString(key: 'mailer.tenantId'),
    clientId: $core->environmentSettings->getString(key: 'mailer.clientId'),
    clientSecret: $core->environmentSettings->getString(key: 'mailer.clientSecret'),
    tokenCache: $core->fileCache, // the token serves the next requests too; null requests one per request
);
$mailer = new GraphMailer(
    serverAddress: '192.0.2.1',
    senderMailbox: 'noreply@example.com', // user ID or user principal name
    oAuthTokenProvider: $tokenProvider,
    serverNameCache: $core->fileCache,
);
```

- Graph answers `202 Accepted` before the delivery: a later bounce is not reported. Any other answer throws a
  `MailerException` with the HTTP status and the `error.code` / `error.message` of Graph.
- Graph limits the request to 4 MB, so attachments of about 2 MB are the maximum (Base64 twice). A larger message
  throws a `MailerException` before anything is sent; upload sessions are not supported.
- `Bcc` recipients are read from the `Bcc` header (Exchange removes it from the delivered mail).

### SMTP with XOAUTH2

The same token provider serves `SmtpMailer` with `scope: 'https://outlook.office365.com/.default'`. The app needs the
permission `SMTP.SendAsApp` of Office 365 Exchange Online, and its service principal must be registered in Exchange
Online (`New-ServicePrincipal`) with full access to the mailbox (`Add-MailboxPermission`).

## Tests

`RecordingMailer` records the sent `AbstractMail` objects instead of delivering them (no network, no DNS). Pass it
wherever the code expects an `AbstractMailer`; a `FixedClock` as `clock:` fixes the date of the messages:

```php
$mailer = new RecordingMailer();
$mail->send(abstractMailer: $mailer);
$this->assertCount(1, $mailer->sentMails);
```

The test asserts each mail of `sentMails` with `getSubject()`, `getBody()`, `fromAddress` and
`mailerAddressCollection->list(mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO)` (`getPunyEncodedEmail()`,
`getName()` of each `MailerAddress`).

## Performance

- Send mail after the response where possible (a shutdown function runs after `fastcgi_finish_request()`), or from
  a queue or cron job: the user does not wait for the mail server.
- `MicrosoftClientCredentialsTokenProvider` keeps the token in `tokenCache:` (`$core->fileCache`) for the next
  requests: no request to the identity platform per mail.
- The mailers keep the host name of the server (reverse DNS) in `serverNameCache:` (`$core->fileCache`): one lookup a
  day instead of one per mailer.
- Connections give up after 3 seconds (Graph, token, cURL default); the transfer may take longer.
