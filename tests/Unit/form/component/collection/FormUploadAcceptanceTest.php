<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\core\RequestMethodEnum;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\upload\UploadFileType;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use actra\yuf\tests\Double\form\RecordingFieldListener;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only a form with `acceptUpload: true` takes file fields and uploads; any other form rejects a request with uploads
 * or a multipart body before it reads a field.
 */
final class FormUploadAcceptanceTest extends TestCase
{
    private function createForm(bool $acceptUpload): Form
    {
        return new Form(
            context: FormContextFactory::create(),
            name: 'contact',
            acceptUpload: $acceptUpload,
            csrfProtection: false,
        );
    }

    private function createFileField(): FileField
    {
        return new FileField(
            name: 'file',
            label: HtmlText::fromText(text: 'File'),
            storage: new InMemoryFileUploadStorage(),
            allowedFileTypes: [UploadFileType::pdf()],
        );
    }

    public function testFileFieldNeedsAcceptUpload(): void
    {
        $form = $this->createForm(acceptUpload: false);

        $this->expectException(LogicException::class);

        $form->addField(formField: $this->createFileField());
    }

    public function testFileFieldInFormWithAcceptUpload(): void
    {
        $form = $this->createForm(acceptUpload: true);

        $form->addField(formField: $this->createFileField());

        $this->assertTrue($form->hasField(name: 'file'));
    }

    /**
     * @return array<string, array{array<array-key, mixed>, bool}>
     */
    public static function rejectedInput(): array
    {
        return [
            'upload' => [
                ['file' => ['name' => 'a.php', 'type' => 'text/plain', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 1]],
                false,
            ],
            'empty upload entry' => [
                ['file' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]],
                false,
            ],
            'multipart body without files' => [[], true],
        ];
    }

    /**
     * @param array<array-key, mixed> $files
     */
    #[DataProvider('rejectedInput')]
    public function testFormWithoutAcceptUploadRejectsUploadsAndMultipart(array $files, bool $isMultipart): void
    {
        $form = $this->createForm(acceptUpload: false);
        $form->addField(formField: $name = new TextField(name: 'name', label: HtmlText::fromText(text: 'Name')));
        $listener = new RecordingFieldListener();
        $name->addListener(formFieldListener: $listener);

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['name' => 'Ann'],
                files: $files,
                query: [$form->sentIndicator => ''],
                isMultipart: $isMultipart,
            ),
        );

        $this->assertFalse($isValid);
        $this->assertSame('The invalid input was ignored.', $form->errorCollection->getFirstError()->render());
        $this->assertSame([], $listener->calls);
    }

    public function testFormWithAcceptUploadTakesAMultipartBody(): void
    {
        $form = $this->createForm(acceptUpload: true);
        $form->addField(formField: new TextField(name: 'name', label: HtmlText::fromText(text: 'Name')));

        $isValid = $form->validate(
            input: FormInput::fromArray(data: ['name' => 'Ann'], query: [$form->sentIndicator => ''], isMultipart: true),
        );

        $this->assertTrue($isValid);
    }

    public function testFormWithoutAcceptUploadTakesAnUrlEncodedBody(): void
    {
        $form = $this->createForm(acceptUpload: false);
        $form->addField(formField: new TextField(name: 'name', label: HtmlText::fromText(text: 'Name')));

        $this->assertTrue(
            $form->validate(input: FormInput::fromArray(data: ['name' => 'Ann'], query: [$form->sentIndicator => ''])),
        );
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function contentTypes(): array
    {
        return [
            'multipart' => ['multipart/form-data; boundary=x', true],
            'upper case' => [' Multipart/Form-Data; boundary=x', true],
            'url-encoded' => ['application/x-www-form-urlencoded', false],
        ];
    }

    #[DataProvider('contentTypes')]
    public function testInputOfTheRequestKnowsAMultipartBody(string $contentType, bool $isMultipart): void
    {
        $input = FormInput::fromHttpRequest(
            httpRequest: HttpRequestFactory::create(
                method: RequestMethodEnum::POST,
                headers: ['content-type' => $contentType],
            ),
            methodPost: true,
        );

        $this->assertSame($isMultipart, $input->isMultipart);
        $this->assertFalse($input->hasFiles);
    }

    public function testWithoutUploadsKeepsTheValues(): void
    {
        $input = FormInput::fromArray(
            data: ['name' => 'Ann'],
            files: ['file' => ['name' => 'a.pdf', 'type' => '', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 1]],
            isMultipart: true,
        );

        $withoutUploads = $input->withoutUploads();

        $this->assertTrue($input->hasFiles);
        $this->assertCount(1, $input->getUploads(name: 'file'));
        $this->assertFalse($withoutUploads->hasFiles);
        $this->assertSame([], $withoutUploads->getUploads(name: 'file'));
        $this->assertSame('Ann', $withoutUploads->getText(name: 'name'));
        $this->assertTrue($withoutUploads->isMultipart);
    }
}
