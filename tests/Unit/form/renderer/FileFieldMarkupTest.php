<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The HTML of the file field. The expected strings were rendered by yuf v3.3.2 for the same state (the uploads that
 * need no file system access: errors, removing and keeping files), with the German texts (`FormMessages::german()`).
 * The only change on purpose: the text of the remove button comes from `FormMessages::removeFile`.
 */
final class FileFieldMarkupTest extends TestCase
{
    private const string POINTER = 'ptr1';
    private const string FIRST_PATH = '/tmp/v332files/a';
    private const string SECOND_PATH = '/tmp/v332files/b';
    private static int $formCounter = 0;

    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    /**
     * @param list<string> $names
     * @param list<int> $errors
     * @param list<int> $sizes
     * @return array<string, array<string, list<string|int>>>
     */
    private static function upload(array $names, array $errors, array $sizes): array
    {
        return [
            'file' => [
                'name' => $names,
                'type' => array_fill(start_index: 0, count: count(value: $names), value: 'text/plain'),
                'tmp_name' => array_fill(start_index: 0, count: count(value: $names), value: '/nonexistent/php123'),
                'error' => $errors,
                'size' => $sizes,
            ],
        ];
    }

    private function createStorage(bool $withFirstFile): InMemoryFileUploadStorage
    {
        $storage = new InMemoryFileUploadStorage();
        if ($withFirstFile) {
            $storage->preload(
                FileFieldMarkupTest::POINTER,
                new UploadedFile(name: 'first.txt', type: 'text/plain', size: 1, path: FileFieldMarkupTest::FIRST_PATH),
            );
        }

        return $storage;
    }

    /**
     * @param array<array-key, mixed> $inputData
     */
    #[DataProvider('validationProvider')]
    public function testFormHtmlAfterValidationEqualsV332(
        array $inputData,
        int $maxFileUploadCount,
        bool $withFirstFile,
        ?HtmlText $tooManyFilesErrMsg,
        ?HtmlText $alreadyExistsErrorMessage,
        string $expectedHtml,
    ): void {
        $formName = 'fileForm' . FileFieldMarkupTest::$formCounter++;
        $form = new Form(name: $formName, acceptUpload: true, messages: FormMessages::german());
        $form->removeCsrfProtection();
        $field = new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            requiredError: HtmlText::encoded(textContent: 'Req'),
            maxFileUploadCount: $maxFileUploadCount,
            tooManyFilesErrMsg: $tooManyFilesErrMsg,
            alreadyExistsErrorMessage: $alreadyExistsErrorMessage,
            storage: $this->createStorage(withFirstFile: $withFirstFile),
        );
        $form->addField(formField: $field);

        $field->validate(input: $this->request($inputData + ['file_UID' => FileFieldMarkupTest::POINTER]));

