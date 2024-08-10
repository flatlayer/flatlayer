<?php

namespace App\Query\Exceptions;

use Exception;
use Throwable;

class InvalidCastException extends Exception
{
    public function __construct(string $message = "Invalid cast option", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
