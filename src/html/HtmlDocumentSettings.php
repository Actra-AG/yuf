<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * What `HtmlDocument` needs to know about the request and the application, so it renders without `Core` and
 * `RequestHandler`. Everything comes from the request, so `HtmlDocument` treats it as untrusted.
 */
final readonly class HtmlDocumentSettings
{
    /**
     * @param string $viewDirectory Directory of the view of the route, with the trailing slash
     * @param string|null $fileGroup Group (sub directory of the content files) of the request
     * @param string $fileTitle Name of the requested file without extension and path variables
     * @param string|null $fileName Name of the requested file
     * @param string $languageCode Language of the page (`lang` attribute), empty if the application has none
     * @param string $copyright Years of the copyright notice
     * @param string $robots Content of the robots meta tag
     */
    public function __construct(
        public string $viewDirectory,
        public ?string $fileGroup,
        public string $fileTitle,
        public ?string $fileName,
        public string $languageCode,
        public string $copyright,
        public string $robots,
    ) {}
}
