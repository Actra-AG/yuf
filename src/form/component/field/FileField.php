<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\model\UploadInput;
use actra\yuf\form\renderer\FileFieldRenderer;
use actra\yuf\form\upload\FileUploadStorage;
use actra\yuf\form\upload\SessionFileUploadStorage;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;

/**
 * Uploads one or several files. The value is the list of the files uploaded so far (`getFiles()`, key = hash of the
 * stored path), kept in a `FileUploadStorage` between the requests of the form, so a failed validation does not make
 * the user upload the files again. The field has no setter: files only come in with the request.
 */
final class FileField extends FormField
{
    private(set) string $uniqueSessFileStorePointer;
    private readonly FileUploadStorage $storage;
    /** @var array<string, UploadedFile> */
    private array $files = [];
    private ?string $deleteFileHash = null;

    /**
     * @param HtmlText|null $requiredError NULL, if file upload is not required, otherwise the error message if no
     *                                     file was uploaded
     * @param int $maxFileUploadCount Maximal amount of allowed files (1 by default) with that field
     * @param ?HtmlText $tooManyFilesErrMsg Individual error message if more than allowed amount of files are uploaded.
     *                                      Placeholder [max] will be replaced by the max amount. Default:
     *                                      `FormMessages::tooManyFiles`
     * @param HtmlText|null $alreadyExistsErrorMessage Individual error message if a file with that name has been
     *                                                 uploaded already. Placeholder [fileName]. Default:
     *                                                 `FormMessages::duplicateFile`
     * @param ?FileUploadStorage $storage Where the files are kept between the requests (default: session and temp
     *                                    directory)
     */
    public function __construct(
        string $name,
        HtmlText $label,
        ?HtmlText $requiredError = null,
        private(set) int $maxFileUploadCount = 1,
        private readonly ?HtmlText $tooManyFilesErrMsg = null,
        private readonly ?HtmlText $alreadyExistsErrorMessage = null,
        ?FileUploadStorage $storage = null
    ) {
        if ($this->maxFileUploadCount < 1) {
            $this->maxFileUploadCount = 1; // Silent correction
        }
        $this->storage = $storage ?? SessionFileUploadStorage::forCurrentRequest();
        $this->uniqueSessFileStorePointer = $this->sanitizePointer(
            pointer: uniqid(
                prefix: $name . '__',
                more_entropy: true
            )
        );
        parent::__construct(
            name: $name,
            label: $label,
            labelInfoText: $this->maxFileUploadCount === 1 ? null : HtmlText::encoded(
                textContent: '(max. ' . $this->maxFileUploadCount . ')'
            )
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
    }

    private function sanitizePointer(string $pointer): string
    {
        // We do not allow dangerous characters in the pointer, as it will become part of a filesystem path;
        // And we want to easily detect these later in the external input:
        return preg_replace(pattern: '/[^a-zA-Z\d_]/', replacement: '', subject: $pointer) ?? '';
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new FileFieldRenderer(fileField: $this);
    }

    /**
     * The files uploaded so far, by hash (`UploadedFile::getHash()`).
     *
     * @return array<string, UploadedFile>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Reads the request: removes the old files of all forms, takes over the pointer of the form, removes the file the
     * user asked to remove and adds the new uploads. Manipulated upload data adds one error and the rules do not run;
     * the files uploaded before stay. Texts, lists and invalid values posted under the name of the field are ignored.
     */
    final protected function readInput(FormInput $input): void
    {
        $this->storage->removeExpired();
        $files = $this->removeRequestedFile(
            files: $this->storage->load(pointer: $this->uniqueSessFileStorePointer)
        );
        if ($input->hasMalformedUpload(name: $this->name)) {
            $this->rejectInput(errorMessage: $this->messages->invalidInput);
        } else {
            $files = $this->addUploads(files: $files, uploads: $input->getUploads(name: $this->name));
        }
        $this->files = $files;
        $this->storage->save(pointer: $this->uniqueSessFileStorePointer, files: $files);
    }

    /**
     * The pointer of the files and the removal request come with the form, not with the files themselves.
     */
    protected function readAdditionalInput(FormInput $input): void
    {
        $this->readPointer(input: $input);
        $this->readRemoveRequest(input: $input);
    }

    /**
     * Takes the pointer of the files from the form. If that value is tampered by a "black-hat hacker", he should just
     * grab securely into an "empty bowl", therefore only a pointer with allowed characters is taken.
     */
    private function readPointer(FormInput $input): void
    {
        $receivedPointer = trim(string: $input->getText(name: $this->name . '_UID') ?? '');
        if ($receivedPointer !== '' && $this->sanitizePointer(pointer: $receivedPointer) === $receivedPointer) {
            $this->uniqueSessFileStorePointer = $receivedPointer;
        }
    }

    /**
     * Referenced usage at `FileFieldRenderer::prepare()`
     */
    private function readRemoveRequest(FormInput $input): void
    {
        $hash = $input->getText(name: $this->name . '_removeAttachment');
        if ($hash !== null) {
            $this->deleteFileHash = trim(string: $hash);
        }
    }

    /**
     * @param array<string, UploadedFile> $files
     * @return array<string, UploadedFile>
     */
    private function removeRequestedFile(array $files): array
    {
        if ($this->deleteFileHash === null || !array_key_exists(key: $this->deleteFileHash, array: $files)) {
            return $files;
        }
        $this->storage->delete(file: $files[$this->deleteFileHash]);
        unset($files[$this->deleteFileHash]);

        return $files;
    }

    /**
     * @param array<string, UploadedFile> $files
     * @param list<UploadInput> $uploads
     * @return array<string, UploadedFile> The files including the new ones, or the same files if there are too many
     */
    private function addUploads(array $files, array $uploads): array
    {
        // An entry without a file represents "no file selected"
        $newUploads = array_filter(
            array: $uploads,
            callback: static fn(UploadInput $upload): bool => $upload->error !== UPLOAD_ERR_NO_FILE
        );
        if (count(value: $files) + count(value: $newUploads) > $this->maxFileUploadCount) {
            $this->addErrorAsHtmlTextObject(
                errorMessageObject: $this->buildMessage(
                    individualMessage: $this->tooManyFilesErrMsg,
                    defaultMessage: $this->messages->tooManyFiles,
                    placeholder: '[max]',
                    replacement: (string)$this->maxFileUploadCount
                )
            );

            return $files;
        }
        foreach ($newUploads as $upload) {
            $file = $this->acceptUpload(upload: $upload, files: $files);
            if ($file !== null) {
                $files[$file->getHash()] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<string, UploadedFile> $files The files of the field so far
     * @return ?UploadedFile `null` (with an error added) if the file was not accepted
     */
    private function acceptUpload(UploadInput $upload, array $files): ?UploadedFile
    {
        if ($upload->error !== UPLOAD_ERR_OK) {
            $this->addFileError(message: $this->getUploadErrorMessage(error: $upload->error), fileName: $upload->name);

            return null;
        }
        if (array_any(array: $files, callback: static fn(UploadedFile $file): bool => $file->name === $upload->name)) {
            $this->addErrorAsHtmlTextObject(
                errorMessageObject: $this->buildMessage(
                    individualMessage: $this->alreadyExistsErrorMessage,
                    defaultMessage: $this->messages->duplicateFile,
                    placeholder: '[fileName]',
                    replacement: HtmlEncoder::encode(value: $upload->name)
                )
            );

            return null;
        }
        // Special case from LIVE/PROD:
        if ($upload->size === 0) {
            $this->addFileError(message: $this->messages->fileEmpty, fileName: $upload->name);

            return null;
        }
        $file = $this->storage->store(pointer: $this->uniqueSessFileStorePointer, upload: $upload);
        if ($file === null) {
            $this->addFileError(message: $this->messages->fileTechnicalError, fileName: $upload->name);
        }

        return $file;
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->messages->fileTooBig,
            UPLOAD_ERR_PARTIAL => $this->messages->fileIncomplete,
            default => $this->messages->fileTechnicalError,
        };
    }

    private function addFileError(string $message, string $fileName): void
    {
        $this->addErrorAsHtmlTextObject(
            errorMessageObject: HtmlText::encoded(
                textContent: HtmlEncoder::encodeKeepQuotes(value: $message) . ' ' . HtmlEncoder::encode(
                    value: $fileName
                )
            )
        );
    }

    /**
     * An individual message is HTML, the default message of `FormMessages` is plain text (its quotes stay as they
     * are, like in v3). The replacement must be encoded already.
     */
    private function buildMessage(
        ?HtmlText $individualMessage,
        string $defaultMessage,
        string $placeholder,
        string $replacement
    ): HtmlText {
        $template = $individualMessage?->render() ?? HtmlEncoder::encodeKeepQuotes(value: $defaultMessage);

        return HtmlText::encoded(
            textContent: str_replace(search: $placeholder, replace: $replacement, subject: $template)
        );
    }

    /**
     * Removes all files older than 2 days (of all forms of this server)
     */
    public function removeOldFiles(): void
    {
        $this->storage->removeExpired();
    }

    /**
     * Completely removes the stored files of this field. To be used after successful form processing.
     */
    public function clearData(): void
    {
        $this->storage->clear(pointer: $this->uniqueSessFileStorePointer);
        $this->files = [];
    }

    /**
     * The files uploaded so far, see `getFiles()`.
     *
     * @return list<UploadedFile>
     */
    public function getAddedValues(): array
    {
        return array_values(array: $this->files);
    }

    /**
     * Returns an array with the removed file hash if we removed (or tried to) a file with the current request.
     * This information can be used by the form to prevent from further actions like the final processing.
     *
     * @return list<string>
     */
    public function getRemovedValues(): array
    {
        return $this->deleteFileHash !== null ? [$this->deleteFileHash] : [];
    }

    public function isValueEmpty(): bool
    {
        return $this->files === [];
    }

    public function valueHasChanged(): bool
    {
        return $this->files !== [];
    }

    /**
     * A file field has no text value to render.
     */
    public function renderValue(): string
    {
        return '';
    }
}