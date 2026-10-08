<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\ExceptionHandlerContext;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\PhpException;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\exception\ContextExposingExceptionHandler;
use actra\yuf\tests\Double\exception\ExceptionHandlerContextFactory;
use actra\yuf\tests\Double\exception\TeapotExceptionHandler;
use actra\yuf\tests\Double\security\CountingCsrfTokenSource;
use actra\yuf\tests\Double\session\FailingSessionStorage;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use JsonException;
use LogicException;
use Override;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `register()` sets the global exception handler of PHP, which each test restores. `handleException()` sends the
 * response of `createResponse()` through the sender of the context (a recording double); what is sent is covered
 * through `createResponse()`.
 */
final class ExceptionHandlerTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private RecordingLogger $logger;
    private bool $isRegistered = false;
    private string|false $previousLocale = false;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->logger = new RecordingLogger();
        $this->previousLocale = setlocale(LC_ALL, '0');
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->isRegistered) {
            restore_exception_handler();
        }
        $this->workDirectory->cleanUp();
        if ($this->previousLocale !== false) {
            setlocale(LC_ALL, $this->previousLocale);
        }
    }

    private function createContext(
        bool $isDebug = false,
        ?CspPolicySettings $cspPolicySettings = new CspPolicySettings(),
        ?string $errorDocsDirectory = null,
        LanguageCollection $availableLanguages = new LanguageCollection(),
        ?ResponseSender $responseSender = null,
    ): ExceptionHandlerContext {
        return ExceptionHandlerContextFactory::create(
            logger: $this->logger,
            cacheDirectory: $this->workDirectory->cacheDirectory,
            isDebug: $isDebug,
            cspPolicySettings: $cspPolicySettings,
            httpRequest: HttpRequestFactory::create(
                queryParameters: ['id' => '7'],
                postParameters: ['name' => 'Anna <b>'],
            ),
            errorDocsDirectory: $errorDocsDirectory,
            availableLanguages: $availableLanguages,
            responseSender: $responseSender,
        );
    }

    private function register(
        ?ExceptionHandler $handler = null,
        ?ExceptionHandlerContext $context = null,
    ): ExceptionHandler {
        $registered = ExceptionHandler::register(
            individualExceptionHandler: $handler ?? new ExceptionHandler(
                htmlReplacementCollection: $this->createReplacements(),
            ),
            context: $context ?? $this->createContext(),
        );
        $this->isRegistered = true;

        return $registered;
    }

    private function createReplacements(): HtmlReplacementCollection
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addText(identifier: 'projectValue', text: 'from the project');

        return $replacements;
    }

    private function createJsonHandler(bool $isDebug = false): ExceptionHandler
    {
        $handler = $this->register(context: $this->createContext(isDebug: $isDebug));
        $handler->setContentHandler(
            contentHandler: new ContentHandler(
                contentType: ContentType::createJson(),
                cspNonce: new CspNonce(value: ExceptionHandlerContextFactory::NONCE),
            ),
        );

        return $handler;
    }

    private function createTextHandler(ContentType $contentType): ExceptionHandler
    {
        $handler = $this->register();
        $handler->setContentHandler(
            contentHandler: new ContentHandler(
                contentType: $contentType,
                cspNonce: new CspNonce(value: ExceptionHandlerContextFactory::NONCE),
            ),
        );

        return $handler;
    }

    private static function content(HttpResponse $response): string
    {
        return $response->getContentString() ?? '';
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeJson(HttpResponse $response): array
    {
        $decoded = json_decode(json: self::content(response: $response), associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array(value: $decoded)) {
            throw new JsonException(message: 'The response is no JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private static function arrayAt(array $array, string $key): array
    {
        $value = array_key_exists(key: $key, array: $array) ? $array[$key] : null;
        if (!is_array(value: $value)) {
            throw new JsonException(message: 'No object at "' . $key . '".');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function stringAt(array $array, string $key): string
    {
        $value = array_key_exists(key: $key, array: $array) ? $array[$key] : null;
        if (!is_string(value: $value)) {
            throw new JsonException(message: 'No string at "' . $key . '".');
        }

        return $value;
    }

    public function testContextWithoutRegisterThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('ExceptionHandler is not registered: the context is not available.');

        new ContextExposingExceptionHandler()->context();
    }

    public function testResponseWithoutRegisterThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('ExceptionHandler is not registered: the context is not available.');

        new ExceptionHandler()->createResponse(throwable: new RuntimeException());
    }

    public function testRegisterKeepsTheContext(): void
    {
        $handler = new ContextExposingExceptionHandler();
        $context = $this->createContext();

        $this->register(handler: $handler, context: $context);

        $this->assertSame($context, $handler->context());
    }

    public function testRegisterReturnsTheRegisteredHandler(): void
    {
        $handler = new ContextExposingExceptionHandler();

        $registered = $this->register(handler: $handler);

        $this->assertSame($handler, $registered);
    }

    public function testRegisterWithoutHandlerCreatesTheDefaultHandler(): void
    {
        $registered = ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
        $this->isRegistered = true;

        $this->assertSame(ExceptionHandler::class, $registered::class);
    }

    public function testRegisterSetsTheHandlerAsGlobalExceptionHandler(): void
    {
        $handler = $this->register();

        $previous = set_exception_handler(callback: null);
        $this->isRegistered = false;

        $this->assertSame([$handler, 'handleException'], $previous);
        // The test set it again to restore PHP's own stack below
        set_exception_handler(callback: $previous);
        $this->isRegistered = true;
    }

    public function testSecondRegisterThrowsAndKeepsTheFirstHandler(): void
    {
        $first = $this->register();

        try {
            ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
            self::fail('The second registration must throw.');
        } catch (LogicException $exception) {
            $this->assertSame('ExceptionHandler is already registered.', $exception->getMessage());
        }

        $current = set_exception_handler(callback: null);
        $this->assertSame([$first, 'handleException'], $current);
        set_exception_handler(callback: $current);
    }

    public function testSetRequestHandlerTwiceThrows(): void
    {
        $handler = $this->register();
        $requestHandler = $this->createRequestHandler();
        $handler->setRequestHandler(requestHandler: $requestHandler);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The request handler is already set.');
        $handler->setRequestHandler(requestHandler: $requestHandler);
    }

    public function testSetContentHandlerTwiceThrows(): void
    {
        $handler = $this->register();
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $handler->setContentHandler(contentHandler: $contentHandler);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The content handler is already set.');
        $handler->setContentHandler(contentHandler: $contentHandler);
    }

    public function testSetSessionTwiceThrows(): void
    {
        $handler = $this->register();
        $handler->setSession(session: null, csrfTokenSource: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The session is already set.');
        $handler->setSession(session: null, csrfTokenSource: null);
    }

    public function testSetSessionAcceptsASessionOnce(): void
    {
        $handler = $this->register();
        $session = new Session(storage: new ArraySessionStorage());

        $handler->setSession(session: $session, csrfTokenSource: new SessionCsrfTokenSource(session: $session));

        $this->expectException(LogicException::class);
        $handler->setSession(session: $session, csrfTokenSource: null);
    }

    private function createRequestHandler(
        string $requestUri = '/en/nope.html',
        ?Language $language = null,
        ?Language $otherLanguage = null,
    ): RequestHandler {
        $language ??= new Language(code: 'en', locale: 'C');
        $languages = [$language];
        $routes = [
            new Route(
                path: '/' . $language->code . '/',
                viewDirectory: $this->workDirectory->templateDirectory,
                defaultFileName: 'index.html',
                isDefaultForLanguage: true,
                language: $language,
            ),
        ];
        if ($otherLanguage !== null) {
            $languages[] = $otherLanguage;
            $routes[] = new Route(
                path: '/' . $otherLanguage->code . '/',
                viewDirectory: $this->workDirectory->templateDirectory,
                defaultFileName: 'index.html',
                isDefaultForLanguage: true,
                language: $otherLanguage,
            );
        }

        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(uri: $requestUri),
            routeCollection: new RouteCollection(routes: $routes),
            availableLanguages: new LanguageCollection(languages: $languages),
            allowedDomains: ['example.com'],
            session: null,
        );
    }

    public function testHandleExceptionSendsTheResponseThroughTheSenderOfTheContext(): void
    {
        $sentResponse = RecordingResponseSender::capture(
            action: function (ResponseSender $sender): void {
                $this->register(context: $this->createContext(responseSender: $sender))->handleException(
                    throwable: new NotFoundException(message: 'Invoice 42 is missing'),
                );
            },
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $sentResponse->httpStatusCode);
        $this->assertStringContainsString('<h1>Not found page</h1>', self::content(response: $sentResponse));
    }

    // Production: HTML

    public function testNotFoundIsAnsweredWithTheNotFoundPage(): void
    {
        $handler = $this->register();

        $response = $handler->createResponse(throwable: new NotFoundException(message: 'Invoice 42 is missing'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $response->httpStatusCode);
        $this->assertStringContainsString('<h1>Not found page</h1>', self::content(response: $response));
        $this->assertStringContainsString('class="body-notFound"', self::content(response: $response));
    }

    public function testUnauthorizedIsAnsweredWithTheUnauthorizedPage(): void
    {
        $handler = $this->register();

        $response = $handler->createResponse(throwable: new UnauthorizedException(message: 'JWT verification failed'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED, $response->httpStatusCode);
        $this->assertStringContainsString('<h1>Unauthorized page</h1>', self::content(response: $response));
        $this->assertStringContainsString('class="body-unauthorized"', self::content(response: $response));
    }

    public function testOtherExceptionsAreAnsweredWithTheDefaultPage(): void
    {
        $handler = $this->register();

        $response = $handler->createResponse(throwable: new RuntimeException('SQLSTATE[HY000] in /var/www/secret.php'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
        $this->assertStringContainsString('<h1>Default error page</h1>', self::content(response: $response));
        $this->assertStringContainsString('class="body-default"', self::content(response: $response));
    }

    public function testProductionPagesNeverShowTheExceptionMessageFileOrTrace(): void
    {
        $handler = $this->register();
        $exceptions = [
            new RuntimeException('SQLSTATE[HY000] secret query'),
            new NotFoundException(message: 'secret query'),
            new UnauthorizedException(message: 'secret query'),
            new PhpException(message: 'secret query', code: E_WARNING, file: '/var/www/secret.php', line: 3),
        ];

        foreach ($exceptions as $exception) {
            $content = self::content(response: $handler->createResponse(throwable: $exception));

            $this->assertStringNotContainsString('secret', $content);
            $this->assertStringNotContainsString('ExceptionHandlerTest', $content);
            $this->assertStringNotContainsString('#0', $content);
        }
    }

    public function testPhpErrorsAreAnsweredAsInternalErrors(): void
    {
        $handler = $this->register();

        $response = $handler->createResponse(
            throwable: new PhpException(message: 'Undefined variable', code: E_WARNING, file: 'x.php', line: 1),
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
    }

    public function testUnexpectedErrorsAreLoggedOnce(): void
    {
        $handler = $this->register();
        $exception = new RuntimeException('Broken');

        $handler->createResponse(throwable: $exception);

        $this->assertSame([$exception], $this->logger->loggedExceptions);
    }

    public function testNotFoundAndUnauthorizedAreNotLogged(): void
    {
        $handler = $this->register();

        $handler->createResponse(throwable: new NotFoundException());
        $handler->createResponse(throwable: new UnauthorizedException());

        $this->assertSame([], $this->logger->loggedExceptions);
    }

    public function testHtmlResponseHasTheContentSecurityPolicyWithTheNonce(): void
    {
        $handler = $this->register();

        $response = $handler->createResponse(throwable: new NotFoundException());

        $policy = $response->getHeader(key: 'Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString("'nonce-" . ExceptionHandlerContextFactory::NONCE . "'", $policy);
        $this->assertSame('text/html; charset=utf-8', $response->getHeader(key: 'Content-Type'));
    }

    public function testHtmlResponseWithoutPolicyHasNoPolicyHeader(): void
    {
        $handler = $this->register(context: $this->createContext(cspPolicySettings: null));

        $response = $handler->createResponse(throwable: new NotFoundException());

        $this->assertNull($response->getHeader(key: 'Content-Security-Policy'));
    }

    public function testPageHasTheValuesOfThePage(): void
    {
        $handler = $this->register();

        $content = self::content(response: $handler->createResponse(throwable: new NotFoundException()));

        $this->assertStringContainsString('<html lang="en" class="notFound">', $content);
        $this->assertStringContainsString('<title>Error</title>', $content);
        $this->assertStringContainsString('<p id="root">/</p>', $content);
        $this->assertStringContainsString('<p id="copyright">2020-2026</p>', $content);
        $this->assertStringContainsString('<p id="nonce">' . ExceptionHandlerContextFactory::NONCE . '</p>', $content);
        $this->assertStringContainsString('<p id="meta">UTF-8 noindex,nofollow</p>', $content);
        $this->assertStringContainsString('<p id="projectValue">from the project</p>', $content);
        $this->assertStringContainsString('<p id="file"></p>', $content);
        $this->assertStringContainsString('<div id="csrf"></div>', $content);
    }

    public function testPageHasTheCsrfFieldIfThereIsASession(): void
    {
        $handler = $this->register();
        $session = new Session(storage: new ArraySessionStorage());
        $handler->setSession(session: $session, csrfTokenSource: new SessionCsrfTokenSource(session: $session));

        $content = self::content(response: $handler->createResponse(throwable: new NotFoundException()));

        $this->assertMatchesRegularExpression(
            '#<div id="csrf"><input type="hidden" name="[^"]+" value="[^"]+"></div>#',
            $content,
        );
    }

    public function testPageWithoutCsrfFieldNeverReadsTheSession(): void
    {
        $directory = $this->workDirectory->templateDirectory;
        file_put_contents(filename: $directory . 'notFound.html', data: '<h1>plain</h1>');
        $handler = $this->register(context: $this->createContext(errorDocsDirectory: $directory));
        $csrfTokenSource = new CountingCsrfTokenSource();
        $handler->setSession(session: null, csrfTokenSource: $csrfTokenSource);

        $content = self::content(response: $handler->createResponse(throwable: new NotFoundException()));

        $this->assertStringContainsString('<h1>plain</h1>', $content);
        $this->assertSame(0, $csrfTokenSource->tokenReads);
    }

    public function testPageIsShownWithoutCsrfFieldIfTheSessionCannotBeStarted(): void
    {
        $handler = $this->register();
        $session = new Session(storage: new FailingSessionStorage());
        $handler->setSession(session: $session, csrfTokenSource: new SessionCsrfTokenSource(session: $session));

        $content = self::content(response: $handler->createResponse(throwable: new NotFoundException()));

        $this->assertStringContainsString('<h1>Not found page</h1>', $content);
        $this->assertStringContainsString('<div id="csrf"></div>', $content);
    }

    public function testDebugPageShowsTheErrorIfTheSessionCannotBeStarted(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));
        $handler->setSession(session: new Session(storage: new FailingSessionStorage()), csrfTokenSource: null);

        $content = self::content(response: $handler->createResponse(throwable: new RuntimeException('the error')));

        $this->assertStringContainsString('<p id="message">the error</p>', $content);
        $this->assertStringContainsString('The session could not be read: The session could not be started.', $content);
    }

    public function testPageHasTheLanguageAndTheRootOfTheRequest(): void
    {
        $handler = $this->register(
            context: $this->createContext(
                availableLanguages: new LanguageCollection(languages: [new Language(code: 'en', locale: 'C')]),
            ),
        );
        $handler->setRequestHandler(requestHandler: $this->createRequestHandler());

        $response = $handler->createResponse(throwable: new NotFoundException());

        $content = self::content(response: $response);
        $this->assertStringContainsString('<html lang="en" class="notFound">', $content);
        $this->assertStringContainsString('<p id="root">/en/</p>', $content);
        $this->assertStringContainsString('<p id="file">nope.html</p>', $content);
        $this->assertSame('en', $response->getHeader(key: 'Content-Language'));
    }

    public function testPageOfAResolvedRequestHasTheLanguageRootAndFileOfTheRoute(): void
    {
        $german = new Language(code: 'de', locale: 'C');
        $english = new Language(code: 'en', locale: 'C');
        $handler = $this->register(
            context: $this->createContext(availableLanguages: new LanguageCollection(languages: [$german, $english])),
        );
        $requestHandler = $this->createRequestHandler(
            requestUri: '/en/',
            language: $german,
            otherLanguage: $english,
        );
        $handler->setRequestHandler(requestHandler: $requestHandler);
        $requestHandler->resolveRoute();

        $response = $handler->createResponse(throwable: new NotFoundException());

        $content = self::content(response: $response);
        $this->assertStringContainsString('<html lang="en" class="notFound">', $content);
        $this->assertStringContainsString('<p id="root">/en/</p>', $content);
        $this->assertStringContainsString('<p id="file">index.html</p>', $content);
        $this->assertSame('en', $response->getHeader(key: 'Content-Language'));
    }

    public function testRequestedFileNameIsEscaped(): void
    {
        $handler = $this->register(
            context: $this->createContext(
                availableLanguages: new LanguageCollection(languages: [new Language(code: 'en', locale: 'C')]),
            ),
        );
        $handler->setRequestHandler(
            requestHandler: $this->createRequestHandler(requestUri: '/en/"><img src=x onerror=alert(1)>.html'),
        );

        $content = self::content(response: $handler->createResponse(throwable: new NotFoundException()));

        $this->assertStringNotContainsString('<img', $content);
        $this->assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;.html', $content);
    }

    public function testMissingPageInProductionShowsOnlyTheShortTextAndLogsThePath(): void
    {
        $directory = $this->workDirectory->templateDirectory;
        $handler = $this->register(context: $this->createContext(errorDocsDirectory: $directory));

        $response = $handler->createResponse(throwable: new NotFoundException());

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $response->httpStatusCode);
        $this->assertSame('Not Found', self::content(response: $response));
        $this->assertSame(['Missing error html file ' . $directory . 'notFound.html'], $this->logger->loggedMessages);
    }

    public function testMissingPageInDebugModeShowsThePath(): void
    {
        $directory = $this->workDirectory->templateDirectory;
        $handler = $this->register(context: $this->createContext(isDebug: true, errorDocsDirectory: $directory));

        $response = $handler->createResponse(throwable: new NotFoundException());

        $this->assertSame('Missing error html file ' . $directory . 'debug.html', self::content(response: $response));
        $this->assertSame([], $this->logger->loggedMessages);
    }

    // Production: JSON and text

    public function testJsonRequestsGetJsonErrors(): void
    {
        $handler = $this->createJsonHandler();

        $response = $handler->createResponse(throwable: new NotFoundException(message: 'Invoice 42 is missing'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $response->httpStatusCode);
        $this->assertSame('application/json; charset=utf-8', $response->getHeader(key: 'Content-Type'));
        $this->assertSame(
            ['success' => false, 'error' => ['code' => 404, 'message' => 'Not Found']],
            self::decodeJson(response: $response),
        );
    }

    public function testJsonUnauthorizedHasAFixedMessage(): void
    {
        $handler = $this->createJsonHandler();

        $response = $handler->createResponse(throwable: new UnauthorizedException(message: 'Unknown key ID'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED, $response->httpStatusCode);
        $this->assertSame(
            ['success' => false, 'error' => ['code' => 401, 'message' => 'Unauthorized']],
            self::decodeJson(response: $response),
        );
    }

    public function testJsonInternalErrorHasAFixedMessageAndTheStatusAsCode(): void
    {
        $handler = $this->createJsonHandler();
        $exception = new PDOException(message: 'SQLSTATE[42S02]: Base table not found: users');

        $response = $handler->createResponse(throwable: $exception);

        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
        $this->assertSame(
            ['success' => false, 'error' => ['code' => 500, 'message' => 'Internal Server Error']],
            self::decodeJson(response: $response),
        );
        $this->assertSame([$exception], $this->logger->loggedExceptions);
    }

    public function testProjectValuesAreNotPartOfTheJsonAnswer(): void
    {
        $handler = $this->createJsonHandler();

        $content = self::content(response: $handler->createResponse(throwable: new RuntimeException()));

        $this->assertStringNotContainsString('from the project', $content);
        $this->assertStringNotContainsString('"data"', $content);
    }

    public function testPlainTextRequestsGetTextErrors(): void
    {
        $handler = $this->createTextHandler(contentType: ContentType::createTxt());

        $response = $handler->createResponse(throwable: new RuntimeException('Broken'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
        $this->assertSame('error: Internal Server Error (500)', self::content(response: $response));
        $this->assertSame('text/plain; charset=utf-8', $response->getHeader(key: 'Content-Type'));
    }

    public function testCsvRequestsGetTextErrors(): void
    {
        $handler = $this->createTextHandler(contentType: ContentType::createCsv());

        $response = $handler->createResponse(throwable: new NotFoundException());

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $response->httpStatusCode);
        $this->assertSame('error: Not Found (404)', self::content(response: $response));
    }

    public function testOtherContentTypesGetTheErrorPage(): void
    {
        $handler = $this->createTextHandler(contentType: ContentType::createXml());

        $response = $handler->createResponse(throwable: new NotFoundException());

        $this->assertStringContainsString('<h1>Not found page</h1>', self::content(response: $response));
        $this->assertSame('text/html; charset=utf-8', $response->getHeader(key: 'Content-Type'));
    }

    // Debug

    public function testDebugPageShowsTheErrorAndTheRequest(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));

        $response = $handler->createResponse(throwable: new RuntimeException('Broken thing', 12));

        $content = self::content(response: $response);
        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
        $this->assertStringContainsString('<h1>Internal Server Error</h1>', $content);
        $this->assertStringContainsString('<p id="type">RuntimeException</p>', $content);
        $this->assertStringContainsString('<p id="message">Broken thing</p>', $content);
        $this->assertStringContainsString('<p id="origin">' . __FILE__ . ':', $content);
        $this->assertStringContainsString('<p id="code">12</p>', $content);
        $this->assertStringContainsString('ExceptionHandlerTest', $content);
        $this->assertStringContainsString('class="body-debug"', $content);
        $this->assertStringContainsString('<title>Internal Server Error</title>', $content);
        $this->assertStringContainsString('&#039;id&#039; =&gt; &#039;7&#039;', $content);
        $this->assertStringContainsString('&#039;name&#039; =&gt; &#039;Anna &lt;b&gt;&#039;', $content);
    }

    public function testDebugPageStatusFollowsTheKindOfError(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));

        $notFound = $handler->createResponse(throwable: new NotFoundException());
        $unauthorized = $handler->createResponse(throwable: new UnauthorizedException());

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $notFound->httpStatusCode);
        $this->assertStringContainsString('<h1>Page not found</h1>', self::content(response: $notFound));
        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED, $unauthorized->httpStatusCode);
        $this->assertStringContainsString('<h1>Unauthorized</h1>', self::content(response: $unauthorized));
    }

    public function testDebugPageEscapesTheMessageTheFileAndTheTrace(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));

        $content = self::content(
            response: $handler->createResponse(
                throwable: new PhpException(
                    message: '<script>alert("x")</script>',
                    code: E_WARNING,
                    file: '/tmp/<b>file</b>.php',
                    line: 4,
                ),
            ),
        );

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringNotContainsString('<b>file', $content);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $content);
        $this->assertStringContainsString('/tmp/&lt;b&gt;file&lt;/b&gt;.php:4', $content);
    }

    public function testDebugPageShowsTheCauseOfAWrappedException(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));
        $cause = new LogicException(message: 'the cause', code: 5);
        $wrapper = new RuntimeException(message: 'wrapper', previous: $cause);

        $content = self::content(response: $handler->createResponse(throwable: $wrapper));

        $this->assertStringContainsString('<p id="type">RuntimeException</p>', $content);
        $this->assertStringContainsString('<p id="message">the cause</p>', $content);
        $this->assertStringContainsString('<p id="code">5</p>', $content);
    }

    public function testDebugPageShowsTheSessionEscaped(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));
        $session = new Session(storage: new ArraySessionStorage());
        $session->set(key: 'name', value: '<i>Anna</i>');
        $handler->setSession(session: $session, csrfTokenSource: null);

        $content = self::content(response: $handler->createResponse(throwable: new RuntimeException()));

        $this->assertStringContainsString(
            '&#039;name&#039; =&gt; &#039;&lt;i&gt;Anna&lt;/i&gt;&#039;',
            $content,
        );
        $this->assertStringNotContainsString('<i>', $content);
    }

    public function testDebugPageWithoutSessionHasNoSessionData(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));

        $content = self::content(response: $handler->createResponse(throwable: new RuntimeException()));

        $this->assertStringContainsString('<pre id="session"></pre>', $content);
    }

    public function testDebugModeDoesNotLog(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));

        $handler->createResponse(throwable: new RuntimeException());

        $this->assertSame([], $this->logger->loggedExceptions);
    }

    public function testDebugJsonHasTheErrorAndTheDetails(): void
    {
        $handler = $this->createJsonHandler(isDebug: true);

        $response = $handler->createResponse(throwable: new RuntimeException('Broken <thing>', 12));

        $decoded = self::decodeJson(response: $response);
        $data = self::arrayAt(array: $decoded, key: 'data');
        $this->assertSame(HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR, $response->httpStatusCode);
        $this->assertSame(['code' => 12, 'message' => 'Broken <thing>'], self::arrayAt(array: $decoded, key: 'error'));
        $this->assertSame('Internal Server Error', self::stringAt(array: $data, key: 'title'));
        $this->assertSame('RuntimeException', self::stringAt(array: $data, key: 'errorType'));
        $this->assertSame('Broken <thing>', self::stringAt(array: $data, key: 'errorMessage'));
        $this->assertSame(__FILE__, self::stringAt(array: $data, key: 'errorFile'));
        $this->assertSame('12', self::stringAt(array: $data, key: 'errorCode'));
        $this->assertStringContainsString('ExceptionHandlerTest', self::stringAt(array: $data, key: 'backtrace'));
        $this->assertStringContainsString("'id' => '7'", self::stringAt(array: $data, key: 'vardump_get'));
    }

    public function testDebugTextHasTheErrorAndTheDetails(): void
    {
        $handler = $this->register(context: $this->createContext(isDebug: true));
        $handler->setContentHandler(
            contentHandler: new ContentHandler(
                contentType: ContentType::createTxt(),
                cspNonce: new CspNonce(value: ExceptionHandlerContextFactory::NONCE),
            ),
        );

        $content = self::content(response: $handler->createResponse(throwable: new RuntimeException('Broken', 3)));

        $this->assertStringStartsWith('error: Broken (3)', $content);
        $this->assertStringContainsString('[errorType] => RuntimeException', $content);
    }

    // Extension point

    public function testProjectHandlerCanAnswerUnexpectedErrorsItself(): void
    {
        $handler = $this->register(
            handler: new TeapotExceptionHandler(htmlReplacementCollection: $this->createReplacements()),
        );

        $response = $handler->createResponse(throwable: new RuntimeException('Broken'));

        $this->assertSame(HttpStatusCodeEnum::HTTP_BAD_GATEWAY, $response->httpStatusCode);
        $this->assertStringContainsString('<h1>Default error page</h1>', self::content(response: $response));
    }

    public function testProjectHandlerStillLogsUnexpectedErrors(): void
    {
        $handler = $this->register(
            handler: new TeapotExceptionHandler(htmlReplacementCollection: $this->createReplacements()),
        );
        $exception = new RuntimeException('Broken');

        $handler->createResponse(throwable: $exception);

        $this->assertSame([$exception], $this->logger->loggedExceptions);
    }

    public function testProjectHandlerAnswersJsonWithItsMessage(): void
    {
        $handler = new TeapotExceptionHandler();
        $this->register(handler: $handler);
        $handler->setContentHandler(
            contentHandler: new ContentHandler(
                contentType: ContentType::createJson(),
                cspNonce: new CspNonce(value: ExceptionHandlerContextFactory::NONCE),
            ),
        );

        $response = $handler->createResponse(throwable: new RuntimeException('Broken'));

        $this->assertSame(
            ['success' => false, 'error' => ['code' => 502, 'message' => 'Try again later']],
            self::decodeJson(response: $response),
        );
    }
}
