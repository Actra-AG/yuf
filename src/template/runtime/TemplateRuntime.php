<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\tag\TemplateTagContext;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateException;
use Closure;
use Throwable;
use Traversable;

/**
 * The only object that compiled templates can reach (variable `$runtime`): it holds the data scopes, resolves
 * selectors, compares, iterates and renders the tags. One runtime lives for one render call and is shared by the
 * sub-templates.
 *
 * @internal
 *
 * @phpstan-import-type TemplateValue from TemplateData
 */
final class TemplateRuntime
{
    private readonly TemplateScopes $scopes;
    private readonly SelectorResolver $resolver;
    private readonly ValueFormatter $formatter;
    private readonly ValueComparator $comparator;
    private readonly TemplateTagContext $tagContext;
    /** @var list<string> the template files that are being rendered, the last one is the current one */
    private array $templateFiles = [];

    public function __construct(
        TemplateData $data,
        private readonly TemplateTagCollection $tags,
        private readonly TemplateLoader $loader,
    ) {
        $this->scopes = new TemplateScopes(data: $data);
        $this->resolver = new SelectorResolver();
        $this->formatter = new ValueFormatter();
        $this->comparator = new ValueComparator();
        $this->tagContext = new TemplateTagContext(runtime: $this);
    }

    /**
     * Renders a template with the current data and returns the HTML. The compiled file runs in a static closure that
     * only gets the runtime. An exception closes the output buffers that the template opened.
     */
    public function renderTemplate(string $templateFile): string
    {
        $compiledFile = $this->loader->getCompiledFile(templateFile: $templateFile);
        $this->templateFiles[] = $templateFile;
        $outputBufferLevel = ob_get_level();
        ob_start();

        try {
            $execute = static function (TemplateRuntime $runtime, string $compiledFile): void {
                require $compiledFile;
            };
            $execute($this, $compiledFile);
            $html = ob_get_clean();

            return $html === false ? '' : $html;
        } catch (Throwable $throwable) {
            while (ob_get_level() > $outputBufferLevel) {
                ob_end_clean();
            }

            throw $throwable;
        } finally {
            array_pop(array: $this->templateFiles);
        }
    }

    /**
     * @return TemplateValue
     *
     * @throws TemplateException
     */
    public function resolve(string $selector): bool|int|float|string|object|array|null
    {
        return $this->resolver->resolve(selector: $selector, scopes: $this->scopes);
    }

    public function escape(mixed $value): string
    {
        return $this->formatter->escape(value: $value);
    }

    public function text(mixed $value): string
    {
        return $this->formatter->text(value: $value);
    }

    /**
     * Called by the compiled code of an `if` tag.
     */
    public function compare(string $selector, string $operator, string $against, int $line): bool
    {
        try {
            return $this->comparator->compare(
                value: $this->resolve(selector: $selector),
                operator: ComparisonOperatorEnum::from(value: $operator),
                against: $against,
            );
        } catch (TemplateException $exception) {
            throw $this->locate(exception: $exception, line: $line);
        }
    }

    /**
     * Called by the compiled code of a `for` tag: the items of an array, a `Traversable` or the public properties of an
     * object; `null` is an empty list.
     *
     * @return list<mixed>
     */
    public function iterate(string $selector, int $line): array
    {
        try {
            $value = $this->resolve(selector: $selector);
        } catch (TemplateException $exception) {
            throw $this->locate(exception: $exception, line: $line);
        }
        $items = match (true) {
            $value === null => [],
            is_array(value: $value) => $value,
            $value instanceof Traversable => iterator_to_array(iterator: $value, preserve_keys: false),
            is_object(value: $value) && !$value instanceof TrustedHtml => get_object_vars(object: $value),
            default => throw $this->locate(
                exception: new TemplateException(
                    reason: 'The value "' . $selector . '" of type ' . get_debug_type(value: $value)
                        . ' cannot be used in a for tag, it must be an array or an object',
                ),
                line: $line,
            ),
        };

        return array_values(array: $items);
    }

    public function pushScope(string $name, mixed $value): void
    {
        $this->scopes->push(name: $name, value: $value);
    }

    public function popScope(): void
    {
        $this->scopes->pop();
    }

    /**
     * Called by the compiled code of every tag except `if`, `else` and `for`.
     *
     * @param array<string, string> $attributes
     * @param (Closure(): string)|null $body renders the children of the tag
     */
    public function renderTag(string $name, array $attributes, int $line, ?Closure $body): string
    {
        try {
            $tag = $this->tags->find(name: $name);
            if ($tag === null) {
                throw new TemplateException(reason: 'Unknown template tag "' . $name . '"');
            }

            return $tag->render(context: $this->tagContext, attributes: $attributes, body: $body);
        } catch (TemplateException $exception) {
            throw $this->locate(exception: $exception, line: $line);
        }
    }

    /**
     * Adds the current template file and the line to an exception that does not name them yet.
     */
    private function locate(TemplateException $exception, int $line): TemplateException
    {
        $templateFile = end(array: $this->templateFiles);
        if ($exception->templateFile !== null || $templateFile === false) {
            return $exception;
        }

        return $exception->withLocation(templateFile: $templateFile, templateLine: $line);
    }
}
