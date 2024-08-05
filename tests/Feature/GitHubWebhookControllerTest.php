<?php

namespace Tests\Feature;

use App\Jobs\ProcessGitHubWebhookJob;
use App\Services\ModelResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitHubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('flatlayer.github.webhook_secret', 'test_secret');
    }

    public function test_handle_valid_webhook()
    {
        Queue::fake();

        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $this->mock(ModelResolverService::class, function ($mock) {
            $mock->shouldReceive('resolve')
                ->with('posts')
                ->andReturn('App\Models\Post');
        });

        $response = $this->postJson('/posts/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertSee('Webhook received');

        Queue::assertPushed(ProcessGitHubWebhookJob::class);
    }

    public function test_handle_invalid_signature()
    {
        $payload = ['test' => 'data'];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/posts/webhook', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(403);
        $response->assertSee('Invalid signature');
    }

    public function test_handle_invalid_model_slug()
    {
        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $this->mock(ModelResolverService::class, function ($mock) {
            $mock->shouldReceive('resolve')
                ->with('invalid-model')
                ->andReturnNull();
        });

        $response = $this->postJson('/invalid-model/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(400);
        $response->assertSee('Invalid model slug');
    }
}
