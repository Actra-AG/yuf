# Setup

## Application entry point

Create `.env.php` based on [.env.example.php](../.env.example.php) and an `index.php` in the document root based on
[index.example.php](../index.example.php). Composer loads all classes: the project maps its own namespace in
`composer.json` (`"autoload": {"psr-4": {"app\\": "app/"}}`).

`App` sets up the application with one line per language and route; everything that differs between environments
(dev, stage, prod) is in `.env.php`, languages and routes are the same everywhere:

```php
require __DIR__ . '/../vendor/autoload.php';

App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
    ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
    ->withHtmlRoute(path: '/', viewGroup: 'frontend', defaultFileTitle: 'home')
    ->withHtmlRoute(path: '/news/', viewGroup: 'news', defaultFileTitle: 'overview')
    ->withFileRoute(path: '/export/', viewGroup: 'export', contentType: ContentType::createCsv())
    ->run();
```

- `withLanguage()`: before the routes. The first language is the language of every route that names none
  (`languageCode:`).
- `withHtmlRoute()`: `<path><name>.html` renders `app/view/<viewGroup>/html/<name>.html` with the view class
  `app\view\<viewGroup>\php\<name>`, if it exists (or the views of `viewFactory:`, e.g. a `ViewMap`). The first HTML
  route of each language is the target of the redirect of `/`. Without any route, `App` serves `app/view/frontend/` at
  `/`.
  - `defaultFileTitle:` (default `index`) is the page of the path itself. Its file name is redirected permanently
    (301) to the path, so the page has one URL: `/home.html` → `/` with `defaultFileTitle: 'home'`
    (`redirectDefaultFileName: false` serves both).
  - `fileExtension: HtmlFileExtensionEnum::NONE` serves paths without extension (`/about`), e.g. to keep the URLs of a
    site taken over from another system; `.html` is then a 404. Prefer the default `.html`.
  - A `-` in the file name always separates path variables ([views.md](views.md#path-variables)): `/about-us` is the
    page `about` with the variable `us`, so a view must allow it (`maxAllowedPathVars`) or the request is a 404.
- `withFileRoute()`: CSV, PDF, JSON and other files, only with the extension of the content type.
- `withJsonRoute()`: JSON at paths without extension (`/api/v1/domains`: REST API, webhooks, cron calls); a path with
  extension is a 404.
- `withCsp()`, `withoutSession()`, `withSessionHandler()`, `withLogger()`, `withLoginPath()`, `withTemplateTags()`,
  `withNavigation()`, `withExceptionHandler()`: the arguments of `Core::prepareHttpResponse()`.
- `withFileSessions(sessionSettings: new SessionSettings(maxLifeTime: 43200))`: the default session files with own
  settings.
- `withFileLogger(mailer:, mailSenderAddress:, clock:)`: the default `FileLogger` with a mailer or a clock
  ([Error log](#error-log)). `getLogger()` returns the logger of the `App` for the project's own classes (e.g. a
  `LoginRateLimit`), so they write the same tickets.
- `fromEnvironment()` takes the directories of `Core::fromEnvironment()` and `viewClassPrefix:` (the namespace of the
  view classes, default `app`).
- Special cases: `withRoute()` with an own `Route` (its language from `$app->getLanguage(code:)`), `$app->core` for
  the settings and directories, or `Core` directly:

```php
$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026);
$core->prepareHttpResponse(routeCollection: $routes)->sendAndExit();
```

Mistakes in the setup (a language added after a route, a route of a language that was not added, two routes with the
same path, a missing `.env.php`) throw at startup with a message that names the fix.

`fromEnvironment()` does everything global, once per process: it registers the error handler, reads the environment
file, sets `error_reporting()` and the time zone, creates the directories and the request from the PHP
globals. A request without HTTPS gets the redirect to HTTPS as response of `prepareHttpResponse()`, but only for a
host of `allowedDomains` (an unknown host gets a 404 without redirect). Tests build `Core`
without globals (see [testing.md](testing.md)).

## Environment settings

`.env.php` returns an array. `Core` checks it once when it starts and throws an `UnexpectedValueException` naming the
key if a setting is missing or has the wrong type:

| Key                     | Type           | Meaning                                              |
|:------------------------|:---------------|:-----------------------------------------------------|
| `defaultErrorReporting` | `int`          | Optional, default `E_ALL`: `error_reporting()` level |
| `defaultTimeZone`       | `string`       | PHP time zone, e.g. `Europe/Zurich`                  |
| `allowedDomains`        | `list<string>` | Host names the application answers to (else 404)     |
| `logEmailRecipient`     | `string`       | Mail address of new errors, empty for no mails       |
| `debug`                 | `bool`         | Shows the debug page for errors; `false` in production |
| `robots`                | `string`       | Content of the `robots` meta tag                     |
| `checkTemplateChanges`  | `bool`         | Optional, default `debug`: compile changed templates |

Own keys of a project (flat, e.g. `'mailer.hostname'`) are read from `$core->environmentSettings` with `getString()`,
`getInt()`, `getBool()`, `getStringList()` and `getEnum(key:, enumClass:)` (a backed enum by its value, e.g.
`'mailer.encryption' => 'starttls'` for `SmtpEncryptionEnum`); `has()` tells if a key exists. A missing key, a wrong
type or an unknown enum value throws an `UnexpectedValueException`. Pass the values to your own settings class instead of reading them statically.

## Host and client IP address

- The host of a request (`HttpRequest::getHost()`, the `Host` header) is sent by the client and only trustworthy after
  the domain check against `allowedDomains`. `Core` does it first, before the redirect to HTTPS and before the route is
  resolved; absolute URLs (redirects, links) use the host of the request, so a site with several domains lists all of
  them in `allowedDomains`. Do not use `getHost()` for security decisions in code that runs before `Core`.
- The client IP address (`HttpRequest::getRemoteAddress()`) is `REMOTE_ADDR` only: yuf never reads `X-Forwarded-For`
  or similar headers, a client could send them. Behind a reverse proxy or load balancer, configure the web server so
  that `REMOTE_ADDR` is the client address of the trusted proxy (Apache `mod_remoteip` with `RemoteIPHeader` and
  `RemoteIPInternalProxy`, nginx `ngx_http_realip_module` with `set_real_ip_from` and `real_ip_header`).

## Production

- `opcache.validate_timestamps=0`: reset the opcache on every deployment (restart PHP-FPM or call `opcache_reset()`),
  else the old code keeps running. This includes the compiled templates in `app/cache/`.
- Without `debug`, compiled templates are used without comparing them with the templates (no file checks per render,
  `checkTemplateChanges` follows `debug`). Delete `app/cache/v[0-9]*/` on every deployment (`clearDirectories` of
  `deploy.json`), else changed templates are not compiled again; or set `'checkTemplateChanges' => true`. Not
  `app/cache/v*/`: that also deletes `app/cache/values/` (`FileCache`, e.g. access tokens).
- `zend.exception_ignore_args=On` (the default of the `php.ini-production`): the stack traces of exceptions then carry
  no arguments (passwords, tokens) into the error log and the tickets.
- `debug` is `false`: the error pages show no details of the exception.
- Deploy with `composer install --no-dev --optimize-autoloader`: Composer's class map finds every class with one array
  lookup (and still finds classes added later by their PSR-4 path).
- Optional: `opcache.preload` with a script that loads the classes of yuf and your application.
- With PHP-FPM, yuf calls `fastcgi_finish_request()` after the response is sent: destructors and shutdown functions run
  after the client has the response. Nothing can be output afterwards, and the session is already closed (see
  [session-and-login.md](session-and-login.md)).

## Request and upload limits

PHP parses the request body (form data, uploads, the upload progress of the session) before any code of yuf runs. A
bug there cannot be caught by the application: the "php2shell" vulnerabilities of October 2026 (CVE-2026-109618,
CVE-2026-109619: memory errors in the multipart parser and the session upload progress) are fixed only by PHP 8.5.12,
8.4.27, 8.3.36 and 8.2.35. Update PHP first; yuf's checks (CSRF, rate limits, upload rules) are defence in depth.

Set in `php.ini` or the PHP-FPM pool (`php_admin_value[...]`; these settings cannot be changed with `ini_set()`):

| Setting | Recommended | Why |
| --- | --- | --- |
| `session.upload_progress.enabled` | `0` | yuf does not use it; it writes request data into the session while the body is parsed |
| `file_uploads` | `0` for sites without upload forms | no multipart file parsing at all |
| `max_file_uploads` | the largest `maxFileUploadCount` of the site | more files per request are dropped by PHP |
| `upload_max_filesize` | the largest `maxFileSize` | larger files arrive as `UPLOAD_ERR_INI_SIZE` |
| `post_max_size` | slightly above `max_file_uploads` × `upload_max_filesize`, as small as possible (e.g. `2M` without uploads) | limits the whole body |
| `max_input_vars` | `1000` (default) or the largest form | limits the number of parsed fields |
| `max_multipart_body_parts` | explicitly, e.g. `max_input_vars` + `max_file_uploads` | limits the parts of a multipart body (default `-1` = that sum) |

Limit the body in the web server too, matching `post_max_size` (Apache `LimitRequestBody`, nginx
`client_max_body_size`). A form without file field rejects uploads and multipart bodies itself
([forms.md](forms.md#file-uploads)).

## Error log

`Core` logs every exception with `FileLogger` (`app/logs/ticket_<hash>.txt`, one file per distinct issue; a new issue is
mailed to `logEmailRecipient`). The entry describes the request without secrets: request line without query string,
host, IP address, user agent, referrer without query string, a fixed list of server variables, query and post
parameters, uploaded files (name, type, size) and the cookie *names*. Values of parameters whose name contains
`password`, `token`, `secret`, `csrf`, `key` or `auth` (case-insensitive, at any depth) are replaced by `***`. Control
characters of logged values and messages (line feed, carriage return, ...) are written as `\n`, `\r` or `\xNN`, so a
value cannot forge lines of the log. A ticket is one distinct issue: its hash covers the exception class, file, line
and trace, not the message.

By default, new issues are mailed with PHP's `mail()`. To send them with `SmtpMailer` or `GraphMailer`
(see [mail.md](mail.md)), pass a mailer and a sender address:

```php
$app->withFileLogger(mailer: $mailer, mailSenderAddress: 'noreply@example.com');
```

The mail contains the ticket file name, the issue and the request description. If the mailer fails (SMTP server or
Graph API not reachable), the failure is noted in the ticket file and the issue is mailed with `mail()` as fallback, so
the notification still arrives. The mail is sent after the response (shutdown function), so the error page does not
wait for the mail server; the ticket file is written at once.

Without `App`: `$core->prepareHttpResponse(logger: $core->createFileLogger(mailer: …, mailSenderAddress: …), …)`.
For another destination, implement `actra\yuf\core\Logger` and pass it as
`prepareHttpResponse(logger: …)` or `withLogger(logger: …)`.
