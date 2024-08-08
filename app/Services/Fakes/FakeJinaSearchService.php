<?php

namespace App\Services\Fakes;

use App\Services\JinaSearchService;

class FakeJinaSearchService extends JinaSearchService
{
    protected $embeddings = [];
    protected $embeddingIndex = 0;

    public function __construct()
    {
        // Set a fixed seed for reproducibility
        mt_srand(1234);

        // Generate 10 random embeddings
        for ($i = 0; $i < 10; $i++) {
            $this->embeddings[] = array_map(function () {
                return mt_rand() / mt_getrandmax();
            }, array_fill(0, 768, 0));
        }
    }

    public function embed(array $texts): array
    {
        $result = [];
        foreach ($texts as $text) {
            $result[] = ['embedding' => $this->embeddings[$this->embeddingIndex % 10]];
            $this->embeddingIndex++;
        }
        return $result;
    }

    public function rerank(string $query, array $documents, int $topN = 40): array
    {
        $results = [];
        $queryWords = str_word_count(strtolower($query), 1);

        foreach ($documents as $index => $document) {
            $docWords = str_word_count(strtolower($document), 1);

            $overlap = 0;
            foreach ($queryWords as $word) {
                $overlap += count(array_keys($docWords, $word));
            }

            $maxPossibleOverlap = max(count($queryWords), count($docWords));
            $relevance = $overlap / $maxPossibleOverlap;

            $results[] = [
                'index' => $index,
                'relevance_score' => $relevance,
                'document' => $document
            ];
        }

        // Sort results by relevance_score in descending order
        usort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        // Limit to topN results
        $results = array_slice($results, 0, $topN);

        return ['results' => $results];
    }
}
