# Templates

Templates are HTML files with `tst:` tags, rendered by the `TemplateEngine`. `Core` creates one engine per request; a
view gets it as `$this->context->templateEngine`, `HtmlDocument` renders the page with it. The engine compiles a
template to PHP once and keeps it in the cache directory (below `v<format version>/`, so an upgrade never runs old
compiled code).

## Syntax

- Inline tag: `{tst:text value='customer.name'}` (attribute values in single quotes).
- Element tag: `<tst:text value="customer.name"/>` or `<tst:for …>…</tst:for>` (attribute values in double quotes). A
  closing tag that does not match its opening tag is an error.
- Selector (attributes that name data): `a.b.c`. The first part is a key of the template data; each further part is an
  array key, a public property, a public getter (`getB()`, `isB()`, `hasB()`) or a public method without arguments.
- Text outside of tags is copied unchanged. PHP code in a template (`<?php`) is an error.

| Tag | Attributes | Output |
|:--|:--|:--|
| `text` | `value` | The value, escaped. |
| `if` / `else` | `compare`, `operator` (`eq` default, `ne`, `gt`, `ge`, `lt`, `le`, `in`), `against` | The block when the comparison is true, else the `else` block that directly follows `</tst:if>`. |
| `for` | `value`, `var` | The body for each item of an array or iterable; `var` is only visible inside. |
| `loadSubTpl` | `tplfile` (path or `{key}`) | Another template with the same data. |
| `lang` | `key`, `vars` (optional, an array) | The text of the `LocaleHandler`; `[NAME]` is replaced by `vars['name']` (escaped). |
| `snippet` | `name` | A file of the snippets directory: `.html` as template, other files as they are. |
| `print` | `var` | Debug output of a value, escaped. |
| `date` | `format` | The current date of the clock, in the `date()` format. |
| `options` | `options`, `selected` (optional) | `<option>` elements (`<optgroup>` for nested arrays). |

`if` compares explicitly: `against="null"` is true for `null`, `''`, `[]`, `false` and `0`; `against="true"` / `"false"`
test the truthiness; any other `against` is compared as string with strings, numbers and `Stringable` values;
`gt`/`ge`/`lt`/`le` need numbers and throw a `TemplateException` otherwise.

```html
<tst:if compare="user.isAdmin" operator="eq" against="true">
    <p>{tst:lang key='welcomeAdmin'}</p>
</tst:if>
<tst:else>
    <p>{tst:text value='user.name'}</p>
</tst:else>
<tst:for value="items" var="item">
    <li>{tst:text value='item.label'}</li>
</tst:for>
<tst:snippet name="menu.html"/>
```

## Escaping

- `text`, `print`, `options` and the `vars` of `lang` escape every string, number and `Stringable` value with
  `htmlspecialchars()`.
- `addText()` / `HtmlText::fromText()` take plain text (escaped once), `addHtml()` / `HtmlText::fromHtml()` take HTML
  built by your own code and output it as it is. Never pass user data to `addHtml()`.
- Language texts and non-`.html` snippets are output as they are.
- Escaping is for HTML text and quoted attributes. Values in `<script>` or `<style>` are not escaped for these
  contexts: use `data-*` attributes or JSON prepared by the view.

## Snippets, pagination and tables

`HtmlSnippet` renders a snippet file with replacements; the engine is passed explicitly (there is no static accessor):

```php
$snippet = HtmlSnippet::createForCurrentView(route: $this->context->route, snippetName: 'menu');
$snippet->replacements->addText(identifier: 'title', text: $title);
$html = $snippet->render(templateEngine: $this->context->templateEngine);
```

- `Pagination::render()`, `TablePaginationRenderer::render()` and `TableFilter::render()` take `templateEngine:` the
  same way.
- A `DbResultTable` takes the engine in its constructor
  (`TableHelper::createDbResultTable(identifier:, db:, selectQuery:, templateEngine:, httpRequest:, session:)`). It
  reads sorting and page from the query string and remembers them in the session.
- A `TableFilter` (`new TableFilter(identifier:, httpRequest:, session:, csrfTokenSource:)`, the token source is
  `$this->context->formContext->csrfTokenSource`) takes the filter values from the posted data (with a valid CSRF
  token, if there is a token source) and remembers them in the session.
- Typed values in table columns: see [database.md](database.md).

## Using the engine directly

Outside a view, with the `Core` at hand:

```php
$engine = $core->createTemplateEngine(localeHandler: $localeHandler);
$html = $engine->render(
    templateFile: $templateFile,
    data: new TemplateData(values: ['name' => $name]), // plain strings are escaped by the template
);
```

`TemplateData::fromReplacements($replacements)` takes an `HtmlReplacementCollection`. Errors are `TemplateException`s
with the template file and line.

## Own template tags

A project adds tags by implementing `TemplateTag` and passing them to `Core::prepareHttpResponse()`. Views, snippets,
tables and the error pages know them.

```php
final readonly class PriceTag implements TemplateTag
{
    public function getName(): string
    {
        return 'price';
    }

    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $amount = $context->resolve(selector: $context->requireAttribute(attributes: $attributes, name: 'value'));
        $text = number_format(num: (float) $context->text(value: $amount), decimals: 2, thousands_separator: "'");
        $html = $context->escape(value: $text . ' CHF');

        return $body === null ? $html : '<span class="price">' . $html . $body() . '</span>';
    }
}

$core->prepareHttpResponse(routeCollection: $routes, templateTags: [new PriceTag()]);
```

```html
{tst:price value='article.price'}
<tst:price value="article.price">(incl. VAT)</tst:price>
```

- `render()` returns HTML that is output as it is: the tag escapes. Use `$context->escape()` for every value that is
  not HTML; `$context->text()` gives a value as plain text for calculations and paths.
- `$attributes` are the strings as written in the template. Resolve a selector with `$context->resolve()`;
  `$context->requireAttribute()` throws a `TemplateException` for a missing attribute.
- `$body` renders the children of an element tag, `null` for inline tags and `<tst:price/>`.
- Dependencies come through the constructor; a tag must not use static state.
- The name of a built-in tag, of another own tag, `if`, `else` or `for` throws an `InvalidArgumentException` in
  `prepareHttpResponse()`.
