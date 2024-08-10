<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdvancedQueryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    protected function createTestData()
    {
        Entry::factory()->create([
            'title' => 'PHP Tutorial',
            'content' => 'Learn PHP programming',
            'type' => 'post',
            'meta' => ['difficulty' => 'beginner', 'duration' => 60],
            'published_at' => now()->subDays(5),
        ])->attachTag('programming')->attachTag('php');

        Entry::factory()->create([
            'title' => 'Advanced JavaScript Concepts',
            'content' => 'Deep dive into JavaScript',
            'type' => 'post',
            'meta' => ['difficulty' => 'advanced', 'duration' => 120],
            'published_at' => now()->subDays(2),
        ])->attachTag('programming')->attachTag('javascript');

        Entry::factory()->create([
            'title' => 'Introduction to Python',
            'content' => 'Getting started with Python',
            'type' => 'post',
            'meta' => ['difficulty' => 'beginner', 'duration' => 90],
            'published_at' => now()->subDays(10),
        ])->attachTag('programming')->attachTag('python');
    }

    public function test_complex_meta_filters()
    {
        $filters = [
            'meta.difficulty' => 'beginner',
            'meta.duration' => ['$gte' => 60, '$lte' => 90]
        ];

        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply()->get();

        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->pluck('title')->contains('PHP Tutorial'));
        $this->assertTrue($filtered->pluck('title')->contains('Introduction to Python'));
    }

    public function test_combined_tag_and_date_filters()
    {
        $filters = [
            '$tags' => ['programming', 'javascript'],
            'published_at' => ['$gte' => now()->subDays(3)->toDateTimeString()],
            'meta.difficulty' => 'advanced'
        ];

        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply()->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals('Advanced JavaScript Concepts', $filtered->first()->title);
    }

    public function test_nested_or_filters()
    {
        $filters = [
            '$or' => [
                ['meta.difficulty' => 'advanced'],
                [
                    '$and' => [
                        ['meta.difficulty' => 'beginner'],
                        ['meta.duration' => ['$lt' => 70]]
                    ]
                ]
            ]
        ];

        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply();

        // Debug: Let's see what SQL query is being generated
        Log::info('Generated SQL: ' . $filtered->toSql());
        Log::info('SQL Bindings: ' . json_encode($filtered->getBindings()));

        // Get the results
        $results = $filtered->get();

        // Debug: Let's see what entries we have in the database
        Log::info('All entries in database:');
        Entry::all()->each(function ($entry) {
            Log::info("Title: {$entry->title}, Difficulty: {$entry->meta['difficulty']}, Duration: {$entry->meta['duration']}");
        });

        // Debug: Let's see what results we got
        Log::info('Filtered results:');
        $results->each(function ($entry) {
            Log::info("Title: {$entry->title}, Difficulty: {$entry->meta['difficulty']}, Duration: {$entry->meta['duration']}");
        });

        $this->assertCount(2, $results);
        $this->assertTrue($results->pluck('title')->contains('Advanced JavaScript Concepts'));
        $this->assertTrue($results->pluck('title')->contains('PHP Tutorial'));
    }

    public function test_full_text_search_with_filters()
    {
        $filters = [
            '$search' => 'programming',
            'meta.difficulty' => 'beginner',
            '$tags' => ['php']
        ];

        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply()->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals('PHP Tutorial', $filtered->first()->title);
    }

    public function test_complex_date_range_filter()
    {
        $filters = [
            'published_at' => [
                '$gte' => now()->subDays(7)->startOfDay()->toDateTimeString(),
                '$lte' => now()->subDay()->endOfDay()->toDateTimeString()
            ]
        ];

        $query = Entry::query();
        $filtered = (new EntryFilter($query, $filters))->apply()->get();

        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->pluck('title')->contains('PHP Tutorial'));
        $this->assertTrue($filtered->pluck('title')->contains('Advanced JavaScript Concepts'));
    }
}
