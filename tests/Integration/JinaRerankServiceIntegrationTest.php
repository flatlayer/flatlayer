<?php

namespace Tests\Integration;

use App\Services\JinaRerankService;
use Tests\TestCase;

class JinaRerankServiceIntegrationTest extends TestCase
{
    protected JinaRerankService $jinaService;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = config('flatlayer.search.jina.key');
        $model = config('flatlayer.search.jina.model');

        if (!$apiKey || !$model) {
            $this->markTestSkipped('Jina API key or model not configured in flatlayer config.');
        }

        $this->jinaService = new JinaRerankService($apiKey, $model);
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
}
