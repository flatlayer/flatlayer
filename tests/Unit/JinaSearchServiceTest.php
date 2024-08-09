<?php

namespace Tests\Unit;

use App\Services\JinaSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JinaSearchServiceTest extends TestCase
{
    protected $jinaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jinaService = new JinaSearchService('fake-api-key', 'jina-reranker-v2-base-multilingual', 'jina-embeddings-v2-base-en');
    }

    public function test_embed_success()
    {
        Http::fake([
            'https://api.jina.ai/v1/embeddings' => Http::response([
                'model' => 'jina-embeddings-v2-base-en',
                'data' => [
                    ['embedding' => array_fill(0, 768, 0.1), 'index' => 0],
                    ['embedding' => array_fill(0, 768, 0.2), 'index' => 1]
                ]
            ], 200)
        ]);

        $texts = ['Text 1', 'Text 2'];
        $result = $this->jinaService->embed($texts);

        $this->assertCount(2, $result);
        $this->assertCount(768, $result[0]['embedding']);
        $this->assertCount(768, $result[1]['embedding']);
    }

    public function test_embed_failure()
    {
        Http::fake([
            'https://api.jina.ai/v1/embeddings' => Http::response('Error message', 400)
        ]);

        $texts = ['Text 1', 'Text 2'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Jina API embedding request failed: Error message');

        $this->jinaService->embed($texts);
    }

    public function test_rerank_success()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response([
                'model' => 'jina-reranker-v2-base-multilingual',
                'usage' => ['total_tokens' => 815, 'prompt_tokens' => 815],
                'results' => [
                    ['index' => 0, 'document' => ['text' => 'Document 1'], 'relevance_score' => 0.9],
                    ['index' => 1, 'document' => ['text' => 'Document 2'], 'relevance_score' => 0.7],
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

    public function test_rerank_failure()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response('Error message', 400)
        ]);

        $query = 'test query';
        $documents = ['Document 1', 'Document 2'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Jina API rerank request failed: Error message');

        $this->jinaService->rerank($query, $documents);
    }

    public function test_embed_request_parameters()
    {
        Http::fake([
            'https://api.jina.ai/v1/embeddings' => Http::response([
                'model' => 'jina-embeddings-v2-base-en',
                'data' => [
                    ['embedding' => array_fill(0, 768, 0.1), 'index' => 0],
                    ['embedding' => array_fill(0, 768, 0.2), 'index' => 1]
                ]
            ], 200)
        ]);

        $texts = ['Text 1', 'Text 2'];
        $this->jinaService->embed($texts);

        Http::assertSent(function ($request) use ($texts) {
            return $request->url() == 'https://api.jina.ai/v1/embeddings'
                && $request->method() == 'POST'
                && $request['model'] == 'jina-embeddings-v2-base-en'
                && $request['input'] == $texts
                && $request->header('Authorization')[0] == 'Bearer fake-api-key';
        });
    }

    public function test_rerank_request_parameters()
    {
        Http::fake([
            'https://api.jina.ai/v1/rerank' => Http::response([
                'model' => 'jina-reranker-v2-base-multilingual',
                'usage' => ['total_tokens' => 815, 'prompt_tokens' => 815],
                'results' => []
            ], 200)
        ]);

        $query = 'Organic skincare products for sensitive skin';
        $documents = [
            "Organic skincare for sensitive skin with aloe vera and chamomile: Imagine the soothing embrace of nature with our organic skincare range, crafted specifically for sensitive skin. Infused with the calming properties of aloe vera and chamomile, each product provides gentle nourishment and protection. Say goodbye to irritation and hello to a glowing, healthy complexion.",
            "New makeup trends focus on bold colors and innovative techniques: Step into the world of cutting-edge beauty with this seasons makeup trends. Bold, vibrant colors and groundbreaking techniques are redefining the art of makeup. From neon eyeliners to holographic highlighters, unleash your creativity and make a statement with every look.",
        ];
        $topN = 3;

        $this->jinaService->rerank($query, $documents, $topN);

        Http::assertSent(function ($request) use ($query, $documents, $topN) {
            return $request->url() == 'https://api.jina.ai/v1/rerank'
                && $request->method() == 'POST'
                && $request['model'] == 'jina-reranker-v2-base-multilingual'
                && $request['query'] == $query
                && $request['documents'] == $documents
                && $request['top_n'] == $topN
                && $request->header('Authorization')[0] == 'Bearer fake-api-key';
        });
    }
}
