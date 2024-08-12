<?php

namespace App\Services;

use App\Services\Fakes\FakeJinaSearchService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class JinaSearchService
{
    private const MAX_CHARS = 8600;

    private const EMBEDDING_ENDPOINT = 'https://api.jina.ai/v1/embeddings';

    private const RERANK_ENDPOINT = 'https://api.jina.ai/v1/rerank';

    /**
     * @param string $apiKey The API key for Jina AI
     * @param string $rerankModel The model to use for reranking
     * @param string $embeddingModel The model to use for embedding
     */
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $rerankModel,
        protected readonly string $embeddingModel = 'jina-embeddings-v2-base-en'
    ) {}

    /**
     * Rerank documents based on a query.
     *
     * @param string $query The query to rerank documents against
     * @param array $documents The documents to rerank
     * @param int $topN The number of top results to return
     * @return array The reranked documents
     *
     * @throws \Exception If the API request fails
     */
    public function rerank(string $query, array $documents, int $topN = 40): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post(self::RERANK_ENDPOINT, [
            'model' => $this->rerankModel,
            'query' => $query,
            'documents' => array_map([$this, 'cropDocument'], $documents),
            'top_n' => $topN,
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new \Exception('Jina API rerank request failed: ' . $response->body());
    }

    /**
     * Generate embeddings for the given texts.
     *
     * @param array $texts The texts to generate embeddings for
     * @return array The generated embeddings
     *
     * @throws \Exception If the API request fails
     */
    public function embed(array $texts): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post(self::EMBEDDING_ENDPOINT, [
            'model' => $this->embeddingModel,
            'embedding_type' => 'float',
            'input' => $texts,
        ]);

        if ($response->successful()) {
            return $response->json('data', []);
        }

        throw new \Exception('Jina API embedding request failed: ' . $response->body());
    }

    /**
     * Crop a document to the maximum allowed length.
     */
    protected function cropDocument(string $document): string
    {
        return Str::limit($document, self::MAX_CHARS, '');
    }

    /**
     * Create a fake instance of the JinaSearchService for testing.
     */
    public static function fake(): FakeJinaSearchService
    {
        $fake = new FakeJinaSearchService();
        App::instance(JinaSearchService::class, $fake);

        return $fake;
    }
}
