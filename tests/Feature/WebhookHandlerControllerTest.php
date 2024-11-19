<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebhookHandlerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Storage::fake('content.docs');

        // Set webhook secret for testing
        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');

        Config::set('flatlayer.repositories.docs', [
            'disk' => 'content.docs',
            'webhook_url' => 'https://example.com/webhook',
            'pull' => true,
        ]);
    }

    public function test_webhook_requires_valid_signature()
    {
        $payload = ['repository' => ['name' => 'test-repo']];

        // Test with invalid signature
        $response = $this->postJson('/webhook/docs', $payload, [
            'X-Hub-Signature-256' => 'invalid_signature',
        ]);

        $response->assertStatus(403)
            ->assertSeeText('Invalid signature');

        // Test with valid signature
        $validSignature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/docs', $payload, [
            'X-Hub-Signature-256' => $validSignature,
        ]);

        $response->assertStatus(202)
            ->assertSeeText('Sync initiated');
    }

    public function test_webhook_initiates_sync_job()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        // Configure test webhook URL
        Config::set('flatlayer.sync.docs.webhook_url', 'https://example.com/webhook');

        $response = $this->postJson('/webhook/docs', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        // Verify that the sync job was dispatched with correct parameters
        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'docs'
                && $config['shouldPull'] === true
                && $config['skipIfNoChanges'] === true
                && $config['webhookUrl'] === 'https://example.com/webhook';
        });
    }

    public function test_webhook_handles_empty_payload()
    {
        $payload = [];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/docs', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(EntrySyncJob::class);
    }

    public function test_webhook_route_is_throttled()
    {
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        // Make 11 requests (throttle limit is 10 per minute)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/webhook/docs', $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);

            if ($i < 10) {
                $response->assertStatus(202);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    public function test_webhook_handles_missing_signature()
    {
        $payload = ['repository' => ['name' => 'test-repo']];

        $response = $this->postJson('/webhook/docs', $payload, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(403)
            ->assertSeeText('Invalid signature');
    }

    public function test_webhook_handles_malformed_json_payload()
    {
        $payload = ['invalid' => null];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->withHeaders([
            'Content-Type' => 'text/plain',
            'X-Hub-Signature-256' => $signature,
        ])->post('/webhook/docs', ['invalid']);

        $response->assertStatus(400);
    }
}
