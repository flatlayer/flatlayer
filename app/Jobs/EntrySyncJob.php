<?php

namespace App\Jobs;

use App\Services\Content\ContentSyncManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 180];

    public function __construct(
        protected string $type,
        protected string|array|Filesystem|null $disk = null,
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false,
        protected ?string $webhookUrl = null
    ) {}

    public function handle(ContentSyncManager $syncManager): void
    {
        try {
            $result = $syncManager->sync(
                type: $this->type,
                disk: $this->disk,
                shouldPull: $this->shouldPull,
                skipIfNoChanges: $this->skipIfNoChanges
            );

            if (! $result['skipped'] && $this->webhookUrl) {
                dispatch(new WebhookTriggerJob(
                    webhookUrl: $this->webhookUrl,
                    contentType: $this->type,
                    payload: [
                        'status' => 'completed',
                        ...$result,
                        'timestamp' => now()->toIso8601String(),
                    ]
                ));
            }
        } catch (\Exception $e) {
            if ($this->webhookUrl) {
                dispatch(new WebhookTriggerJob(
                    webhookUrl: $this->webhookUrl,
                    contentType: $this->type,
                    payload: [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String(),
                    ]
                ));
            }
            throw $e;
        }
    }

    /**
     * Get the job configuration for testing.
     *
     * @return array The job configuration
     */
    public function getJobConfig(): array
    {
        return [
            'type' => $this->type,
            'disk' => $this->disk,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }
}
