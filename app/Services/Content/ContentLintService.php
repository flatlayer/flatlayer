<?php

namespace App\Services\Content;

use App\Models\Entry;
use App\Services\Storage\StorageResolver;
use App\Support\Path;
use DOMDocument;
use DOMNode;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Path as SymfonyPath;

class ContentLintService
{
    protected Filesystem $disk;

    protected Collection $entries;

    private ClientInterface $httpClient;

    protected array $fileIssues = [];

    protected array $linkIssues = [];

    protected bool $checkExternal = false;

    protected array $externalLinkCache = [];

    public function __construct(
        protected readonly StorageResolver $diskResolver,
        protected readonly ContentSearch $searchService,
        ?ClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new \GuzzleHttp\Client(['timeout' => 10]);
    }

    /**
     * Initialize the service with a specific disk and content type.
     */
    public function initializeForType(string $type, ?string $disk = null): void
    {
        $this->disk = $this->diskResolver->resolve($disk, $type);
        $this->entries = Entry::where('type', $type)->get();
        $this->fileIssues = [];
        $this->linkIssues = [];
    }

    /**
     * Check for common file naming issues.
     *
     * @return array<string, array{
     *     message: string,
     *     fix?: callable
     * }>
     */
    public function checkFilenames(): array
    {
        $files = $this->disk->allFiles();
        $issues = [];

        foreach ($files as $file) {
            // Check for trailing whitespace in filename
            if (preg_match('/\s+$/', $file)) {
                $newName = rtrim($file);
                $issues[$file] = [
                    'message' => 'File has trailing whitespace',
                    'fix' => fn () => $this->disk->move($file, $newName),
                ];
            }

            // Check for incorrect extension case
            if (preg_match('/\.MD$/i', $file) && !str_ends_with($file, '.md')) {
                $newName = preg_replace('/\.MD$/i', '.md', $file);
                $issues[$file] = [
                    'message' => 'File has incorrect extension case',
                    'fix' => fn () => $this->disk->move($file, $newName),
                ];
            }
        }

        return $this->fileIssues = $issues;
    }

