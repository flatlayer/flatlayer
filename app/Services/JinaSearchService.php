<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class JinaSearchService
{
    private const MAX_CHARS = 8600;
    private const EMBEDDING_ENDPOINT = 'https://api.jina.ai/v1/embeddings';
    private const RERANK_ENDPOINT = 'https://api.jina.ai/v1/rerank';

    public function __construct(
        protected string $apiKey,
        protected string $rerankModel,
        protected string $embeddingModel = 'jina-embeddings-v2-base-en'
    ) {}

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
            $result = $response->json();
            return $result['data'] ?? [];
        }

        throw new \Exception('Jina API embedding request failed: ' . $response->body());
    }

    protected function cropDocument(string $document): string
    {
        return Str::limit($document, self::MAX_CHARS, '');
    }
}
