<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getFactoryPath()
    {
        return [
            __DIR__.'/Factories',
        ];
    }
}
