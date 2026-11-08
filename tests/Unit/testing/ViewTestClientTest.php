<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\testing;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\ViewContext;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\testing\ViewTestClient;
use actra\yuf\testing\ViewTestResult;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use actra\yuf\tests\Double\testing\TwoStepTestView;
use ArrayObject;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

final class ViewTestClientTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private ViewTestClient $client;

    #[Override]
    protected function setUp(): void
    {
        TwoStepTestView::$sentMails = [];
        $this->workDirectory = new TemplateWorkDirectory();
        $root = $this->workDirectory->templateDirectory;
        ViewTestClientTest::write(
            path: $root . 'view/frontend/html/step1.html',
            content: '<form method="post">{tst:text value=\'csrfField\'}</form>',
        );
        ViewTestClientTest::write(
            path: $root . 'view/frontend/html/step2.html',
            content: '<p lang="{tst:text value=\'language\'}">{tst:text value=\'email\'} {tst:lang key=\'greeting\'}</p>',
        );
        ViewTestClientTest::write(
            path: $root . 'view/frontend/language/de/step2.lang.php',
            content: '<?php $txt[\'greeting\'] = \'Hallo\';',
        );
        $this->client = new ViewTestClient(
            viewDirectory: $root . 'view/',
            viewGroup: 'frontend',
            templateBaseDirectory: $root,
            cacheDirectory: $this->workDirectory->cacheDirectory,
            host: 'www.example.com',
            language: new Language(code: 'de', locale: 'de_CH.UTF-8'),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    public function testViewRendersItsPage(): void
    {
        $result = $this->runView(fileTitle: 'step1');

        $this->assertNull($result->sentResponse);
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $result->httpStatusCode);
        $this->assertStringContainsString(
            '<input type="hidden" name="csrftoken" value="' . $this->client->getCsrfToken() . '">',
            $result->content,
        );
        $this->assertSame('/step1.html', $result->context->httpRequest->getUri());
        $this->assertSame('www.example.com', $result->context->httpRequest->getHost());
    }

    public function testTwoStepFormKeepsTheSessionAndRunsTheWorkAfterTheResponse(): void
    {
        $posted = $this->runView(fileTitle: 'step1', method: RequestMethodEnum::POST, post: ['email' => 'a@example.com']);

        $this->assertSame('https://www.example.com/step2.html', $posted->getLocation());
        $this->assertSame(['a@example.com'], TwoStepTestView::$sentMails);
        $page = $this->runView(fileTitle: 'step2');
        $this->assertSame('<p lang="de">a@example.com Hallo</p>', $page->content);
        $this->assertTrue($page->getHtmlDocument()->replacements->has(identifier: 'email'));
    }

    public function testWorkAfterTheResponseCanBeLeftOut(): void
    {
        $this->runView(
            fileTitle: 'step1',
            method: RequestMethodEnum::POST,
            post: ['email' => 'a@example.com'],
            runAfterResponseCallbacks: false,
        );

        $this->assertSame([], TwoStepTestView::$sentMails);
    }

    public function testPostWithAnotherCsrfTokenIsRefused(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->runView(fileTitle: 'step1', method: RequestMethodEnum::POST, post: ['csrftoken' => 'wrong']);
    }

    public function testQueryIsPartOfTheRequest(): void
    {
        $result = $this->runView(fileTitle: 'step1', query: ['returnTo' => '/a b']);

        $this->assertSame('/step1.html?returnTo=%2Fa%20b', $result->context->httpRequest->getUri());
        $this->assertSame('/a b', $result->context->httpRequest->getQueryString(name: 'returnTo'));
    }

    public function testMissingContentFileIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->runView(fileTitle: 'missing');
    }

    public function testPathVarsTheViewDoesNotAllowAreNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->runView(fileTitle: 'step1', pathVars: ['2']);
    }

    public function testHtmlDocumentOfASentResponseThrows(): void
    {
        $result = $this->runView(fileTitle: 'step1', method: RequestMethodEnum::POST, post: ['email' => 'a@example.com']);

        $this->expectException(LogicException::class);

        $result->getHtmlDocument();
    }

    public function testContextSharesTheSession(): void
    {
        $this->runView(fileTitle: 'step1', method: RequestMethodEnum::POST, post: ['email' => 'a@example.com']);

        $context = $this->client->createContext(fileTitle: 'step2', cookies: ['a' => 'b']);

        $this->assertSame('a@example.com', $context->session?->getString(key: 'email'));
        $this->assertSame('b', $context->httpRequest->getCookie(name: 'a'));
        $this->assertSame('de', $context->locale->language?->code);
    }

    public function testCaptureRunsTheWorkAfterTheResponseOnRequest(): void
    {
        $runs = new ArrayObject();
        $action = static function (ResponseSender $responseSender) use ($runs): void {
            $responseSender->afterResponse(callback: static function () use ($runs): void {
                $runs->append(value: 'run');
            });
            $responseSender->send(httpResponse: HttpResponse::createStatusResponse(
                httpStatusCode: HttpStatusCodeEnum::HTTP_NO_CONTENT,
            ));
        };

        RecordingResponseSender::capture(action: $action);
        $this->assertCount(0, $runs);
        RecordingResponseSender::capture(action: $action, runAfterResponseCallbacks: true);
        $this->assertCount(1, $runs);
    }

    private static function write(string $path, string $content): void
    {
        if (!is_dir(filename: dirname(path: $path))) {
            mkdir(directory: dirname(path: $path), recursive: true);
        }
        file_put_contents(filename: $path, data: $content);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     * @param list<string> $pathVars
     */
    private function runView(
        string $fileTitle,
        RequestMethodEnum $method = RequestMethodEnum::GET,
        array $query = [],
        array $post = [],
        array $pathVars = [],
        bool $runAfterResponseCallbacks = true,
    ): ViewTestResult {
        return $this->client->run(
            viewFactory: static fn(ViewContext $context): TwoStepTestView => new TwoStepTestView(context: $context),
            fileTitle: $fileTitle,
            method: $method,
            query: $query,
            post: $post,
            pathVars: $pathVars,
            runAfterResponseCallbacks: $runAfterResponseCallbacks,
        );
    }
}
