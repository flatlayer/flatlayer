<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\SyncConfigurationService;
use CzProject\GitPhp\Git;
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

    protected array $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);

        Queue::fake();

        Log::listen(function ($message) {
            $this->logMessages[] = $message->message;
        });
    }

    public function test_valid_webhook_request_triggers_sync()
    {
        $tempDir = Storage::path('temp_posts');
        mkdir($tempDir, 0755, true);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('post')
            ->andReturn([]);

        $this->syncConfigService->shouldReceive('getConfigAsArgs')
            ->with('post')
            ->andReturn([
                '--path' => $tempDir,
                '--pattern' => '*.md',
                '--pull' => true,
            ]);

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertSee('Sync initiated');

        // Verify that the correct job was pushed to the queue
        Queue::assertPushed(EntrySyncJob::class, function ($job) use ($tempDir) {
            $config = $job->getJobConfig();

            return $config['type'] === 'post' &&
                $config['path'] === $tempDir &&
                $config['pattern'] === '*.md' &&
                $config['shouldPull'] === true &&
                $config['skipIfNoChanges'] === true;
        });

        rmdir($tempDir);
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
        $this->assertTrue(in_array('Invalid GitHub webhook signature', $this->logMessages), 'Expected log message not found');
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
        $this->assertTrue(in_array('Configuration for invalid-type not found', $this->logMessages), 'Expected log message not found');
    }

    public function test_entry_sync_job_logs_correctly()
    {
        $gitMock = Mockery::mock(Git::class);
        $this->app->instance(Git::class, $gitMock);

        $repoMock = Mockery::mock('CzProject\GitPhp\GitRepository');
        $gitMock->shouldReceive('open')->andReturn($repoMock);
        $repoMock->shouldReceive('pull')->once();
        $repoMock->shouldReceive('getLastCommitId->toString')->twice()->andReturn('old-hash', 'new-hash');

        $type = 'post';
        $path = '/path/to/posts';
        $pattern = '*.md';

        $job = new EntrySyncJob($path, $type, $pattern, true, true);
        $job->handle($gitMock);

        // Check for start and completion log messages
        $this->assertTrue(
            in_array("Starting content sync for type: {$type}", $this->logMessages),
            'Expected start log message not found'
        );
        $this->assertTrue(
            in_array("Content sync completed for type: {$type}", $this->logMessages),
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
                '--path' => '/path/to/posts',
                '--pattern' => '**/*.md',
            ]);

        // Simulate a sync error
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
        $tempDir = Storage::path('temp_custom');
        mkdir($tempDir, 0755, true);

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
                '--path' => $tempDir,
                '--pattern' => '*.custom',
                '--pull' => true,
            ]);

        $response = $this->postJson('/webhook/custom', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'custom' && $config['pattern'] === '*.custom';
        });

        rmdir($tempDir);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
