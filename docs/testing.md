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
| Mailer         | `new RecordingMailer()`: records the sent mails, see [mail.md](mail.md#tests)                |
| Mailbox        | `new RecordingImapMailbox(folders: […])`: messages in memory, see [mailbox.md](mailbox.md#tests) |
| Response sender | `new RecordingResponseSender()`: records responses that end the script, see below           |
| Views          | `new ViewTestClient(…)`: runs views of an HTML route with one session, see below             |

`HttpRequest::fromGlobals()` is meant for `Core`, bootstrap files and CLI scripts, not for logic classes.

## Redirects, downloads and other responses that end the script

Redirects, file downloads and JSON responses with `sendAndExit` end the script. Give `Core` a
`RecordingResponseSender`: it records the response and throws `ResponseSentException` instead of ending the process.
`RecordingResponseSender::capture()` runs the code and returns the sent response:

```php
$httpResponse = RecordingResponseSender::capture(
    action: function (ResponseSender $responseSender): void {
        App::fromCore(core: new Core(settings: $settings, httpRequest: $httpRequest, responseSender: $responseSender))
            ->withHtmlRoute(path: '/', viewGroup: 'frontend')
            ->prepare();
    },
);
$this->assertSame(HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY, $httpResponse->httpStatusCode);
```

Work registered with `afterResponse()` runs only when the test calls `runAfterResponseCallbacks()`, or with
`capture(action: …, runAfterResponseCallbacks: true)`.

## Views

`actra\yuf\testing\ViewTestClient` runs the views of an HTML route like a browser: one session for all requests
(multi-step forms, CSRF tokens, logins), one request per `run()` and the real view pipeline (language files, path
variables, `execute()`, rendering of the `HtmlDocument`). A response that ends the script (redirect, download) is
recorded, the work registered with `afterResponse()` runs after the view (`runAfterResponseCallbacks: false` leaves it
out). The posted fields of a POST get the CSRF token of the session unless they have one (`csrftoken`).

```php
$client = new ViewTestClient(
    viewDirectory: $root . 'app/view/',     // Core::$viewDirectory
    viewGroup: 'frontend',
    templateBaseDirectory: $root,             // Core::$baseDirectory
    cacheDirectory: $cacheDirectory,          // compiled templates of the test
    host: 'www.example.com',
    language: new Language(code: 'de', locale: 'de_CH.UTF-8'),
);
$mailer = new RecordingMailer();
// The factory creates the view of the request, as the ViewFactory of the route does
$viewFactory = static fn(ViewContext $context): BaseView => $context->fileTitle === 'login'
    ? new LoginView(context: $context, mailer: $mailer)
    : new LoginCodeView(context: $context);

// Step 1: the email address; the view sends the code by mail after the response
$result = $client->run(
    viewFactory: $viewFactory,
    fileTitle: 'login',
    method: RequestMethodEnum::POST,
    query: ['returnTo' => '/account.html'],
    post: ['email' => 'a@example.com'],
);
$this->assertSame('https://www.example.com/login_code.html?returnTo=%2Faccount.html', $result->getLocation());
$this->assertCount(1, $mailer->sentMails);

// Step 2, same session: the form with an error
$page = $client->run(
    viewFactory: $viewFactory,
    fileTitle: 'login_code',
    method: RequestMethodEnum::POST,
    post: ['token' => 'WRONG1'],
);
$this->assertStringContainsString('has-error', $page->content);         // the rendered page
$page->getHtmlDocument()->replacements->has(identifier: 'title');      // the replacements of the view
$page->context->session?->getString(key: 'email');                     // the session, request and content
```

`run()` throws what the view throws (`NotFoundException`, `UnauthorizedException`, …). `createContext()` returns the
`ViewContext` of a request for code that needs one without running a view (e.g. to read the login of the session).

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
