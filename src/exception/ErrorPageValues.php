<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\html\HtmlReplacement;
use actra\yuf\html\HtmlReplacementCollection;
use Closure;

/**
 * The values every error page can use besides its own: language, copyright, nonce, CSRF field and the like. Plain
 * values are added as text (escaped); only the CSRF field is HTML that yuf built itself.
 *
 * @internal
 */
final readonly class ErrorPageValues
{
    private const string DEFAULT_LANGUAGE_CODE = 'en';
    private const string DEFAULT_PAGE_TITLE = 'Error';

    /**
     * @param ?string $languageCode `en` if the request has no language yet
     * @param string $languageRoot The path of the start page of the language (`/` if the request is not known yet)
     * @param Closure(): string $csrfFieldHtml Builds the hidden field with the CSRF token (empty without session); it
     *                                         only runs if the error page uses `csrfField`
     * @param ?string $requestedFileName The file name of the request: user input
     */
    public function __construct(
        public string $htmlFileName,
        public string $copyright,
        public ?string $languageCode,
        public string $languageRoot,
        public string $cspNonce,
        public Closure $csrfFieldHtml,
        public ?string $requestedFileName,
    ) {}

    /**
     * Adds the values to the replacements of the error page; the `pageTitle` is the `title` if there is one (debug
     * page), else `Error`.
     */
    public function addTo(HtmlReplacementCollection $replacements): void
    {
        $replacements->addText(identifier: 'copyright', text: $this->copyright);
        $replacements->addText(
            identifier: 'language',
            text: $this->languageCode ?? ErrorPageValues::DEFAULT_LANGUAGE_CODE,
        );
        $replacements->addText(identifier: 'langRoot', text: $this->languageRoot);
        $replacements->addText(identifier: 'charset', text: 'UTF-8');
        $replacements->addText(identifier: 'cspNonce', text: $this->cspNonce);
        $replacements->addLazyHtml(identifier: 'csrfField', html: $this->csrfFieldHtml);
        $replacements->addText(identifier: 'robots', text: 'noindex,nofollow');
        $replacements->set(
            identifier: 'pageTitle',
            htmlReplacement: $replacements->get(identifier: 'title')
                ?? HtmlReplacement::fromText(text: ErrorPageValues::DEFAULT_PAGE_TITLE),
        );
        $replacements->addText(
            identifier: 'bodyClassName',
            text: 'body-' . pathinfo(path: $this->htmlFileName, flags: PATHINFO_FILENAME),
        );
        $replacements->addText(identifier: 'requestedFileName', text: $this->requestedFileName);
    }
}
