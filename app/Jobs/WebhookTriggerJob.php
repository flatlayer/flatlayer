<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookTriggerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 60, 180];

    /**
     * Create a new job instance.
     *
     * @param  string  $webhookUrl  The URL to send the webhook request to
     * @param  string  $contentType  The type of content that was synced
     * @param  array  $payload  Additional payload data to send with the webhook
     */
    public function __construct(
        protected string $webhookUrl,
        protected string $contentType,
        protected array $payload = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Triggering webhook for content type: {$this->contentType}");

        try {
            $response = Http::post($this->webhookUrl, [
                'content_type' => $this->contentType,
                'event' => 'sync_completed',
                'timestamp' => now()->toIso8601String(),
                ...$this->payload,
            ]);

            if ($response->successful()) {
                Log::info("Webhook for {$this->contentType} triggered successfully.");
            } else {
                Log::warning("Webhook request failed for {$this->contentType}. Status: {$response->status()}");
                $this->fail($response->body());
            }
        } catch (\Exception $e) {
            Log::error("Error triggering webhook for {$this->contentType}: ".$e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable|string  $exception
     */
    public function failed($exception): void
    {
        Log::error("WebhookTriggerJob failed for {$this->contentType}: ".$exception);
    }
}
