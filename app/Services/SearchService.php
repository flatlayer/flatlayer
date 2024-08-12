<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MathPHP\Statistics\Distance;
use Pgvector\Vector;

class SearchService
{
    public function __construct(
        protected readonly JinaSearchService $jinaService
    ) {}

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
    public function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        $embedding = $this->jinaService->embed([$query])[0]['embedding'];
        $builder ??= Entry::query();

        $results = match (DB::connection()->getDriverName()) {
            'pgsql' => $this->pgVectorSearch($builder, $embedding, $limit),
            default => $this->fallbackSearch($builder, $embedding, $limit),
        };

        return $rerank ? $this->rerankResults($query, $results) : $results;
    }

    /**
     * Perform a vector search using PostgreSQL.
     *
     * @param  Builder  $builder  The query builder
     * @param  array  $embedding  The query embedding
     * @param  int  $limit  The maximum number of results to return
     * @return Collection The search results
     */
    protected function pgVectorSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        return $builder
            ->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [new Vector($embedding)])
            ->orderByDesc('similarity')
            ->limit($limit)
            ->get();
    }

    /**
     * Perform a fallback search using cosine similarity.
     *
     * @param  Builder  $builder  The query builder
     * @param  array  $embedding  The query embedding
     * @param  int  $limit  The maximum number of results to return
     * @return Collection The search results
     */
    protected function fallbackSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        return $builder->get()
            ->map(fn ($item) => tap($item, fn () => $item->similarity = Distance::cosineSimilarity($item->embedding->toArray(), $embedding)))
            ->sortByDesc('similarity')
            ->take($limit);
    }

    /**
     * Get the embedding for a given text.
     *
     * @param  string  $text  The text to embed
     * @return array The embedding
     *
     * @throws \Exception
     */
    public function getEmbedding(string $text): array
    {
        return $this->jinaService->embed([$text])[0]['embedding'];
    }

    /**
     * Rerank the search results.
     *
     * @param  string  $query  The original search query
     * @param  Collection  $results  The initial search results
     * @return Collection The reranked results
     *
     * @throws \Exception
     */
    protected function rerankResults(string $query, Collection $results): Collection
    {
        $results = $results->map(fn ($result) => tap($result, fn () => $result->relevance = 0))->values();

        $documents = $results->map(fn ($result) => $result->toSearchableText())->toArray();
        $rerankedResults = $this->jinaService->rerank($query, $documents);

        if (! isset($rerankedResults['results']) || ! is_array($rerankedResults['results'])) {
            return $results;
        }

        return collect($rerankedResults['results'])
            ->map(function ($rerankedResult) use ($results) {
                if (! isset($rerankedResult['index'], $results[$rerankedResult['index']])) {
                    return null;
                }

                return tap($results[$rerankedResult['index']], fn ($originalResult) => $originalResult->relevance = $rerankedResult['relevance_score'] ?? 0);
            })
            ->filter()
            ->sortByDesc('relevance')
            ->values();
    }
}
