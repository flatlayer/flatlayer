<?php

namespace Tests\Unit;

use App\Jobs\EntrySyncJob;
use App\Jobs\WebhookTriggerJob;
use App\Services\EntrySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EntrySyncJobTest extends TestCase
{
    use RefreshDatabase;

    private $syncService;

    private array $logMessages = [];

    private string $fakePath;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('sync');
        $this->fakePath = Storage::disk('sync')->path('');

        $this->syncService = Mockery::mock(EntrySyncService::class);
        $this->app->instance(EntrySyncService::class, $this->syncService);

        Log::listen(function ($message) {
            $this->logMessages[] = $message->message;
        });
    }

    public function test_job_calls_sync_service_with_correct_parameters()
    {
        // Arrange
        $this->syncService->shouldReceive('sync')
            ->once()
            ->with(
                'post',
                Mockery::any(),
                true,
                true
            )
            ->andReturn([
                'files_processed' => 10,
                'entries_updated' => 5,
                'entries_created' => 3,
                'entries_deleted' => 2,
                'skipped' => false,
            ]);

        // Act
        $job = new EntrySyncJob(
            type: 'post',
            path: $this->fakePath,
            shouldPull: true,
            skipIfNoChanges: true
        );

        $job->handle($this->syncService);

        // Assert
        $this->assertTrue(in_array(
            'Starting EntrySyncJob for type: post',
            $this->logMessages
        ));
    }

    public function test_job_triggers_webhook_on_success()
    {
        // Arrange
        $this->syncService->shouldReceive('sync')->andReturn([
            'files_processed' => 10,
            'entries_updated' => 5,
            'entries_created' => 3,
            'entries_deleted' => 2,
            'skipped' => false,
        ]);

        // Act
        $job = new EntrySyncJob(
            type: 'post',
            path: $this->fakePath,
            webhookUrl: 'https://example.com/webhook'
        );

        $job->handle($this->syncService);

        // Assert
        Queue::assertPushed(WebhookTriggerJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['webhookUrl'] === 'https://example.com/webhook' &&
                $config['contentType'] === 'post' &&
                $config['payload']['status'] === 'completed' &&
                isset($config['payload']['files_processed']) &&
                isset($config['payload']['timestamp']);
        });
    }

    public function test_job_triggers_webhook_on_error()
    {
        // Arrange
        $this->syncService->shouldReceive('sync')
            ->andThrow(new \Exception('Sync failed'));

        // Act
        $job = new EntrySyncJob(
            type: 'post',
            path: $this->fakePath,
            webhookUrl: 'https://example.com/webhook'
        );

        // Assert
        try {
            $job->handle($this->syncService);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Sync failed', $e->getMessage());
        }

        Queue::assertPushed(WebhookTriggerJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['webhookUrl'] === 'https://example.com/webhook' &&
                $config['contentType'] === 'post' &&
                $config['payload']['status'] === 'failed' &&
                $config['payload']['error'] === 'Sync failed' &&
                isset($config['payload']['timestamp']);
        });
    }

    public function test_job_skips_webhook_when_sync_skipped()
    {
        // Arrange
        $this->syncService->shouldReceive('sync')->andReturn([
            'files_processed' => 0,
            'entries_updated' => 0,
            'entries_created' => 0,
            'entries_deleted' => 0,
            'skipped' => true,
        ]);

        // Act
        $job = new EntrySyncJob(
            type: 'post',
            path: $this->fakePath,
            webhookUrl: 'https://example.com/webhook'
        );

        $job->handle($this->syncService);

        // Assert
        Queue::assertNotPushed(WebhookTriggerJob::class);
        $this->assertTrue(in_array(
            'Sync skipped for type post - no changes detected',
            $this->logMessages
        ));
    }

    public function test_job_logs_completion_with_sync_results()
    {
        // Arrange
        $this->syncService->shouldReceive('sync')->andReturn([
            'files_processed' => 10,
            'entries_updated' => 5,
            'entries_created' => 3,
            'entries_deleted' => 2,
            'skipped' => false,
        ]);

        // Act
        $job = new EntrySyncJob(
            type: 'post',
            path: $this->fakePath
        );

        $job->handle($this->syncService);

        // Assert
        $this->assertTrue(in_array(
            'Starting EntrySyncJob for type: post',
            $this->logMessages
        ));
        $this->assertTrue(in_array(
            'Sync completed for type post',
            $this->logMessages
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
