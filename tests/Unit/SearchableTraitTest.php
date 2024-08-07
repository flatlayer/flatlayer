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
        $fakeEmbeddings = [
            'Test Content' => [0.1, 0.2, 0.3],
            'First Content' => [0.4, 0.5, 0.6],
            'Second Content' => [0.7, 0.8, 0.9],
            'test query' => [0.2, 0.3, 0.4],
        ];

        $fakeResponses = collect($fakeEmbeddings)->map(function ($embedding) {
            return CreateResponse::fake([
                'data' => [
                    ['embedding' => $embedding]
                ],
            ]);
        })->values()->all();

        OpenAI::fake($fakeResponses);
    }

    public function testUpdateSearchVector()
    {
        $model = new FakeSearchableModel(['title' => 'Test', 'content' => 'Content']);
        $model->save();

        $this->assertEquals([0.1, 0.2, 0.3], $model->embedding);
    }

    public function testSearch()
    {
        // Create test records
        $first = FakeSearchableModel::create([
            'title' => 'First',
            'content' => 'Content',
        ]);
        $second = FakeSearchableModel::create([
            'title' => 'Second',
            'content' => 'Content',
        ]);

        // Perform the search
        $results = FakeSearchableModel::search('test query', 2, false);

        // Detailed assertions
        $this->assertCount(2, $results);

        // Check distances
        $this->assertTrue(isset($results[0]->distance), "First result should have a distance attribute");
        $this->assertTrue(isset($results[1]->distance), "Second result should have a distance attribute");

        // Assert that the distances are different
        $this->assertNotEquals($results[0]->distance, $results[1]->distance);

        // Check ordering based on distance (smaller distance should come first)
        $this->assertLessThan($results[0]->distance, $results[1]->distance);

        // Check that the results are the models we created
        $this->assertTrue($results->contains($second));
        $this->assertTrue($results->contains($first));

        // Check the order of results
        $this->assertEquals($first->id, $results[0]->id);
        $this->assertEquals($second->id, $results[1]->id);
    }

    public function testSearchWithReranking()
    {
        // Create actual records in the database
        FakeSearchableModel::create(['title' => 'First', 'content' => 'Content', 'embedding' => json_encode([0.4, 0.4, 0.4])]);
        FakeSearchableModel::create(['title' => 'Second', 'content' => 'Content', 'embedding' => json_encode([0.5, 0.5, 0.5])]);

        // Mock the OpenAI facade to return a specific embedding
        OpenAI::fake([
            CreateResponse::fake([
                'data' => [
                    ['embedding' => [0.6, 0.6, 0.6]]
                ],
            ])
        ]);

        $jinaService = Mockery::mock(JinaRerankService::class);
        $jinaService->shouldReceive('rerank')
            ->once()
            ->andReturn([
                'results' => [
                    ['index' => 1, 'relevance_score' => 0.9],
                    ['index' => 0, 'relevance_score' => 0.8],
                ]
            ]);

        $this->app->instance(JinaRerankService::class, $jinaService);

        $results = FakeSearchableModel::search('test query', 2, true);

        $this->assertEquals(2, $results->count());
        $this->assertEquals(1, $results[0]->id);  // The model with higher relevance score
        $this->assertEquals(2, $results[1]->id);  // The model with lower relevance score
        $this->assertEquals(0.9, $results[0]->relevance_score);
        $this->assertEquals(0.8, $results[1]->relevance_score);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
