<?php

namespace Tests;

use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();

        // Set up the Log facade to just print the log messages
        //Log::shouldReceive('info')->andReturnUsing(function ($message) {
        //    echo $message . "\n";
        //});
    }

    protected function getFactoryPath()
    {
        return [
            __DIR__.'/Factories',
        ];
    }
}
