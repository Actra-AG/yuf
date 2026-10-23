<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\clock\FixedClock;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormOptions;
use actra\yuf\form\spam\FormRateLimit;
use actra\yuf\form\spam\SpamProtection;
use actra\yuf\form\upload\UploadFileType;
use actra\yuf\html\HtmlText;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\form\FixedFileTypeDetector;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use actra\yuf\tests\Double\form\RecordingFieldListener;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The rate limit of the spam protection in `Form::validate()`: every submit with a valid CSRF token is counted, a
 * blocked submit keeps the typed values without processing them.
 */
final class FormRateLimitTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    // 12:00:00 UTC: the start of a window of 600 seconds
    private const int NOW = 1_800_000_000;

    private string $directory;
    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-form-rate-limit-'
            . bin2hex(string: random_bytes(length: 8));
        $this->logger = new RecordingLogger();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file(filename: $this->directory)) {
            unlink(filename: $this->directory);

            return;
        }
        if (!is_dir(filename: $this->directory)) {
            return;
        }
        $files = scandir(directory: $this->directory);
        foreach ($files === false ? [] : $files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink(filename: $this->directory . DIRECTORY_SEPARATOR . $file);
            }
        }
        rmdir(directory: $this->directory);
    }

    private function createRateLimit(
        int $maxHits = 2,
        RateLimitFailModeEnum $failMode = RateLimitFailModeEnum::CLOSED,
        int $globalMaxHits = 0,
    ): FormRateLimit {
        return new FormRateLimit(
            rateLimiter: new RateLimiter(
                directory: $this->directory,
                secret: FormRateLimitTest::SECRET,
                clock: FormRateLimitTest::clock(),
                purgeProbabilityPercent: 0,
            ),
            failMode: $failMode,
            logger: $this->logger,
            clientLimits: [new RateLimit(maxHits: $maxHits, windowSeconds: 600)],
            globalLimits: $globalMaxHits === 0 ? [] : [new RateLimit(maxHits: $globalMaxHits, windowSeconds: 600)],
        );
    }

    private static function clock(): FixedClock
    {
        return new FixedClock(now: new DateTimeImmutable(datetime: '@' . FormRateLimitTest::NOW));
    }

    private function createForm(
        ?FormRateLimit $rateLimit,
        string $ipAddress = '192.0.2.1',
        FormMessages $messages = new FormMessages(),
        string $name = 'contact',
        bool $acceptUpload = false,
    ): Form {
        $form = new Form(
            context: FormContextFactory::create(
                httpRequest: HttpRequestFactory::create(remoteAddress: $ipAddress),
                csrfTokenSource: new InMemoryCsrfTokenSource(token: 'expected-token'),
            ),
            name: $name,
            acceptUpload: $acceptUpload,
            messages: $messages,
        );
        $form->addField(
            formField: new TextField(
                name: 'name',
                label: HtmlText::fromText(text: 'Name'),
                requiredError: HtmlText::fromText(text: 'required'),
            ),
        );
        $form->addSpamProtection(
            spamProtection: new SpamProtection(
                clock: FormRateLimitTest::clock(),
                secret: FormRateLimitTest::SECRET,
                rateLimit: $rateLimit,
                emailField: null,
                rejectedEmailAddresses: [],
            ),
        );

        return $form;
    }

    /**
     * @param array<string, string> $post
     * @param array<array-key, mixed> $files
     */
    private function send(Form $form, array $post, array $files = []): bool
    {
        return $form->validate(
            input: FormInput::fromArray(
                data: $post + ['csrftoken' => 'expected-token'],
                files: $files,
                query: [$form->sentIndicator => ''],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private static function errors(Form $form): array
    {
        return array_map(
            callback: static fn(HtmlText $error): string => $error->render(),
            array: $form->errorCollection->listErrors(),
        );
    }

    public function testRequiresAClientLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FormRateLimit(
            rateLimiter: new RateLimiter(directory: $this->directory, secret: FormRateLimitTest::SECRET),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $this->logger,
            clientLimits: [],
        );
    }

    public function testDefaultClientLimits(): void
    {
        $rateLimit = new FormRateLimit(
            rateLimiter: new RateLimiter(directory: $this->directory, secret: FormRateLimitTest::SECRET),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $this->logger,
        );

        $this->assertEquals(
            [new RateLimit(maxHits: 10, windowSeconds: 600), new RateLimit(maxHits: 50, windowSeconds: 86400)],
            $rateLimit->clientLimits,
        );
        $this->assertSame([], $rateLimit->globalLimits);
    }

    public function testCountsValidAndInvalidSubmitsAndBlocksAfterTheLimit(): void
    {
        $rateLimit = $this->createRateLimit();

        // Invalid (required field empty) and spam (no render time): both count
        $this->assertFalse($this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => '']));
        $this->assertFalse($this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']));
        $form = $this->createForm(rateLimit: $rateLimit);
        $this->assertFalse($this->send(form: $form, post: ['name' => 'Ann']));

        $this->assertSame(['Too many submissions. Please try again in a few minutes.'], FormRateLimitTest::errors($form));
        $this->assertSame(600, $form->retryAfterSeconds);
    }

    public function testGermanMessage(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);
        $form = $this->createForm(rateLimit: $rateLimit, messages: FormMessages::german());

        $this->send(form: $form, post: ['name' => 'Ann']);

        $this->assertSame(
            ['Zu viele Übermittlungen. Bitte versuchen Sie es in einigen Minuten erneut.'],
            FormRateLimitTest::errors($form),
        );
    }

    public function testNotBlockedSubmitHasNoRetryTime(): void
    {
        $form = $this->createForm(rateLimit: $this->createRateLimit());

        $this->send(form: $form, post: ['name' => '']);

        $this->assertSame(0, $form->retryAfterSeconds);
        $this->assertSame(['required'], array_map(
            callback: static fn(HtmlText $error): string => $error->render(),
            array: $form->getField(name: 'name')->errorCollection->listErrors(),
        ));
    }

    public function testBlockedSubmitKeepsTheTypedValuesWithoutRulesAndListeners(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);
        $form = $this->createForm(rateLimit: $rateLimit);
        $form->addField(
            formField: $required = new TextField(
                name: 'city',
                label: HtmlText::fromText(text: 'City'),
                requiredError: HtmlText::fromText(text: 'required'),
            ),
        );
        $listener = new RecordingFieldListener();
        $required->addListener(formFieldListener: $listener);

        $this->assertFalse($this->send(form: $form, post: ['name' => 'Bob <b>', 'city' => '']));

        $this->assertSame([], $listener->calls);
        $this->assertFalse($required->hasErrors(withChildElements: true));
        $this->assertStringContainsString('value="Bob &lt;b&gt;"', $form->render());
    }

    public function testBlockedSubmitKeepsTheValuesOfToggleChildren(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);
        $form = $this->createForm(rateLimit: $rateLimit);
        $options = new FormOptions();
        $options->addItem(key: 'pickup', htmlText: HtmlText::fromText(text: 'Pickup'));
        $options->addItem(key: 'post', htmlText: HtmlText::fromText(text: 'Post'));
        $form->addField(
            formField: $delivery = new ToggleField(
                name: 'delivery',
                label: HtmlText::fromText(text: 'Delivery'),
                formOptions: $options,
                initialValue: 'pickup',
            ),
        );
        $delivery->addChildField(
            mainOption: 'post',
            childField: $street = new TextField(name: 'street', label: HtmlText::fromText(text: 'Street')),
        );

        $this->send(form: $form, post: ['name' => 'Ann', 'delivery' => 'post', 'street' => 'Main Street 1']);

        $this->assertSame('post', $delivery->getValueAsString());
        $this->assertSame('Main Street 1', $street->getValueAsString());
    }

    public function testBlockedSubmitStoresNoUpload(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $this->send(form: $this->createForm(rateLimit: $rateLimit, acceptUpload: true), post: ['name' => 'Ann']);
        $form = $this->createForm(rateLimit: $rateLimit, acceptUpload: true);
        $storage = new InMemoryFileUploadStorage();
        $form->addField(
            formField: new FileField(
                name: 'file',
                label: HtmlText::fromText(text: 'File'),
                storage: $storage,
                allowedFileTypes: [UploadFileType::pdf()],
                fileTypeDetector: new FixedFileTypeDetector(defaultType: 'application/pdf'),
            ),
        );

        $this->send(form: $form, post: ['name' => 'Ann'], files: [
            'file' => ['name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 5],
        ]);

        $this->assertSame([], $storage->getStoredUploads());
    }

    public function testInvalidCsrfTokenIsNotCounted(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $form = $this->createForm(rateLimit: $rateLimit);
        $form->validate(
            input: FormInput::fromArray(
                data: ['name' => 'Ann', 'csrftoken' => 'wrong'],
                query: [$form->sentIndicator => ''],
            ),
        );
        $form = $this->createForm(rateLimit: $rateLimit);

        $this->send(form: $form, post: ['name' => 'Ann']);

        $this->assertSame(0, $form->retryAfterSeconds);
    }

    public function testCountsPerClientAndForm(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1);
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);

        $otherClient = $this->createForm(rateLimit: $rateLimit, ipAddress: '192.0.2.2');
        $this->send(form: $otherClient, post: ['name' => 'Ann']);
        $otherForm = $this->createForm(rateLimit: $rateLimit, name: 'order');
        $this->send(form: $otherForm, post: ['name' => 'Ann']);

        $this->assertSame(0, $otherClient->retryAfterSeconds);
        $this->assertSame(0, $otherForm->retryAfterSeconds);
    }

    public function testGlobalLimitIsCheckedAfterTheClientLimit(): void
    {
        $rateLimit = $this->createRateLimit(maxHits: 1, globalMaxHits: 2);
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);
        // Blocked by its own limit: uses up nothing of the global limit
        $this->send(form: $this->createForm(rateLimit: $rateLimit), post: ['name' => 'Ann']);
        $second = $this->createForm(rateLimit: $rateLimit, ipAddress: '192.0.2.2');
        $this->send(form: $second, post: ['name' => 'Ann']);
        $third = $this->createForm(rateLimit: $rateLimit, ipAddress: '192.0.2.3');

        $this->send(form: $third, post: ['name' => 'Ann']);

        $this->assertSame(0, $second->retryAfterSeconds);
        $this->assertSame(600, $third->retryAfterSeconds);
    }

    public function testFailClosedBlocksAndLogs(): void
    {
        // A file where the directory should be: the counter cannot be written
        touch(filename: $this->directory);
        $form = $this->createForm(rateLimit: $this->createRateLimit(failMode: RateLimitFailModeEnum::CLOSED));

        $this->assertFalse($this->send(form: $form, post: ['name' => 'Ann']));

        $this->assertSame(['Too many submissions. Please try again in a few minutes.'], FormRateLimitTest::errors($form));
        $this->assertCount(1, $this->logger->loggedExceptions);
        $this->assertInstanceOf(RuntimeException::class, $this->logger->loggedExceptions[0]);
    }

    public function testFailOpenAllowsAndLogs(): void
    {
        touch(filename: $this->directory);
        $form = $this->createForm(rateLimit: $this->createRateLimit(failMode: RateLimitFailModeEnum::OPEN));

        $this->send(form: $form, post: ['name' => 'Ann']);

        $this->assertSame(0, $form->retryAfterSeconds);
        $this->assertNotContains('Too many submissions. Please try again in a few minutes.', FormRateLimitTest::errors($form));
        $this->assertCount(1, $this->logger->loggedExceptions);
    }

    public function testInvalidClientAddressUsesTheFailMode(): void
    {
        $form = $this->createForm(rateLimit: $this->createRateLimit(), ipAddress: '');

        $this->send(form: $form, post: ['name' => 'Ann']);

        $this->assertSame(60, $form->retryAfterSeconds);
        $this->assertInstanceOf(InvalidArgumentException::class, array_first($this->logger->loggedExceptions));
    }

    public function testOptOutCountsNothing(): void
    {
        $form = $this->createForm(rateLimit: null);

        $this->send(form: $form, post: ['name' => 'Ann']);

        $this->assertSame(0, $form->retryAfterSeconds);
        $this->assertDirectoryDoesNotExist($this->directory);
    }
}
