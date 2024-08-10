<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_filters_with_type()
    {
        $post1 = Entry::factory()->create(['type' => 'post']);
        $post2 = Entry::factory()->create(['type' => 'post']);
        $post1->attachTag('red', 'colors');
        $post2->attachTag('blue', 'colors');

        $filters = ['$tags' => ['type' => 'colors', 'values' => ['red', 'blue']]];
        $query = Entry::query();
        $filteredQuery = (new EntryFilter($query, $filters))->apply();

        $results = $filteredQuery->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('red');
        }));
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('blue');
        }));
    }
}
