<?php

namespace Tests\Unit;

use App\Services\SearchRerankingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchRerankingServiceTest extends TestCase
{
    protected $jinaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jinaService = new SearchRerankingService('fake-api-key', 'jina-reranker-v2-base-multilingual');
    }

    public function testRerankSuccess()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response([
                'model' => 'jina-reranker-v2-base-multilingual',
                'usage' => [
                    'total_tokens' => 815,
                    'prompt_tokens' => 815
                ],
                'results' => [
                    [
                        'index' => 0,
                        'document' => ['text' => 'Document 1'],
                        'relevance_score' => 0.9
                    ],
                    [
                        'index' => 1,
                        'document' => ['text' => 'Document 2'],
                        'relevance_score' => 0.7
                    ],
                ]
            ], 200)
        ]);

        $query = 'test query';
        $documents = ['Document 1', 'Document 2'];
        $result = $this->jinaService->rerank($query, $documents);

        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        $this->assertEquals(0, $result['results'][0]['index']);
        $this->assertEquals(0.9, $result['results'][0]['relevance_score']);
    }

    public function testRerankFailure()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response('Error message', 400)
        ]);

        $query = 'test query';
        $documents = ['Document 1', 'Document 2'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Jina API request failed: Error message');

        $this->jinaService->rerank($query, $documents);
    }

    public function testCorrectRequestParameters()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response([
                'model' => 'jina-reranker-v2-base-multilingual',
                'usage' => [
                    'total_tokens' => 815,
                    'prompt_tokens' => 815
                ],
                'results' => []
            ], 200)
        ]);

        $query = 'Organic skincare products for sensitive skin';
        $documents = [
            "Organic skincare for sensitive skin with aloe vera and chamomile: Imagine the soothing embrace of nature with our organic skincare range, crafted specifically for sensitive skin. Infused with the calming properties of aloe vera and chamomile, each product provides gentle nourishment and protection. Say goodbye to irritation and hello to a glowing, healthy complexion.",
            "New makeup trends focus on bold colors and innovative techniques: Step into the world of cutting-edge beauty with this seasons makeup trends. Bold, vibrant colors and groundbreaking techniques are redefining the art of makeup. From neon eyeliners to holographic highlighters, unleash your creativity and make a statement with every look.",
        ];
        $topN = 3;

        $result = $this->jinaService->rerank($query, $documents, $topN);

        $this->assertIsArray($result);

        Http::assertSent(function ($request) use ($query, $documents, $topN) {
            $this->assertEquals('https://api.jina.ai/v1/rerank', $request->url());
            $this->assertEquals('POST', $request->method());
            $this->assertEquals('jina-reranker-v2-base-multilingual', $request['model']);
            $this->assertEquals($query, $request['query']);
            $this->assertEquals($documents, $request['documents']);
            $this->assertEquals($topN, $request['top_n']);
            $this->assertEquals('Bearer fake-api-key', $request->header('Authorization')[0]);

            return true;
        });
    }
}
