<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * The file extension that an HTML route accepts in its paths (the value is the `acceptedExtension` of the `Route`).
 */
enum HtmlFileExtensionEnum: string
{
    /** `/about.html`: the default, clear file names */
    case HTML = 'html';
    /** `/about`: paths without file extension, e.g. to keep the paths of a site taken over from another system */
    case NONE = '';

    /** The file name of `$fileTitle` with this extension (`about.html` or `about`) */
    public function createFileName(string $fileTitle): string
    {
        return $this === HtmlFileExtensionEnum::NONE ? $fileTitle : $fileTitle . '.' . $this->value;
    }
}
