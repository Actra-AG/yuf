<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\clock\Clock;
use actra\yuf\core\LocaleHandler;
use actra\yuf\template\compiler\TemplateCompiler;
use InvalidArgumentException;

/**
 * The tags of a `TemplateEngine`: the built-in tags plus the own tags of a project. A name can only be registered once,
 * so own tags cannot replace built-in tags. `if`, `else` and `for` are compiled by the engine itself and cannot be
 * registered.
 */
final readonly class TemplateTagCollection
{
    /** @var array<string, TemplateTag> */
    private array $tags;

    /**
     * @throws InvalidArgumentException for a name that is used twice or reserved
     */
    public function __construct(TemplateTag ...$tags)
    {
        $tagsByName = [];
        foreach ($tags as $tag) {
            $name = $tag->getName();
            if (in_array(needle: $name, haystack: TemplateCompiler::NATIVE_TAGS, strict: true)) {
                throw new InvalidArgumentException(message: 'The tag name "' . $name . '" is reserved for the template engine');
            }
            if (array_key_exists(key: $name, array: $tagsByName)) {
                throw new InvalidArgumentException(message: 'The template tag "' . $name . '" is already registered');
            }
            $tagsByName[$name] = $tag;
        }
        $this->tags = $tagsByName;
    }

    /**
     * The built-in tags: `text`, `loadSubTpl`, `lang`, `snippet`, `print`, `date` and `options`.
     */
    public static function createDefault(
        LocaleHandler $localeHandler,
        string $snippetsDirectory,
        Clock $clock,
    ): TemplateTagCollection {
        return new TemplateTagCollection(
            new TextTag(),
            new LoadSubTplTag(),
            new LangTag(localeHandler: $localeHandler),
            new SnippetTag(snippetsDirectory: $snippetsDirectory),
            new PrintTag(),
            new DateTag(clock: $clock),
            new OptionsTag(),
        );
    }

    /**
     * Returns a copy with the tag added.
     *
     * @throws InvalidArgumentException if the name is already registered or reserved
     */
    public function with(TemplateTag $tag): TemplateTagCollection
    {
        return new TemplateTagCollection(...array_values(array: $this->tags), ...[$tag]);
    }

    public function find(string $name): ?TemplateTag
    {
        return array_key_exists(key: $name, array: $this->tags) ? $this->tags[$name] : null;
    }
}
