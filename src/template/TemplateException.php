<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template;

use RuntimeException;
use Throwable;

/**
 * Error of the template engine: a syntax error, a missing value or a tag that cannot render. The message names the
 * template file and the line, if known.
 */
final class TemplateException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $templateFile = null,
        public readonly ?int $templateLine = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: TemplateException::createMessage(
                reason: $reason,
                templateFile: $templateFile,
                templateLine: $templateLine,
            ),
            previous: $previous,
        );
    }

    /**
     * Returns a copy of this exception that names the template file and the line.
     */
    public function withLocation(string $templateFile, int $templateLine): TemplateException
    {
        return new TemplateException(
            reason: $this->reason,
            templateFile: $templateFile,
            templateLine: $templateLine,
            previous: $this,
        );
    }

    private static function createMessage(string $reason, ?string $templateFile, ?int $templateLine): string
    {
        if ($templateFile === null) {
            return $reason;
        }
        if ($templateLine === null) {
            return $reason . ' in ' . $templateFile;
        }

        return $reason . ' in ' . $templateFile . ' on line ' . $templateLine;
    }
}
