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
        // Create test records
        $first = FakeSearchableModel::create([
            'title' => 'First',
            'content' => 'Content',
            'embedding' => json_encode(array_fill(0, 1536, 0.1))
        ]);
        $second = FakeSearchableModel::create([
            'title' => 'Second',
            'content' => 'Content',
            'embedding' => json_encode(array_fill(0, 1536, 0.2))
        ]);

        // Mock the OpenAI facade to return a specific embedding
        OpenAI::fake([
            CreateResponse::fake([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ],
            ])
        ]);

        // Perform the search
        $results = FakeSearchableModel::search('test query', 2, false);

        // Detailed assertions
        $this->assertCount(2, $results);

        // Check distances
        $this->assertTrue(isset($results[0]->distance), "First result should have a distance attribute");
        $this->assertTrue(isset($results[1]->distance), "Second result should have a distance attribute");

        // Output actual distances for debugging
        echo "First result distance: " . $results[0]->distance . "\n";
        echo "Second result distance: " . $results[1]->distance . "\n";

        // Assert that the distances are different
        $this->assertNotEquals($results[0]->distance, $results[1]->distance);

        // Check ordering based on distance (smaller distance should come first)
        $this->assertLessThan($results[1]->distance, $results[0]->distance);

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
                ['index' => 1, 'relevance_score' => 0.9],
                ['index' => 0, 'relevance_score' => 0.8],
            ]);

        $this->app->instance(JinaRerankService::class, $jinaService);

        $results = FakeSearchableModel::search('test query', 2, true);

        $this->assertCount(2, $results);
        $this->assertContains($results[0]->id, [1, 2]);
        $this->assertContains($results[1]->id, [1, 2]);
        $this->assertNotEquals($results[0]->id, $results[1]->id);
        $this->assertEquals(0.9, $results[0]->relevance_score);
        $this->assertEquals(0.8, $results[1]->relevance_score);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
