<?php

namespace App\Traits;

use App\Services\SearchService;
use DOMDocument;
use DOMNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Trait Searchable
 *
 * This trait provides search functionality to models.
 * It manages the updating of search vectors and provides methods for searching.
 */
trait Searchable
{
    /**
     * Boot the searchable trait.
     */
    public static function bootSearchable(): void
    {
        static::saving(function (Model $model) {
            if ($model instanceof self) {
                $model->updateSearchVectorIfNeeded();
            }
        });
    }

    /**
     * Update the search vector if needed.
     */
    public function updateSearchVectorIfNeeded(): void
    {
        if ($this->shouldUpdateSearchVector()) {
            $this->updateSearchVector();
        }
    }

    /**
     * Determine if the search vector should be updated.
     */
    protected function shouldUpdateSearchVector(): bool
    {
        return $this->isNewSearchableRecord() || $this->hasSearchableChanges();
    }

    /**
     * Check if this is a new searchable record.
     */
    protected function isNewSearchableRecord(): bool
    {
        return ! $this->exists || $this->wasRecentlyCreated;
    }

    /**
     * Check if there are searchable changes.
     */
    protected function hasSearchableChanges(): bool
    {
        $originalModel = $this->getOriginalSearchableModel();

        return $this->toSearchableText() !== $originalModel->toSearchableText();
    }

    /**
     * Creates a new model instance with original attributes for search-related change detection.
     */
    protected function getOriginalSearchableModel(): static
    {
        return tap(new static, function ($model) {
            $model->setRawAttributes($this->getOriginal());
            $model->exists = true;
        });
    }

    /**
     * Update the search vector using the model's searchable text.
     */
    public function updateSearchVector(): void
    {
        $this->embedding = App::make(SearchService::class)->getEmbedding($this->toSearchableText());
    }

    /**
     * Convert the model to searchable text.
     */
    abstract public function toSearchableText(): string;

    /**
     * Strip MDX-style components from the content.
     */
    protected function stripMdxComponents(string $content): string
    {
        // Wrap content in a root element to ensure we can parse partial HTML/XML
        $wrappedContent = '<root>'.$content.'</root>';

        $dom = new DOMDocument;

        // Disable libxml errors and clear the error buffer
        $internalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        // Load HTML, suppressing warnings
        $dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Restore libxml error handling and clear the error buffer
        libxml_use_internal_errors($internalErrors);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $rootNode = $dom->documentElement;

        // Extract text content recursively
        $textContent = $this->extractTextContent($rootNode);

        // Normalize whitespace
        $textContent = preg_replace('/\s+/', ' ', $textContent);

        return trim($textContent);
    }

    /**
     * Recursively extract text content from DOM nodes.
     */
    private function extractTextContent(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }

        $text = '';
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                $text .= $this->extractTextContent($childNode);
            }
        }

        return $text;
    }

    /**
     * Perform a search query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  The maximum number of results to return
     * @param  bool  $rerank  Whether to rerank the results
     * @param  Builder|null  $builder  An optional query builder to start with
     * @return Collection The search results
     *
     * @throws \Exception
     */
    public static function search(
        string $query,
        int $limit = 40,
        bool $rerank = true,
        ?Builder $builder = null
    ): Collection {
        return App::make(SearchService::class)->search($query, $limit, $rerank, $builder ?? static::query());
    }

    /**
     * Scope a query to search for similar records based on embedding.
     */
    public function scopeSearchSimilar(Builder $query, array $embedding): Builder
    {
        return $query->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [$embedding])
            ->orderByDesc('similarity');
    }
}
