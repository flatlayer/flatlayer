<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SearchableTest extends TestCase
{
    use RefreshDatabase;

    public function testUpdateSearchVector()
    {
        $contentItem = Entry::factory()->create([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'type' => 'post'
        ]);

        $this->assertNotEmpty($contentItem->embedding);
        $this->assertCount(768, $contentItem->embedding->toArray());
    }

    public function testSearch()
    {
        // Create test records
        $first = Entry::factory()->create([
            'title' => 'First document',
            'content' => 'This is the first test document',
            'type' => 'post'
        ]);
        $second = Entry::factory()->create([
            'title' => 'Second document',
            'content' => 'This is the second test document',
            'type' => 'post'
        ]);

        // Perform the search
        $results = Entry::search('test document', 2, false);

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
        Entry::factory()->create([
            'title' => 'First',
            'content' => 'This is a test document',
            'type' => 'post'
        ]);
        Entry::factory()->create([
            'title' => 'Second',
            'content' => 'This is an actual real document',
            'type' => 'post'
        ]);

        $results = Entry::search('test document', 2, true);

        $this->assertEquals(2, $results->count());
        $this->assertEquals('First', $results[0]->title);
        $this->assertEquals('Second', $results[1]->title);
        $this->assertGreaterThanOrEqual(0.3, $results[0]->relevance);  // 3 overlapping words: "test", "document", "is"
        $this->assertGreaterThanOrEqual(0.1, $results[1]->relevance);  // 2 overlapping words: "document", "is"
        $this->assertGreaterThan($results[1]->relevance, $results[0]->relevance);
    }
}
