<?php

namespace Tests\Feature;

use App\Jobs\ContentSyncJob;
use App\Services\SyncConfigurationService;
use CzProject\GitPhp\Git;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class GitHubWebhookFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;
    protected $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);

        Queue::fake();

        Log::listen(function($message) {
            $this->logMessages[] = $message->message;
        });
    }

    public function testValidWebhookRequest()
    {
        $tempDir = Storage::path('temp_posts');
        mkdir($tempDir, 0755, true);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('post')
            ->andReturn([
                'path' => $tempDir,
                '--pattern' => '*.md',
            ]);

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertSee('Sync initiated');

        Queue::assertPushed(ContentSyncJob::class, function ($job) use ($tempDir) {
            $config = $job->getJobConfig();
            return $config['type'] === 'post' &&
                $config['path'] === $tempDir &&
                $config['pattern'] === '*.md' &&
                $config['shouldPull'] === true &&
                $config['skipIfNoChanges'] === true;
        });

        rmdir($tempDir);
    }

    public function testInvalidSignature()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(403);
        $response->assertSee('Invalid signature');

        Queue::assertNotPushed(ContentSyncJob::class);
        $this->assertTrue(in_array('Invalid GitHub webhook signature', $this->logMessages), "Expected log message not found");
    }

    public function testInvalidContentType()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('invalid-type')
            ->andReturn(false);

        $response = $this->postJson('/webhook/invalid-type', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(400);
        $response->assertSee('Configuration for invalid-type not found');

        Queue::assertNotPushed(ContentSyncJob::class);
        $this->assertTrue(in_array('Configuration for invalid-type not found', $this->logMessages), "Expected log message not found");
    }

    public function testContentSyncJob()
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

        $job = new ContentSyncJob($path, $type, $pattern, true, true);
        $job->handle($gitMock);

        $this->assertTrue(
            in_array("Starting content sync for type: {$type}", $this->logMessages),
            "Expected log message not found"
        );

        $this->assertTrue(
            in_array("Content sync completed for type: {$type}", $this->logMessages),
            "Expected log message not found"
        );
    }

    public function testHandleSyncError()
    {
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('post')
            ->andReturn([
                'path' => '/path/to/posts',
                '--pattern' => '**/*.md',
            ]);

        Artisan::shouldReceive('call')
            ->andThrow(new \Exception('Sync error'));

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(500);
        $response->assertSee('Error executing sync');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
