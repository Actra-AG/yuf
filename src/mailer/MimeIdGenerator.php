<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Creates the unique id of a message: the left part of the message id and the suffix of the MIME boundaries.
 */
interface MimeIdGenerator
{
    /**
     * @return string only letters and digits
     */
    public function generate(): string;
}
