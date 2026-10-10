# Static analysis and tests in projects using yuf

## PHPStan and PHPUnit

yuf has a Composer autoload configuration (PSR-4, since v5.0.0): PHPStan and PHPUnit find its classes through
`vendor/autoload.php`, no `scanDirectories` and no yuf path in the test bootstrap are needed. Who loads yuf in the
application: see [setup.md](setup.md).

The bootstrap (e.g. `tests/bootstrap.php`) loads the Composer autoloader. Projects whose own classes have no Composer
autoload register `actra/autoloader` for them; delete its cache file first, so no stale class paths remain after files
have been moved:

```php
require __DIR__ . '/../vendor/autoload.php';

$autoloaderCacheFilePath = __DIR__ . '/../.phpunit.cache/autoloader.php';
if (file_exists(filename: $autoloaderCacheFilePath)) {
    unlink(filename: $autoloaderCacheFilePath);
}
$autoloader = Autoloader::register(cacheFilePath: $autoloaderCacheFilePath);
$autoloader->addPath(autoloaderPath: new AutoloaderPath(path: __DIR__ . '/../app/', prefix: 'app\\'));
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
