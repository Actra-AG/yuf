<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\FileField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use Override;

final class FileFieldRenderer extends FormRenderer
{
    public bool $enhanceMultipleField = true;

    public function __construct(private readonly FileField $fileField) {}

    #[Override]
    public function prepare(): void
    {
        $fileField = $this->fileField;
        $alreadyUploadedFiles = $fileField->getFiles();
        $stillAllowedToUploadCount = $fileField->maxFileUploadCount - count(value: $alreadyUploadedFiles);
        if ($stillAllowedToUploadCount < 0) {
            $stillAllowedToUploadCount = 0;
        }
        $wrapperClass = $stillAllowedToUploadCount > 1 && $this->enhanceMultipleField
            ? 'fileupload-enhanced'
            : 'fileupload';
        $divFileUpload = new HtmlTag(
            name: 'div',
            selfClosing: false,
        );
        $divFileUpload->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'class', text: $wrapperClass),
        );
        $divFileUpload->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(
                name: 'data-max-files',
                text: (string) $stillAllowedToUploadCount,
            ),
        );
        if (count(value: $alreadyUploadedFiles) > 0) {
            $ulFileUploadList = new HtmlTag(
                name: 'ul',
                selfClosing: false,
            );
            $ulFileUploadList->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'class', text: 'fileupload-list'),
            );
            $htmlContent = '';
            $removeButtonName = HtmlEncoder::encode(value: $fileField->name . '_removeAttachment');
            $removeButtonText = HtmlEncoder::encode(value: $fileField->messages->removeFile);
            foreach ($alreadyUploadedFiles as $hash => $uploadedFile) {
                $htmlContent .= '<li><span>' . HtmlEncoder::encode(value: $uploadedFile->name) . '</span> '
                    . '<button type="submit" name="' . $removeButtonName . '" value="'
                    . HtmlEncoder::encode(value: $hash) . '">' . $removeButtonText . '</button></li>';
            }
            $ulFileUploadList->addText(htmlText: HtmlText::fromHtml(html: $htmlContent));
            $divFileUpload->addTag(htmlTag: $ulFileUploadList);
        }
        $inputTag = new HtmlTag(
            name: 'input',
            selfClosing: true,
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'type', text: 'file'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'name', text: $fileField->name . '[]'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $fileField->id),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'accept', text: $this->getAcceptValue()),
        );
        if ($stillAllowedToUploadCount > 1) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromName(name: 'multiple'),
            );
        }
        FormRenderer::addAriaAttributesToHtmlTag(
            formField: $fileField,
            parentHtmlTag: $inputTag,
        );
        $divFileUpload->addTag(htmlTag: $inputTag);
        // Add the fileStore-Pointer-ID for the SESSION as a hidden field
        $hiddenField = new HtmlTag(
            name: 'input',
            selfClosing: true,
        );
        $hiddenField->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'type', text: 'hidden'),
        );
        $hiddenField->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'name', text: $this->fileField->name . '_UID'),
        );
        $hiddenField->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'value', text: $fileField->uniqueSessFileStorePointer),
        );
        $divFileUpload->addTag(htmlTag: $hiddenField);
        $this->setHtmlTag(htmlTag: $divFileUpload);
    }

    /**
     * The extensions of all allowed types, e.g. `.pdf,.jpg,.jpeg`. Only a hint for the file dialog of the browser, the
     * check happens on the server.
     */
    private function getAcceptValue(): string
    {
        $extensions = [];
        foreach ($this->fileField->allowedFileTypes as $fileType) {
            foreach ($fileType->extensions as $extension) {
                $extensions['.' . $extension] = true;
            }
        }

        return implode(separator: ',', array: array_keys(array: $extensions));
    }
}
