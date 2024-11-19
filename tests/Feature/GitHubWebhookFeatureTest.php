<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GitHubWebhookFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected array $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('content.post');
        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');
        Queue::fake();

        Log::listen(function ($message) {
            $this->logMessages[] = $message->message;
        });
    }

    public function test_valid_webhook_request_triggers_sync()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
            'pull' => true,
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
                $config['skipIfNoChanges'] === true &&
                $config['webhookUrl'] === 'http://example.com/webhook';
        });
    }

    public function test_invalid_signature_returns_403()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $invalidSignature = 'sha256=invalid_signature';

        // Add a config so we don't get a 404
        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
            'pull' => true,
        ]);

        $response = $this->postJson('/webhook/post', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(403);
        $response->assertSee('Invalid signature');

        Queue::assertNotPushed(EntrySyncJob::class);
        $this->assertTrue(in_array('Invalid GitHub webhook signature', $this->logMessages));
    }

    public function test_invalid_content_type_returns_404()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/invalid-type', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(404);
        Queue::assertNotPushed(EntrySyncJob::class);
    }

    public function test_webhook_handles_multiple_repositories()
    {
        Storage::fake('content.docs');
        Storage::fake('content.blog');

        Config::set('flatlayer.repositories', [
            'docs' => [
                'disk' => 'content.docs',
                'webhook_url' => 'http://example.com/webhook/docs',
                'pull' => true,
            ],
            'blog' => [
                'disk' => 'content.blog',
                'webhook_url' => 'http://example.com/webhook/blog',
                'pull' => true,
            ],
        ]);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        foreach (['docs', 'blog'] as $type) {
            $response = $this->postJson("/webhook/{$type}", $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);

            $response->assertStatus(202);
            $response->assertSee('Sync initiated');

            Queue::assertPushed(EntrySyncJob::class, function ($job) use ($type) {
                $config = $job->getJobConfig();

                return $config['type'] === $type &&
                    $config['webhookUrl'] === "http://example.com/webhook/{$type}";
            });
        }
    }

    public function test_webhook_respects_repository_config()
    {
        Storage::fake('content.custom');
        Config::set('flatlayer.repositories.custom', [
            'disk' => 'content.custom',
            'webhook_url' => 'http://example.com/webhook/custom',
            'pull' => false, // Should not pull
        ]);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/custom', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'custom' &&
                $config['shouldPull'] === false &&
                $config['webhookUrl'] === 'http://example.com/webhook/custom';
        });
    }

    public function test_webhook_handles_missing_webhook_url()
    {
        Storage::fake('content.test');

        Config::set('flatlayer.repositories.test', [
            'disk' => 'content.test',
            'pull' => true,
        ]);

        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/test', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'test' &&
                $config['webhookUrl'] === null;
        });
    }

    public function test_webhook_handles_malformed_payload()
    {
        Storage::fake('content.test');

        Config::set('flatlayer.repositories.test', [
            'disk' => 'content.test',
        ]);

        $invalidPayload = ['invalid' => null];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($invalidPayload), 'test_webhook_secret');

        $response = $this->withHeaders([
            'Content-Type' => 'text/plain',
            'X-Hub-Signature-256' => $signature,
        ])->post('/webhook/test', ['invalid']);

        $response->assertStatus(400);
        Queue::assertNotPushed(EntrySyncJob::class);
    }
}
