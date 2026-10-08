<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template;

use actra\yuf\template\cache\TemplateCache;
use actra\yuf\template\compiler\TemplateCompiler;
use actra\yuf\template\parser\TemplateParser;
use actra\yuf\template\runtime\TemplateLoader;
use actra\yuf\template\runtime\TemplateRuntime;
use actra\yuf\template\tag\TemplateTagCollection;

/**
 * Renders templates: parses and compiles them to PHP (kept in the `TemplateCache`), runs the compiled code and returns
 * the HTML. The values of the `TemplateData` are escaped on output (design section 5). Create one engine per request
 * and pass it to the code that renders templates; it has no static state.
 */
final readonly class TemplateEngine
{
    private TemplateLoader $loader;

    /**
     * @param string $namespacePrefix The prefix of the tags in the templates (`{tst:text value='a'}`)
     */
    public function __construct(
        TemplateCache $cache,
        private TemplateTagCollection $tags,
        string $namespacePrefix = 'tst',
    ) {
        $this->loader = new TemplateLoader(
            cache: $cache,
            parser: new TemplateParser(namespacePrefix: $namespacePrefix),
            compiler: new TemplateCompiler(),
        );
    }

    /**
     * @throws TemplateException for a missing template, a syntax error and a value or tag that fails while rendering
     */
    public function render(string $templateFile, TemplateData $data): string
    {
        return new TemplateRuntime(data: $data, tags: $this->tags, loader: $this->loader)->renderTemplate(
            templateFile: $templateFile,
        );
    }
}
