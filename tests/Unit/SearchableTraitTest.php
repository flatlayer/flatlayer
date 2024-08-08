<?php

namespace Tests\Unit;

use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeSearchableModel;
use Tests\TestCase;

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

        $this->jinaService = JinaSearchService::fake();
    }

    public function testUpdateSearchVector()
    {
        $model = new FakeSearchableModel(['title' => 'Test', 'content' => 'Content']);

        $model->save();

        $this->assertNotEmpty($model->embedding);
        $this->assertCount(768, $model->embedding);
    }

    public function testSearch()
    {
        // Create test records
        $first = FakeSearchableModel::create([
            'title' => 'First document',
            'content' => 'This is the first test document',
        ]);
        $second = FakeSearchableModel::create([
            'title' => 'Second document',
            'content' => 'This is the second test document',
        ]);

        // Perform the search
        $results = FakeSearchableModel::search('test document', 2, false);

        // Detailed assertions
        $this->assertCount(2, $results);

        // Check similarities
        $this->assertTrue(isset($results[0]->similarity), "First result should have a similarity attribute");
        $this->assertTrue(isset($results[1]->similarity), "Second result should have a similarity attribute");

        // Assert that the similarities are different
        $this->assertNotEquals($results[0]->similarity, $results[1]->similarity);

        // Test that the order is as expected
        $this->assertEquals($first->id, $results[0]->id);
        $this->assertEquals($second->id, $results[1]->id);

        // Check that the results are the models we created
        $this->assertTrue($results->contains($first));
        $this->assertTrue($results->contains($second));
    }

    public function testSearchWithReranking()
    {
        // Create actual records in the database
        FakeSearchableModel::create(['title' => 'First', 'content' => 'This is a test document']);
        FakeSearchableModel::create(['title' => 'Second', 'content' => 'This is an actual real document']);

        $results = FakeSearchableModel::search('test document', 2, true);

        $this->assertEquals(2, $results->count());
        $this->assertEquals(1, $results[0]->id);  // "another test document" has more overlapping words
        $this->assertEquals(2, $results[1]->id);
        $this->assertGreaterThanOrEqual(0.3, $results[0]->relevance);  // 3 overlapping words: "test", "document", "is"
        $this->assertGreaterThanOrEqual(0.1, $results[1]->relevance);  // 2 overlapping words: "test", "document"
        $this->assertGreaterThan($results[1]->relevance, $results[0]->relevance);
    }
}
