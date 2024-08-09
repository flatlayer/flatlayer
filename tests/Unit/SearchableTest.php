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

    public function test_update_search_vector()
    {
        $entry = Entry::factory()->create([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'type' => 'post'
        ]);

        $this->assertNotEmpty($entry->embedding);
        $this->assertCount(768, $entry->embedding->toArray());
    }

    public function test_search_without_reranking()
    {
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

        $results = Entry::search('test document', 2, false);

        $this->assertCount(2, $results);
        $this->assertTrue(isset($results[0]->similarity), "First result should have a similarity attribute");
        $this->assertTrue(isset($results[1]->similarity), "Second result should have a similarity attribute");
        $this->assertNotEquals($results[0]->similarity, $results[1]->similarity);

        // Check order and content of results
        $this->assertEquals($first->id, $results[0]->id);
        $this->assertEquals($second->id, $results[1]->id);
        $this->assertTrue($results->contains($first));
        $this->assertTrue($results->contains($second));
    }

    public function test_search_with_reranking()
    {
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

        // Check relevance scores
        $this->assertGreaterThanOrEqual(0.3, $results[0]->relevance, "First result should have higher relevance due to more overlapping words");
        $this->assertGreaterThanOrEqual(0.1, $results[1]->relevance, "Second result should have lower but positive relevance");
        $this->assertGreaterThan($results[1]->relevance, $results[0]->relevance, "First result should be more relevant than the second");
    }
}
