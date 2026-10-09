# Sending mail

Passwords, client secrets and tokens are never written to `$mailer->log` (`AUTH ... (hidden)`) or exception messages.

## SMTP

`SmtpMailer` sends through an SMTP server. With `useTls: true` (default) the connection is encrypted with STARTTLS
before the credentials are sent; a server without STARTTLS aborts the delivery. With a user name the mailer
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
    oAuthTokenProvider: $tokenProvider, // your implementation of OAuthTokenProvider
);
```

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
    tokenCache: new FileCache(directory: $core->cacheDirectory . 'values'), // the token serves the next requests too
);
$mailer = new GraphMailer(
    serverAddress: '192.0.2.1',
    senderMailbox: 'noreply@example.com', // user ID or user principal name
    oAuthTokenProvider: $tokenProvider,
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

## Performance

- Send mail after the response where possible (a shutdown function runs after `fastcgi_finish_request()`), or from
  a queue or cron job: the user does not wait for the mail server.
- With a `FileCache`, `MicrosoftClientCredentialsTokenProvider` keeps the token for the next requests (no request to
  the identity platform per mail), and `new ReverseDnsServerNameResolver(cache: …)` (argument `serverNameResolver:` of
  the mailers) looks up the server name once a day instead of once per mailer.
- Connections give up after 3 seconds (Graph, token, cURL default); the transfer may take longer.
