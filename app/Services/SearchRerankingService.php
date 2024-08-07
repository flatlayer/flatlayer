<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SearchRerankingService
{
    protected string $apiKey;
    protected string $model;
    private const MAX_CHARS = 8600;

    public function __construct(string $apiKey, string $model)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function rerank(string $query, array $documents, int $topN = 40): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.jina.ai/v1/rerank', [
            'model' => $this->model,
            'query' => $query,
            // Use cropped versions as the documents
            'documents' => array_map([$this, 'cropDocument'], $documents),
            'top_n' => $topN,
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new \Exception('Jina API request failed: ' . $response->body());
    }

    protected function cropDocument(string $document): string
    {
        return Str::limit($document, self::MAX_CHARS, '');
    }
}
