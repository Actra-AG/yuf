<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\Core;
use actra\yuf\core\RequestHandler;
use actra\yuf\security\CspNonce;
use actra\yuf\template\template\DirectoryTemplateCache;
use actra\yuf\template\template\TemplateEngine;

readonly class HtmlSnippet
{
    public function __construct(
        private string $htmlSnippetFilePath,
        public HtmlReplacementCollection $replacements = new HtmlReplacementCollection()
    ) {
    }

    public static function createForCurrentView(string $snippetName): HtmlSnippet
    {
        return new HtmlSnippet(
            htmlSnippetFilePath: RequestHandler::get(
            )->route->viewDirectory . 'snippets' . DIRECTORY_SEPARATOR . $snippetName . '.html'
        );
    }

    public function render(): string
    {
        $htmlSnippetFilePath = $this->htmlSnippetFilePath;
        $replacements = $this->replacements;
        if (!$replacements->has(identifier: 'cspNonce')) {
            $replacements->addEncodedText(identifier: 'cspNonce', content: CspNonce::get());
        }
        $core = Core::get();

        return new TemplateEngine(
            templateCacheInterface: new DirectoryTemplateCache(
                cachePath: $core->cacheDirectory,
                templateBaseDirectory: str_replace(
                    search: $htmlSnippetFilePath,
                    replace: $core->documentRoot,
                    subject: $htmlSnippetFilePath
                )
            ),
            tplNsPrefix: 'tst'
        )->getResultAsHtml(
            tplFile: $htmlSnippetFilePath,
            dataPool: $this->replacements->getArrayObject()
        );
    }
}