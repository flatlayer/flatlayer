<?php

namespace Tests\Unit;

use App\Services\JinaRerankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;
use Tests\Fakes\FakeSearchableModel;
use Tests\TestCase;
use Mockery;

class SearchableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('fake_searchable_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->text('embedding')->nullable();
            $table->timestamps();
        });

        Config::set('flatlayer.search.embedding_model', 'fake-model');

        $this->setupFakeOpenAIResponses();
    }

    protected function setupFakeOpenAIResponses()
    {
        $fakeResponses = [];
        for ($i = 0; $i < 10; $i++) {
            $fakeResponses[] = CreateResponse::fake([
                'data' => [
                    ['embedding' => array_fill(0, 3, $i / 10)]
                ],
            ]);
        }

        OpenAI::fake($fakeResponses);
    }

    public function testUpdateSearchVector()
    {
        $model = new FakeSearchableModel(['title' => 'Test', 'content' => 'Content']);
        $model->updateSearchVector();
        $model->save();

        $this->assertEquals([0.1, 0.1, 0.1], $model->embedding);
    }

    public function testSearch()
    {
        FakeSearchableModel::create(['title' => 'First', 'content' => 'Content', 'embedding' => json_encode([0.1, 0.1, 0.1])]);
        FakeSearchableModel::create(['title' => 'Second', 'content' => 'Content', 'embedding' => json_encode([0.2, 0.2, 0.2])]);

        // Mock the static getEmbedding method
        FakeSearchableModel::shouldReceive('getEmbedding')
            ->once()
            ->andReturn([0.3, 0.3, 0.3]);

        // Mock the database query
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['id' => 1, 'title' => 'First', 'content' => 'Content', 'distance' => 0.1],
                (object) ['id' => 2, 'title' => 'Second', 'content' => 'Content', 'distance' => 0.2],
            ]);

        $results = FakeSearchableModel::search('test query', 2, false);

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals(2, $results[1]->id);
    }

    public function testSearchWithReranking()
    {
        FakeSearchableModel::create(['title' => 'First', 'content' => 'Content', 'embedding' => json_encode([0.4, 0.4, 0.4])]);
        FakeSearchableModel::create(['title' => 'Second', 'content' => 'Content', 'embedding' => json_encode([0.5, 0.5, 0.5])]);

        // Mock the static getEmbedding method
        FakeSearchableModel::shouldReceive('getEmbedding')
            ->once()
            ->andReturn([0.6, 0.6, 0.6]);

        // Mock the database query
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['id' => 1, 'title' => 'First', 'content' => 'Content', 'distance' => 0.1],
                (object) ['id' => 2, 'title' => 'Second', 'content' => 'Content', 'distance' => 0.2],
            ]);

        $jinaService = Mockery::mock(JinaRerankService::class);
        $jinaService->shouldReceive('rerank')
            ->once()
            ->andReturn([
                ['index' => 1, 'relevance_score' => 0.9],
                ['index' => 0, 'relevance_score' => 0.8],
            ]);

        $this->app->instance(JinaRerankService::class, $jinaService);

        $results = FakeSearchableModel::search('test query', 2, true);

        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]->id);
        $this->assertEquals(1, $results[1]->id);
        $this->assertEquals(0.9, $results[0]->relevance_score);
        $this->assertEquals(0.8, $results[1]->relevance_score);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
