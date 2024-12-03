<?php

namespace App\Console\Commands;

use App\Services\Content\ContentLintService;
use App\Services\Storage\StorageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LintCommand extends Command
{
    protected $signature = 'flatlayer:lint
                            {--type= : Content type (required)}
                            {--disk= : Optional disk name to use instead of configured repository}
                            {--external : Check external links}
                            {--fix : Attempt to fix broken internal links}
                            {--ignore= : Comma-separated list of URLs or patterns to ignore}';

    protected $description = 'Lint content files for broken links and other issues';

    private Collection $externalIssues;

    private Collection $internalIssues;

    private Collection $fileIssues;

    public function __construct(
        protected ContentLintService $lintService,
        protected StorageResolver $diskResolver
    ) {
        parent::__construct();
        $this->externalIssues = collect();
        $this->internalIssues = collect();
        $this->fileIssues = collect();
    }

    public function handle()
    {
        $type = $this->option('type');

        if (! $type) {
            $this->error("The '--type' option is required.");
            return Command::FAILURE;
        }

        try {
            // Initialize service with content type
            $disk = $this->option('disk');
            $this->lintService->initializeForType($type, $disk);

            // Configure external link checking
            $this->lintService->setCheckExternal($this->option('external'));

            // Check filenames first
            $this->checkFilenames();

            // Check internal links
            $this->checkInternalLinks();

            // Check external links if enabled
            if ($this->option('external')) {
                $this->checkExternalLinks();
            }

            // Display results
            $this->displayResults();

            // Handle fixes if requested
            if ($this->option('fix') && $this->internalIssues->isNotEmpty()) {
                $this->handleFixes();
            }

            return $this->determineExitCode();

        } catch (\Exception $e) {
            $this->error("Error running lint: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function checkFilenames(): void
    {
        $this->info('Checking filenames...');
        $issues = $this->lintService->checkFilenames();

        $this->fileIssues = collect($issues)->map(function($issue, $file) {
            return [
                'file' => $file,
                'message' => $issue['message'],
                'fixable' => isset($issue['fix']),
                'fix' => $issue['fix'] ?? null,
            ];
        });
    }

    protected function checkInternalLinks(): void
    {
        $this->info('Checking internal links...');
        $issues = $this->lintService->checkInternalLinks();

        $this->internalIssues = collect($issues)->map(function($issue) {
            return [
                'file' => $issue['file'],
                'line' => $issue['line'],
                'link' => $issue['link'],
                'text' => $issue['text'],
                'suggestions' => $issue['suggestions'] ?? [],
            ];
        });
    }

    protected function checkExternalLinks(): void
    {
        $this->info('Checking external links...');
        $ignorePatterns = $this->getIgnorePatterns();

        $issues = $this->lintService->checkExternalLinks();

        $this->externalIssues = collect($issues)
            ->filter(function($issue) use ($ignorePatterns) {
                $link = $issue['link'];
                return !$ignorePatterns->contains(function($pattern) use ($link) {
                    return fnmatch($pattern, $link);
                });
            })
            ->map(function($issue) {
                return [
                    'file' => $issue['file'],
                    'line' => $issue['line'],
                    'link' => $issue['link'],
                    'status' => $issue['status'],
                ];
            });
    }

    protected function displayResults(): void
    {
        $this->newLine();

        // Show filename issues
        if ($this->fileIssues->isNotEmpty()) {
            $this->info('📁 Filename Issues:');
            $this->table(
                ['File', 'Issue', 'Fixable'],
                $this->fileIssues->map(fn($issue) => [
                    $issue['file'],
                    $issue['message'],
                    $issue['fixable'] ? 'Yes' : 'No'
                ])
            );
        }

        // Show internal link issues
        if ($this->internalIssues->isNotEmpty()) {
            $this->info('🔗 Internal Link Issues:');
            $this->table(
                ['File', 'Line', 'Link', 'Text', 'Suggestions'],
                $this->internalIssues->map(fn($issue) => [
                    $issue['file'],
                    $issue['line'],
                    $issue['link'],
                    $issue['text'],
                    empty($issue['suggestions'])
                        ? 'No suggestions'
                        : collect($issue['suggestions'])
                        ->take(3)
                        ->map(fn($s) => "{$s['title']} ({$s['slug']})")
                        ->join("\n"),
                ])
            );
        }

        // Show external link issues
        if ($this->externalIssues->isNotEmpty()) {
            $this->info('🌐 External Link Issues:');
            $this->table(
                ['File', 'Line', 'Link', 'Status'],
                $this->externalIssues->map(fn($issue) => [
                    $issue['file'],
                    $issue['line'],
                    $issue['link'],
                    $issue['status'],
                ])
            );
        }

        // Show summary
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Issue Type', 'Count'],
            [
                ['Filename Issues', $this->fileIssues->count()],
                ['Internal Link Issues', $this->internalIssues->count()],
                ['External Link Issues', $this->externalIssues->count()],
            ]
        );
    }

    protected function handleFixes(): void
    {
        if (!$this->confirm('Would you like to fix broken internal links?')) {
            return;
        }

        foreach ($this->internalIssues as $issue) {
            $this->info("Fixing link in {$issue['file']} line {$issue['line']}:");
            $this->line("Current link: [{$issue['text']}]({$issue['link']})");

            if (empty($issue['suggestions'])) {
                $this->warn('No suggestions available for this link.');
                continue;
            }

            // Display suggestions
            $suggestions = collect($issue['suggestions'])->take(5);
            $choices = $suggestions->map(fn($s, $i) => sprintf(
                "%d) %s (%s) [score: %.2f]",
                $i + 1,
                $s['title'],
                $s['slug'],
                $s['score']
            ))->prepend('Skip this link');

            $choice = $this->choice('Select a replacement link:', $choices->toArray());

            if ($choice === 'Skip this link') {
                continue;
            }

            // Get the selected suggestion
            $index = array_search($choice, $choices->toArray()) - 1;
            $selected = $suggestions[$index];

            // Fix the link
            $this->lintService->fixInternalLink(
                $issue['file'],
                $issue['line'],
                $issue['link'],
                $selected['slug']
            );

            $this->info("✅ Link updated to: [{$issue['text']}]({$selected['slug']})");
        }
    }

    protected function getIgnorePatterns(): Collection
    {
        $patterns = $this->option('ignore');
        if (!$patterns) {
            return collect();
        }

        return collect(explode(',', $patterns))
            ->map(fn($pattern) => trim($pattern))
            ->filter();
    }

    protected function determineExitCode(): int
    {
        $hasIssues = $this->fileIssues->isNotEmpty()
            || $this->internalIssues->isNotEmpty()
            || $this->externalIssues->isNotEmpty();

        return $hasIssues ? Command::FAILURE : Command::SUCCESS;
    }
}
