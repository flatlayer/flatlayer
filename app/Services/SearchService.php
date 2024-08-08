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
    protected $jinaService;

    public function __construct(JinaSearchService $jinaService)
    {
        $this->jinaService = $jinaService;
    }

    public function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        $embedding = $this->jinaService->embed([$query])[0]['embedding'];

        $builder = $builder ?? Entry::query();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $results = $this->pgVectorSearch($builder, $embedding, $limit);
        } else {
            $results = $this->fallbackSearch($builder, $embedding, $limit);
        }

        if ($rerank) {
            return $this->rerankResults($query, $results);
        }

        return $results;
    }

    protected function pgVectorSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        $vector = new Vector($embedding);
        return $builder
            ->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [$vector])
            ->orderByDesc('similarity')
            ->limit($limit)
            ->get();
    }

    protected function fallbackSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        return $builder->get()
            ->map(function ($item) use ($embedding) {
                $item->similarity = Distance::cosineSimilarity($item->embedding->toArray(), $embedding);
                return $item;
            })
            ->sortByDesc('similarity')
            ->take($limit);
    }

    public function getEmbedding(string $text): array
    {
        $embeddings = $this->jinaService->embed([$text]);
        return $embeddings[0]['embedding'];
    }

    protected function rerankResults(string $query, Collection $results): Collection
    {
        $results = $results->map(function($result){
            $result->relevance = 0;
            return $result;
        })->values();

        $documents = $results->map(function ($result) {
            return $result->toSearchableText();
        })->toArray();

        $rerankedResults = $this->jinaService->rerank($query, $documents);

        if (!isset($rerankedResults['results']) || !is_array($rerankedResults['results'])) {
            return $results;
        }

        return collect($rerankedResults['results'])->map(function ($rerankedResult) use ($results) {
            if (!isset($rerankedResult['index']) || !isset($results[$rerankedResult['index']])) {
                return null;
            }
            $originalResult = $results[$rerankedResult['index']];
            $originalResult->relevance = $rerankedResult['relevance_score'] ?? 0;
            return $originalResult;
        })->filter()->sortByDesc('relevance')->values();
    }
}
