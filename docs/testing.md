# Static analysis and tests in projects using yuf

## PHPStan and PHPUnit

Composer loads yuf and the project's classes (PSR-4 in `composer.json`): PHPStan needs no `scanDirectories`, and the
PHPUnit bootstrap (e.g. `tests/bootstrap.php`) only loads the Composer autoloader:

```php
require __DIR__ . '/../vendor/autoload.php';
```

## Objects without globals

| Object         | In tests                                                                                     |
|:---------------|:---------------------------------------------------------------------------------------------|
| `Core`         | `new Core(settings: new CoreSettings(…), httpRequest: …, responseSender: …)`                 |
| `HttpRequest`  | `new HttpRequest(host: 'example.com', method: …, queryParameters: …)`                        |
| `Session`      | `new Session(storage: new ArraySessionStorage())`                                            |
| `FormInput`    | `FormInput::fromArray(data: […], query: […])` (see [forms.md](forms.md))                     |
| `FrameworkDb`  | `new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'))`  |
| `Clock`        | `new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05'))`                |

`HttpRequest::fromGlobals()` is meant for `Core`, bootstrap files and CLI scripts, not for logic classes.

## Clock

Time-dependent code takes an `actra\yuf\clock\Clock` (`now(): DateTimeImmutable`, the signature of PSR-20's
`ClockInterface`, without the `psr/clock` dependency) through its constructor. Production code uses `SystemClock` (the
default everywhere), tests pass a `FixedClock`. There is no static accessor.

```php
$logger = new FileLogger(
    logEmailRecipient: '',
    logDirectory: $logDirectory,
    httpRequest: $core->httpRequest,
    mailer: null,
    clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
);
```
