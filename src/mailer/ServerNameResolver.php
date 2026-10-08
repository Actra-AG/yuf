<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Finds the host name that identifies this server: the right part of the message id and the argument of `EHLO`.
 */
interface ServerNameResolver
{
    /**
     * @return string a host name or the address itself; never contains whitespace or line breaks
     */
    public function resolve(string $serverAddress): string;
}