    /**
     * Check for broken internal links in markdown files.
     *
     * @return array<string, array{
     *     file: string,
     *     line: int,
     *     link: string,
     *     text: string,
     *     suggestions?: array<int, array{
     *         title: string,
     *         slug: string,
     *         score: float
     *     }>
     * }>
     */
    public function checkInternalLinks(): array
    {
        $files = $this->disk->allFiles();
        $issues = [];

        foreach ($files as $file) {
            if (!str_ends_with(strtolower($file), '.md')) {
                continue;
            }

            $content = $this->disk->get($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        [, $text, $link] = $match;

                        // Skip external links and anchors
                        if ($this->isExternalLink($link) || $this->isAnchorLink($link)) {
                            continue;
                        }

                        // Convert link to slug
                        $linkPath = $this->resolveInternalLink($link, $file);
                        $slug = Path::toSlug($linkPath);

                        $entryExists = $this->entries->where('slug', $slug)->count() > 0;

                        // Check if the linked file exists
                        if (!$entryExists) {
                            // Find similar content using embedding search
                            $suggestions = $this->findSimilarContent($text, $slug);

                            $issues["{$file}:{$lineNum}:{$link}"] = [
                                'file' => $file,
                                'line' => $lineNum + 1,
                                'link' => $link,
                                'text' => $text,
                                'suggestions' => $suggestions,
                            ];
                        }
                    }
                }
            }
        }

        return $this->linkIssues = $issues;
    }

    /**
     * Check external links for validity.
     *
     * @return array<string, array{
     *     file: string,
     *     line: int,
     *     link: string,
     *     status: int|string
     * }>
     */
    public function checkExternalLinks(): array
    {
        if (!$this->checkExternal) {
            return [];
        }

        $client = new Client(['timeout' => 10]);
        $files = $this->disk->allFiles();
        $issues = [];
        $requests = [];
        $issueData = [];  // New array to store full issue data

        // First, collect all external links
        foreach ($files as $file) {
            if (!str_ends_with(strtolower($file), '.md')) {
                continue;
            }

            $content = $this->disk->get($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        [, $text, $link] = $match;  // Also capture link text

                        if ($this->isExternalLink($link) && !isset($this->externalLinkCache[$link])) {
                            $key = "{$file}:{$lineNum}:{$link}";
                            // Create request for this link
                            $requests[] = new Request('HEAD', $link);
                            $this->externalLinkCache[$link] = "pending";

                            // Store full issue info
                            $issueData[] = [
                                'key' => $key,
                                'file' => $file,
                                'line' => $lineNum + 1,
                                'link' => $link,
                                'text' => $text,
                                'status' => 'pending'
                            ];
                        }
                    }
                }
            }
        }

        // Now check all external links concurrently
        $pool = new Pool($client, $requests, [
            'concurrency' => 5,
            'fulfilled' => function (Response $response, $index) use (&$issues, $issueData) {
                $data = $issueData[$index];
                $this->externalLinkCache[$data['link']] = $response->getStatusCode();
                $data['status'] = $response->getStatusCode();
                if ($data['status'] >= 400) {
                    $issues[$data['key']] = $data;
                }
            },
            'rejected' => function (\Exception $e, $index) use (&$issues, $issueData) {
                $data = $issueData[$index];
                $status = $e instanceof RequestException && $e->hasResponse()
                    ? $e->getResponse()->getStatusCode()
                    : 'connection failed';
                $this->externalLinkCache[$data['link']] = $status;
                $data['status'] = $status;
                $issues[$data['key']] = $data;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $issues;
    }

    /**
     * Fix a broken internal link.
     */
    public function fixInternalLink(string $file, int $line, string $oldLink, string $newSlug): bool
    {
        if (!$this->disk->exists($file)) {
            return false;
        }

        $content = $this->disk->get($file);
        $lines = explode("\n", $content);

        // Find the entry that matches the new slug
        $targetEntry = $this->entries->firstWhere('slug', $newSlug);
        if (!$targetEntry) {
            return false;
        }

        // Replace the link in the specified line
        $lines[$line - 1] = preg_replace(
            '/\[([^\]]+)\]\(' . preg_quote($oldLink, '/') . '\)/',
            '[$1](' . SymfonyPath::makeRelative($targetEntry->filename, SymfonyPath::getDirectory($file)) . ')',
            $lines[$line - 1]
        );

        // Write the updated content back to the file
        $this->disk->put($file, implode("\n", $lines));

        return true;
    }

    /**
     * Enable or disable external link checking.
     */
    public function setCheckExternal(bool $check): void
    {
        $this->checkExternal = $check;
    }

    /**
     * Find similar content using embedding search.
     *
     * @return array<int, array{
     *     title: string,
     *     slug: string,
     *     score: float
     * }>
     */
    protected function findSimilarContent(string $searchText, string $originalSlug): array
    {
        // Create a more comprehensive search text by combining the link text and path text
        $pathText = $this->getSearchablePathText($originalSlug);
        $combinedText = trim($searchText.' '.$pathText);

        // Use the injected search service with combined text
        $results = $this->searchService->search($combinedText, 3);

        return $results->map(function ($entry) use ($originalSlug) {
            // Calculate string similarity between slugs as a tiebreaker
            $slugSimilarity = similar_text($entry->slug, $originalSlug, $percentage) / 100;

            return [
                'title' => $entry->title,
                'slug' => $entry->slug,
                'score' => ($entry->relevance + $slugSimilarity) / 2,
            ];
        })->sortByDesc('score')->values()->toArray();
    }

    /**
     * Convert a path/slug into searchable text by removing extensions and replacing separators with spaces.
     */
    protected function getSearchablePathText(string $path): string
    {
        $path = preg_replace('/\.md$/', '', $path);
        $text = str_replace(['/', '-'], ' ', $path);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Resolve an internal link relative to the current file.
     */
    protected function resolveInternalLink(string $link, string $currentFile): string
    {
        // Remove anchor and query parts
        $link = preg_replace('/(#|\?).*$/', '', $link);

        // If it's an absolute path starting with /
        if (str_starts_with($link, '/')) {
            return Path::toSlug(ltrim($link, '/'));
        }

        // Get the directory path and normalize it
        $currentDir = SymfonyPath::getDirectory($currentFile);

        // Use Symfony's join to combine the paths
        $resolvedPath = SymfonyPath::join($currentDir, $link);

        // Convert to slug format
        $slug = Path::toSlug($resolvedPath);

        // Log what entry exists at this slug
        $entryExists = $this->entries->where('slug', $slug)->first();

        return $slug;
    }

    /**
     * Get the relative path from one file to another.
     */
    protected function getRelativePath(string $from, string $to): string
    {
        $from = explode('/', $from);
        $to = explode('/', $to);

        // Remove common parts
        while (count($from) > 0 && count($to) > 0 && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        // Add "../" for each remaining part in $from
        $relative = str_repeat('../', count($from));

        // Add the remaining $to parts
        $relative .= implode('/', $to);

        return $relative ?: './';
    }

    /**
     * Check if a link is external or uses a URI scheme.
     * Excludes Windows drive letters (e.g., C:) but catches all URI schemes.
     */
    protected function isExternalLink(string $link): bool
    {
        // Match either:
        // 1. anything followed by :// (standard protocol format)
        // 2. anything followed by : that's not a single letter (to exclude Windows drive letters)
        return preg_match('/(:\/\/|(?<![A-Za-z]:|^[A-Za-z]):)/', $link) === 1;
    }

    /**
     * Check if a link is just an anchor.
     */
    protected function isAnchorLink(string $link): bool
    {
        return str_starts_with($link, '#');
    }
}
