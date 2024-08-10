<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_single_tag()
    {
        $post1 = Entry::factory()->create(['type' => 'post']);
        $post2 = Entry::factory()->create(['type' => 'post']);
        $post3 = Entry::factory()->create(['type' => 'post']);

        $post1->attachTag('red');
        $post2->attachTag('blue');
        $post3->attachTag('red');

        $filters = ['$tags' => ['red']];
        $query = Entry::query();
        $filteredQuery = (new EntryFilter($query, $filters))->apply();

        $results = $filteredQuery->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($post1));
        $this->assertTrue($results->contains($post3));
        $this->assertFalse($results->contains($post2));
    }

    public function test_filter_by_multiple_tags()
    {
        $post1 = Entry::factory()->create(['type' => 'post']);
        $post2 = Entry::factory()->create(['type' => 'post']);
        $post3 = Entry::factory()->create(['type' => 'post']);
        $post4 = Entry::factory()->create(['type' => 'post']);

        $post1->attachTags(['red', 'big']);
        $post2->attachTags(['blue', 'small']);
        $post3->attachTags(['red', 'small']);
        $post4->attachTag('green');

        $filters = ['$tags' => ['red', 'small']];
        $query = Entry::query();
        $filteredQuery = (new EntryFilter($query, $filters))->apply();

        $results = $filteredQuery->get();

        $this->assertCount(3, $results);
        $this->assertTrue($results->contains($post1));
        $this->assertTrue($results->contains($post2));
        $this->assertTrue($results->contains($post3));
        $this->assertFalse($results->contains($post4));
    }

    public function test_filter_by_non_existent_tag()
    {
        Entry::factory()->create(['type' => 'post'])->attachTag('red');
        Entry::factory()->create(['type' => 'post'])->attachTag('blue');

        $filters = ['$tags' => ['non_existent_tag']];
        $query = Entry::query();
        $filteredQuery = (new EntryFilter($query, $filters))->apply();

        $results = $filteredQuery->get();

        $this->assertCount(0, $results);
    }
}
