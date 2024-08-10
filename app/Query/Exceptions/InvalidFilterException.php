<?php

namespace App\Query\Exceptions;

use Exception;

class InvalidFilterException extends Exception
{
    public function __construct(string $message = "Invalid filter operation", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
