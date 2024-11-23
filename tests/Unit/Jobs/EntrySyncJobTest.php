<?php

namespace Tests\Unit\Jobs;

use App\Jobs\EntrySyncJob;
use App\Jobs\WebhookTriggerJob;
use App\Services\Content\ContentSyncManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EntrySyncJobTest extends TestCase
{
    use RefreshDatabase;

    private $syncService;

    private $disk;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->disk = Storage::fake('test');

        $this->syncService = Mockery::mock(ContentSyncManager::class);
        $this->app->instance(ContentSyncManager::class, $this->syncService);
    }

    public function test_job_triggers_webhook_on_success()
    {
        $this->syncService->shouldReceive('sync')->andReturn([
            'files_processed' => 10,
            'entries_updated' => 5,
            'entries_created' => 3,
            'entries_deleted' => 2,
            'skipped' => false,
        ]);

        $job = new EntrySyncJob(
            type: 'post',
            disk: $this->disk,
            webhookUrl: 'https://example.com/webhook'
        );

        $job->handle($this->syncService);

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
        $this->syncService->shouldReceive('sync')
            ->andThrow(new \Exception('Sync failed'));

        $job = new EntrySyncJob(
            type: 'post',
            disk: $this->disk,
            webhookUrl: 'https://example.com/webhook'
        );

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
        $this->syncService->shouldReceive('sync')->andReturn([
            'files_processed' => 0,
            'entries_updated' => 0,
            'entries_created' => 0,
            'entries_deleted' => 0,
            'skipped' => true,
        ]);

        $job = new EntrySyncJob(
            type: 'post',
            disk: $this->disk,
            webhookUrl: 'https://example.com/webhook'
        );

        $job->handle($this->syncService);

        Queue::assertNotPushed(WebhookTriggerJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
