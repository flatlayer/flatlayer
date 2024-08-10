<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertCount(1, $filtered);
        $this->assertGreaterThanOrEqual(0.1, $filtered->first()->relevance);
    }
}
