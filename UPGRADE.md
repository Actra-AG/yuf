# Upgrade

Changes of yuf, newest first. ⚠️ marks breaking changes: read them before `composer update`. Older versions:
[v7](docs/upgrade/v7.md), [v6](docs/upgrade/v6.md), [v5](docs/upgrade/v5.md), [v4](docs/upgrade/v4.md),
[v3](docs/upgrade/v3.md), [v2](docs/upgrade/v2.md), [v1](docs/upgrade/v1.md), [v0](docs/upgrade/v0.md).

## v8.19.0 (2026-11-09)

- ⚠️ New case `AuthResultEnum::ERROR_WRONG_CODE` (16), text `AuthResultMessages::$errorWrongCode`: add it to
  exhaustive `match` expressions on the enum. A log that stores the enum as int needs no schema change.
- New protected `Authenticator::consumeCodeAttempt(userName:, userId:)` and `rejectWrongCode(userName:, userId:)` for
  the one-time code of a second login step: count every code attempt per client IP address before the check, log
  blocked and wrong ones. See [session-and-login.md](docs/session-and-login.md#rate-limit).
- New argument `LoginRateLimit(codeLimits:)` (default 30 per hour per IP) and method `consumeCode(ipAddress:)`. Code
  attempts are limited only where the project calls `consumeCodeAttempt()`.

---

## v8.18.0 (2026-11-08)

- ⚠️ `TemplateCache` has the new method `remove(templateFile:)`: own implementations of the interface add it (remove
  the compiled code of the template, if any). `DirectoryTemplateCache::remove()` deletes the compiled file and
  invalidates it in the OPcache.
- New `TemplateEngine::removeCompiled(templateFile:)`: call it after an application changed or deleted a template file
  at runtime with `checkTemplateChanges` off, instead of deleting `DirectoryTemplateCache::getCompiledFile()` yourself;
  the same engine compiles the template again, too.

---

## v8.17.1 (2026-11-08)

No migration needed. Documentation only.

## v8.17.0 (2026-11-08)

No migration needed.

- New `HtmlSanitizer::isAllowedUrl(url:)`: checks a single URL with the rule of the URL attributes of `sanitize()`
  (http, https, mailto, tel or no scheme), e.g. for link targets entered in a form.
- `NavigationItem` checks `href` with the same rule: it now also rejects a scheme with spaces (`java script:`) and a
  colon before the first `/`, `?` or `#` (`1x:a`).

---

## v8.16.1 (2026-11-08)

No migration needed; no changes visible to consumers. Development tooling only: `actra/coding-standard` v2.4.0, quiet
output of the Composer scripts.

---

## v8.16.0 (2026-11-08)

### ⚠️ The `Clock` is a required argument

The `Clock $clock = new SystemClock()` defaults are gone: pass `clock: new SystemClock()` in production and a
`FixedClock` in tests. `App` and `Core` (`withFileSessions()`, `withFileLogger()`, `createFileLogger()`,
`createFileSessionHandler()`) are unchanged: they use the `SystemClock` unless you pass another one.

Now required: `clock:` of `FileCache`, `FileLogger`, `LogFile`, `RateLimiter`, `CachedKeySet`, `LoginHandOver`,
`SharedLogin`, `MicrosoftIdToken`, `IdTokenTimeClaimsValidator`, `SessionFileUploadStorage`, `FileSessionHandler` and
`AbstractSessionHandler` (also in your subclasses), `AbstractMailer` (also in your subclasses), `SmtpMailer`,
`GraphMailer`, `MailMailer`, `RecordingMailer`, `MicrosoftClientCredentialsTokenProvider`, `DbQueryLogItem`,
`DbQueryLogList`, `ViewTestClient`, `HttpResponse::createResponseFromFilePath()`.

New required arguments, because these classes create a clock-dependent object themselves:

- `FrameworkDb`: `queryLog: new DbQueryLogList(clock: …)` (was optional).
- `MicrosoftAuthenticator::__construct()` (your subclass passes it on): `clock:`.
- `CsvFile::pushDownloadAndExit()`, `FileHandler::output()`, `DbResultTable::exportCsv()`: `clock:`.

In constructors with optional arguments, `clock` moved before the first optional one (`SmtpMailer`, `GraphMailer`,
`MailMailer`, `MicrosoftClientCredentialsTokenProvider`, `ViewTestClient`); with named arguments, which yuf requires,
only the order of the arguments in your call changes.

```php
// Before
$rateLimiter = new RateLimiter(directory: $directory, secret: $secret);
$db = new FrameworkDb(connectionParameters: $parameters);

// After
$rateLimiter = new RateLimiter(directory: $directory, secret: $secret, clock: new SystemClock());
$db = new FrameworkDb(connectionParameters: $parameters, queryLog: new DbQueryLogList(clock: new SystemClock()));
```

---

## v8.15.0 (2026-11-08)

### ⚠️ Shared login: check at every request that needs a login

A site no longer remembers a check at the login host for 30 minutes: a login on another site after a check without
login was not found. Every GET of the login page or of a page that needs a login checks again (two invisible
redirects); only the login page that returns from a check (`loginChecked=1`) makes no hop, so there is no loop.

- `SharedLogin::isChecked()` reads only the URL parameter; no code change needed for its callers.
- Removed: `SharedLogin::CHECKED_COOKIE_NAME` and `CHECKED_LIFETIME_IN_SECONDS`; the cookie `__Host-yuf-login-checked`
  is no longer set (an old one is ignored and expires).
- Call the check only on GET (not on the POST of the login form and its redisplay with errors), as documented.

## v8.14.1 (2026-11-08)

No migration needed; no changes visible to consumers. v8.13.0 and v8.14.0 are withdrawn: v8.14.1 contains their
changes (below), update to it.

## v8.14.0 (2026-11-08)

No migration needed. New, optional: `FormMessages::french()`, `italian()`, `TableMessages::french()`, `italian()` and
`AuthResultMessages::french()`, `italian()` (formal address, Swiss usage). Projects that pass own French or Italian
texts may replace them.

## v8.13.0 (2026-11-08)

### Shared login: the visitor stays on the site

New `SharedLogin` ([docs/session-and-login.md](docs/session-and-login.md#shared-login)): sites on one server share the
login of a login host, but the login runs on the site's own login page; only invisible redirects pass the hand-over
page of the login host. Same directory as `LoginHandOver`, which is unchanged.

- Check (site without login, once per browser session): `createCheckUrl()`, `isChecked()`; the login host answers with
  `receiveOnLoginHost()` (its login or "none"), the site's hand-over page logs in with `receiveOnSite()` or goes to its
  login page.
- Push (after the login on the site): `createPushUrl()`, then `receiveOnLoginHost()` / `receiveOnSite()` until the
  login host logs the same user in and returns to the requested page.
- Logout on any host: `end()`; every host checks `isEnded()` at each request with a login.
- New: `SharedLoginSite` (login page, hand-over page, URL of the login host), `SharedLoginUser` (subject and shared
  login ID, `SharedLogin::createSharedLoginId()`), `SharedLoginStep` (redirect, user to log in, failure),
  `LoginTarget::shared()` (a page that needs a login checks at the login host first,
  [docs/views.md](docs/views.md#login-page-of-the-site-shared-login-of-a-login-host)).

### Test helpers for views

- New `actra\yuf\testing\ViewTestClient` and `ViewTestResult` ([docs/testing.md](docs/testing.md#views)): run the
  views of an HTML route with one session across requests (multi-step forms, CSRF tokens), the real view pipeline
  (`execute()` with `HtmlDocument`, rendered page) and the work after the response. Replaces hand-built `ViewContext`s
  and test-only steps in views.
- `RecordingResponseSender::capture(runAfterResponseCallbacks: true)` runs the work registered with `afterResponse()`.

### Upgrade notes

- The notes of v5 to v7 moved to [docs/upgrade/](docs/upgrade/v7.md).

## v8.12.0 (2026-11-08)

### ⚠️ Login page on another host: server-side hand-over

The login on another host is handed over to the site through a directory the hosts share
([docs/session-and-login.md](docs/session-and-login.md#login-on-another-host)); the absolute `returnTo` URL of v8.11.0
is gone, URLs carry only random IDs.

- `LoginTarget::remote(url:)` requires `loginHandOver:` (`LoginHandOver`) and `handOverPath:`; the redirect sends
  `?loginRequest=<ID>` instead of `?returnTo=<absolute URL>`.
- Removed: `LoginRedirect::findReturnUrl()`, `isAllowedReturnUri()` and `createLoginUri(allowedHosts:)`. The login
  page uses `LoginHandOver::findLoginRequest()` and `createHandOverUrl()`, the site's hand-over page `redeemTicket()`.

Before: `LoginTarget::remote(url: 'https://login.example.net/login.html')`.
After: `LoginTarget::remote(url: 'https://login.example.net/login.html', loginHandOver: new LoginHandOver(directory:
'/var/www/shared/login-hand-over'), handOverPath: '/login-ticket.html')`.

## v8.11.0 (2026-11-08)

### ⚠️ Typed login target

The login page is a `LoginTarget` instead of a string; behaviour of a local login page is unchanged
([docs/views.md](docs/views.md#required-access-rights-and-the-login-page)).

- `App::withLoginPath(loginPath:)` → `App::withLoginTarget(loginTarget:)`.
- `RouteCollection(loginPath:)` → `RouteCollection(loginTarget:)`, `RouteCollection::$loginPath` and
  `RequestHandler::$loginPath` → `$loginTarget` (`?LoginTarget`, the path is `$loginTarget->uri`).
- An invalid path throws in `LoginTarget::local()` (same message as before).

Before: `$app->withLoginPath(loginPath: '/login.html')`.
After: `$app->withLoginTarget(loginTarget: LoginTarget::local(path: '/login.html'))`.

### Login page on another host

- New `LoginTarget::remote(url: 'https://login.example.net/login.html')`: visitors without login are redirected there
  with the absolute URL of the request as `returnTo` ([docs/views.md](docs/views.md#login-page-on-another-host)).
- New `LoginRedirect::findReturnUrl(httpRequest:, allowedHosts:)` and `isAllowedReturnUri(uri:, allowedHosts:)` for
  the login page: a local path or an `https` URL of an explicitly allowed host, else `null`. `findReturnPath()` is
  unchanged. `LoginRedirect::createLoginUri()` keeps such a URL with the new `allowedHosts:` (default `[]`: local
  paths only, as before). New `LoginRedirect::findHttpsHost()`.
- The session is not shared between hosts; see the limits in
  [docs/session-and-login.md](docs/session-and-login.md#login-on-another-host).

## v8.10.0 (2026-11-08)

- `ReceivedMail` (`MimeMessageParser`) has the HTML and the attachments of a message
  ([docs/mailbox.md](docs/mailbox.md#received-mail)): `htmlBody` (first inline `text/html` part, `null` without one;
  untrusted HTML, never output it unfiltered) and `attachments` (`list<ReceivedAttachment>`: `fileName`, decoded RFC
  2231/2047, `contentType`, `isInline`, `contentId`, `content`, `getSize()`). `textBody` is unchanged.
- `AbstractMail::isHtmlBody()`, `getAlternativeBody()` and `listAttachments()` for tests with `RecordingMailer`
  ([docs/mail.md](docs/mail.md#tests)).

## v8.9.0 (2026-11-06)

- `ZipCodeValidator` (and `ZipCodeField`) checks Liechtenstein (`LI`): only 9485–9498 are valid. Before, every LI zip
  code was accepted. Checked countries: CH, LI, DE, AT; others accept every zip code.

## v8.8.0 (2026-11-05)

### ⚠️ Links in the text version of HTML-only mails

`ReceivedMail::$textBody` of HTML-only messages keeps link targets as `text <url>` (absolute http/https links with
text). Code that matches or compares this text (alert patterns, fingerprints, stored texts) may see the added URLs.
Before: `Vulnerabilities were found on a site.` After:
`Vulnerabilities were found on a site <https://host.example.com:8443/…/id/23>.`

## v8.7.2 (2026-10-31)

- Fix: the error page of a path without route (`/unknown/page.html` next to a route at `/`, or a path with `//`) has
  the language of the request if the default route has `usesRequestLanguage`, like the pages of that route; it had the
  first language. No change for projects.

## v8.7.1 (2026-10-31)

- Development only: `actra/coding-standard` ^2.0 (named arguments in the order of the parameters); no change for
  projects.

## v8.7.0 (2026-10-31)

- New `SentMailListener` for a log of sent mails ([docs/mail.md](docs/mail.md#log-of-sent-mails)):
  `AbstractMailer::addSentMailListener()` attaches it to any mailer, `AbstractMail::send()` calls it with a `SentMail`
  (time, from, To/Cc/Bcc addresses, subject) after a successful delivery, never after a failed one. An exception of the
  listener propagates, but the mail counts as sent. Without a listener nothing changes.

## v8.6.0 (2026-10-31)

- New routes with the language of the request: one path for all languages (e.g. a backend) instead of one path per
  language ([docs/setup.md](docs/setup.md#languages)). `usesRequestLanguage: true` on `withHtmlRoute()`,
  `withFileRoute()`, `withJsonRoute()` and `Route`; the language comes from the `RequestLanguageProvider` of
  `App::withRequestLanguageProvider()` (or `Core::prepareHttpResponse(requestLanguageProvider:)`, e.g. the profile
  language), else the session, the browser, the first language (`RequestLanguageResolver`). It drives the language
  files, `{tst:lang}`, `LocaleHandler`, the system locale, `Content-Language` and the error pages, like a fixed
  language. Routes with a fixed language are unchanged.
- New `App::withLanguageSwitchRoute(path:)` and `LanguageSwitch`: a link or form sets the language of the session and
  redirects back.
- New `RequestHandler::findDefaultRouteForLanguage()` and `$defaultRequestLanguageRoute`; `Core::$responseSender` is
  public (read-only).
- `BaseView::respondNotModifiedIfUnchanged()` adds the language to the version on routes with `usesRequestLanguage`.

## v8.5.0 (2026-10-31)

- New `App` shortcuts for a shorter front controller ([docs/setup.md](docs/setup.md#application-entry-point)):
  - `withJsonRoute(path:, viewGroup:)`: JSON at paths without extension (REST API, webhooks, cron calls), instead of an
    own `Route` with `acceptedExtension: ''` in `withRoute()`.
  - `withFileSessions(sessionSettings:)`: the default `FileSessionHandler` with own settings, instead of
    `withSessionHandler(sessionHandler: new FileSessionHandler(…))`.
  - `withFileLogger(mailer:, mailSenderAddress:, clock:)`: the default `FileLogger` with a mailer or a clock, instead
    of `withLogger(logger: new FileLogger(…))`; `getLogger()` returns the logger for the project's own classes.
- New `Core::createFileLogger()` and `Core::createFileSessionHandler()`: the defaults of `prepareHttpResponse()`
  (same behaviour), with own mailer, clock or session settings.

## v8.4.0 (2026-10-29)

- New `actra\yuf\mailbox`: reads and moves the messages of one IMAP mailbox without `ext-imap` (not bundled with PHP
  8.5), e.g. for cron jobs that process notification mails ([docs/mailbox.md](docs/mailbox.md)). `StreamImapMailbox`
  (interface `ImapMailbox`) with `ImapSettings` and `ImapEncryptionEnum` (implicit TLS on 993 or STARTTLS on 143,
  certificate verified, never unencrypted); `fetchMessage()` returns a `ReceivedMail` (message ID, date, sender,
  decoded subject, headers, text body, raw message), also for HTML-only mails; errors are `ImapException`s.
- New `MimeMessageParser`: a raw message (e.g. a stored `.eml`) as `ReceivedMail`.
- New `RecordingImapMailbox` for tests of projects ([docs/testing.md](docs/testing.md)).

## v8.3.0 (2026-10-29)

- New `RecordingResponseSender` and `ResponseSentException` (`actra\yuf\core`) for tests of projects: records
  redirects, downloads and other responses that end the script ([docs/testing.md](docs/testing.md)).
- New migration guide for projects on the old [Actra framework](https://github.com/Actra-AG/framework):
  [docs/upgrade/from-framework.md](docs/upgrade/from-framework.md).

## v8.2.0 (2026-10-29)

- New `App`: the front controller with one line per language and route, everything else from `.env.php`
  ([docs/setup.md](docs/setup.md#application-entry-point)). `Core` stays unchanged; switching is optional:

  ```php
  App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
      ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
      ->withHtmlRoute(path: '/', viewGroup: 'frontend', defaultFileTitle: 'home')
      ->withFileRoute(path: '/export/', viewGroup: 'export', contentType: ContentType::createCsv())
      ->run();
  ```

  `withHtmlRoute()` accepts `.html` only (or only paths without extension with
  `fileExtension: HtmlFileExtensionEnum::NONE`) and redirects the default file name to the path (`/home.html` → `/`).
  Setup mistakes (a language after a route, a route of a language that was not added) throw at startup.
- New `Route` argument `redirectDefaultFileName:` (default `false`, same behaviour): `true` redirects a request of the
  default file name permanently (301) to the path of the route ([docs/views.md](docs/views.md#default-file-name)).
- A missing environment file throws an `UnexpectedValueException` with its path and a pointer to `.env.example.php`
  (before: PHP warning and fatal error of `require_once`).
- Deployment: delete `app/cache/v[0-9]*/` instead of `app/cache/v*/` (`clearDirectories` of `deploy.json`):
  `v*` also deletes `app/cache/values/` (`FileCache`, e.g. access tokens) ([docs/setup.md](docs/setup.md#production)).
- `actra/coding-standard` ^1.34.0, `phpunit/phpunit` ^13.4.4 (security fix of PHPUnit; development only).

## v8.1.0 (2026-10-24)

### ⚠️ `CspPolicySettings`: default `default-src 'self'`

- The default of `defaultSrc:` is `'self'` instead of `'self' data: {PROTOCOL}://{HOST}` (security checks such as
  internet.nl flag `data:` in `default-src`). Directives without own value fall back to it, e.g. `media-src`,
  `manifest-src`, `worker-src`: `data:` URLs there are blocked now. `img-src` keeps `data:`.
- An application that needs `data:` for such a directive sets it explicitly, e.g. `mediaSrc: "'self' data:"`.
- `actra/coding-standard` ^1.33.0 (development only).

## v8.0.0

### ⚠️ `SpamProtection`: required `captcha:`

- Optional CAPTCHA for public forms, verified after honeypot, render time and rate limit: `FormCaptcha` with
  `TurnstileCaptcha` (Cloudflare Turnstile), `CaptchaFailModeEnum`, new texts `FormMessages::$captchaFailed` and
  `$captchaUnavailable` (en/de); setup, CSP and privacy policy in [docs/captcha.md](docs/captcha.md).
- Before: `new SpamProtection(clock: …, secret: …, rateLimit: …, emailField: …, rejectedEmailAddresses: …)`, after: add
  `captcha: null` (no CAPTCHA, same output) or `captcha: new FormCaptcha(…)`.
- A `SpamProtection` whose `honeypotName:`, `renderTimeName:` and CAPTCHA field name are not all different throws an
  `InvalidArgumentException`.
