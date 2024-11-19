<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookHandlerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Set webhook secret for testing
        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');
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

        $response = $this->postJson('/webhook/docs', $payload);

        $response->assertStatus(403)
            ->assertSeeText('Invalid signature');
    }

    public function test_webhook_handles_malformed_json_payload()
    {
        $payload = 'invalid json';
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/webhook/docs', $payload, [
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(400);
    }
}
