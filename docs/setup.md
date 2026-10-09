# Setup

## Application entry point

Create `.env.php` based on [.env.example.php](../.env.example.php) and an `index.php` in the document root based on
[index.example.php](../index.example.php). `Core::fromEnvironment()` needs `envFilePath:` and `copyrightYear:`;
`autoloaderPath:` and the directories have defaults:

```php
require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026);
$core->prepareHttpResponse(routeCollection: $routes)->sendAndExit();
```

`fromEnvironment()` does everything global, once per process: it registers the autoloader and the error handler, reads
the environment file, sets `error_reporting()` and the time zone, creates the directories and the request from the PHP
globals. A request without HTTPS gets the redirect to HTTPS as response of `prepareHttpResponse()`. Tests build `Core`
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
| `debug`                 | `bool`         | Shows the debug page for errors                      |
| `robots`                | `string`       | Content of the `robots` meta tag                     |

Own keys of a project (flat, e.g. `'mailer.hostname'`) are read from `$core->environmentSettings` with `getString()`,
`getInt()`, `getBool()` and `getStringList()` (`has()` tells if a key exists); a missing key or a wrong type throws an
`UnexpectedValueException`. Pass the values to your own settings class instead of reading them statically.

## Production

- `opcache.validate_timestamps=0`: reset the opcache on every deployment (restart PHP-FPM or call `opcache_reset()`),
  else the old code keeps running. This includes the compiled templates in `app/cache/`.
- Optional: `opcache.preload` with a script that loads the classes of yuf and your application.
- With PHP-FPM, yuf calls `fastcgi_finish_request()` after the response is sent: destructors and shutdown functions run
  after the client has the response. Nothing can be output afterwards, and the session is already closed (see
  [session-and-login.md](session-and-login.md)).

## Error log

`Core` logs every exception with `FileLogger` (`app/logs/ticket_<hash>.txt`, one file per distinct issue; a new issue is
mailed to `logEmailRecipient`). The entry describes the request without secrets: request line without query string,
host, IP address, user agent, referrer without query string, a fixed list of server variables, query and post
parameters, uploaded files (name, type, size) and the cookie *names*. Values of parameters whose name contains
`password`, `token`, `secret`, `csrf`, `key` or `auth` (case-insensitive, at any depth) are replaced by `***`.

For another destination, implement `actra\yuf\core\Logger` and pass it as `prepareHttpResponse(logger: …)`.
