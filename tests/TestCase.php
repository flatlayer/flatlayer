<?php

namespace Tests;

use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();
    }

    protected function getFactoryPath()
    {
        return [
            __DIR__.'/Factories',
        ];
    }
}
