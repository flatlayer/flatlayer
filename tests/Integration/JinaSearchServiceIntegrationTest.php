<?php

namespace Tests\Integration;

use App\Services\JinaSearchService;
use Tests\TestCase;

class JinaSearchServiceIntegrationTest extends TestCase
{
    protected JinaSearchService $jinaService;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = config('flatlayer.search.jina.key');
        $rerankModel = config('flatlayer.search.jina.rerank');
        $embedModel = config('flatlayer.search.jina.embed');

        if (!$apiKey || !$rerankModel || !$embedModel) {
            $this->markTestSkipped('Jina API key or models not configured in flatlayer config.');
        }

        $this->jinaService = new JinaSearchService($apiKey, $rerankModel, $embedModel);
    }

    public function testEmbedIntegration()
    {
        $texts = [
            "Organic skincare for sensitive skin with aloe vera and chamomile.",
            "New makeup trends focus on bold colors and innovative techniques."
        ];

        $result = $this->jinaService->embed($texts);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        foreach ($result as $embedding) {
            $this->assertArrayHasKey('embedding', $embedding);
            $this->assertCount(768, $embedding['embedding']);
            $this->assertContainsOnly('float', $embedding['embedding']);
        }
    }

    public function testRerankIntegration()
    {
        $query = 'Organic skincare products for sensitive skin';
        $documents = [
            "Organic skincare for sensitive skin with aloe vera and chamomile: Imagine the soothing embrace of nature with our organic skincare range, crafted specifically for sensitive skin. Infused with the calming properties of aloe vera and chamomile, each product provides gentle nourishment and protection. Say goodbye to irritation and hello to a glowing, healthy complexion.",
            "New makeup trends focus on bold colors and innovative techniques: Step into the world of cutting-edge beauty with this seasons makeup trends. Bold, vibrant colors and groundbreaking techniques are redefining the art of makeup. From neon eyeliners to holographic highlighters, unleash your creativity and make a statement with every look.",
        ];
        $topN = 2;

        $result = $this->jinaService->rerank($query, $documents, $topN);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount($topN, $result['results']);

        foreach ($result['results'] as $item) {
            $this->assertArrayHasKey('index', $item);
            $this->assertArrayHasKey('document', $item);
            $this->assertArrayHasKey('relevance_score', $item);
            $this->assertIsNumeric($item['relevance_score']);
            $this->assertGreaterThanOrEqual(0, $item['relevance_score']);
            $this->assertLessThanOrEqual(1, $item['relevance_score']);
        }

        // Check if the results are sorted by relevance_score in descending order
        $scores = array_column($result['results'], 'relevance_score');
        $sortedScores = $scores;
        rsort($sortedScores);
        $this->assertEquals($sortedScores, $scores, "Results should be sorted by relevance_score in descending order");
    }

    public function testEmbedAndRerankIntegration()
    {
        $query = 'Organic skincare products for sensitive skin';
        $documents = [
            "Organic skincare for sensitive skin with aloe vera and chamomile.",
            "New makeup trends focus on bold colors and innovative techniques."
        ];

        // First, embed the query and documents
        $queryEmbedding = $this->jinaService->embed([$query])[0]['embedding'];
        $documentEmbeddings = $this->jinaService->embed($documents);

        // Ensure embeddings are 768-dimensional
        $this->assertCount(768, $queryEmbedding);
        foreach ($documentEmbeddings as $embedding) {
            $this->assertCount(768, $embedding['embedding']);
        }

        // Now, use these embeddings for reranking
        $result = $this->jinaService->rerank($query, $documents);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);

        // The first result should be more relevant to the query
        $this->assertGreaterThan(
            $result['results'][1]['relevance_score'],
            $result['results'][0]['relevance_score'],
            "First result should have a higher relevance score"
        );
    }
}
