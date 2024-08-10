<?php

namespace App\Query\Exceptions;

use Exception;
use Throwable;

class QueryException extends Exception
{
    public function __construct(string $message = 'An error occurred during query processing', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}