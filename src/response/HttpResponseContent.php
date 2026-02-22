<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\response;

abstract class HttpResponseContent
{
    protected function __construct(private(set) readonly string $content)
    {
    }
}