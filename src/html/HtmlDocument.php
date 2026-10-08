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
    public readonly HtmlReplacementCollection $replacements;
    public string $templateDirectory {
        set {
            $this->templateDirectory = $value;
        }
    }
    public string $contentFileDirectory {
        set {
            $this->contentFileDirectory = $value;
        }
    }
    public string $templateName = 'default';
    public string $contentFileName {
        set {
            $this->contentFileName = $value;
        }
    }
    private array $activeHtmlIds = [];

    public function __construct(
        private readonly RequestHandler $requestHandler,
        CspNonce $cspNonce,
        private readonly Core $core,
    ) {
        $requestHandler = $this->requestHandler;
        $viewDirectory = $requestHandler->route->viewDirectory;
        $this->templateDirectory = $viewDirectory . 'templates/';
        $this->contentFileDirectory = $viewDirectory . 'html/';
        $fileTitle = $requestHandler->fileTitle;
        $this->contentFileName = $fileTitle . '.html';
        $this->replacements = new HtmlReplacementCollection();
        $replacements = $this->replacements;
        $core = $this->core;
        $replacements->addHtml(
            identifier: 'bodyClassName',
            html: 'body-' . $fileTitle,
        );
        $replacements->addHtml(
            identifier: 'language',
            html: $requestHandler->language->code,
        );
        $replacements->addHtml(
            identifier: 'charset',
            html: 'UTF-8',
        );
        $replacements->addHtml(
            identifier: 'copyright',
            html: $core->renderCopyrightYear(),
        );
        $replacements->addHtml(
            identifier: 'robots',
            html: $core->robots,
        );
        $replacements->addHtml(
            identifier: 'scripts',
            html: '',
        );
        $replacements->addHtml(
            identifier: 'cspNonce',
            html: $cspNonce->value,
        );
        $replacements->addHtml(
            identifier: 'csrfField',
            html: CsrfToken::renderAsHiddenPostField(),
        );
        $replacements->addHtml(
            identifier: 'requestedFileName',
            html: $requestHandler->fileName,
        );
    }

    public function setActiveHtmlId(int $key, string $val): void
    {
        $this->activeHtmlIds[$key] = $val;
    }

    public function isActiveHtmlIdSet(int $key): bool
    {
        return array_key_exists(
            key: $key,
            array: $this->activeHtmlIds,
        );
    }

    public function getActiveHtmlId(int $key): string
    {
        return $this->activeHtmlIds[$key];
    }

    public function listActiveHtmlIds(): array
    {
        return $this->activeHtmlIds;
    }

    public function render(): string
    {
        $contentFileName = $this->contentFileName;
        if ($contentFileName === '') {
            throw new NotFoundException();
        }
        $contentFileDirectory = $this->contentFileDirectory;
        $requestHandler = $this->requestHandler;
        $fileGroup = $requestHandler->fileGroup;
        if ($fileGroup !== null) {
            $contentFileDirectory .= $fileGroup . '/';
        }
        $fullContentFilePath = $contentFileDirectory . $contentFileName;
        if (!is_file(filename: $fullContentFilePath)) {
            throw new NotFoundException();
        }
        $this->replacements->addHtml(
            identifier: 'this',
            html: $fullContentFilePath,
        );
        $templateName = $this->templateName;
        $templateFilePath = $this->templateDirectory . $templateName . '.html';
        if (
            $templateName === ''
            || !is_file(filename: $templateFilePath)
        ) {
            $templateFilePath = $fullContentFilePath;
        }
        $core = $this->core;
        $tplEngine = new TemplateEngine(
            templateCacheInterface: new DirectoryTemplateCache(
                cachePath: $core->cacheDirectory,
                templateBaseDirectory: $core->baseDirectory,
            ),
            tplNsPrefix: 'tst',
        );
        if ($this->activeHtmlIds === []) {
            $fileTitle = $requestHandler->fileTitle;
            $this->setActiveHtmlId(
                key: 1,
                val: $fileGroup === null ? $fileTitle : $fileGroup . '-' . $fileTitle,
            );
        }
        $htmlAfterReplacements = $tplEngine->getResultAsHtml(
            tplFile: $templateFilePath,
            dataPool: $this->replacements->getArrayObject(),
        );

        return preg_replace_callback(
            pattern: '/(\s+id="nav-(.+?)")(\s+class="(.+?)")?/',
            callback: [
                $this,
                'setCssActive',
            ],
            subject: $htmlAfterReplacements,
        );
    }

    private function setCssActive(array $m): string
    {
        if (!in_array(
            needle: $m[2],
            haystack: $this->activeHtmlIds,
            strict: true,
        )) {
            // The id is not within activeHtmlIds, so we just return the whole unmodified string
            return $m[0];
        }

        // The id is within activeHtmlIds, so we need to add the "active" class
        return $m[1] . ' class="' . (array_key_exists(key: 4, array: $m) ? $m[4] . ' ' : '') . 'active"';
    }
}
