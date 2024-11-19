<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\EntrySyncService;
use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GitHubWebhookFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected MockInterface|SyncConfigurationService $syncConfigService;

    protected MockInterface|EntrySyncService $syncService;

    protected array $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test disk
        Storage::fake('content');

        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->syncService = Mockery::mock(EntrySyncService::class);

        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);
        $this->app->instance(EntrySyncService::class, $this->syncService);

        Queue::fake();

        Log::listen(function ($message) {
            $this->logMessages[] = $message->message;
        });
    }

    public function test_valid_webhook_request_triggers_sync()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        // Set up expectations for config service
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('post')
            ->andReturn([]);

        $this->syncConfigService->shouldReceive('getConfigAsArgs')
            ->with('post')
            ->andReturn([
                '--type' => 'post',
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ]);

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertSee('Sync initiated');

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'post' &&
                $config['shouldPull'] === true &&
                $config['skipIfNoChanges'] === true;
        });
    }

    public function test_invalid_signature_returns_403()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(403);
        $response->assertSee('Invalid signature');

        Queue::assertNotPushed(EntrySyncJob::class);
        $this->assertTrue(in_array('Invalid GitHub webhook signature', $this->logMessages));
    }

    public function test_invalid_content_type_returns_400()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('invalid-type')
            ->andReturn(false);

        $response = $this->postJson('/webhook/invalid-type', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(400);
        $response->assertSee('Configuration for invalid-type not found');

        Queue::assertNotPushed(EntrySyncJob::class);
        $this->assertTrue(in_array('Configuration for invalid-type not found', $this->logMessages));
    }

    public function test_entry_sync_job_logs_correctly()
    {
        // Configure sync service mock
        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 10,
                'entries_updated' => 5,
                'entries_created' => 3,
                'entries_deleted' => 2,
                'skipped' => false,
            ]);

        // Create and handle the job
        $job = new EntrySyncJob(
            type: 'post',
            shouldPull: true,
            skipIfNoChanges: true
        );

        $job->handle($this->syncService);

        // Verify log messages
        $this->assertTrue(
            in_array('Starting EntrySyncJob for type: post', $this->logMessages),
            'Expected start log message not found'
        );
        $this->assertTrue(
            in_array('Sync completed for type: post', $this->logMessages),
            'Expected completion log message not found'
        );
    }

    public function test_sync_error_returns_500()
    {
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfigAsArgs')
            ->with('post')
            ->andReturn([
                '--type' => 'post',
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ]);

        Artisan::shouldReceive('call')
            ->andThrow(new \Exception('Sync error'));

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(500);
        $response->assertSee('Error executing sync');
    }

    public function test_webhook_handles_custom_pattern()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('custom')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('custom')
            ->andReturn([]);

        $this->syncConfigService->shouldReceive('getConfigAsArgs')
            ->with('custom')
            ->andReturn([
                '--type' => 'custom',
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ]);

        $response = $this->postJson('/webhook/custom', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'custom' &&
                $config['shouldPull'] === true &&
                $config['skipIfNoChanges'] === true;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