        $this->assertSame(str_replace(search: '{form}', replace: $formName, subject: $expectedHtml), $form->render());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, int, bool, ?HtmlText, ?HtmlText, string}>
     */
    public static function validationProvider(): iterable
    {
        yield 'form_empty_max3' => [
            [],
            3,
            false,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="3">'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Req</div></dd>'
              . '</dl></form>',
        ];
        yield 'form_preloaded_required_ok' => [
            [],
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd><div class="fileupload-enhanced" data-max-files="2"><ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul><input type="file" name="file[]" id="file" multiple>'
              . '<input type="hidden" name="file_UID" value="ptr1"></div></dd></dl></form>',
        ];
        yield 'too_big' => [
            FileFieldMarkupTest::upload(names: ['a "q".txt'], errors: [UPLOAD_ERR_INI_SIZE], sizes: [0]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Die Datei war zu '
              . 'gross: a &quot;q&quot;.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'form_size' => [
            FileFieldMarkupTest::upload(names: ['b.txt'], errors: [UPLOAD_ERR_FORM_SIZE], sizes: [0]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Die Datei war zu '
              . 'gross: b.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'partial' => [
            FileFieldMarkupTest::upload(names: ['<c>.txt'], errors: [UPLOAD_ERR_PARTIAL], sizes: [5]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Die Datei wurde '
              . 'unvollständig hochgeladen: &lt;c&gt;.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'no_tmp' => [
            FileFieldMarkupTest::upload(names: ['d.txt'], errors: [UPLOAD_ERR_NO_TMP_DIR], sizes: [5]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Es ist ein '
              . 'technischer Fehler beim Hochladen der Datei aufgetreten: d.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'cant_write' => [
            FileFieldMarkupTest::upload(names: ['d.txt'], errors: [UPLOAD_ERR_CANT_WRITE], sizes: [5]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Es ist ein '
              . 'technischer Fehler beim Hochladen der Datei aufgetreten: d.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'no_file' => [
            FileFieldMarkupTest::upload(names: [''], errors: [UPLOAD_ERR_NO_FILE], sizes: [0]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd><div class="fileupload-enhanced" data-max-files="2"><ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul><input type="file" name="file[]" id="file" multiple>'
              . '<input type="hidden" name="file_UID" value="ptr1"></div></dd></dl></form>',
        ];
        yield 'empty_file' => [
            FileFieldMarkupTest::upload(names: ['e.txt'], errors: [UPLOAD_ERR_OK], sizes: [0]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Die Datei war '
              . 'leer: e.txt</div>'
              . '</dd></dl></form>',
        ];
        yield 'dup' => [
            FileFieldMarkupTest::upload(names: ['first.txt'], errors: [UPLOAD_ERR_OK], sizes: [5]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Es wurde bereits '
              . 'eine Datei mit dem Dateinamen "first.txt" hochgeladen.</div>'
              . '</dd></dl></form>',
        ];
        yield 'dup_quote' => [
            FileFieldMarkupTest::upload(names: ['first.txt'], errors: [UPLOAD_ERR_OK], sizes: [5]),
            3,
            true,
            null,
            HtmlText::encoded(textContent: 'Dup <i>[fileName]</i>'),
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Dup '
              . '<i>first.txt</i>'
              . '</div></dd></dl></form>',
        ];
        yield 'too_many' => [
            FileFieldMarkupTest::upload(names: ['1.txt', '2.txt', '3.txt'], errors: [0, 0, 0], sizes: [5, 5, 5]),
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Nur 3 Datei(en) '
              . 'möglich.</div>'
              . '</dd></dl></form>',
        ];
        yield 'too_many_individual' => [
            FileFieldMarkupTest::upload(names: ['1.txt', '2.txt', '3.txt'], errors: [0, 0, 0], sizes: [5, 5, 5]),
            3,
            true,
            HtmlText::encoded(textContent: 'Max <b>[max]</b>'),
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="2">'
              . '<ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul>'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Max <b>3</b>'
              . '</div></dd></dl></form>',
        ];
        yield 'remove' => [
            ['file_removeAttachment' => sha1(string: FileFieldMarkupTest::FIRST_PATH)],
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="3">'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Req</div></dd>'
              . '</dl></form>',
        ];
        yield 'remove_unknown' => [
            ['file_removeAttachment' => 'zzz'],
            3,
            true,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 3)</i></label>'
              . '</dt><dd><div class="fileupload-enhanced" data-max-files="2"><ul class="fileupload-list"><li>'
              . '<span>first.txt</span> <button type="submit" name="file_removeAttachment" '
              . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
              . '</li></ul><input type="file" name="file[]" id="file" multiple>'
              . '<input type="hidden" name="file_UID" value="ptr1"></div></dd></dl></form>',
        ];
        yield 'mixed_errors' => [
            FileFieldMarkupTest::upload(
                names: ['big.txt', 'ok-empty.txt', 'x.txt'],
                errors: [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
                sizes: [0, 0, 0],
            ),
            5,
            false,
            null,
            null,
            '<form method="post" action="?{form}" enctype="multipart/form-data"><dl><dt>'
              . '<label for="file">File<span class="required">*</span><i class="label-info">(max. 5)</i></label>'
              . '</dt><dd class="has-error"><div class="fileupload-enhanced" data-max-files="5">'
              . '<input type="file" name="file[]" id="file" multiple aria-invalid="true" '
              . 'aria-describedby="file-error">'
              . '<input type="hidden" name="file_UID" value="ptr1"></div>'
              . '<div class="form-input-error" id="file-error" role="alert" aria-live="assertive">Die Datei war zu '
              . 'gross: big.txt<br>Die Datei war leer: ok-empty.txt<br>Req</div>'
              . '</dd></dl></form>',
        ];
    }

    private function createFieldWithTwoFiles(int $maxFileUploadCount): FileField
    {
        $storage = new InMemoryFileUploadStorage();
        $storage->preload(
            FileFieldMarkupTest::POINTER,
            new UploadedFile(
                name: 'first "file".txt',
                type: 'text/plain',
                size: 1,
                path: FileFieldMarkupTest::FIRST_PATH,
            ),
            new UploadedFile(
                name: '<b>second</b>.pdf',
                type: 'application/pdf',
                size: 1,
                path: FileFieldMarkupTest::SECOND_PATH,
            ),
        );
        $field = new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            requiredError: HtmlText::encoded(textContent: 'Req'),
            maxFileUploadCount: $maxFileUploadCount,
            storage: $storage,
        );
        $field->messages = FormMessages::german();
        $field->validate(input: $this->request(['file_UID' => FileFieldMarkupTest::POINTER]));

        return $field;
    }

    public function testFieldWithTwoFilesAndRoomForMoreEqualsV332(): void
    {
        $this->assertSame(
            '<div class="fileupload" data-max-files="1"><ul class="fileupload-list"><li>'
            . '<span>first &quot;file&quot;.txt</span> <button type="submit" name="file_removeAttachment" '
            . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
            . '</li><li>'
            . '<span>&lt;b&gt;second&lt;/b&gt;.pdf</span> <button type="submit" name="file_removeAttachment" '
            . 'value="f50dd1d7180e89a360be1c2912862904aebf506f">löschen</button>'
            . '</li></ul><input type="file" name="file[]" id="file">'
            . '<input type="hidden" name="file_UID" value="ptr1"></div>',
            $this->createFieldWithTwoFiles(maxFileUploadCount: 3)->render(),
        );
    }

    public function testFieldWithTwoFilesAndNoRoomEqualsV332(): void
    {
        $this->assertSame(
            '<div class="fileupload" data-max-files="0"><ul class="fileupload-list"><li>'
            . '<span>first &quot;file&quot;.txt</span> <button type="submit" name="file_removeAttachment" '
            . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
            . '</li><li>'
            . '<span>&lt;b&gt;second&lt;/b&gt;.pdf</span> <button type="submit" name="file_removeAttachment" '
            . 'value="f50dd1d7180e89a360be1c2912862904aebf506f">löschen</button>'
            . '</li></ul><input type="file" name="file[]" id="file">'
            . '<input type="hidden" name="file_UID" value="ptr1"></div>',
            $this->createFieldWithTwoFiles(maxFileUploadCount: 2)->render(),
        );
    }

    public function testFieldWithFilesAndErrorEqualsV332(): void
    {
        $field = $this->createFieldWithTwoFiles(maxFileUploadCount: 3);
        $field->addError(errorMessage: HtmlText::unencoded(textContent: 'Boom'));

        $this->assertSame(
            '<div class="fileupload" data-max-files="1"><ul class="fileupload-list"><li>'
            . '<span>first &quot;file&quot;.txt</span> <button type="submit" name="file_removeAttachment" '
            . 'value="25b7df71caccd1b9b20886b5ad455874d00d8e1c">löschen</button>'
            . '</li><li>'
            . '<span>&lt;b&gt;second&lt;/b&gt;.pdf</span> <button type="submit" name="file_removeAttachment" '
            . 'value="f50dd1d7180e89a360be1c2912862904aebf506f">löschen</button>'
            . '</li></ul>'
            . '<input type="file" name="file[]" id="file" aria-invalid="true" aria-describedby="file-error">'
            . '<input type="hidden" name="file_UID" value="ptr1"></div>',
            $field->render(),
        );
    }

    private function createEmptyField(int $maxFileUploadCount): FileField
    {
        return new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            maxFileUploadCount: $maxFileUploadCount,
            storage: new InMemoryFileUploadStorage(),
        );
    }

    public function testEmptySingleFileFieldEqualsV332(): void
    {
        $field = $this->createEmptyField(maxFileUploadCount: 1);

        $this->assertSame(
            '<div class="fileupload" data-max-files="1"><input type="file" name="file[]" id="file">'
            . '<input type="hidden" name="file_UID" value="' . $field->uniqueSessFileStorePointer . '"></div>',
            $field->render(),
        );
    }

    public function testEmptyMultiFileFieldEqualsV332(): void
    {
        $field = $this->createEmptyField(maxFileUploadCount: 3);

        $this->assertSame(
            '<div class="fileupload-enhanced" data-max-files="3"><input type="file" name="file[]" id="file" multiple>'
            . '<input type="hidden" name="file_UID" value="' . $field->uniqueSessFileStorePointer . '"></div>',
            $field->render(),
        );
    }

    public function testRemoveButtonUsesTheEnglishDefaultText(): void
    {
        $field = $this->createFieldWithTwoFiles(maxFileUploadCount: 3);
        $field->messages = new FormMessages();

        $html = $field->render();

        $this->assertStringContainsString('>remove</button>', $html);
        $this->assertStringNotContainsString('löschen', $html);
    }

    public function testRemoveButtonTextIsEncoded(): void
    {
        $field = $this->createFieldWithTwoFiles(maxFileUploadCount: 3);
        $field->messages = new FormMessages(removeFile: 'x<y');

        $this->assertStringContainsString('>x&lt;y</button>', $field->render());
    }

    /**
     * @param array<array-key, mixed> $request Posted values and uploads in one array (`FormInput` reads both from it)
     */
    private function request(array $request): FormInput
    {
        return FormInput::fromArray(data: [], files: $request);
    }
}
