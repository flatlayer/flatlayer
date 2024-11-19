<?php

namespace App\Jobs;

use App\Services\EntrySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public array $backoff = [10, 60, 180];

    /**
     * Create a new job instance.
     *
     * @param string $type The type of content being synced
     * @param string|null $path Local path to the content repository
     * @param string|null $pattern The glob pattern for finding content files
     * @param bool $shouldPull Whether to pull latest changes from Git (default: false)
     * @param bool $skipIfNoChanges Whether to skip processing if no changes detected (default: false)
     * @param string|null $webhookUrl The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $type,
        protected ?string $path = null,
        protected ?string $pattern = null,
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

            // Create the disk if a path was provided
            $disk = null;
            if ($this->path) {
                $diskName = "sync_{$this->type}_".md5($this->path);

                // Configure the disk
                config(["filesystems.disks.{$diskName}" => [
                    'driver' => 'local',
                    'root' => $this->path,
                    'throw' => true,
                ]]);

                $disk = Storage::build([
                    'driver' => 'local',
                    'root' => $this->path,
                    'throw' => true,
                ]);
            }

            // Perform the sync
            $result = $syncService->sync(
                type: $this->type,
                disk: $disk,
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
            'path' => $this->path,
            'pattern' => $this->pattern,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }
}
