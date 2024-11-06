<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MathPHP\LinearAlgebra\Vector as MathVector;
use MathPHP\Statistics\Distance;
use OpenAI\Laravel\Facades\OpenAI;
use Pgvector\Vector;

class SearchService
{
    /**
     * Perform a search query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  The maximum number of results to return
     * @param  Builder|null  $builder  An optional query builder to start with
     * @return Collection The search results
     *
     * @throws \Exception
     */
    public function search(string $query, int $limit = 40, ?Builder $builder = null): Collection
    {
        $embedding = $this->getEmbedding($query);
        $builder ??= Entry::query();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => $this->pgVectorSearch($builder, $embedding, $limit),
            default => $this->fallbackSearch($builder, $embedding, $limit),
        };
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
     *
     * @throws \MathPHP\Exception\BadDataException
     */
    protected function fallbackSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        $queryVector = new MathVector($embedding);

        return $builder->get()
            ->map(function ($item) use ($embedding) {
                $item->relevance = 1 - Distance::cosine($embedding, $item->embedding->toArray());

                return $item;
            })
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
        $response = OpenAI::embeddings()->create([
            'model' => config('flatlayer.search.openai.embedding'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }
}
