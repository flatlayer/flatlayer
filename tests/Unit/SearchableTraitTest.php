<?php

namespace Tests\Unit;

use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeSearchableModel;
use Tests\TestCase;
use Mockery;

class SearchableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected $jinaService;

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

        Config::set('flatlayer.search.embedding_model', 'jina-embeddings-v2-base-en');

        $this->jinaService = Mockery::mock(JinaSearchService::class);
        $this->app->instance(JinaSearchService::class, $this->jinaService);

        $this->setupFakeJinaResponses();
    }

    protected function setupFakeJinaResponses()
    {
        $this->jinaService->shouldReceive('embed')
            ->andReturn([
                ['embedding' => array_fill(0, 768, 0.1)],
                ['embedding' => array_fill(0, 768, 0.2)],
                ['embedding' => array_fill(0, 768, 0.3)],
                ['embedding' => array_fill(0, 768, 0.4)],
            ]);
    }

    public function testUpdateSearchVector()
    {
        $model = new FakeSearchableModel(['title' => 'Test', 'content' => 'Content']);

        $this->jinaService->shouldReceive('embed')
            ->once()
            ->with(['Test Content'])
            ->andReturn([['embedding' => array_fill(0, 768, 0.5)]]);

        $model->save();

        $this->assertEquals(array_fill(0, 768, 0.5), $model->embedding);
    }

    public function testSearch()
    {
        // Create test records
        $first = FakeSearchableModel::create([
            'title' => 'First',
            'content' => 'Content',
            'embedding' => json_encode(array_fill(0, 768, 0.1)),
        ]);
        $second = FakeSearchableModel::create([
            'title' => 'Second',
            'content' => 'Content',
            'embedding' => json_encode(array_fill(0, 768, 0.2)),
        ]);

        $this->jinaService->shouldReceive('embed')
            ->once()
            ->with(['test query'])
            ->andReturn([['embedding' => array_fill(0, 768, 0.3)]]);

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
        $this->assertTrue($results->contains($first));
        $this->assertTrue($results->contains($second));

        // Check the order of results
        $this->assertEquals($first->id, $results[0]->id);
        $this->assertEquals($second->id, $results[1]->id);
    }

    public function testSearchWithReranking()
    {
        // Create actual records in the database
        FakeSearchableModel::create(['title' => 'First', 'content' => 'Content', 'embedding' => json_encode(array_fill(0, 768, 0.1))]);
        FakeSearchableModel::create(['title' => 'Second', 'content' => 'Content', 'embedding' => json_encode(array_fill(0, 768, 0.2))]);

        $this->jinaService->shouldReceive('embed')
            ->once()
            ->with(['test query'])
            ->andReturn([['embedding' => array_fill(0, 768, 0.3)]]);

        $this->jinaService->shouldReceive('rerank')
            ->once()
            ->andReturn([
                'results' => [
                    ['index' => 1, 'relevance_score' => 0.9],
                    ['index' => 0, 'relevance_score' => 0.8],
                ]
            ]);

        $results = FakeSearchableModel::search('test query', 2, true);

        $this->assertEquals(2, $results->count());
        $this->assertEquals(2, $results[0]->id);  // The model with higher relevance score
        $this->assertEquals(1, $results[1]->id);  // The model with lower relevance score
        $this->assertEquals(0.9, $results[0]->relevance_score);
        $this->assertEquals(0.8, $results[1]->relevance_score);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
