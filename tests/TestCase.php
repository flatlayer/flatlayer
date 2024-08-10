<?php

namespace Tests;

use App\Services\JinaSearchService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();

        // Set up the Log facade to just print the log messages
        //$this->logToPrint();
    }

    protected function logToPrint()
    {
        Log::shouldReceive('info')->andReturnUsing(function ($message) {
            echo $message . "\n";
        });
    }

    protected function logSqlResult(Builder|EloquentBuilder $filtered)
    {
        // Log the SQL query and bindings
        Log::info('Generated SQL: ' . $filtered->toSql() . "\n");
        Log::info('SQL Bindings: ' . json_encode($filtered->getBindings()) . "\n");
        Log::info('Raw SQL: ' . $filtered->toRawSql() . "\n");
    }

    protected function getFactoryPath()
    {
        return [
            __DIR__.'/Factories',
        ];
    }
}
