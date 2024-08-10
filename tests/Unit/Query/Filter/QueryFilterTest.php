<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_tag_filters_with_type()
    {
        $post1 = Entry::factory()->create(['type' => 'post']);
        $post2 = Entry::factory()->create(['type' => 'post']);
        $post1->attachTag('red', 'colors');
        $post2->attachTag('blue', 'colors');

        $filters = ['$tags' => ['type' => 'colors', 'values' => ['red', 'blue']]];
        $query = Entry::query();
        $filteredQuery = (new EntryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('red');
        }));
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('blue');
        }));
    }

    public function test_search_filter()
    {
        Entry::factory()->create([
            'title' => 'John Doe',
            'type' => 'post',
            'content' => 'A post about John',
        ]);

        $filters = ['$search' => 'a post about John'];
        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);
        $this->assertCount(1, $filtered);
        // Check if the relevance score is present and above a threshold
        $this->assertGreaterThanOrEqual(0.1, $filtered->first()->relevance);
    }

    public function test_combined_filters()
    {
        // Create an older post
        $olderJohn = Entry::factory()->create([
            'title' => 'John Smith',
            'type' => 'post',
            'content' => 'An older John post',
            'meta' => ['age' => 50],
            'published_at' => now()->subDays(30),
        ]);
        $olderJohn->attachTag('important');

        // Create a younger post
        $youngerJohn = Entry::factory()->create([
            'title' => 'John Smith',
            'type' => 'post',
            'content' => 'A younger John post',
            'meta' => ['age' => 30],
            'published_at' => now()->subDays(10),
        ]);
        $youngerJohn->attachTag('important');

        // Apply multiple filters
        $filters = [
            'meta.age' => ['$gte' => 25, '$lt' => 40],
            'published_at' => ['$gte' => now()->subDays(20)->toDateTimeString()],
            '$tags' => ['important'],
            '$search' => 'John'
        ];

        $filtered = (new EntryFilter(Entry::query(), $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);
        $this->assertCount(1, $filtered);

        $model = $filtered->first();
        $this->assertEquals('John Smith', $model->title);
        $this->assertEquals(30, $model->meta['age']);
        $this->assertTrue($model->published_at->gt(now()->subDays(20)));
        $this->assertTrue($model->tags->pluck('name')->contains('important'));
        $this->assertStringContainsString('younger John', $model->content);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
