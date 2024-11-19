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
     * Create a new job instance.
     *
     * @param string $type The type of content being synced
     * @param string|null $diskName The name of the Laravel disk to use (optional)
     * @param bool $shouldPull Whether to pull latest changes from Git (default: false)
     * @param bool $skipIfNoChanges Whether to skip processing if no changes detected (default: false)
     * @param string|null $webhookUrl The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $type,
        protected ?string $diskName = null,
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false,
        protected ?string $webhookUrl = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EntrySyncService $syncService): void
    {
        try {
            // Get the disk if one was specified
            $disk = $this->diskName ? Storage::disk($this->diskName) : null;

            $result = $syncService->sync(
                type: $this->type,
                disk: $disk,
                shouldPull: $this->shouldPull,
                skipIfNoChanges: $this->skipIfNoChanges
            );

            if ($result['skipped']) {
                return;
            }

            if ($this->webhookUrl) {
                dispatch(new WebhookTriggerJob($this->webhookUrl, $this->type, [
                    'files_processed' => $result['files_processed'],
                    'entries_updated' => $result['entries_updated'],
                    'entries_created' => $result['entries_created'],
                    'entries_deleted' => $result['entries_deleted'],
                ]));
                Log::info("Webhook job dispatched for type: {$this->type}");
            }
        } catch (\Exception $e) {
            Log::error("EntrySyncJob failed for type {$this->type}: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            if ($this->webhookUrl) {
                dispatch(new WebhookTriggerJob($this->webhookUrl, $this->type, [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]));
            }

            throw $e;
        }
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
            'diskName' => $this->diskName,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }
}
