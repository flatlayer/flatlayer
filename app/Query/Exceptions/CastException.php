<?php

namespace App\Query\Exceptions;

use Exception;
use Throwable;

class CastException extends Exception
{
    public function __construct(string $message = "Error occurred during value casting", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
