<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class JinaRerankService
{
    protected string $apiKey;
    protected string $model;

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
            'documents' => $documents,
            'top_n' => $topN,
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new \Exception('Jina API request failed: ' . $response->body());
    }
}
