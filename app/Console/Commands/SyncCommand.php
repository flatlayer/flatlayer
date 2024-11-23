<?php

namespace App\Console\Commands;

use App\Jobs\EntrySyncJob;
use App\Services\Content\ContentSyncManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncCommand extends Command
{
    protected $signature = 'flatlayer:sync
                            {--type= : Content type (required)}
                            {--disk= : Optional disk name to use instead of configured repository}
                            {--pull : Pull latest changes from Git repository before syncing}
                            {--skip : Skip syncing if no changes are detected}
                            {--dispatch : Dispatch the job to the queue}
                            {--webhook= : URL of the webhook to trigger after sync}
                            {--detailed : Show detailed information about each processed entry}';

    protected $description = 'Sync files from source to Entries, optionally pulling latest changes and triggering a webhook.';

    private ?ProgressBar $progressBar = null;

    private Collection $detailedOutput;

    public function __construct(
        protected ContentSyncManager $syncManager
    ) {
        parent::__construct();
        $this->detailedOutput = collect();
    }

    public function handle()
    {
        $type = $this->option('type');

        if (! $type) {
            $this->error("The '--type' option is required.");

            return Command::FAILURE;
        }

        try {
            if ($this->option('dispatch')) {
                $config = Config::get("flatlayer.repositories.{$type}", []);

                // Handle dispatched job
                dispatch(new EntrySyncJob(
                    type: $type,
                    disk: $this->option('disk') ?? $config['disk'] ?? null,
                    shouldPull: $this->getOptionOrConfig('pull', $config, false),
                    skipIfNoChanges: $this->getOptionOrConfig('skip', $config, false),
                    webhookUrl: $this->option('webhook') ?? $config['webhook_url'] ?? null
                ));

                $this->info("EntrySyncJob for type '{$type}' has been dispatched to the queue.");
                if ($this->option('webhook')) {
                    $this->info("Webhook URL set: {$this->option('webhook')}");
                }

                return Command::SUCCESS;
            }

            // Execute sync with progress reporting
            $result = $this->syncManager->sync(
                type: $type,
                disk: $this->option('disk'),
                shouldPull: $this->option('pull'),
                skipIfNoChanges: $this->option('skip'),
                progressCallback: $this->handleProgress(...)
            );

            if ($result['skipped']) {
                $this->info('Sync skipped - no changes detected');

                return Command::SUCCESS;
            }

            $this->finishProgress();
            $this->displayResults($result);

            // Show detailed output if requested
            if ($this->option('detailed')) {
                $this->displayDetailedResults();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error running sync: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    public function handleProgress(string $message, ?int $current = null, ?int $total = null): void
    {
        // If we're showing detailed output, capture entry-specific messages
        if ($this->option('detailed') && str_contains($message, 'content item:')) {
            preg_match('/(\w+) content item: (.+)$/', $message, $matches);
            if (isset($matches[1], $matches[2])) {
                $action = strtolower($matches[1]);
                $slug = trim($matches[2]);
                $this->detailedOutput->push(['action' => $action, 'slug' => $slug]);
            }
        }

        if ($total && ! $this->progressBar) {
            $this->progressBar = $this->output->createProgressBar($total);
            $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        }

        if ($this->progressBar) {
            $this->progressBar->setMessage($message);
            if ($current !== null) {
                $this->progressBar->setProgress($current);
            }
        } else {
            $this->info($message);
        }
    }

    protected function finishProgress(): void
    {
        if ($this->progressBar) {
            $this->progressBar->finish();
            $this->output->newLine(2);
        }
    }

    protected function displayResults(array $result): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files Processed', $result['files_processed']],
                ['Entries Created', $result['entries_created']],
                ['Entries Updated', $result['entries_updated']],
                ['Entries Deleted', $result['entries_deleted']],
            ]
        );
    }

    protected function displayDetailedResults(): void
    {
        if ($this->detailedOutput->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info('Detailed Entry Status:');
        $this->newLine();

        $rows = $this->detailedOutput->map(function ($item) {
            $status = match ($item['action']) {
                'new' => '🟢 New',
                'updated' => '🟡 Modified',
                'unchanged' => '⚪️ Unchanged',
                'deleted' => '🔴 Deleted',
                default => '❔ Unknown'
            };

            return [$status, $item['slug']];
        });

        $this->table(
            ['Status', 'Slug'],
            $rows->toArray()
        );
    }

    protected function getOptionOrConfig(string $option, array $config, mixed $default): mixed
    {
        if ($this->hasOption($option) && $this->option($option) !== null) {
            return $this->option($option);
        }

        return $config[$option] ?? $default;
    }
}
