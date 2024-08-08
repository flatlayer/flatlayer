<?php

namespace Tests\Feature;

use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class GitHubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('flatlayer.github.webhook_secret', 'test_secret');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);
    }

    public function test_handle_valid_webhook()
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
            ->with('flatlayer:content-sync', [
                'path' => '/path/to/posts',
                '--pattern' => '**/*.md',
                '--type' => 'post',
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ])
            ->once();

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertSee('Sync initiated');
    }

    public function test_handle_invalid_signature()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(403);
        $response->assertSee('Invalid signature');
    }

    public function test_handle_invalid_type()
    {
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('invalid-type')
            ->andReturn(false);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhook/invalid-type', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(400);
        $response->assertSee('Configuration for invalid-type not found');
    }

    public function test_handle_sync_error()
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
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

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
