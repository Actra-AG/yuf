<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\Core;
use actra\yuf\core\RequestHandler;
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfToken;
use actra\yuf\template\template\DirectoryTemplateCache;
use actra\yuf\template\template\TemplateEngine;

class HtmlDocument
{
    private static ?HtmlDocument $instance = null;
    public readonly HtmlReplacementCollection $replacements;
    public string $templateName = 'default';
    public string $contentFileName;
    private array $activeHtmlIds = [];

    private function __construct()
    {
        $requestHandler = RequestHandler::get();
        $fileTitle = $requestHandler->fileTitle;
        $this->contentFileName = $fileTitle;
        $this->replacements = new HtmlReplacementCollection();
        $replacements = $this->replacements;
        $core = Core::get();
        $replacements->addEncodedText(
            identifier: 'bodyClassName',
            content: 'body-' . $fileTitle
        );
        $replacements->addEncodedText(
            identifier: 'language',
            content: $requestHandler->language->code
        );
        $replacements->addEncodedText(
            identifier: 'charset',
            content: 'UTF-8'
        );
        $replacements->addEncodedText(
            identifier: 'copyright',
            content: $core->renderCopyrightYear()
        );
        $replacements->addEncodedText(
            identifier: 'robots',
            content: $core->robots
        );
        $replacements->addEncodedText(
            identifier: 'scripts',
            content: ''
        );
        $replacements->addEncodedText(
            identifier: 'cspNonce',
            content: CspNonce::get()
        );
        $replacements->addEncodedText(
            identifier: 'csrfField',
            content: CsrfToken::renderAsHiddenPostField()
        );
        $replacements->addEncodedText(
            identifier: 'requestedFileName',
            content: $requestHandler->fileName
        );
    }

    public static function get(): HtmlDocument
    {
        if (is_null(value: HtmlDocument::$instance)) {
            HtmlDocument::$instance = new HtmlDocument();
        }

        return HtmlDocument::$instance;
    }

    public function setActiveHtmlId(int $key, string $val): void
    {
        $this->activeHtmlIds[$key] = $val;
    }

    public function isActiveHtmlIdSet(int $key): bool
    {
        return array_key_exists(key: $key, array: $this->activeHtmlIds);
    }

    public function render(): string
    {
        $request = RequestHandler::get();
        $viewDirectory = $request->route->viewDirectory;
        $contentFileDirectory = $viewDirectory . 'html/';
        $fileGroup = $request->fileGroup;
        if (!is_null(value: $fileGroup)) {
            $contentFileDirectory .= $fileGroup . '/';
        }
        $contentFileName = $this->contentFileName;
        if ($contentFileName === '') {
            throw new NotFoundException();
        }
        $fullContentFilePath = $contentFileDirectory . $contentFileName . '.html';
        if (!is_file(filename: $fullContentFilePath)) {
            throw new NotFoundException();
        }
        $this->replacements->addEncodedText(identifier: 'this', content: $fullContentFilePath);
        $templateName = $this->templateName;
        $templateFilePath = $viewDirectory . 'templates/' . $templateName . '.html';
        if ($templateName === '' || !is_file(filename: $templateFilePath)) {
            $templateFilePath = $fullContentFilePath;
        }
        $core = Core::get();
        $tplEngine = new TemplateEngine(
            templateCacheInterface: new DirectoryTemplateCache(
                cachePath: $core->cacheDirectory,
                templateBaseDirectory: $core->siteDirectory
            ),
            tplNsPrefix: 'tst'
        );
        if (count(value: $this->activeHtmlIds) === 0) {
            $this->setActiveHtmlId(
                key: 1,
                val: is_null(value: $fileGroup) ? $contentFileName : $fileGroup . '-' . $contentFileName
            );
        }
        $htmlAfterReplacements = $tplEngine->getResultAsHtml(
            tplFile: $templateFilePath,
            dataPool: $this->replacements->getArrayObject()
        );

        return preg_replace_callback(
            pattern: '/(\s+id="nav-(.+?)")(\s+class="(.+?)")?/',
            callback: [$this, 'setCSSActive'],
            subject: $htmlAfterReplacements
        );
    }

    private function setCSSActive(array $m): string
    {
        if (!in_array(needle: $m[2], haystack: $this->activeHtmlIds)) {
            // The id is not within activeHtmlIds, so we just return the whole unmodified string
            return $m[0];
        }

        // The id is within activeHtmlIds, so we need to add the "active" class
        return $m[1] . ' class="' . (isset($m[3]) ? $m[4] . ' ' : '') . 'active"';
    }
}