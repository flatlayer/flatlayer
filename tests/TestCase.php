<?php

namespace Tests;

use App\Services\JinaSearchService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    protected $loggingToPrint = false;

    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();

        $this->loggingToPrint = false;
    }

    protected function logToPrint()
    {
        Log::shouldReceive('info')->andReturnUsing(function ($message) {
            echo $message . "\n";
        });
        $this->loggingToPrint = true;
    }

    protected function logSqlResult(Builder|EloquentBuilder $filtered)
    {
        if(!$this->loggingToPrint) {
            $this->logToPrint();
        }

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
