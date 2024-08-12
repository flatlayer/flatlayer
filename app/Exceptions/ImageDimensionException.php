<?php

namespace App\Exceptions;

use Exception;

class ImageDimensionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
