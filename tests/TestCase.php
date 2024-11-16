<?php

namespace Tests;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;

abstract class TestCase extends BaseTestCase
{
    protected $loggingToPrint = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeOpenAi();

        $this->loggingToPrint = false;
    }

    protected function logToPrint()
    {
        Log::shouldReceive('info')->andReturnUsing(function ($message) {
            echo $message."\n";
        });
        Log::shouldReceive('error')->andReturnUsing(function ($message) {
            echo 'ERROR: '.$message."\n";
        });
        $this->loggingToPrint = true;
    }

    protected function logSqlResult(Builder|EloquentBuilder $filtered)
    {
        if (! $this->loggingToPrint) {
            $this->logToPrint();
        }

        // Log the SQL query and bindings
        Log::info('Generated SQL: '.$filtered->toSql()."\n");
        Log::info('SQL Bindings: '.json_encode($filtered->getBindings())."\n");
        Log::info('Raw SQL: '.$filtered->toRawSql()."\n");
    }

    protected function getFactoryPath()
    {
        return [
            __DIR__.'/Factories',
        ];
    }

    protected function fakeOpenAi(int $count = 50)
    {
        // Save the current random seed
        $currentSeed = mt_rand();

        // Set a fixed seed for reproducibility
        mt_srand(42);

        // Create fake embeddings
        $fakeEmbeddings = [];
        for ($i = 0; $i < $count; $i++) {
            $fakeEmbeddings[] = array_map(
                fn () => mt_rand(0, 100) / 100, // Random float between 0 and 1
                array_fill(0, 1536, 0)
            );
        }

        $fakeResponses = array_map(function ($embedding) {
            return CreateResponse::fake([
                'data' => [
                    [
                        'embedding' => $embedding,
                        'index' => 0,
                        'object' => 'embedding',
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'object' => 'list',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ]);
        }, $fakeEmbeddings);

        // Restore the original random seed
        mt_srand($currentSeed);

        OpenAI::fake($fakeResponses);
    }
}
