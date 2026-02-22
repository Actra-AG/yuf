<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpStatusCode;
use Exception;

class NotFoundException extends Exception
{
    public function __construct(
        string         $message = '',
        HttpStatusCode $code = HttpStatusCode::HTTP_NOT_FOUND
    )
    {
        if ($message === '') {
            $message = 'Not Found';
        }
        parent::__construct(
            message: $message,
            code: $code->value
        );
    }
}