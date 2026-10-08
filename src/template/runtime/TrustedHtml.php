<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use Closure;
use LogicException;

/**
 * Marks a string as HTML that is output as it is. Created for the text of the html classes (`TemplateData`); a value
 * is never trusted because of its content. The HTML can be given as a function: it only runs when a template reads the
 * value (once), so something that costs (e.g. the CSRF token of the session) is only built for templates that use it.
 *
 * @internal
 */
final class TrustedHtml
{
    private ?string $resolvedHtml = null;
    /** @var (Closure(): string)|null */
    private ?Closure $htmlFactory = null;

    /**
     * @param string|(Closure(): string) $html
     */
    public function __construct(string|Closure $html)
    {
        if ($html instanceof Closure) {
            $this->htmlFactory = $html;
        } else {
            $this->resolvedHtml = $html;
        }
    }

    public string $html {
        get {
            if ($this->resolvedHtml === null) {
                $htmlFactory = $this->htmlFactory;
                if ($htmlFactory === null) {
                    throw new LogicException(message: 'The trusted HTML has neither a value nor a function.');
                }
                $this->resolvedHtml = $htmlFactory();
                $this->htmlFactory = null;
            }

            return $this->resolvedHtml;
        }
    }
}
