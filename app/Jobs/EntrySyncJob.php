<?php

namespace App\Jobs;

use App\Services\EntrySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [10, 60, 180];

    /**
     * Create a new job instance.
     *
     * @param  string  $type  The type of content being synced
     * @param  Filesystem  $disk  The filesystem disk to use for syncing
     * @param  bool  $shouldPull  Whether to pull latest changes from Git (default: false)
     * @param  bool  $skipIfNoChanges  Whether to skip processing if no changes detected (default: false)
     * @param  string|null  $webhookUrl  The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $type,
        protected Filesystem $disk,
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false,
        protected ?string $webhookUrl = null
    ) {}

    /**
     * Execute the job.
     *
     * @throws \Exception If synchronization fails
     */
    public function handle(EntrySyncService $syncService): void
    {
        try {
            Log::info("Starting EntrySyncJob for type: {$this->type}");

            // Perform the sync
            $result = $syncService->sync(
                type: $this->type,
                disk: $this->disk,
                shouldPull: $this->shouldPull,
                skipIfNoChanges: $this->skipIfNoChanges
            );

            // Log the sync results
            Log::info("Sync completed for type {$this->type}", [
                'files_processed' => $result['files_processed'],
                'entries_updated' => $result['entries_updated'],
                'entries_created' => $result['entries_created'],
                'entries_deleted' => $result['entries_deleted'],
            ]);

            // If sync was skipped due to no changes, return early
            if ($result['skipped']) {
                Log::info("Sync skipped for type {$this->type} - no changes detected");
                return;
            }

            // Trigger webhook if configured
            if ($this->webhookUrl) {
                $this->triggerWebhook($result);
            }
        } catch (\Exception $e) {
            Log::error("EntrySyncJob failed for type {$this->type}: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            // Trigger webhook with error status if configured
            if ($this->webhookUrl) {
                $this->triggerWebhookError($e);
            }

            throw $e;
        }
    }

    /**
     * Trigger the webhook with successful sync results.
     */
    protected function triggerWebhook(array $result): void
    {
        dispatch(new WebhookTriggerJob(
            webhookUrl: $this->webhookUrl,
            contentType: $this->type,
            payload: [
                'status' => 'completed',
                'files_processed' => $result['files_processed'],
                'entries_updated' => $result['entries_updated'],
                'entries_created' => $result['entries_created'],
                'entries_deleted' => $result['entries_deleted'],
                'timestamp' => now()->toIso8601String(),
            ]
        ));

        Log::info("Webhook job dispatched for type: {$this->type}");
    }

    /**
     * Trigger the webhook with error information.
     */
    protected function triggerWebhookError(\Exception $e): void
    {
        dispatch(new WebhookTriggerJob(
            webhookUrl: $this->webhookUrl,
            contentType: $this->type,
            payload: [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]
        ));

        Log::info("Error webhook job dispatched for type: {$this->type}");
    }

    /**
     * Get the job configuration.
     *
     * @return array The job configuration
     */
    public function getJobConfig(): array
    {
        return [
            'type' => $this->type,
            'disk' => 'filesystem',
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }
}
