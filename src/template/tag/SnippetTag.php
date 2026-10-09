<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\template\TemplateException;
use Closure;
use Override;

/**
 * `{tst:snippet name='file.html'}`: renders a file of the snippets directory. A `.html` file is a template and gets
 * the same data, every other file is output as it is. A name that leaves the snippets directory is an error.
 */
final class SnippetTag implements TemplateTag
{
    private readonly string $snippetsDirectory;
    /** The real path of the snippets directory, resolved on the first use */
    private ?string $realSnippetsDirectory = null;
    /** @var array<string, string> content of the snippets that are not templates, by name */
    private array $contents = [];

    public function __construct(string $snippetsDirectory)
    {
        $this->snippetsDirectory = rtrim(string: $snippetsDirectory, characters: '/') . '/';
    }

    #[Override]
    public function getName(): string
    {
        return 'snippet';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $name = $context->requireAttribute(attributes: $attributes, name: 'name');
        if ($name === '') {
            return '';
        }
        if (array_key_exists(key: $name, array: $this->contents)) {
            return $this->contents[$name];
        }
        $snippetFile = $this->findSnippetFile(name: $name);
        if (str_ends_with(haystack: strtolower(string: $name), needle: '.html')) {
            return $context->renderTemplate(templateFile: $snippetFile);
        }
        $content = file_get_contents(filename: $snippetFile);
        if ($content === false) {
            throw new TemplateException(reason: 'Could not read the snippet "' . $name . '"');
        }
        // A snippet used many times (in a loop) is read once per request
        $this->contents[$name] = $content;

        return $content;
    }

    private function findSnippetFile(string $name): string
    {
        $segments = explode(separator: '/', string: str_replace(search: '\\', replace: '/', subject: $name));
        if (str_starts_with(haystack: $name, needle: '/') || str_contains(haystack: $name, needle: "\0")
            || in_array(needle: '..', haystack: $segments, strict: true)
        ) {
            throw new TemplateException(reason: 'The snippet name "' . $name . '" leaves the snippets directory');
        }
        $snippetFile = realpath(path: $this->snippetsDirectory . $name);
        if ($this->realSnippetsDirectory === null) {
            $realSnippetsDirectory = realpath(path: $this->snippetsDirectory);
            $this->realSnippetsDirectory = $realSnippetsDirectory === false ? null : $realSnippetsDirectory;
        }
        $snippetsDirectory = $this->realSnippetsDirectory;
        if ($snippetFile === false || $snippetsDirectory === null || !is_file(filename: $snippetFile)) {
            throw new TemplateException(reason: 'Snippet not found: ' . $name);
        }
        if (!str_starts_with(haystack: $snippetFile, needle: $snippetsDirectory . '/')) {
            throw new TemplateException(reason: 'The snippet name "' . $name . '" leaves the snippets directory');
        }

        return $snippetFile;
    }
}
